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
use App\Mail\SalesHandoverOtpMail;

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

            $warehouseName = $warehouse->warehouse_name
                ?? $warehouse->name
                ?? ('Warehouse #' . $warehouse->id);

            // ================================
            // VALIDASI: SALES MASIH PUNYA HDO AKTIF?
            // ================================
            $openHandover = SalesHandover::where('sales_id', $sales->id)
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->orderByDesc('handover_date')
                ->orderByDesc('id')
                ->first();

            if ($openHandover) {
                $statusLabel = strtoupper(str_replace('_', ' ', $openHandover->status));

                return back()
                    ->withInput()
                    ->with(
                        'error',
                        "Sales {$sales->name} masih punya handover aktif ({$openHandover->code}) ".
                        "dengan status {$statusLabel}. Silakan closing dulu handover tersebut ".
                        "sebelum membuat handover baru."
                    );
            }
            // ================================

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

                    // Harga rupiah utuh (integer)
                    $unitPrice = (int) ($product->selling_price ?? 0);

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
                $otp = (string) random_int(100000, 999999);

                // simpan "PLAIN|HASH" di kolom lama
                $handover->morning_otp_hash    = $this->packOtp($otp);
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
        Warehouse : {$warehouseName}
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

        [$storedPlain, $storedHash] = $this->splitOtpField($handover->morning_otp_hash);

        if (! $storedHash || ! Hash::check($data['otp_code'], $storedHash)) {
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
                $unitPrice = $item->unit_price ?: (int) ($product->selling_price ?? 0);
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
     * - pilih handover status on_sales => input qty_returned (SALES)
     * - verifikasi OTP sore (closing)
     */
    public function eveningForm(Request $request)
    {
        $me    = auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isAdminLike = $roles->contains('slug', 'admin')
                    || $roles->contains('slug', 'superadmin');

        // Menu ini cuma buat admin/warehouse
        if (! $isWarehouse && ! $isAdminLike) {
            abort(403, 'Menu ini hanya untuk admin warehouse.');
        }

        // ===== LIST HANDOVER UNTUK DROPDOWN (APPROVAL) =====
        $listQuery = SalesHandover::with(['warehouse', 'sales'])
            ->whereIn('status', ['on_sales', 'waiting_evening_otp'])
            ->where('evening_filled_by_sales', true); // sudah diisi sore oleh sales

        // Warehouse murni dikunci ke warehouse_id nya
        if ($isWarehouse && $me->warehouse_id && ! $isAdminLike) {
            $listQuery->where('warehouse_id', $me->warehouse_id);
        }

        $handoverList = $listQuery
            ->orderBy('handover_date', 'desc')
            ->orderBy('code')
            ->get();

        // ===== DETAIL HANDOVER YANG DIPILIH =====
        $selectedId = $request->integer('handover_id') ?: null;
        $handover   = null;

        if ($selectedId) {
            $detailQuery = SalesHandover::with(['warehouse', 'sales', 'items.product'])
                ->where('id', $selectedId);

            if ($isWarehouse && $me->warehouse_id && ! $isAdminLike) {
                $detailQuery->where('warehouse_id', $me->warehouse_id);
            }

            $handover = $detailQuery->firstOrFail();
        }

        return view('wh.handover_evening', [
            'me'           => $me,
            'handoverList' => $handoverList,
            'handover'     => $handover,
        ]);
    }


    /**
     * API JSON – load detail item untuk sore (SALES).
     * Hanya boleh akses handover milik sales yang login.
     */
    public function eveningItems(SalesHandover $handover)
    {
        $me = auth()->user();

        if ($handover->sales_id !== $me->id) {
            abort(403, 'Tidak boleh mengakses handover milik sales lain.');
        }

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
                'unit_price'   => (int) $item->unit_price,
            ];
        });

        return response()->json([
            'success'  => true,
            'handover' => [
                'id'            => $handover->id,
                'code'          => $handover->code,
                'handover_date' => optional($handover->handover_date)->format('Y-m-d'),
                'sales_name'    => $handover->sales->name ?? null,
                'warehouse'     => optional($handover->warehouse)->warehouse_name
                                    ?? optional($handover->warehouse)->name
                                    ?? null,
            ],
            'items' => $items,
        ]);
    }

    /**
     * SALES menyimpan hasil penjualan:
     * - Mengisi qty_returned, qty_sold, total_sold_amount
     * - Mengisi cash_amount, transfer_amount, bukti TF
     * - Boleh sekali saja, ditandai dengan evening_filled_by_sales = true
     *
     * Tidak generate OTP sore di sini.
     */
    public function eveningSave(Request $request, SalesHandover $handover)
    {
        $me = auth()->user();

        if ($handover->sales_id !== $me->id) {
            return back()->with('error', 'Tidak boleh mengubah handover milik sales lain.');
        }

        if ($handover->status !== 'on_sales') {
            return back()->with('error', 'Handover harus berstatus ON_SALES untuk diisi penjualannya.');
        }

        // Kalau sudah pernah diisi oleh sales, lock
        if ($handover->evening_filled_by_sales) {
            return back()->with('error', 'Data penjualan sudah pernah dikirim. Hubungi admin gudang jika ingin revisi.');
        }

        $data = $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer'],
            'items.*.qty_returned' => ['required', 'integer', 'min:0'],
            'cash_amount'          => ['required', 'integer', 'min:0'],
            'transfer_amount'      => ['required', 'integer', 'min:0'],
            'transfer_proof'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $cashAmount     = (int) $data['cash_amount'];
        $transferAmount = (int) $data['transfer_amount'];

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

        $handover->load(['items.product']);

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

                $unitPrice = $item->unit_price ?: (int) ($product->selling_price ?? 0);
                $lineSold  = $qtySold * $unitPrice;

                $item->qty_returned    = $qtyRet;
                $item->qty_sold        = $qtySold;
                $item->unit_price      = $unitPrice;
                $item->line_total_sold = $lineSold;
                $item->save();

                $totalSold += $lineSold;
            }

            $handover->total_sold_amount       = $totalSold;
            $handover->cash_amount             = $cashAmount;
            $handover->transfer_amount         = $transferAmount;
            $handover->evening_filled_by_sales = true;
            $handover->evening_filled_at       = now();

            // simpan / ganti bukti tf kalau ada
            if ($request->hasFile('transfer_proof')) {
                // asumsi helper replace_uploaded_file() sudah ada
                $handover->transfer_proof_path = replace_uploaded_file(
                    $handover->transfer_proof_path,
                    $request->file('transfer_proof'),
                    'handover_transfer_proofs'
                );
            }

            $handover->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal menyimpan data penjualan: ' . $e->getMessage())
                         ->withInput();
        }

        return back()->with(
            'success',
            'Data penjualan berhasil disimpan. Menunggu pengecekan & approval admin gudang.'
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
        
        [$storedPlain, $storedHash] = $this->splitOtpField($handover->evening_otp_hash);

        if (! $storedHash || ! Hash::check($data['otp_code'], $storedHash)) {
            return back()->with('error', 'OTP sore tidak valid.');
        }

        // >>> NEW: pastikan semua payment item sudah APPROVED <<<
        $hasUnapproved = $handover->items()
            ->where('qty_sold', '>', 0)                 // cuma yang benar-benar terjual
            ->where('payment_status', '!=', 'approved') // pending / rejected
            ->exists();

        if ($hasUnapproved) {
            return back()->with('error', 'Masih ada item terjual yang payment-nya belum APPROVED.');
        }
        // <<< END NEW >>>

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

            $handover->status                  = 'closed';
            $handover->closed_by               = $me->id;
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
     * DAILY REPORT (ADMIN/WAREHOUSE VIEW)
     */
    public function warehouseSalesReport(Request $request)
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

        if ($request->ajax()) {
            return $this->buildReportJson($handovers, $statusOptions, $dateFrom, $dateTo);
        }

        $totalDispatched = (int) $handovers->sum('total_dispatched_amount');
        $totalSold       = (int) $handovers->sum('total_sold_amount');
        $totalDiff       = max(0, $totalDispatched - $totalSold);

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

    /**
     * DAILY REPORT versi halaman SALES (tapi view-nya sama)
     */
    public function salesReport(Request $request)
    {
        $me    = auth()->user();
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

        if ($request->ajax()) {
            return $this->buildReportJson($handovers, $statusOptions, $dateFrom, $dateTo);
        }

        $totalDispatched = (int) $handovers->sum('total_dispatched_amount');
        $totalSold       = (int) $handovers->sum('total_sold_amount');
        $totalDiff       = max(0, $totalDispatched - $totalSold);

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

    /**
     * DETAIL HANDOVER – dipakai modal (sales & warehouse report)
     */
    public function salesReportDetail(SalesHandover $handover)
    {
        return $this->buildHandoverDetailResponse($handover);
    }

    public function warehouseSalesReportDetail(SalesHandover $handover)
    {
        return $this->buildHandoverDetailResponse($handover);
    }

    protected function buildHandoverDetailResponse(SalesHandover $handover)
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

                // >>> NEW: info payment per item <<<
                'payment_qty'          => (int) $item->payment_qty,
                'payment_method'       => $item->payment_method,
                'payment_amount'       => (int) $item->payment_amount,
                'payment_status'       => $item->payment_status,
                'payment_reject_reason'=> $item->payment_reject_reason,
                'payment_proof_url'    => $item->payment_transfer_proof_path
                                            ? asset('storage/'.$item->payment_transfer_proof_path)
                                            : null,
            ];
        });

        $warehouseName = optional($handover->warehouse)->warehouse_name
                      ?? optional($handover->warehouse)->name
                      ?? null;

        return response()->json([
            'success'  => true,
            'handover' => [
                'id'                    => $handover->id,
                'code'                  => $handover->code,
                'handover_date'         => optional($handover->handover_date)->format('Y-m-d'),
                'status'                => $handover->status,
                'warehouse_name'        => $warehouseName,
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
                'morning_otp_sent_at'     => optional($handover->morning_otp_sent_at)->format('Y-m-d H:i'),
                'morning_otp_verified_at' => optional($handover->morning_otp_verified_at)->format('Y-m-d H:i'),
                'evening_otp_sent_at'     => optional($handover->evening_otp_sent_at)->format('Y-m-d H:i'),
                'evening_otp_verified_at' => optional($handover->evening_otp_verified_at)->format('Y-m-d H:i'),
            ],
            'items'   => $items,
        ]);
    }


    // ================== HELPER ==================

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

     protected function packOtp(string $otp): string
    {
        return $otp . '|' . Hash::make($otp);
    }

    /**
     * Split kolom OTP jadi [plain, hash]
     * Kompatibel sama data lama (yang cuma hash doang).
     */
    protected function splitOtpField(?string $stored): array
    {
        if (! $stored) {
            return [null, null];
        }

        if (str_contains($stored, '|')) {
            [$plain, $hash] = explode('|', $stored, 2);
            return [$plain, $hash];
        }

        // mode lama: cuma hash murni
        return [null, $stored];
    }

    public function salesOtpIndex(Request $request)
    {
        $me = auth()->user();

        // default filter tanggal: hari ini
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo   = $request->input('date_to',   now()->format('Y-m-d'));

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $status = $request->input('status', 'all');

        $statusOptions = [
            'all'                 => 'Semua Status',
            'draft'               => 'Draft',
            'waiting_morning_otp' => 'Menunggu OTP Pagi',
            'on_sales'            => 'On Sales',
            'waiting_evening_otp' => 'Menunggu OTP Sore',
            'closed'              => 'Closed',
            'cancelled'           => 'Cancelled',
        ];

        $query = SalesHandover::with(['warehouse'])
            ->where('sales_id', $me->id)
            ->whereBetween('handover_date', [$dateFrom, $dateTo])
            ->orderBy('handover_date', 'desc')
            ->orderBy('code');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // ==== RESPONSE AJAX (JSON) ====
        if ($request->ajax()) {
            $handovers = $query->get();

        $rows = $handovers->values()->map(function (SalesHandover $h, int $idx) {
            $badgeClass = match ($h->status) {
                'closed'               => 'bg-label-success',
                'on_sales'             => 'bg-label-info',
                'waiting_morning_otp',
                'waiting_evening_otp'  => 'bg-label-warning',
                'cancelled'            => 'bg-label-danger',
                default                => 'bg-label-secondary',
            };

            $warehouseName = optional($h->warehouse)->warehouse_name
                        ?? optional($h->warehouse)->name
                        ?? '-';

            [$morningPlain] = $this->splitOtpField($h->morning_otp_hash);
            [$eveningPlain] = $this->splitOtpField($h->evening_otp_hash);

            return [
                'no'                  => $idx + 1,
                'date'                => optional($h->handover_date)->format('Y-m-d'),
                'code'                => $h->code,
                'warehouse'           => $warehouseName,
                'status'              => $h->status,
                'status_badge_class'  => $badgeClass,
                'morning_otp_plain'   => $morningPlain,
                'morning_otp_sent_at' => optional($h->morning_otp_sent_at)->format('H:i'),
                'evening_otp_plain'   => $eveningPlain,
                'evening_otp_sent_at' => optional($h->evening_otp_sent_at)->format('H:i'),
            ];
        });

            return response()->json([
                'success' => true,
                'rows'    => $rows,
            ]);
        }

        // ==== FIRST LOAD (VIEW BIASA) ====
        $handovers = $query->get();

        return view('sales.handover_otp_Sidebar', [
            'me'            => $me,
            'handovers'     => $handovers,
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'status'        => $status,
            'statusOptions' => $statusOptions,
        ]);
    }

        /**
     * FORM APPROVAL PAYMENT PER ITEM (WAREHOUSE)
     */
    public function paymentApprovalForm(Request $request, SalesHandover $handover)
    {
        $me = $request->user();

        $handoverList = SalesHandover::with(['sales', 'warehouse'])
            ->whereIn('status', ['on_sales', 'waiting_evening_otp', 'closed'])
            ->orderByDesc('handover_date')
            ->get();

        $statusLabelMap = [
            'draft'               => 'Draft',
            'waiting_morning_otp' => 'Menunggu OTP Pagi',
            'on_sales'            => 'On Sales',
            'waiting_evening_otp' => 'Menunggu OTP Sore',
            'closed'              => 'Closed',
            'cancelled'           => 'Cancelled',
        ];

        $badgeClassMap = [
            'closed'              => 'bg-label-success',
            'on_sales'            => 'bg-label-info',
            'waiting_morning_otp' => 'bg-label-warning',
            'waiting_evening_otp' => 'bg-label-warning',
            'cancelled'           => 'bg-label-danger',
            'default'             => 'bg-label-secondary',
        ];

        $statusKey   = $handover->status;
        $statusLabel = $statusLabelMap[$statusKey] ?? $statusKey;
        $badgeClass  = $badgeClassMap[$statusKey] ?? $badgeClassMap['default'];

        $canEdit      = $handover->status !== 'closed';
        $canVerifyOtp = $handover->status === 'waiting_evening_otp';

        $itemsSold   = $handover->items->where('qty_sold', '>', 0);
        $allApproved = $itemsSold->count() > 0
            && $itemsSold->every(fn($it) => $it->payment_status === 'approved');
        $hasEveningOtp  = ! empty($handover->evening_otp_hash);
        $canGenerateOtp = $canEdit && $allApproved && ! $hasEveningOtp;

        return view('wh.handover_evening', compact(
            'me', 'handover', 'handoverList',
            'statusLabel', 'badgeClass', 'canEdit', 'canVerifyOtp', 'canGenerateOtp'
        ));
    }
