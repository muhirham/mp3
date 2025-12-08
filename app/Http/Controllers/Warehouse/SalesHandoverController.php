<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class SalesHandoverController extends Controller
{
    /**
     * ISSUE PAGI – simpan & kirim OTP pagi ke email sales.
     */
    public function issue(Request $request)
    {
        $me = auth()->user();

        $validated = $request->validate([
            'handover_date'      => ['required', 'date'],
            'warehouse_id'       => ['required', 'exists:warehouses,id'],
            'sales_id'           => ['required', 'exists:users,id'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty'        => ['required', 'integer', 'min:1'],
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $sales     = User::findOrFail($validated['sales_id']);
        $date      = Carbon::parse($validated['handover_date'])->toDateString();

        DB::beginTransaction();

        try {
            // 1. Generate kode handover
            $dayPrefix  = Carbon::parse($date)->format('ymd'); // 251207
            $codePrefix = 'HDO-' . $dayPrefix . '-';

            $lastToday = SalesHandover::whereDate('handover_date', $date)
                ->orderByDesc('id')
                ->first();

            $nextNumber = 1;
            if ($lastToday) {
                $lastSeq    = (int) substr($lastToday->code, -4);
                $nextNumber = $lastSeq + 1;
            }

            $code = $codePrefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 2. Siapkan data item dan total
            $itemsInput             = $validated['items'];
            $itemsData              = [];
            $totalDispatchedAmount  = 0;

            foreach ($itemsInput as $row) {
                $product = Product::find($row['product_id']);
                if (! $product) {
                    continue;
                }

                $qty = (int) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }

                $unitPrice  = (float) ($product->selling_price ?? 0);
                $lineTotal  = $qty * $unitPrice;
                $totalDispatchedAmount += $lineTotal;

                $itemsData[] = [
                    'product_id'              => $product->id,
                    'product_name'            => $product->name,
                    'product_code'            => $product->product_code,
                    'qty'                     => $qty,
                    'unit_price'              => $unitPrice,
                    'line_total_dispatched'   => $lineTotal,
                ];
            }

            if (empty($itemsData)) {
                throw new \RuntimeException('Minimal harus ada 1 item valid dengan qty > 0.');
            }

            // 3. Simpan header
            $handover = SalesHandover::create([
                'code'                     => $code,
                'warehouse_id'             => $warehouse->id,
                'sales_id'                 => $sales->id,
                'handover_date'            => $date,
                'status'                   => 'issued',
                'issued_by'                => $me->id,
                'total_dispatched_amount'  => $totalDispatchedAmount,
                'total_sold_amount'        => 0,
            ]);

            // 4. Simpan detail
            foreach ($itemsData as $row) {
                SalesHandoverItem::create([
                    'handover_id'            => $handover->id,
                    'product_id'             => $row['product_id'],
                    'qty_dispatched'         => $row['qty'],
                    'qty_returned_good'      => 0,
                    'qty_returned_damaged'   => 0,
                    'qty_sold'               => 0,
                    'unit_price'             => $row['unit_price'],
                    'line_total_dispatched'  => $row['line_total_dispatched'],
                    'line_total_sold'        => 0,
                ]);
            }

            // 5. Generate OTP Pagi
            $otpPlain = random_int(100000, 999999);

            $handover->morning_otp_hash       = Hash::make($otpPlain);
            $handover->morning_otp_expires_at = now()->addHours(24);
            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('sales.handover.morning')
                ->withInput()
                ->with('error', 'Gagal membuat handover: ' . $e->getMessage());
        }

        // 6. Kirim email OTP Pagi
        $detailLines = [];
        foreach ($itemsData as $idx => $row) {
            $detailLines[] = sprintf(
                '%d. %s (%s) → Qty %d x %s = %s',
                $idx + 1,
                $row['product_name'],
                $row['product_code'],
                $row['qty'],
                number_format($row['unit_price'], 0, ',', '.'),
                number_format($row['line_total_dispatched'], 0, ',', '.')
            );
        }
        $detailText = implode("\n", $detailLines);
        $totalText  = number_format($totalDispatchedAmount, 0, ',', '.');

        $subject = 'OTP Handover Pagi & Sore - ' . $handover->code;

        $body = <<<EOT
Halo {$sales->name},

Berikut detail handover pagi dan kode OTP yang akan dipakai juga saat tutup sore.

Handover Pagi - Serah Terima Barang
Kode      : {$handover->code}
Tanggal   : {$handover->handover_date}
Warehouse : {$warehouse->warehouse_name}
Sales     : {$sales->name}

Detail barang:
{$detailText}

Total nilai barang: {$totalText}

OTP Handover (pagi & sore): {$otpPlain}

Simpan baik-baik OTP ini. OTP harus diinput admin gudang ketika tutup sore supaya laporan hari itu bisa di-close dan besok kamu bisa ambil barang lagi.

Terima kasih.
EOT;

        $emailError = null;
        try {
            if ($sales->email) {
                Mail::raw($body, function ($message) use ($sales, $subject) {
                    $message->to($sales->email, $sales->name)
                        ->subject($subject);
                });
            }
        } catch (\Throwable $e) {
            $emailError = $e->getMessage();
        }

        // 7. SweetAlert message
        $html = "Handover berhasil dibuat. Kode : <b>{$handover->code}</b><br>"
            . "Tanggal : {$handover->handover_date}<br>"
            . "Total nilai barang : <b>{$totalText}</b><br>"
            . "OTP handover (pagi & sore) : <b>{$otpPlain}</b>";

        if ($emailError) {
            $html .= "<br><br><b>Namun email OTP TIDAK berhasil dikirim.</b><br>"
                . "Segera kirim OTP di atas ke sales secara manual.<br>"
                . "<small>Error email: " . e($emailError) . '</small>';
        }

        return redirect()
            ->route('sales.handover.morning')
            ->with('success', $html);
    }

    /**
     * API untuk halaman sore: load item handover (AJAX).
     */
    public function items(SalesHandover $handover)
    {
        $handover->load(['items.product']);

        $items = $handover->items->map(function (SalesHandoverItem $item) {
            $product = $item->product;

            return [
                'product_id'         => $product->id,
                'product_name'       => $product->name,
                'product_code'       => $product->product_code,
                'qty_dispatched'     => $item->qty_dispatched,
                'qty_returned_good'  => $item->qty_returned_good,
                'qty_returned_damaged'=> $item->qty_returned_damaged,
                'qty_sold'           => $item->qty_sold,
            ];
        });

        return response()->json([
            'success' => true,
            'items'   => $items,
        ]);
    }

    /**
     * RECONCILE SORE – qty kembali, setor uang, upload bukti tf,
     * validasi OTP pagi & nilai uang vs barang terjual.
     */
    public function reconcile(Request $request, SalesHandover $handover)
    {
        $validated = $request->validate([
            'otp_code'                    => ['required', 'digits:6'],

            'items'                       => ['required', 'array', 'min:1'],
            'items.*.product_id'          => ['required', 'exists:products,id'],
            'items.*.qty_returned_good'   => ['required', 'integer', 'min:0'],
            'items.*.qty_returned_damaged'=> ['required', 'integer', 'min:0'],

            'cash_amount'                 => ['nullable', 'numeric', 'min:0'],
            'transfer_amount'             => ['nullable', 'numeric', 'min:0'],
            'transfer_proof'              => ['nullable', 'image', 'max:2048'], // 2MB
        ]);

        // 1. Validasi OTP pagi
        if (! $handover->morning_otp_hash ||
            ! Hash::check($validated['otp_code'], $handover->morning_otp_hash)
        ) {
            return redirect()
                ->route('sales.handover.evening')
                ->with('error', 'OTP tidak valid. Pastikan kode OTP dari email pagi sudah benar.');
        }

        $handover->load(['items.product', 'sales', 'warehouse']);

        DB::beginTransaction();

        try {
            $itemsClosing      = [];
            $totalSoldAmount   = 0;
            $totalDispatched   = $handover->total_dispatched_amount ?? 0;

            foreach ($validated['items'] as $row) {
                /** @var \App\Models\SalesHandoverItem|null $item */
                $item = $handover->items->firstWhere('product_id', $row['product_id']);
                if (! $item) {
                    continue;
                }

                $qtyGood    = (int) $row['qty_returned_good'];
                $qtyDamaged = (int) $row['qty_returned_damaged'];
                $qtyReturn  = $qtyGood + $qtyDamaged;

                if ($qtyReturn > $item->qty_dispatched) {
                    throw new \RuntimeException(
                        'Qty kembali melebihi qty dibawa untuk produk ' . $item->product->product_code
                    );
                }

                $qtySold = $item->qty_dispatched - $qtyReturn;

                $item->qty_returned_good    = $qtyGood;
                $item->qty_returned_damaged = $qtyDamaged;
                $item->qty_sold             = $qtySold;

                $unitPrice        = (float) ($item->unit_price ?? $item->product->selling_price ?? 0);
                $lineSold         = $qtySold * $unitPrice;
                $item->line_total_sold = $lineSold;
                $item->save();

                $totalSoldAmount += $lineSold;

                $itemsClosing[] = [
                    'product_name'   => $item->product->name,
                    'product_code'   => $item->product->product_code,
                    'qty_dispatched' => $item->qty_dispatched,
                    'qty_good'       => $qtyGood,
                    'qty_damaged'    => $qtyDamaged,
                    'qty_sold'       => $qtySold,
                    'unit_price'     => $unitPrice,
                    'line_sold'      => $lineSold,
                ];
            }

            // 2. Proses setoran uang + bukti transfer
            $cash     = (float) ($validated['cash_amount'] ?? 0);
            $transfer = (float) ($validated['transfer_amount'] ?? 0);
            $proofPath = $handover->transfer_proof_path;

            if ($request->hasFile('transfer_proof')) {
                $proofPath = $request->file('transfer_proof')
                    ->store('handover_transfer_proofs', 'public');
            }

            // nilai barang terjual harus sama dengan total setor
            if (round($cash + $transfer) !== round($totalSoldAmount)) {
                throw new \RuntimeException(
                    'Total setor (tunai + transfer) harus sama dengan nilai barang terjual.'
                );
            }

            // 3. Update header
            $handover->status             = 'reconciled';
            $handover->reconciled_by      = auth()->id();
            $handover->reconciled_at      = now();
            $handover->total_sold_amount  = $totalSoldAmount;
            $handover->cash_amount        = $cash;
            $handover->transfer_amount    = $transfer;
            $handover->transfer_proof_path= $proofPath;
            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('sales.handover.evening')
                ->with('error', 'Gagal rekonsiliasi: ' . $e->getMessage());
        }

        // 4. Kirim email closing (OTP sore)
        $closingOtp = random_int(100000, 999999);

        $handover->closing_otp_hash       = Hash::make($closingOtp);
        $handover->closing_otp_expires_at = now()->addHours(24);
        $handover->save();

        $detailLines = [];
        foreach ($itemsClosing as $idx => $row) {
            $detailLines[] = sprintf(
                "%d. %s (%s)\n   Dibawa: %d | Kembali (good): %d | Rusak: %d | Terjual: %d",
                $idx + 1,
                $row['product_name'],
                $row['product_code'],
                $row['qty_dispatched'],
                $row['qty_good'],
                $row['qty_damaged'],
                $row['qty_sold'],
            );
        }
        $detailText   = implode("\n\n", $detailLines);
        $soldText     = number_format($totalSoldAmount, 0, ',', '.');
        $cashText     = number_format($cash, 0, ',', '.');
        $transferText = number_format($transfer, 0, ',', '.');

        $subject = 'OTP Handover Sore (Closing) - ' . $handover->code;

        $body = <<<EOT
Halo {$handover->sales->name},

Berikut hasil rekonsiliasi sore untuk handover berikut:

Kode      : {$handover->code}
Tanggal   : {$handover->handover_date}
Warehouse : {$handover->warehouse->warehouse_name}
Sales     : {$handover->sales->name}

Rincian barang:
{$detailText}

Nilai barang terjual (berdasarkan harga jual master produk): {$soldText}

Setoran:
- Tunai    : {$cashText}
- Transfer : {$transferText}

OTP Handover Sore (closing): {$closingOtp}

OTP ini bisa kamu simpan sebagai bukti bahwa laporan sore sudah di-close oleh admin gudang.

Terima kasih.
EOT;

        $emailError = null;
        try {
            if ($handover->sales->email) {
                Mail::raw($body, function ($message) use ($handover, $subject) {
                    $message->to($handover->sales->email, $handover->sales->name)
                        ->subject($subject);
                });
            }
        } catch (\Throwable $e) {
            $emailError = $e->getMessage();
        }

        $html = "Rekonsiliasi berhasil untuk handover <b>{$handover->code}</b>.<br>"
            . "Total nilai terjual: <b>{$soldText}</b><br>"
            . "Silakan cek email sales untuk detail laporan sore & OTP closing.";

        if ($emailError) {
            $html .= "<br><br><b>Namun email OTP sore TIDAK berhasil dikirim.</b><br>"
                . "<small>Error email: " . e($emailError) . "</small>";
        }

        return redirect()
            ->route('sales.handover.evening')
            ->with('success', $html);
    }
}
