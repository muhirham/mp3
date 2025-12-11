<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HandoverOtpItemsController extends Controller
{
    /**
     * Helper: parse nilai kolom OTP.
     * Bisa 3 kemungkinan:
     *  - "PLAIN|HASH"
     *  - HASH murni (bcrypt/argon)
     *  - PLAIN 6 digit (data lama / salah simpan)
     */
    protected function parseOtpValue(?string $value): array
    {
        if (! $value) {
            return [null, null]; // [plain, hash]
        }

        // Format "PLAIN|HASH"
        if (str_contains($value, '|')) {
            [$plain, $hash] = explode('|', $value, 2);
            $plain = trim($plain) !== '' ? trim($plain) : null;
            $hash  = trim($hash)  !== '' ? trim($hash)  : null;
            return [$plain, $hash];
        }

        // Kalau kelihatan seperti hash (bcrypt/argon)
        if (
            str_starts_with($value, '$2y$') ||
            str_starts_with($value, '$2a$') ||
            str_starts_with($value, '$2b$') ||
            str_starts_with($value, '$argon2')
        ) {
            return [null, $value]; // hash only
        }

        // Selain itu anggap plain OTP (contoh: "422516")
        return [$value, null];
    }

    /**
     * Halaman OTP pagi & list barang dibawa hari ini (read only).
     */
    public function index(Request $request)
    {
        $me    = $request->user();
        $today = now()->toDateString();

        // cari handover aktif untuk sales ini di hari ini
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

        $isOtpVerified = false;
        $items         = collect();

        if ($handover) {
            // flag per-session: OTP pagi sudah dimasukkan di menu Sales ini
            $sessionKey    = 'sales_handover_otp_verified_'.$handover->id;
            $isOtpVerified = (bool) $request->session()->get($sessionKey, false);

            if ($isOtpVerified) {
                $items = $handover->items;
            }
        }

        return view('sales.handover_otp', [
            'me'            => $me,
            'handover'      => $handover,
            'items'         => $items,
            'isOtpVerified' => $isOtpVerified,
        ]);
    }

    /**
     * Verifikasi OTP pagi via AJAX dan balikin data item.
     * HANYA untuk kebutuhan tampilan Sales (read-only), tidak mengubah stok.
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

        // kalau ada hash valid → pakai Hash::check
        if ($hashStored) {
            $isValid = Hash::check($inputOtp, $hashStored);
        }
        // kalau cuma plain OTP → bandingkan biasa
        elseif ($plainStored) {
            $isValid = hash_equals($plainStored, $inputOtp);
        }

        if (! $isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak sesuai.',
            ], 422);
        }

        // ========= OTP VALID =========
        // TIDAK mengubah stok/status.
        // Cuma set flag di session bahwa OTP sudah dimasukkan di menu Sales.
        $sessionKey = 'sales_handover_otp_verified_'.$handover->id;
        $request->session()->put($sessionKey, true);

        $handover->loadMissing('items.product', 'warehouse', 'sales');

        // mapping item untuk dikirim ke JS
        $items = $handover->items->map(function ($row) {
            $p = $row->product;

            return [
                'product_name'  => $p->name
                                   ?? $p->product_name
                                   ?? '-',
                'product_code'  => $p->product_code ?? '',
                'qty_start'     => (int) $row->qty_start,
                'qty_returned'  => (int) $row->qty_returned,
                'qty_sold'      => (int) $row->qty_sold,
                'unit_price'    => (int) $row->unit_price,
                'line_start'    => (int) $row->line_total_start,
                'line_sold'     => (int) $row->line_total_sold,
            ];
        });

        return response()->json([
            'success'  => true,
            'message'  => 'OTP berhasil diverifikasi.',
            'handover' => [
                'id'             => $handover->id,
                'code'           => $handover->code,
                'date'           => optional($handover->handover_date)->format('Y-m-d'),
                'warehouse_name' => $handover->warehouse->warehouse_name
                                    ?? $handover->warehouse->name
                                    ?? '-',
                'sales_name'     => $handover->sales->name ?? '-',
                'status'         => $handover->status,
                'verified_at'    => now()->format('Y-m-d H:i'),
            ],
            'items'   => $items,
        ]);
    }
}
