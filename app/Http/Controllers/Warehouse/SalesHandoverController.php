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
use Illuminate\Support\Facades\Schema;

class SalesHandoverController extends Controller
{
    /**
     * HALAMAN PAGI:
     * - Form buat handover baru + kirim OTP pagi
     * - List handover waiting_morning_otp buat verifikasi OTP pagi
     */
    public function morningForm(Request $request)
    {
        $me = auth()->user();

        // Warehouses
        $whQuery = Warehouse::query();
        if ($me->warehouse_id) {
            $whQuery->where('id', $me->warehouse_id);
        }

        if (Schema::hasColumn('warehouses', 'warehouse_name')) {
            $whQuery->orderBy('warehouse_name');
            $warehouses = $whQuery->get(['id', DB::raw('warehouse_name as name')]);
        } elseif (Schema::hasColumn('warehouses', 'name')) {
            $whQuery->orderBy('name');
            $warehouses = $whQuery->get(['id', 'name']);
        } else {
            $warehouses = $whQuery->get(['id'])->map(
                fn ($w) => (object) ['id' => $w->id, 'name' => 'Warehouse #' . $w->id]
            );
        }

        // Sales list
        $salesUsers = User::whereHas('roles', fn ($q) => $q->where('slug', 'sales'))
            ->when($me->warehouse_id, fn ($q) => $q->where('warehouse_id', $me->warehouse_id))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'warehouse_id']);

        // Products with selling_price
        $products = Product::select('id', 'name', 'product_code', 'selling_price')
            ->orderBy('name')
            ->get();

        // Handovers menunggu OTP pagi
        $waitingMorning = SalesHandover::with('sales:id,name')
            ->where('status', 'waiting_morning_otp')
            ->when($me->warehouse_id, fn ($q) => $q->where('warehouse_id', $me->warehouse_id))
            ->orderBy('handover_date', 'desc')
            ->orderBy('code')
            ->get();

        return view('wh.handover_morning', compact(
            'me',
            'warehouses',
            'salesUsers',
            'products',
            'waitingMorning'
        ));
    }

    /**
     * PAGI – Simpan handover (draft) + kirim OTP pagi ke email sales
     * Status: waiting_morning_otp
     */
    public function morningStoreAndSendOtp(Request $request)
    {
        $me = auth()->user();

        $data = $request->validate([
            'handover_date'      => ['required', 'date'],
            'warehouse_id'       => ['required', 'exists:warehouses,id'],
            'sales_id'           => ['required', 'exists:users,id'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty'        => ['required', 'integer', 'min:1'],
        ]);

        $date      = Carbon::parse($data['handover_date'])->toDateString();
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $sales     = User::findOrFail($data['sales_id']);

        $itemsData = [];

        try {
            DB::beginTransaction();

            // Generate kode HDO-YYMMDD-XXXX
            $dayPrefix  = Carbon::parse($date)->format('ymd');
            $codePrefix = 'HDO-' . $dayPrefix . '-';

            $lastToday = SalesHandover::whereDate('handover_date', $date)
                ->orderByDesc('id')
                ->first();

            $nextNumber = $lastToday
                ? ((int) substr($lastToday->code, -4)) + 1
                : 1;

            $code = $codePrefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Buat header
            $handover = SalesHandover::create([
                'code'          => $code,
                'warehouse_id'  => $warehouse->id,
                'sales_id'      => $sales->id,
                'handover_date' => $date,
                'status'        => 'waiting_morning_otp',
                'issued_by'     => $me->id,
            ]);

            // Detail item
            foreach ($data['items'] as $row) {
                $product = Product::find($row['product_id']);
                if (! $product) {
                    continue;
                }

                $qty = (int) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }

                $unitPrice = (float) ($product->selling_price ?? 0);

                $itemsData[] = [
                    'product'    => $product,
                    'qty'        => $qty,
                    'unit_price' => $unitPrice,
                ];

                SalesHandoverItem::create([
                    'handover_id'      => $handover->id,
                    'product_id'       => $product->id,
                    'qty_start'        => $qty,
                    'qty_returned'     => 0,
                    'qty_sold'         => 0,
                    'unit_price'       => $unitPrice,
                    'line_total_start' => 0,
                    'line_total_sold'  => 0,
                ]);
            }

            if (empty($itemsData)) {
                throw new \RuntimeException('Minimal harus ada 1 item valid (produk + qty > 0).');
            }

            // Generate OTP pagi
            $otp = random_int(100000, 999999);

            $handover->morning_otp_hash   = Hash::make($otp);
            $handover->morning_otp_sent_at = now();
            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->with('error', 'Gagal membuat handover: ' . $e->getMessage())
                ->withInput();
        }

        // Kirim email OTP Pagi ke SALES
        if ($sales->email) {
            $lines = [];
            foreach ($itemsData as $i => $row) {
                $p = $row['product'];
                $lines[] = sprintf(
                    '%d. %s (%s) — Qty %d x %s',
                    $i + 1,
                    $p->name,
                    $p->product_code,
                    $row['qty'],
                    number_format($row['unit_price'], 0, ',', '.')
                );
            }
            $detailText = implode("\n", $lines);

            $body = <<<EOT
Halo {$sales->name},

Berikut draft handover PAGI (serah terima barang):

Kode      : {$handover->code}
Tanggal   : {$handover->handover_date}
Warehouse : {$warehouse->warehouse_name}
Sales     : {$sales->name}

Detail barang dibawa (draft):
{$detailText}

OTP Handover Pagi: {$otp}

OTP ini dipegang oleh SALES.
Setelah admin gudang input OTP ini, barang dianggap resmi dibawa sales dan stok gudang dipindah ke stok sales.

Terima kasih.
EOT;

            try {
                Mail::raw($body, function ($message) use ($sales, $handover) {
                    $message->to($sales->email, $sales->name)
                        ->subject('OTP Handover Pagi - ' . $handover->code);
                });
            } catch (\Throwable $e) {
                return back()->with(
                    'success',
                    "Handover {$handover->code} berhasil dibuat.<br>Namun email OTP pagi gagal dikirim: " . e($e->getMessage())
                );
            }
        }

        return back()->with(
            'success',
            "Handover {$handover->code} berhasil dibuat dan OTP pagi sudah dikirim ke email sales."
        );
    }

    /**
     * Verifikasi OTP Pagi:
     * - pastikan OTP benar
     * - hitung total_dispatched_amount
     * - pindah stok warehouse -> sales
     * - status: waiting_morning_otp -> on_sales
     */
    public function verifyMorningOtp(Request $request)
    {
        $data = $request->validate([
            'handover_id' => ['required', 'exists:sales_handovers,id'],
            'otp_code'    => ['required', 'digits:6'],
        ]);

        $handover = SalesHandover::with(['items.product', 'warehouse', 'sales'])
            ->findOrFail($data['handover_id']);

        if ($handover->status !== 'waiting_morning_otp') {
            return back()->with('error', "Status handover harus 'waiting_morning_otp' untuk verifikasi OTP pagi.");
        }

        if (! $handover->morning_otp_hash || ! Hash::check($data['otp_code'], $handover->morning_otp_hash)) {
            return back()->with('error', 'OTP pagi tidak valid.');
        }

        try {
            DB::beginTransaction();

            $totalDispatched = 0;

            foreach ($handover->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }

                $qty = (int) $item->qty_start;
                if ($qty <= 0) {
                    continue;
                }

                // Harga satuan, fallback ke selling_price
                $unitPrice = $item->unit_price ?: (float) ($product->selling_price ?? 0);
                $lineTotal = $qty * $unitPrice;

                // Update item nilai awal
                $item->unit_price       = $unitPrice;
                $item->line_total_start = $lineTotal;
                $item->save();

                $totalDispatched += $lineTotal;

                // --- Update stok: warehouse -> sales ---
                // stok warehouse
                $whStock = DB::table('stock_levels')
                    ->where('owner_type', 'warehouse')
                    ->where('owner_id', $handover->warehouse_id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $whStock || $whStock->quantity < $qty) {
                    throw new \RuntimeException("Stok gudang tidak cukup untuk produk {$product->name}.");
                }

                DB::table('stock_levels')
                    ->where('id', $whStock->id)
                    ->update([
                        'quantity'   => $whStock->quantity - $qty,
                        'updated_at' => now(),
                    ]);

                // stok sales
                $salesStock = DB::table('stock_levels')
                    ->where('owner_type', 'sales')
                    ->where('owner_id', $handover->sales_id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if ($salesStock) {
                    DB::table('stock_levels')
                        ->where('id', $salesStock->id)
                        ->update([
                            'quantity'   => $salesStock->quantity + $qty,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('stock_levels')->insert([
                        'owner_type' => 'sales',
                        'owner_id'   => $handover->sales_id,
                        'product_id' => $product->id,
                        'quantity'   => $qty,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // movement warehouse -> sales
                $movement = [
                    'product_id' => $product->id,
                    'from_type'  => 'warehouse',
                    'from_id'    => $handover->warehouse_id,
                    'to_type'    => 'sales',
                    'to_id'      => $handover->sales_id,
                    'quantity'   => $qty,
                    'note'       => "Handover {$handover->code} (issue pagi)",
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('stock_movements', 'status')) {
                    $movement['status'] = 'completed';
                }

                DB::table('stock_movements')->insert($movement);
            }

            $handover->total_dispatched_amount = $totalDispatched;
            $handover->status                  = 'on_sales';
            $handover->morning_otp_verified_at = now();
            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal verifikasi OTP pagi: ' . $e->getMessage());
        }

        return back()->with(
            'success',
            "OTP pagi valid. Stok sudah dipindah ke sales dan nilai bawaan tersimpan."
        );
    }

    /**
     * HALAMAN SORE:
     * - pilih handover status on_sales => input qty_returned, generate OTP sore
     * - verifikasi OTP sore (status waiting_evening_otp)
     */
    public function eveningForm(Request $request)
    {
        $me = auth()->user();

        $onSales = SalesHandover::with('sales:id,name')
            ->where('status', 'on_sales')
            ->when($me->warehouse_id, fn ($q) => $q->where('warehouse_id', $me->warehouse_id))
            ->orderBy('handover_date', 'desc')
            ->orderBy('code')
            ->get();

        $waitingEvening = SalesHandover::with('sales:id,name')
            ->where('status', 'waiting_evening_otp')
            ->when($me->warehouse_id, fn ($q) => $q->where('warehouse_id', $me->warehouse_id))
            ->orderBy('handover_date', 'desc')
            ->orderBy('code')
            ->get();

        return view('wh.handover_evening', compact('me', 'onSales', 'waitingEvening'));
    }

    /**
     * API JSON – load detail item untuk sore
     */
    public function eveningItems(SalesHandover $handover)
    {
        $handover->load(['items.product', 'sales', 'warehouse']);

        $items = $handover->items->map(function (SalesHandoverItem $item) {
            $p = $item->product;

            return [
                'product_id'   => $item->product_id,
                'product_name' => $p?->name ?? ('Produk #' . $item->product_id),
                'product_code' => $p?->product_code ?? '',
                'qty_start'    => (int) $item->qty_start,
                'qty_returned' => (int) $item->qty_returned,
                'qty_sold'     => (int) $item->qty_sold,
                'unit_price'   => (float) $item->unit_price,
            ];
        });

        return response()->json([
            'success'  => true,
            'handover' => [
                'id'            => $handover->id,
                'code'          => $handover->code,
                'handover_date' => optional($handover->handover_date)->format('Y-m-d'),
                'sales_name'    => $handover->sales->name ?? null,
                'warehouse'     => $handover->warehouse->warehouse_name ?? null,
            ],
            'items' => $items,
        ]);
    }

    /**
     * SORE – simpan qty_returned, hitung qty_sold & total_sold_amount,
     * simpan setoran tunai/transfer + bukti tf,
     * generate OTP sore, status: on_sales -> waiting_evening_otp
     */
    public function eveningSaveAndSendOtp(Request $request, SalesHandover $handover)
    {
        if ($handover->status !== 'on_sales') {
            return back()->with('error', 'Handover harus berstatus ON_SALES untuk rekonsiliasi sore.');
        }

        $data = $request->validate([
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.product_id'           => ['required', 'integer'],
            'items.*.qty_returned'         => ['required', 'integer', 'min:0'],
            'cash_amount'                  => ['required', 'integer', 'min:0'],
            'transfer_amount'              => ['required', 'integer', 'min:0'],
            'transfer_proof'               => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $cashAmount     = (int) $data['cash_amount'];
        $transferAmount = (int) $data['transfer_amount'];

        // Kalau ada nilai transfer, wajib upload bukti tf
        if ($transferAmount > 0 && ! $request->hasFile('transfer_proof')) {
            return back()
                ->with('error', 'Jika ada nominal transfer, bukti transfer wajib diupload.')
                ->withInput();
        }

        // Map product_id => qty_returned
        $inputReturned = [];
        foreach ($data['items'] as $row) {
            $inputReturned[(int) $row['product_id']] = (int) $row['qty_returned'];
        }

        $handover->load(['items.product', 'sales', 'warehouse']);

        try {
            DB::beginTransaction();

            $totalSold = 0;

            foreach ($handover->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }

                $qtyStart = (int) $item->qty_start;
                $qtyRet   = $inputReturned[$item->product_id] ?? 0;

                if ($qtyRet < 0 || $qtyRet > $qtyStart) {
                    throw new \RuntimeException("Qty kembali tidak valid untuk {$product->name}.");
                }

                $qtySold = max(0, $qtyStart - $qtyRet);

                // harga rupiah utuh (integer)
                $unitPrice = $item->unit_price ?: (int) ($product->selling_price ?? 0);
                $lineSold  = $qtySold * $unitPrice;

                $item->qty_returned    = $qtyRet;
                $item->qty_sold        = $qtySold;
                $item->unit_price      = $unitPrice;
                $item->line_total_sold = $lineSold;
                $item->save();

                $totalSold += $lineSold;
            }

            // simpan nilai penjualan + setoran
            $handover->total_sold_amount = $totalSold;
            $handover->cash_amount       = $cashAmount;
            $handover->transfer_amount   = $transferAmount;


        // simpan / ganti bukti tf kalau ada
        if ($request->hasFile('transfer_proof')) {
            $handover->transfer_proof_path = replace_uploaded_file(
                $handover->transfer_proof_path,
                $request->file('transfer_proof'),
                'handover_transfer_proofs'
            );
        }


            // generate & simpan OTP sore
            $otp = random_int(100000, 999999);
            $handover->evening_otp_hash    = Hash::make($otp);
            $handover->evening_otp_sent_at = now();
            $handover->status              = 'waiting_evening_otp';
            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal menyimpan hasil & mengirim OTP sore: ' . $e->getMessage());
        }

        // Kirim email OTP sore ke SALES
        if ($handover->sales && $handover->sales->email) {
            $lines = [];
            foreach ($handover->items as $i => $item) {
                $p = $item->product;
                $lines[] = sprintf(
                    "%d. %s (%s)\n   Dibawa: %d | Kembali: %d | Terjual: %d",
                    $i + 1,
                    $p?->name ?? ('Produk #' . $item->product_id),
                    $p?->product_code ?? '',
                    $item->qty_start,
                    $item->qty_returned,
                    $item->qty_sold,
                );
            }

            $detailText       = implode("\n\n", $lines);
            $totalSoldText    = number_format($handover->total_sold_amount, 0, ',', '.');
            $cashText         = number_format($handover->cash_amount, 0, ',', '.');
            $transferText     = number_format($handover->transfer_amount, 0, ',', '.');
            $setoranTotal     = $handover->cash_amount + $handover->transfer_amount;
            $setoranTotalText = number_format($setoranTotal, 0, ',', '.');
            $selisih          = max(0, $handover->total_sold_amount - $setoranTotal);
            $selisihText      = number_format($selisih, 0, ',', '.');

            $body = <<<EOT
                Halo {$handover->sales->name},

                Berikut hasil rekonsiliasi SORE (draft closing) untuk handover:

                Kode      : {$handover->code}
                Tanggal   : {$handover->handover_date}
                Warehouse : {$handover->warehouse->warehouse_name}
                Sales     : {$handover->sales->name}

                Rincian barang:
                {$detailText}

                Nilai penjualan (perkiraan): Rp {$totalSoldText}
                Setor tunai                : Rp {$cashText}
                Setor transfer             : Rp {$transferText}
                Total setor                : Rp {$setoranTotalText}
                Selisih (jual - setor)     : Rp {$selisihText}

                OTP Handover Sore: {$otp}

                Jika data ini sudah sesuai dengan catatan kamu, berikan OTP ini ke admin gudang
                supaya handover hari ini bisa di-close.

                Terima kasih.
                EOT;

            try {
                Mail::raw($body, function ($message) use ($handover) {
                    $message->to($handover->sales->email, $handover->sales->name)
                        ->subject('OTP Handover Sore - ' . $handover->code);
                });
            } catch (\Throwable $e) {
                return back()->with(
                    'success',
                    "Rekonsiliasi sore untuk {$handover->code} tersimpan.<br>"
                    . "Namun email OTP sore gagal dikirim: " . e($e->getMessage())
                );
            }
        }

        return back()->with(
            'success',
            "Rekonsiliasi sore untuk {$handover->code} tersimpan dan OTP sore sudah dikirim ke email sales."
        );
    }


    /**
     * Verifikasi OTP sore:
     * - cek otp
     * - update stok (sales -> warehouse & sales -> customer)
     * - status: waiting_evening_otp -> closed
     */
    public function verifyEveningOtp(Request $request)
    {
        $data = $request->validate([
            'handover_id' => ['required', 'exists:sales_handovers,id'],
            'otp_code'    => ['required', 'digits:6'],
        ]);

        $handover = SalesHandover::with(['items.product', 'warehouse', 'sales'])
            ->findOrFail($data['handover_id']);

        if ($handover->status !== 'waiting_evening_otp') {
            return back()->with('error', "Status handover harus 'waiting_evening_otp' untuk verifikasi OTP sore.");
        }

        if (! $handover->evening_otp_hash || ! Hash::check($data['otp_code'], $handover->evening_otp_hash)) {
            return back()->with('error', 'OTP sore tidak valid.');
        }

        $me = auth()->user();

        try {
            DB::beginTransaction();

            foreach ($handover->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }

                $qtyStart = (int) $item->qty_start;
                $qtyRet   = (int) $item->qty_returned;
                $qtySold  = (int) $item->qty_sold;

                // Stok sales: kurangi semua qty_start
                $salesStock = DB::table('stock_levels')
                    ->where('owner_type', 'sales')
                    ->where('owner_id', $handover->sales_id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $salesStock || $salesStock->quantity < $qtyStart) {
                    throw new \RuntimeException("Stok sales kurang untuk produk {$product->name}.");
                }

                DB::table('stock_levels')
                    ->where('id', $salesStock->id)
                    ->update([
                        'quantity'   => $salesStock->quantity - $qtyStart,
                        'updated_at' => now(),
                    ]);

                // Qty kembali: sales -> warehouse
                if ($qtyRet > 0) {
                    $whStock = DB::table('stock_levels')
                        ->where('owner_type', 'warehouse')
                        ->where('owner_id', $handover->warehouse_id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if ($whStock) {
                        DB::table('stock_levels')
                            ->where('id', $whStock->id)
                            ->update([
                                'quantity'   => $whStock->quantity + $qtyRet,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('stock_levels')->insert([
                            'owner_type' => 'warehouse',
                            'owner_id'   => $handover->warehouse_id,
                            'product_id' => $product->id,
                            'quantity'   => $qtyRet,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $movement = [
                        'product_id' => $product->id,
                        'from_type'  => 'sales',
                        'from_id'    => $handover->sales_id,
                        'to_type'    => 'warehouse',
                        'to_id'      => $handover->warehouse_id,
                        'quantity'   => $qtyRet,
                        'note'       => "Handover {$handover->code} (return sore)",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (Schema::hasColumn('stock_movements', 'status')) {
                        $movement['status'] = 'completed';
                    }

                    DB::table('stock_movements')->insert($movement);
                }

                // Qty terjual: sales -> customer
                if ($qtySold > 0) {
                    $movement = [
                        'product_id' => $product->id,
                        'from_type'  => 'sales',
                        'from_id'    => $handover->sales_id,
                        'to_type'    => 'sales',
                        'to_id'      => 0,
                        'quantity'   => $qtySold,
                        'note'       => "Handover {$handover->code} (sold closing)",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (Schema::hasColumn('stock_movements', 'status')) {
                        $movement['status'] = 'completed';
                    }

                    DB::table('stock_movements')->insert($movement);
                }
            }

            $handover->status                 = 'closed';
            $handover->closed_by              = $me->id;
            $handover->evening_otp_verified_at = now();
            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal verifikasi OTP sore: ' . $e->getMessage());
        }

        return back()->with(
            'success',
            "OTP sore valid. Stok sudah diupdate dan handover {$handover->code} ditutup (closed)."
        );
    }

/**
 * DETAIL HANDOVER (JSON)
 * Dipakai oleh:
 * - Daily Report Sales  (route: sales.report.detail)
 * - Sales Reports WH    (route: wh.sales.report.detail)
 */

public function warehouseSalesReport(Request $request)
    {
        $me = auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales     = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug', 'admin')
                       || $roles->contains('slug', 'superadmin');

        $dateFrom = $request->query('date_from', now()->format('Y-m-d'));
        $dateTo   = $request->query('date_to',   now()->format('Y-m-d'));
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $status      = $request->query('status', 'all');
        $warehouseId = $request->query('warehouse_id');
        $salesId     = $request->query('sales_id');
        $search      = trim($request->query('q', ''));

        $statusOptions = [
            'all'                 => 'Semua Status',
            'draft'               => 'Draft',
            'waiting_morning_otp' => 'Menunggu OTP Pagi',
            'on_sales'            => 'On Sales',
            'waiting_evening_otp' => 'Menunggu OTP Sore',
            'closed'              => 'Closed',
            'cancelled'           => 'Cancelled',
        ];

        // Admin warehouse murni -> kunci ke warehouse dia
        if ($isWarehouse && $me->warehouse_id && ! $isAdminLike) {
            $warehouseId = $me->warehouse_id;
        }

        $query = SalesHandover::with(['warehouse', 'sales'])
            ->whereBetween('handover_date', [$dateFrom, $dateTo]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($salesId) {
            $query->where('sales_id', $salesId);
        }

        if ($search !== '') {
            $q = "%{$search}%";
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'like', $q)
                    ->orWhereHas('warehouse', function ($w) use ($q) {
                        $w->where('warehouse_name', 'like', $q)
                          ->orWhere('warehouse_code', 'like', $q);
                    })
                    ->orWhereHas('sales', function ($s) use ($q) {
                        $s->where('name', 'like', $q);
                    });
            });
        }

        $handovers = $query->orderBy('handover_date', 'desc')
            ->orderBy('code')
            ->get();

        $totalDispatched = (int) $handovers->sum('total_dispatched_amount');
        $totalSold       = (int) $handovers->sum('total_sold_amount');
        $totalDiff       = max(0, $totalDispatched - $totalSold);

        if ($request->ajax()) {
            $formatRp = fn (int $n) => 'Rp ' . number_format($n, 0, ',', '.');

            $rows = [];
            foreach ($handovers as $idx => $h) {
                $whName    = optional($h->warehouse)->warehouse_name
                          ?? optional($h->warehouse)->name
                          ?? '-';
                $salesName = optional($h->sales)->name ?? ('Sales #' . $h->sales_id);

                $dispatched = (int) $h->total_dispatched_amount;
                $sold       = (int) $h->total_sold_amount;
                $diff       = max(0, $dispatched - $sold);

                $stLabel    = $statusOptions[$h->status] ?? $h->status;
                $badgeClass = match ($h->status) {
                    'closed'               => 'bg-label-success',
                    'on_sales'             => 'bg-label-info',
                    'waiting_morning_otp',
                    'waiting_evening_otp'  => 'bg-label-warning',
                    'cancelled'            => 'bg-label-danger',
                    default                => 'bg-label-secondary',
                };

                $rows[] = [
                    'id'                  => $h->id,
                    'no'                  => $idx + 1,
                    'date'                => optional($h->handover_date)->format('Y-m-d'),
                    'code'                => $h->code,
                    'warehouse'           => $whName,
                    'sales'               => $salesName,
                    'status'              => $h->status,
                    'status_label'        => $stLabel,
                    'status_badge_class'  => $badgeClass,
                    'amount_dispatched'   => $formatRp($dispatched),
                    'amount_sold'         => $formatRp($sold),
                    'amount_diff'         => $formatRp($diff),
                ];
            }

            return response()->json([
                'success' => true,
                'rows'    => $rows,
                'summary' => [
                    'total_dispatched'            => $totalDispatched,
                    'total_dispatched_formatted'  => $formatRp($totalDispatched),
                    'total_sold'                  => $totalSold,
                    'total_sold_formatted'        => $formatRp($totalSold),
                    'total_diff'                  => $totalDiff,
                    'total_diff_formatted'        => $formatRp($totalDiff),
                    'period_text'                 => "{$dateFrom} s/d {$dateTo}",
                ],
            ]);
        }

        // ===== Dropdown warehouse & sales =====
        $whQuery = Warehouse::query();
        if ($isWarehouse && $me->warehouse_id && ! $isAdminLike) {
            $whQuery->where('id', $me->warehouse_id);
        }

        $warehouses = $whQuery
            ->orderBy(DB::raw('COALESCE(warehouse_name, warehouse_code, id)'))
            ->get(['id', 'warehouse_name', 'warehouse_code']);

        $salesQuery = User::whereHas('roles', fn ($q) => $q->where('slug', 'sales'));

        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
        } elseif ($isWarehouse && $me->warehouse_id && ! $isAdminLike) {
            $salesQuery->where('warehouse_id', $me->warehouse_id);
        }

        $salesList = $salesQuery->orderBy('name')->get(['id', 'name']);

        return view('wh.handover_report', [
            'me'              => $me,
            'handovers'       => $handovers,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'status'          => $status,
            'statusOptions'   => $statusOptions,
            'warehouseId'     => $warehouseId,
            'salesId'         => $salesId,
            'warehouses'      => $warehouses,
            'salesList'       => $salesList,
            'totalDispatched' => $totalDispatched,
            'totalSold'       => $totalSold,
            'totalDiff'       => $totalDiff,
            'search'          => $search,
        ]);
    }



    public function salesReport(Request $request)
    {
        $me = auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales     = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug', 'admin')
                       || $roles->contains('slug', 'superadmin');

        // ===== Filter dasar =====
        $dateFrom = $request->query('date_from', now()->format('Y-m-d'));
        $dateTo   = $request->query('date_to',   now()->format('Y-m-d'));
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $status      = $request->query('status', 'all');
        $warehouseId = $request->query('warehouse_id');
        $salesId     = $request->query('sales_id');
        $search      = trim($request->query('q', ''));

        $statusOptions = [
            'all'                 => 'Semua Status',
            'draft'               => 'Draft',
            'waiting_morning_otp' => 'Menunggu OTP Pagi',
            'on_sales'            => 'On Sales',
            'waiting_evening_otp' => 'Menunggu OTP Sore',
            'closed'              => 'Closed',
            'cancelled'           => 'Cancelled',
        ];

        // ===== Batasan khusus SALES murni =====
        if ($isSales && ! $isAdminLike && ! $isWarehouse) {
            $salesId     = $me->id;
            $warehouseId = $me->warehouse_id ?: null;
        }

        // ===== Query utama =====
        $query = SalesHandover::with(['warehouse', 'sales'])
            ->whereBetween('handover_date', [$dateFrom, $dateTo]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($salesId) {
            $query->where('sales_id', $salesId);
        }

        if ($search !== '') {
            $q = "%{$search}%";
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'like', $q)
                    ->orWhereHas('warehouse', function ($w) use ($q) {
                        $w->where('warehouse_name', 'like', $q)
                          ->orWhere('warehouse_code', 'like', $q);
                    })
                    ->orWhereHas('sales', function ($s) use ($q) {
                        $s->where('name', 'like', $q);
                    });
            });
        }

        $handovers = $query->orderBy('handover_date', 'desc')
            ->orderBy('code')
            ->get();

        $totalDispatched = (int) $handovers->sum('total_dispatched_amount');
        $totalSold       = (int) $handovers->sum('total_sold_amount');
        $totalDiff       = max(0, $totalDispatched - $totalSold);

        // ===== Respon AJAX (dipakai JS untuk reload list) =====
        if ($request->ajax()) {
            $formatRp = fn (int $n) => 'Rp ' . number_format($n, 0, ',', '.');

            $rows = [];
            foreach ($handovers as $idx => $h) {
                $whName    = optional($h->warehouse)->warehouse_name
                          ?? optional($h->warehouse)->name
                          ?? '-';
                $salesName = optional($h->sales)->name ?? ('Sales #' . $h->sales_id);

                $dispatched = (int) $h->total_dispatched_amount;
                $sold       = (int) $h->total_sold_amount;
                $diff       = max(0, $dispatched - $sold);

                $stLabel    = $statusOptions[$h->status] ?? $h->status;
                $badgeClass = match ($h->status) {
                    'closed'               => 'bg-label-success',
                    'on_sales'             => 'bg-label-info',
                    'waiting_morning_otp',
                    'waiting_evening_otp'  => 'bg-label-warning',
                    'cancelled'            => 'bg-label-danger',
                    default                => 'bg-label-secondary',
                };

                $rows[] = [
                    'id'                  => $h->id,
                    'no'                  => $idx + 1,
                    'date'                => optional($h->handover_date)->format('Y-m-d'),
                    'code'                => $h->code,
                    'warehouse'           => $whName,
                    'sales'               => $salesName,
                    'status'              => $h->status,
                    'status_label'        => $stLabel,
                    'status_badge_class'  => $badgeClass,
                    'amount_dispatched'   => $formatRp($dispatched),
                    'amount_sold'         => $formatRp($sold),
                    'amount_diff'         => $formatRp($diff),
                ];
            }

            return response()->json([
                'success' => true,
                'rows'    => $rows,
                'summary' => [
                    'total_dispatched'            => $totalDispatched,
                    'total_dispatched_formatted'  => $formatRp($totalDispatched),
                    'total_sold'                  => $totalSold,
                    'total_sold_formatted'        => $formatRp($totalSold),
                    'total_diff'                  => $totalDiff,
                    'total_diff_formatted'        => $formatRp($totalDiff),
                    'period_text'                 => "{$dateFrom} s/d {$dateTo}",
                ],
            ]);
        }

        // ===== Data dropdown WAREHOUSE & SALES =====
        $whQuery = Warehouse::query();

        // Sales murni -> kalau punya warehouse_id, kunci ke situ
        if ($isSales && ! $isAdminLike && ! $isWarehouse && $me->warehouse_id) {
            $whQuery->where('id', $me->warehouse_id);
        }

        $warehouses = $whQuery
            ->orderBy(DB::raw('COALESCE(warehouse_name, warehouse_code, id)'))
            ->get(['id', 'warehouse_name', 'warehouse_code']);

        $salesQuery = User::whereHas('roles', fn ($q) => $q->where('slug', 'sales'));

        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $salesList = $salesQuery->orderBy('name')->get(['id', 'name']);

        return view('wh.handover_report', [
            'me'              => $me,
            'handovers'       => $handovers,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'status'          => $status,
            'statusOptions'   => $statusOptions,
            'warehouseId'     => $warehouseId,
            'salesId'         => $salesId,
            'warehouses'      => $warehouses,
            'salesList'       => $salesList,
            'totalDispatched' => $totalDispatched,
            'totalSold'       => $totalSold,
            'totalDiff'       => $totalDiff,
            'search'          => $search,
        ]);
    }



        protected function buildHandoverReport(Request $request)
        {
            $me    = auth()->user();
            $roles = $me?->roles ?? collect();

            $isWarehouse = $roles->contains('slug', 'warehouse');
            $isSales     = $roles->contains('slug', 'sales');
            $isAdminLike = $roles->contains('slug', 'admin')
                        || $roles->contains('slug', 'superadmin');

            $dateFrom = $request->query('date_from', now()->format('Y-m-d'));
            $dateTo   = $request->query('date_to',   now()->format('Y-m-d'));
            if ($dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            $status      = $request->query('status', '');
            if ($status === 'all') {
                $status = '';
            }

            $warehouseId = $request->query('warehouse_id');
            $salesId     = $request->query('sales_id');
            $search      = $request->query('q');

            $statusOptions = [
                ''                     => 'Semua Status',
                'draft'                => 'Draft',
                'waiting_morning_otp'  => 'Menunggu OTP Pagi',
                'on_sales'             => 'On Sales',
                'waiting_evening_otp'  => 'Menunggu OTP Sore',
                'closed'               => 'Closed',
                'cancelled'            => 'Cancelled',
            ];

            // Lock ke warehouse user kalau dia role warehouse
            if ($isWarehouse && $me->warehouse_id) {
                $warehouseId = $me->warehouse_id;
            }

            // Lock ke sales sendiri kalau user SALES murni (bukan admin, bukan wh)
            if ($isSales && ! $isAdminLike && ! $isWarehouse) {
                $salesId = $me->id;
            }

            // ===== Query handover =====
            $query = SalesHandover::with(['warehouse', 'sales'])
                ->whereBetween('handover_date', [$dateFrom, $dateTo]);

            if ($status !== '') {
                $query->where('status', $status);
            }

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            if ($salesId) {
                $query->where('sales_id', $salesId);
            }

            if ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('code', 'like', $like)
                    ->orWhereHas('warehouse', function ($w) use ($like) {
                        $w->where('warehouse_name', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    })
                    ->orWhereHas('sales', function ($s) use ($like) {
                        $s->where('name', 'like', $like);
                    });
                });
            }

            $handovers = $query->orderBy('handover_date', 'desc')
                ->orderBy('code')
                ->get();

            $totalDispatched = (int) $handovers->sum('total_dispatched_amount');
            $totalSold       = (int) $handovers->sum('total_sold_amount');
            $totalDiff       = max(0, $totalDispatched - $totalSold);

            // ===== List Warehouse buat filter (superadmin / admin bisa lihat semua) =====
            $whQuery = Warehouse::query();
            if ($isWarehouse && $me->warehouse_id) {
                $whQuery->where('id', $me->warehouse_id);
            }

            $warehouses = $whQuery
                ->orderBy(DB::raw('COALESCE(warehouse_name, warehouse_code, id)'))
                ->get(['id', 'warehouse_name', 'warehouse_code']);

            // ===== List Sales buat filter =====
            $salesQuery = User::whereHas('roles', fn ($q) => $q->where('slug', 'sales'));

            if ($warehouseId) {
                $salesQuery->where('warehouse_id', $warehouseId);
            } elseif ($isWarehouse && $me->warehouse_id) {
                $salesQuery->where('warehouse_id', $me->warehouse_id);
            }

            $salesList = $salesQuery->orderBy('name')
                ->get(['id', 'name']);

            // ===== AJAX response (buat reload tabel + summary) =====
            if ($request->ajax()) {
                $rowsHtml = view('wh.partials.handover_rows', [
                    'handovers'    => $handovers,
                    'statusLabels' => $statusOptions,
                ])->render();

                return response()->json([
                    'success'   => true,
                    'rows_html' => $rowsHtml,
                    'summary'   => [
                        'total_dispatched'             => $totalDispatched,
                        'total_sold'                   => $totalSold,
                        'total_diff'                   => $totalDiff,
                        'total_dispatched_formatted'   => 'Rp ' . number_format($totalDispatched, 0, ',', '.'),
                        'total_sold_formatted'         => 'Rp ' . number_format($totalSold, 0, ',', '.'),
                        'total_diff_formatted'         => 'Rp ' . number_format($totalDiff, 0, ',', '.'),
                        'period_text'                  => "{$dateFrom} s/d {$dateTo}",
                    ],
                ]);
            }

            return view('wh.handover_report', [
                'me'              => $me,
                'handovers'       => $handovers,
                'dateFrom'        => $dateFrom,
                'dateTo'          => $dateTo,
                'status'          => $status,
                'statusOptions'   => $statusOptions,
                'warehouseId'     => $warehouseId,
                'salesId'         => $salesId,
                'warehouses'      => $warehouses,
                'salesList'       => $salesList,
                'totalDispatched' => $totalDispatched,
                'totalSold'       => $totalSold,
                'totalDiff'       => $totalDiff,
                'search'          => $search,
            ]);
        }

        /**
         * Detail handover (dipakai modal di Sales Reports Admin WH).
         * Kalau lu sudah punya salesReportDetail(), tinggal delegate aja.
         */
        protected function buildHandoverDetailResponse(SalesHandover $handover)
        {
            $handover->load(['warehouse', 'sales', 'items.product']);

            $items = $handover->items->map(function ($item) {
                $p = $item->product;

                return [
                    'product_id'   => $item->product_id,
                    'product_name' => $p?->name ?? ('Produk #' . $item->product_id),
                    'product_code' => $p?->product_code ?? '',
                    'qty_start'    => (int) $item->qty_start,
                    'qty_returned' => (int) $item->qty_returned,
                    'qty_sold'     => (int) $item->qty_sold,
                    'unit_price'   => (int) $item->unit_price,
                    'line_start'   => (int) $item->line_total_start,
                    'line_sold'    => (int) $item->line_total_sold,
                ];
            });

            return response()->json([
                'success'  => true,
                'handover' => [
                    'id'                    => $handover->id,
                    'code'                  => $handover->code,
                    'handover_date'         => optional($handover->handover_date)->format('Y-m-d'),
                    'status'                => $handover->status,
                    'warehouse_name'        => $handover->warehouse->warehouse_name
                                                ?? $handover->warehouse->name
                                                ?? null,
                    'sales_name'            => $handover->sales->name ?? null,
                    'total_dispatched'      => (int) $handover->total_dispatched_amount,
                    'total_sold'            => (int) $handover->total_sold_amount,
                    'cash_amount'           => (int) $handover->cash_amount,
                    'transfer_amount'       => (int) $handover->transfer_amount,
                    'setor_total'           => (int) $handover->cash_amount + (int) $handover->transfer_amount,
                    'selisih_jual_vs_setor' => (int) $handover->total_sold_amount
                                                - ((int) $handover->cash_amount + (int) $handover->transfer_amount),
                    'selisih_stock_value'   => (int) $handover->total_dispatched_amount
                                                - (int) $handover->total_sold_amount,
                    'transfer_proof_url'    => $handover->transfer_proof_path
                                                ? asset('storage/' . $handover->transfer_proof_path)
                                                : null,
                    'morning_otp_sent_at'    => optional($handover->morning_otp_sent_at)->format('Y-m-d H:i'),
                    'morning_otp_verified_at'=> optional($handover->morning_otp_verified_at)->format('Y-m-d H:i'),
                    'evening_otp_sent_at'    => optional($handover->evening_otp_sent_at)->format('Y-m-d H:i'),
                    'evening_otp_verified_at'=> optional($handover->evening_otp_verified_at)->format('Y-m-d H:i'),
                ],
                'items'   => $items,
            ]);
        }

  public function salesReportDetail(SalesHandover $handover)
    {
        $handover->load(['warehouse', 'sales', 'items.product']);

        $items = $handover->items->map(function (SalesHandoverItem $item) {
            $p = $item->product;

            return [
                'product_id'   => $item->product_id,
                'product_name' => $p?->name ?? ('Produk #' . $item->product_id),
                'product_code' => $p?->product_code ?? '',
                'qty_start'    => (int) $item->qty_start,
                'qty_returned' => (int) $item->qty_returned,
                'qty_sold'     => (int) $item->qty_sold,
                'unit_price'   => (int) $item->unit_price,
                'line_start'   => (int) $item->line_total_start,
                'line_sold'    => (int) $item->line_total_sold,
            ];
        });

        return response()->json([
            'success'  => true,
            'handover' => [
                'id'                    => $handover->id,
                'code'                  => $handover->code,
                'handover_date'         => optional($handover->handover_date)->format('Y-m-d'),
                'status'                => $handover->status,
                'warehouse_name'        => $handover->warehouse->warehouse_name
                                            ?? $handover->warehouse->name
                                            ?? null,
                'sales_name'            => $handover->sales->name ?? null,
                'total_dispatched'      => (int) $handover->total_dispatched_amount,
                'total_sold'            => (int) $handover->total_sold_amount,
                'cash_amount'           => (int) $handover->cash_amount,
                'transfer_amount'       => (int) $handover->transfer_amount,
                'setor_total'           => (int) $handover->cash_amount + (int) $handover->transfer_amount,
                'selisih_jual_vs_setor' => (int) $handover->total_sold_amount
                                            - ((int) $handover->cash_amount + (int) $handover->transfer_amount),
                'selisih_stock_value'   => (int) $handover->total_dispatched_amount
                                            - (int) $handover->total_sold_amount,
                'transfer_proof_url'    => $handover->transfer_proof_path
                                            ? asset('storage/' . $handover->transfer_proof_path)
                                            : null,
                'morning_otp_sent_at'    => optional($handover->morning_otp_sent_at)->format('Y-m-d H:i'),
                'morning_otp_verified_at'=> optional($handover->morning_otp_verified_at)->format('Y-m-d H:i'),
                'evening_otp_sent_at'    => optional($handover->evening_otp_sent_at)->format('Y-m-d H:i'),
                'evening_otp_verified_at'=> optional($handover->evening_otp_verified_at)->format('Y-m-d H:i'),
            ],
            'items'   => $items,
        ]);
    }

        public function warehouseSalesReportDetail(SalesHandover $handover)
        {
            return $this->buildHandoverDetailResponse($handover);
        }

        // taruh di dalam class SalesHandoverController, sebelum penutup "}"
protected function formatRupiah(int $value): string
{
    return 'Rp ' . number_format($value, 0, ',', '.');
}

/**
 * Dipakai oleh salesReport() & warehouseSalesReport() untuk response AJAX
 */
protected function buildReportJson($handovers, array $statusLabels, string $dateFrom, string $dateTo)
{
    $totalDispatched = (int) $handovers->sum('total_dispatched_amount');
    $totalSold       = (int) $handovers->sum('total_sold_amount');
    $totalDiff       = max(0, $totalDispatched - $totalSold);

    $rows = $handovers->values()->map(function (SalesHandover $h, int $idx) use ($statusLabels) {
        $dispatched = (int) $h->total_dispatched_amount;
        $sold       = (int) $h->total_sold_amount;
        $diff       = max(0, $dispatched - $sold);

        $stLabel = $statusLabels[$h->status] ?? $h->status;
        $badgeClass = match ($h->status) {
            'closed'               => 'bg-label-success',
            'on_sales'             => 'bg-label-info',
            'waiting_morning_otp',
            'waiting_evening_otp'  => 'bg-label-warning',
            'cancelled'            => 'bg-label-danger',
            default                => 'bg-label-secondary',
        };

        $whName = optional($h->warehouse)->warehouse_name
               ?? optional($h->warehouse)->name
               ?? '-';

        $salesName = optional($h->sales)->name ?? ('Sales #' . $h->sales_id);

        return [
            'id'                 => $h->id,
            'no'                 => $idx + 1,
            'date'               => optional($h->handover_date)->format('Y-m-d'),
            'code'               => $h->code,
            'warehouse'          => $whName,
            'sales'              => $salesName,
            'status'             => $h->status,
            'status_label'       => $stLabel,
            'status_badge_class' => $badgeClass,
            'amount_dispatched'  => $this->formatRupiah($dispatched),
            'amount_sold'        => $this->formatRupiah($sold),
            'amount_diff'        => $this->formatRupiah($diff),
        ];
    });

    return response()->json([
        'success' => true,
        'rows'    => $rows,
        'summary' => [
            'total_dispatched'           => $totalDispatched,
            'total_sold'                 => $totalSold,
            'total_diff'                 => $totalDiff,
            'total_dispatched_formatted' => $this->formatRupiah($totalDispatched),
            'total_sold_formatted'       => $this->formatRupiah($totalSold),
            'total_diff_formatted'       => $this->formatRupiah($totalDiff),
            'period_text'                => "{$dateFrom} s/d {$dateTo}",
        ],
    ]);
}


}
