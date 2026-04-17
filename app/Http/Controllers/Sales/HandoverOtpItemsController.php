<?php
namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class HandoverOtpItemsController extends Controller
{
    /**
     * Normalise stored transfer proof paths to a consistent array structure.
     */
    protected function normalizeTransferProofPaths($item): array
    {
        $paths = $item->payment_transfer_proof_paths ?? [];
        if (is_string($paths) && $paths !== '') {
            $decoded = json_decode($paths, true);
            $paths = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($paths)) {
            $paths = [];
        }
        $normalized = [];
        foreach ($paths as $entry) {
            if (is_string($entry) && $entry !== '') {
                $normalized[] = ['path' => $entry];
            } elseif (is_array($entry) && !empty($entry['path'])) {
                $normalized[] = $entry;
            }
        }
        if (!empty($item->payment_transfer_proof_path)) {
            $exists = collect($normalized)->contains(fn($e) => ($e['path'] ?? null) === $item->payment_transfer_proof_path);
            if (! $exists) {
                $normalized[] = ['path' => $item->payment_transfer_proof_path];
            }
        }
        return array_values(array_filter($normalized, fn($e) => !empty($e['path'] ?? null)));
    }

    /**
     * Parse OTP value – can be plain, hash, or "plain|hash".
     */
    protected function parseOtpValue(?string $value): array
    {
        if (! $value) {
            return [null, null];
        }
        if (str_contains($value, '|')) {
            [$plain, $hash] = explode('|', $value, 2);
            return [trim($plain) ?: null, trim($hash) ?: null];
        }
        if (str_starts_with($value, '$2y$') || str_starts_with($value, '$2a$')) {
            return [null, $value];
        }
        return [$value, null];
    }

    /**
     * Show OTP page + list of items.
     */
    public function index(Request $request)
    {
        $me = $request->user();
        $handovers = SalesHandover::with(['warehouse', 'items.product', 'sales'])
            ->where('sales_id', $me->id)
            ->whereIn('status', ['waiting_morning_otp', 'on_sales', 'waiting_evening_otp'])
            ->orderByDesc('handover_date')
            ->get();

        $reqId = $request->integer('handover_id');
        $actId = $request->session()->get('sales_active_handover_id');
        $handover = $handovers->first();
        if ($reqId) {
            $handover = $handovers->firstWhere('id', $reqId) ?? $handover;
        } elseif ($actId) {
            $handover = $handovers->firstWhere('id', $actId) ?? $handover;
        }
        if ($handover) {
            $request->session()->put('sales_active_handover_id', $handover->id);
        }

        $isOtpVerified = false;
        $items = collect();
        $canEdit = false;
        if ($handover) {
            $sessionKey = 'sales_handover_otp_verified_' . $handover->id;
            $isOtpVerified = $request->session()->get($sessionKey, false) ||
                ($handover->status === 'on_sales' && ! is_null($handover->morning_otp_verified_at));
            if ($isOtpVerified) {
                $items = $handover->items()->with('product')->orderBy('id')->get();
                $canEdit = in_array($handover->status, ['on_sales', 'waiting_evening_otp'], true);
            }
        }

        return view('sales.handover_otp', [
            'me'               => $me,
            'handovers'        => $handovers,
            'handover'         => $handover,
            'items'            => $items,
            'isOtpVerified'    => $isOtpVerified,
            'canEditPayment'   => $canEdit,
            'harusPopupOtp'    => ($handover && in_array($handover->status, ['waiting_morning_otp', 'on_sales'], true) && ! $isOtpVerified),
        ]);
    }

    /**
     * Verify morning OTP via AJAX.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp_code'    => 'required',
            'handover_id' => 'sometimes|exists:sales_handovers,id'
        ]);

        $me = $request->user();
        $inputOtp = trim($request->otp_code);
        $targetId = $request->handover_id;

        $query = SalesHandover::where('sales_id', $me->id)
            ->whereIn('status', ['waiting_morning_otp', 'on_sales', 'waiting_evening_otp']);

        if ($targetId) {
            $query->where('id', $targetId);
        }

        $handovers = $query->get();
        $matched = null;

        foreach ($handovers as $ho) {
            [$plain, $hash] = $this->parseOtpValue($ho->morning_otp_hash);
            if (! $plain && ! $hash) {
                continue;
            }
            if (($hash && Hash::check($inputOtp, $hash)) || ($plain && hash_equals($plain, $inputOtp))) {
                $matched = $ho;
                break;
            }
        }

        if (! $matched) {
            $msg = 'OTP invalid';
            if ($targetId) {
                $hdo = SalesHandover::find($targetId);
                $msg = "OTP is incorrect for handover {$hdo->code}.";
            }
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        $request->session()->put('sales_active_handover_id', $matched->id);
        $request->session()->put('sales_handover_otp_verified_' . $matched->id, true);

        // 🔔 Bersihkan notifikasi "OTP Pagi Sent" karena sudah diverifikasi oleh Sales
        \App\Helpers\NotificationHelper::markAsReadByReference('handover_otp_sent', 'sales_handovers', $matched->id);

        // 🔥 SINYAL: Biar Admin WH di laptop juga tau kalau ini udah verified
        broadcast(new \App\Events\HandoverUpdated($matched->sales_id, $matched->warehouse_id, $matched->id, 'verified'));

        return response()->json(['success' => true]);
    }



    /**
     * Save payment data – supports split cash/transfer.
     */
    public function savePayments(Request $request)
    {
        $me = $request->user();
        $request->validate([
            'handover_id' => 'required|exists:sales_handovers,id',
            'submit_mode' => 'required|in:draft,submit',
            'items'       => 'required|array',
        ]);

        $handover = SalesHandover::with(['items.product'])
            ->where('id', $request->handover_id)
            ->where('sales_id', $me->id)
            ->whereIn('status', ['on_sales', 'waiting_evening_otp'])
            ->firstOrFail();

        $otpVerified = session('sales_handover_otp_verified_' . $handover->id, false) ||
            ($handover->status === 'on_sales' && ! is_null($handover->morning_otp_verified_at));
        if (! $otpVerified) {
            return back()->with('error', 'OTP not verified');
        }

        $isFinal = $request->submit_mode === 'submit';
        $itemsInput = $request->input('items', []);

        try {
            DB::transaction(function () use ($handover, $itemsInput, $request, $isFinal, $me) {
                $touched = 0;
                $totalSold = 0;

                foreach ($handover->items as $item) {
                    if (! isset($itemsInput[$item->id])) {
                        continue;
                    }

                    $status = $item->payment_status ?: 'draft';
                    if (in_array($status, ['pending', 'approved'], true)) {
                        continue;
                    }

                    $row = $itemsInput[$item->id];
                    $qtyStart = (int) $item->qty_start;
                    $unitPrice = (int) ($item->unit_price ?: ($item->product?->selling_price ?? 0));

                    $rawSoldQty = (int) $item->qty_sold;
                    $existingPaymentQty = (int) ($item->payment_qty ?? 0);
                    $isRejected = $status === 'rejected';
                    $isDraftProgress = (! $isRejected && $rawSoldQty > 0 && $existingPaymentQty > 0 && $existingPaymentQty < $rawSoldQty);

                    $baseSoldQty = $isRejected ? 0 : $rawSoldQty;
                    $isReinput = $isRejected || $isDraftProgress;

                    $baseCashQty = $isRejected ? 0 : (int) ($item->payment_cash_qty ?? 0);
                    $baseTransferQty = $isRejected ? 0 : (int) ($item->payment_transfer_qty ?? 0);
                    $baseCashAmt = $isRejected ? 0 : (int) ($item->payment_cash_amount ?? 0);
                    $baseTransferAmt = $isRejected ? 0 : (int) ($item->payment_transfer_amount ?? 0);
                    $proofPaths = $isRejected ? [] : $this->normalizeTransferProofPaths($item);

                    // Qty that can still be paid for this item
                    $editableQty = $isReinput
                        ? max(0, ($baseSoldQty > 0 ? $baseSoldQty : $qtyStart) - ($baseCashQty + $baseTransferQty))
                        : max(0, $qtyStart - $baseSoldQty);

                    $cashQty = max(0, (int) ($row['payment_cash_qty'] ?? 0));
                    $transferQty = max(0, (int) ($row['payment_transfer_qty'] ?? 0));
                    $totalQty = $cashQty + $transferQty;

                    if ($totalQty > $editableQty) {
                        throw new \RuntimeException("Total payment qty ({$totalQty}) exceeds allowed qty ({$editableQty}) for item #{$item->id}");
                    }

                    $proofFile = $request->file("items.{$item->id}.payment_proof");
                    $proofFiles = $proofFile ? (is_array($proofFile) ? $proofFile : [$proofFile]) : [];

                    if (!empty($proofFiles)) {
                        // 🔥 REPLACE LOGIC: Delete old files from storage before replacing
                        foreach ($proofPaths as $oldProof) {
                            $oldPath = $oldProof['path'] ?? null;
                            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                                Storage::disk('public')->delete($oldPath);
                            }
                        }
                        $proofPaths = []; // Reset list so only new ones are stored
                    }

                    $qtySold = $isReinput
                        ? ($isRejected ? $totalQty : $baseSoldQty)
                        : $baseSoldQty + $totalQty;

                    $qtyReturned = max(0, $qtyStart - $qtySold);

                    $cashAmount = max(0, (int) ($row['payment_cash_amount'] ?? 0));
                    $transferAmount = max(0, (int) ($row['payment_transfer_amount'] ?? 0));

                    // Nominal validation
                    if ($cashAmount > ($unitPrice * $cashQty)) {
                        throw new \RuntimeException("Cash amount exceeds allowed value for item #{$item->id}");
                    }
                    if ($transferAmount > ($unitPrice * $transferQty)) {
                        throw new \RuntimeException("Transfer amount exceeds allowed value for item #{$item->id}");
                    }

                    // Proof required when there is a transfer payment
                    if ($transferQty > 0 && $transferAmount > 0 && empty($proofFiles) && empty($proofPaths)) {
                        throw new \RuntimeException("Transfer proof is required for item #{$item->id}");
                    }

                    foreach ($proofFiles as $pf) {
                        // 🔥 OPTIMIZED IMAGE SAVE (Global Helper: Resize & Compress)
                        $stored = save_optimized_image($pf, 'handover_item_transfer_proofs');
                        $proofPaths[] = [
                            'path'   => $stored,
                            'qty'    => $transferQty,
                            'amount' => $transferAmount,
                            'saved_at' => now()->format('Y-m-d H:i:s'),
                        ];
                    }
                    $proofPaths = collect($proofPaths)
                        ->filter(fn($e) => ! empty($e['path']))
                        ->unique('path')
                        ->values()
                        ->all();

                    // Update payment fields
                    $item->payment_cash_qty        = $baseCashQty + $cashQty;
                    $item->payment_cash_amount     = $baseCashAmt + $cashAmount;
                    $item->payment_transfer_qty    = $baseTransferQty + $transferQty;
                    $item->payment_transfer_amount = $baseTransferAmt + $transferAmount;
                    $item->payment_qty             = $item->payment_cash_qty + $item->payment_transfer_qty;
                    $item->payment_amount          = $item->payment_cash_amount + $item->payment_transfer_amount;
                    $item->payment_transfer_proof_paths = $proofPaths;
                    $item->payment_method = ($item->payment_cash_qty > 0 && $item->payment_transfer_qty > 0)
                        ? null
                        : ($item->payment_transfer_qty > 0 ? 'transfer' : 'cash');
                    $item->payment_status = $isFinal ? 'pending' : null;
                    $item->payment_reject_reason = null;

                    // Update pricing / qty fields
                    $item->unit_price = $unitPrice;
                    $item->qty_sold = $qtySold;
                    $item->qty_returned = $qtyReturned;
                    $item->line_total_sold = $qtySold * $unitPrice;

                    // Discount handling
                    $discountPerUnit = (int) ($item->discount_per_unit ?? 0);
                    $netPrice = max(0, $unitPrice - $discountPerUnit);
                    $item->unit_price_after_discount = $netPrice;
                    $item->line_total_after_discount = $qtySold * $netPrice;
                    $item->discount_total = $discountPerUnit * $qtySold;

                    if ((int) $item->line_total_start <= 0) {
                        $item->line_total_start = $qtyStart * $unitPrice;
                    }

                    $item->save();
                    $totalSold += $item->line_total_sold;
                    $touched++;
                }

                if ($touched > 0) {
                    $handover->total_sold_amount = $totalSold;
                    $handover->evening_filled_by_sales = $isFinal;
                    $handover->evening_filled_at = $isFinal ? now() : null;
                    $handover->save();

                    if ($isFinal) {
                        // 🔔 Tambahkan notifikasi database untuk Admin WH
                        \App\Helpers\NotificationHelper::notifyWarehouse(
                            $handover->warehouse_id,
                            'handover_payment_submitted',
                            'Setoran Sore (Approval)',
                            "Sales {$me->name} telah mengirim setoran sore untuk {$handover->code}. Silakan verifikasi.",
                            route('sales.handover.evening', ['handover_id' => $handover->id]),
                            'sales_handovers',
                            $handover->id
                        );

                        // 🔔 Bersihkan notifikasi "rejected" jika ini adalah submit perbaikan
                        \App\Helpers\NotificationHelper::markAsReadByReference('handover_payment_rejected', 'sales_handovers', $handover->id);
                    }
                }
            });

            // 🔥 SINYAL: Kasih tau Admin WH kalau Sales udah submit payment/draft (di luar transaction)
            broadcast(new \App\Events\HandoverUpdated($handover->sales_id, $handover->warehouse_id, $handover->id, $isFinal ? 'payment_submitted' : 'payment_draft_saved'));

            $msg = $isFinal ? 'Submitted' : 'Draft saved';
            if (request()->ajax()) {
                return response()->json(['success' => true, 'message' => $msg, 'isFinal' => $isFinal]);
            }
            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 422);
            }
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
