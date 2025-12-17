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
     * Helper: parse nilai kolom OTP.
     */
    protected function parseOtpValue(?string $value): array
    {
        if (! $value) {
            return [null, null]; // [plain, hash]
        }

        if (str_contains($value, '|')) {
            [$plain, $hash] = explode('|', $value, 2);
            $plain = trim($plain) !== '' ? trim($plain) : null;
            $hash  = trim($hash)  !== '' ? trim($hash)  : null;
            return [$plain, $hash];
        }

        if (
            str_starts_with($value, '$2y$') ||
            str_starts_with($value, '$2a$') ||
            str_starts_with($value, '$2b$') ||
            str_starts_with($value, '$argon2')
        ) {
            return [null, $value]; // hash only
        }

        return [$value, null]; // plain 6 digit
    }

    /**
     * Halaman OTP pagi & list barang dibawa hari ini
     * + form isi payment per item.
     */
    public function index(Request $request)
    {
        $me = $request->user();

        $handover = SalesHandover::with([
                'warehouse',
                'items.product',
                'sales',
            ])
            ->where('sales_id', $me->id)
            ->whereIn('status', [
                'waiting_morning_otp',
                'on_sales',
                'waiting_evening_otp',
            ])
            ->orderByDesc('handover_date')
            ->orderByDesc('id')
            ->first();

        $isOtpVerified  = false;
        $items          = collect();
        $canEditPayment = false;

        if ($handover) {
            $sessionKey    = 'sales_handover_otp_verified_'.$handover->id;
            $isOtpVerified = (bool) $request->session()->get($sessionKey, false);

            if ($isOtpVerified) {
                $items = $handover->items()
                    ->with('product')
                    ->orderBy('id')
                    ->get();

                // payment cuma boleh saat on_sales / waiting_evening_otp
                $canEditPayment = in_array($handover->status, ['on_sales', 'waiting_evening_otp']);
            }
        }

        return view('sales.handover_otp', [
            'me'             => $me,
            'handover'       => $handover,
            'items'          => $items,
            'isOtpVerified'  => $isOtpVerified,
            'canEditPayment' => $canEditPayment,
        ]);
    }


    /**
     * Verifikasi OTP pagi via AJAX.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp_code'    => ['required', 'string'],
            'handover_id' => ['nullable', 'integer'],
        ]);

        $me = $request->user();

        $query = SalesHandover::with(['warehouse', 'items.product', 'sales'])
            ->where('sales_id', $me->id)
            ->whereIn('status', [
                'waiting_morning_otp',
                'on_sales',
                'waiting_evening_otp',
            ]);

        if ($request->handover_id) {
            $query->where('id', $request->handover_id);
        }

        $handover = $query
            ->orderByDesc('handover_date')
            ->orderByDesc('id')
            ->first();

        if (! $handover) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada handover aktif untuk hari ini.',
            ], 404);
        }

        $inputOtp = trim((string) $request->otp_code);
        [$plainStored, $hashStored] = $this->parseOtpValue($handover->morning_otp_hash);

        if (! $plainStored && ! $hashStored) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP pagi belum dibuat oleh admin warehouse.',
            ], 422);
        }

        $isValid = false;

        if ($hashStored) {
            $isValid = Hash::check($inputOtp, $hashStored);
        } elseif ($plainStored) {
            $isValid = hash_equals($plainStored, $inputOtp);
        }

        if (! $isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak sesuai.',
            ], 422);
        }

        $sessionKey = 'sales_handover_otp_verified_'.$handover->id;
        $request->session()->put($sessionKey, true);

        return response()->json([
            'success' => true,
            'message' => 'OTP valid.',
        ]);
    }


    /**
     * SALES isi payment per item.
     */
    public function savePayments(Request $request)
    {
        $me = $request->user();

        $request->validate([
            'handover_id' => ['required', 'integer', 'exists:sales_handovers,id'],
            'items'       => ['required', 'array'],
            'items.*.payment_qty'    => ['nullable', 'integer', 'min:0'],
            'items.*.payment_method' => ['nullable', 'in:cash,transfer'],
            'items.*.payment_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.payment_proof'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $handover = SalesHandover::with(['items.product'])->findOrFail($request->handover_id);

        // keamanan: cuma boleh handover milik sales yang login
        if ((int) $handover->sales_id !== (int) $me->id) {
            abort(403, 'Tidak boleh mengubah handover milik sales lain.');
        }

        // hanya bisa edit saat open
        if (!in_array($handover->status, ['on_sales', 'waiting_evening_otp'], true)) {
            return back()->with('error', 'Handover tidak bisa diisi payment pada status ini.');
        }

        // wajib OTP verified
        $sessionKey = 'sales_handover_otp_verified_'.$handover->id;
        if (!$request->session()->get($sessionKey, false)) {
            return back()->with('error', 'OTP pagi belum diverifikasi.');
        }

        $itemsInput = $request->input('items', []);

        try {
            DB::transaction(function () use ($handover, $itemsInput, $request) {

                $touched   = 0;
                $totalSold = 0;

                foreach ($handover->items as $item) {
                    if (!isset($itemsInput[$item->id])) {
                        continue;
                    }

                    $currentStatus = $item->payment_status ?: 'draft';

                    // LOCK server: pending/approved ga boleh diubah
                    if (in_array($currentStatus, ['pending', 'approved'], true)) {
                        continue;
                    }

                    $row = $itemsInput[$item->id];

                    $qtyStart  = (int) $item->qty_start;
                    $unitPrice = (int) ($item->unit_price ?: ($item->product?->selling_price ?? 0));
                    $maxQty    = $qtyStart;

                    $qtyBayar = (int) ($row['payment_qty'] ?? 0);
                    if ($qtyBayar < 0) $qtyBayar = 0;
                    if ($qtyBayar > $maxQty) $qtyBayar = $maxQty;

                    $method = $row['payment_method'] ?? null;
                    $method = ($method !== null && trim($method) === '') ? null : $method;

                    $proofFile = $request->file("items.{$item->id}.payment_proof");

                    // ====== HITUNG SOLD/RETURNED OTOMATIS (INI YANG LO MAU) ======
                    // Terjual = qty bayar
                    $qtySold = $qtyBayar;

                    // Kembali = qty_start - qty_sold
                    $qtyReturned = max(0, $qtyStart - $qtySold);

                    $lineSold = $qtySold * $unitPrice;

                    // ====== PAYMENT LOGIC ======
                    // Kalau qtyBayar = 0 -> anggap batal / tidak ada transaksi
                    // Balikin field payment ke 0/null & status ke draft (biar bisa diubah lagi)
                    if ($qtyBayar === 0) {

                        // kalau ada bukti lama, hapus biar gak numpuk & biar bener-bener reset
                        if ($item->payment_transfer_proof_path) {
                            Storage::disk('public')->delete($item->payment_transfer_proof_path);
                            $item->payment_transfer_proof_path = null;
                        }

                        $item->payment_qty           = 0;
                        $item->payment_method        = null;
                        $item->payment_amount        = 0;
                        $item->payment_status        = 'draft';
                        $item->payment_reject_reason = null;

                    } else {
                        // qtyBayar > 0 => metode wajib
                        if (!$method) {
                            throw new \RuntimeException("Metode payment wajib diisi (Item ID: {$item->id}).");
                        }

                        // nominal dibatasi max = unitPrice * qtySold
                        $maxNominal = $unitPrice * $qtySold;
                        $nominal    = (int) ($row['payment_amount'] ?? 0);
                        if ($nominal < 0) $nominal = 0;
                        if ($nominal > $maxNominal) $nominal = $maxNominal;

                        // transfer & nominal > 0 => butuh bukti (baru atau existing)
                        if ($method === 'transfer' && $nominal > 0 && !$proofFile && !$item->payment_transfer_proof_path) {
                            throw new \RuntimeException("Bukti transfer wajib diupload (Item ID: {$item->id}).");
                        }

                        // kalau method bukan transfer, bersihin proof lama biar gak nyangkut
                        if ($method !== 'transfer' && $item->payment_transfer_proof_path) {
                            Storage::disk('public')->delete($item->payment_transfer_proof_path);
                            $item->payment_transfer_proof_path = null;
                        }

                        // replace proof jika upload baru
                        if ($proofFile) {
                            if ($item->payment_transfer_proof_path) {
                                Storage::disk('public')->delete($item->payment_transfer_proof_path);
                            }
                            $item->payment_transfer_proof_path = $proofFile->store('handover_item_transfer_proofs', 'public');
                        }

                        $item->payment_qty           = $qtyBayar;
                        $item->payment_method        = $method;
                        $item->payment_amount        = $nominal;
                        $item->payment_status        = 'pending';
                        $item->payment_reject_reason = null;
                    }

                    // ====== SIMPAN SOLD/RETURNED/LINE SOLD (INI BIAR UI KEISI & WH BISA PROSES) ======
                    $item->unit_price      = $unitPrice;
                    $item->qty_sold        = $qtySold;
                    $item->qty_returned    = $qtyReturned;
                    $item->line_total_sold = $lineSold;

                    // (optional aman) pastiin line_total_start kebentuk kalau masih 0
                    if ((int) $item->line_total_start <= 0) {
                        $item->line_total_start = $qtyStart * $unitPrice;
                    }

                    $item->save();

                    $totalSold += $lineSold;
                    $touched++;
                }

                if ($touched <= 0) {
                    throw new \RuntimeException('Tidak ada item yang bisa diubah. Item PENDING/APPROVED terkunci.');
                }

                // update header biar report gak 0 terus
                $handover->total_sold_amount       = $totalSold;
                $handover->evening_filled_by_sales = true;
                $handover->evening_filled_at       = now();
                $handover->save();
            });

        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal simpan payment: '.$e->getMessage());
        }

        return back()->with('success', 'Data payment berhasil disimpan, menunggu approval admin warehouse.');
    }


}