/**
 * SIMPAN APPROVAL PAYMENT PER ITEM
 * - approved  => sales ga bisa ubah lagi
 * - rejected  => reason dikirim, sales boleh isi ulang payment (status jadi pending saat sales save lagi)
 */
    public function paymentApprovalSave(Request $request, SalesHandover $handover)
    {
        $data = $request->validate([
            'decisions'              => ['required', 'array'],
            'decisions.*.status'     => ['required', 'in:approved,rejected'],
            'decisions.*.reason'     => ['nullable', 'string', 'max:500'],
        ]);

        // butuh items + product + sales buat hitung & email
        $handover->load(['items.product', 'sales']);

        if ($handover->status === 'closed') {
            return back()->with('error', 'Handover sudah CLOSED. Approval tidak bisa diubah lagi.');
        }

        $autoOtpGenerated = false;
        $generatedOtpCode = null;

        try {
            DB::beginTransaction();

            // ===== 1. SIMPAN KEPUTUSAN PER ITEM =====
            foreach ($handover->items as $item) {
                $key = (string) $item->id;
                if (! isset($data['decisions'][$key])) {
                    continue;
                }

                $row    = $data['decisions'][$key];
                $status = $row['status'];

                if ($status === 'approved') {
                    $item->payment_status        = 'approved';
                    $item->payment_reject_reason = null;
                } else {
                    $item->payment_status        = 'rejected';
                    $item->payment_reject_reason = $row['reason'] ?? null;
                }

                $item->save();
            }

            // ===== 2. SINKRON QTY JUAL / KEMBALI & NILAI TERJUAL DARI PAYMENT_QTY =====
            $totalSoldAmount = 0;

            foreach ($handover->items as $item) {
                $qtyStart = (int) $item->qty_start;
                $payQty   = max(0, (int) $item->payment_qty);   // qty bayar dari sales

                if ($payQty > $qtyStart) {
                    $payQty = $qtyStart;
                }

                $qtySold     = $payQty;
                $qtyReturned = max(0, $qtyStart - $qtySold);

                $unitPrice = $item->unit_price ?: (int) ($item->product->selling_price ?? 0);
                $lineSold  = $qtySold * $unitPrice;

                $item->qty_sold        = $qtySold;
                $item->qty_returned    = $qtyReturned;
                $item->unit_price      = $unitPrice;
                $item->line_total_sold = $lineSold;
                $item->save();

                $totalSoldAmount += $lineSold;
            }

            // update total penjualan di header
            $handover->total_sold_amount = $totalSoldAmount;
            $handover->save();

            // ===== 3. CEK LAYAK AUTO-GENERATE OTP SORE =====
            $itemsSold = $handover->items
                ->filter(fn ($it) => (int) $it->qty_sold > 0);

            $allApproved = $itemsSold->count() > 0
                && $itemsSold->every(fn ($it) => $it->payment_status === 'approved');

            $hasEveningOtp = ! empty($handover->evening_otp_hash);

            if ($allApproved && ! $hasEveningOtp) {
                $generatedOtpCode = (string) random_int(100000, 999999);

                // simpan "PLAIN|HASH" di kolom evening_otp_hash
                $handover->evening_otp_hash    = $this->packOtp($generatedOtpCode);
                $handover->evening_otp_sent_at = now();
                $handover->status              = 'waiting_evening_otp';
                $handover->save();

                $autoOtpGenerated = true;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal menyimpan approval payment: '.$e->getMessage());
        }

        // ===== 4. KIRIM EMAIL OTP KALAU BARU DIGENERATE =====
        if ($autoOtpGenerated && $handover->sales && $handover->sales->email) {

            $itemsForMail = $handover->items->map(function ($it) {
                return [
                    'name'  => $it->product->name ?? ('Produk #'.$it->product_id),
                    'code'  => $it->product->product_code ?? '',
                    'qty'   => (int) $it->qty_sold,
                    'price' => (int) $it->unit_price,
                    'total' => (int) $it->line_total_sold,
                ];
            })->toArray();

            $grandTotal = (int) $handover->cash_amount + (int) $handover->transfer_amount;
            if ($grandTotal <= 0) {
                $grandTotal = (int) $handover->items->sum('payment_amount');
            }

            try {
                Mail::to($handover->sales->email)
                    ->send(new SalesHandoverOtpMail($handover, $generatedOtpCode, $itemsForMail, $grandTotal));
            } catch (\Throwable $e) {
                return redirect()
                    ->route('warehouse.handovers.payments.form', ['handover' => $handover->id])
                    ->with(
                        'success',
                        'Approval payment tersimpan & OTP sore otomatis dibuat, '
                        .'tetapi email gagal dikirim: '.e($e->getMessage())
                    );
            }
        }

        $msg = 'Approval payment per item berhasil disimpan.';
        if ($autoOtpGenerated) {
            $msg .= '<br>Semua item terjual sudah <b>APPROVED</b>, OTP sore otomatis dibuat '
                . 'dan status handover berubah menjadi <b>WAITING_EVENING_OTP</b>.';
        }

        return redirect()
            ->route('warehouse.handovers.payments.form', ['handover' => $handover->id])
            ->with('success', $msg);
    }
        
    public function generateEveningOtp(SalesHandover $handover)
    {
        if ($handover->status === 'closed') {
            return back()->with('error', 'Handover ini sudah CLOSED.');
        }

        $handover->loadMissing(['items.product', 'sales']);

        $itemsSold = $handover->items->where('qty_sold', '>', 0);
        if ($itemsSold->isEmpty()) {
            return back()->with('error', 'Belum ada item terjual, tidak bisa generate OTP sore.');
        }

        $allApproved = $itemsSold->every(fn($item) => $item->payment_status === 'approved');
        if (! $allApproved) {
            return back()->with('error', 'Masih ada payment yang belum APPROVED.');
        }

        $otpCode = (string) random_int(100000, 999999);

        // simpan "PLAIN|HASH" di kolom evening_otp_hash
        $handover->evening_otp_hash    = $this->packOtp($otpCode);
        $handover->evening_otp_sent_at = now();
        $handover->status              = 'waiting_evening_otp';
        $handover->save();

        if ($handover->sales && $handover->sales->email) {
            $itemsForMail = $handover->items->map(function ($it) {
                return [
                    'name'  => $it->product->name ?? ('Produk #'.$it->product_id),
                    'code'  => $it->product->product_code ?? '',
                    'qty'   => (int) $it->qty_sold,
                    'price' => (int) $it->unit_price,
                    'total' => (int) $it->line_total_sold,
                ];
            })->toArray();

            $grandTotal = (int) $handover->cash_amount + (int) $handover->transfer_amount;
            if ($grandTotal <= 0) {
                $grandTotal = (int) $handover->items->sum('payment_amount');
            }

            try {
                Mail::to($handover->sales->email)
                    ->send(new SalesHandoverOtpMail($handover, $otpCode, $itemsForMail, $grandTotal));
            } catch (\Throwable $e) {
                return back()->with(
                    'success',
                    'OTP sore dibuat, tetapi pengiriman email gagal: '.$e->getMessage()
                );
            }
        }

        return back()->with('success', 'OTP sore berhasil dibuat dan dikirim ke sales.');
    }



}
