<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
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
        $me    = $request->user();
        $today = now()->toDateString();

        $handover = SalesHandover::with([
                'warehouse',
                'items.product',
                'sales',
            ])
            ->where('sales_id', $me->id)
            ->whereDate('handover_date', $today)
            ->whereIn('status', [
                'waiting_morning_otp',
                'on_sales',
                'waiting_evening_otp',
            ])
            ->latest('id')
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
            'me'            => $me,
            'handover'      => $handover,
            'items'         => $items,
            'isOtpVerified' => $isOtpVerified,
            'canEditPayment'=> $canEditPayment,
        ]);
    }

    /**
     * Verifikasi OTP pagi via AJAX.
     * Setelah sukses → reload halaman (biar render form payment dari server).
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp_code'    => ['required', 'string'],
            'handover_id' => ['nullable', 'integer'],
        ]);

        $me    = $request->user();
        $today = now()->toDateString();

        $query = SalesHandover::with(['warehouse', 'items.product', 'sales'])
            ->where('sales_id', $me->id)
            ->whereDate('handover_date', $today)
            ->whereIn('status', [
                'waiting_morning_otp',
                'on_sales',
                'waiting_evening_otp',
            ]);

        if ($request->handover_id) {
            $query->where('id', $request->handover_id);
        }

        $handover = $query->latest('id')->first();

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

        $data = $request->validate([
            'handover_id'            => ['required', 'exists:sales_handovers,id'],
            'items'                  => ['required', 'array'],
            'items.*.payment_qty'    => ['nullable', 'integer', 'min:0'],
            'items.*.payment_method' => ['nullable', 'in:cash,transfer'],
            'items.*.payment_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.payment_proof'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $handover = SalesHandover::with('items.product')
            ->where('id', $data['handover_id'])
            ->where('sales_id', $me->id)
            ->firstOrFail();

        if (! in_array($handover->status, ['on_sales', 'waiting_evening_otp'])) {
            return back()->with('error', 'Payment hanya bisa diisi ketika status handover ON_SALES / WAITING_EVENING_OTP.');
        }

        try {
            DB::beginTransaction();

            foreach ($handover->items as $item) {
                $key = (string) $item->id;
                if (! isset($data['items'][$key])) {
                    continue;
                }

                $row = $data['items'][$key];

                // Kalau sudah approved, jangan bisa diubah lagi.
                if ($item->payment_status === 'approved') {
                    continue;
                }

                $qty    = (int) ($row['payment_qty'] ?? 0);
                $method = $row['payment_method'] ?? null;
                $amount = (int) ($row['payment_amount'] ?? 0);

                // ================================
                // KOSONG SEMUA → RESET KE NULL
                // ================================
                if ($qty === 0 && ! $method && $amount === 0 && ! $request->hasFile("items.$key.payment_proof")) {
                    $item->payment_qty                 = 0;
                    $item->payment_method              = null;
                    $item->payment_amount              = 0;
                    $item->payment_status              = null; // <--- BUKAN pending, biarin kosong
                    $item->payment_reject_reason       = null;
                    // bukti tidak dihapus, biarin aja kalau ada
                    $item->save();
                    continue;
                }

                // ================================
                // Validasi basic (kalau ada isi)
                // ================================
                if ($qty < 0) {
                    throw new \RuntimeException("Qty bayar untuk {$item->product->name} tidak boleh minus.");
                }

                if ($qty > $item->qty_sold && $item->qty_sold > 0) {
                    throw new \RuntimeException("Qty bayar untuk {$item->product->name} tidak boleh melebihi qty terjual.");
                }

                if (! $method) {
                    throw new \RuntimeException("Metode pembayaran wajib diisi untuk {$item->product->name}.");
                }

                if ($amount <= 0) {
                    throw new \RuntimeException("Nominal pembayaran untuk {$item->product->name} harus lebih dari 0.");
                }

                // ================================
                // Bukti transfer
                // ================================
                $proofPath = $item->payment_transfer_proof_path;
                if ($method === 'transfer') {
                    if ($request->hasFile("items.$key.payment_proof")) {
                        $file = $request->file("items.$key.payment_proof");

                        // hapus file lama kalau ada
                        if ($proofPath && Storage::disk('public')->exists($proofPath)) {
                            Storage::disk('public')->delete($proofPath);
                        }

                        $proofPath = $file->store('handover_item_payments', 'public');
                    } elseif (! $proofPath) {
                        throw new \RuntimeException("Bukti transfer wajib diupload untuk {$item->product->name}.");
                    }
                } else {
                    // cash -> kalau ada file baru, simpan; kalau tidak, abaikan
                    if ($request->hasFile("items.$key.payment_proof")) {
                        $file = $request->file("items.$key.payment_proof");

                        if ($proofPath && Storage::disk('public')->exists($proofPath)) {
                            Storage::disk('public')->delete($proofPath);
                        }

                        $proofPath = $file->store('handover_item_payments', 'public');
                    }
                }

                // ================================
                // Isi & set status ke pending
                // (karena memang sudah diisi payment)
                // ================================
                $item->payment_qty                 = $qty;
                $item->payment_method              = $method;
                $item->payment_amount              = $amount;
                $item->payment_transfer_proof_path = $proofPath;
                $item->payment_status              = 'pending';
                $item->payment_reject_reason       = null;
                $item->save();
            }

            // Update summary di header (buat report + flag sore sudah diisi sales)
            $handover->cash_amount = (int) $handover->items()
                ->where('payment_method', 'cash')
                ->sum('payment_amount');

            $handover->transfer_amount = (int) $handover->items()
                ->where('payment_method', 'transfer')
                ->sum('payment_amount');

            // >>> FLAG: sore sudah diisi sales <<<
            $handover->evening_filled_by_sales = true;
            $handover->evening_filled_at       = now();

            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->with('error', 'Gagal menyimpan payment: ' . $e->getMessage())
                ->withInput();
        }

        return back()->with('success', 'Payment per item berhasil disimpan. Menunggu approval admin gudang.');
    }
}
