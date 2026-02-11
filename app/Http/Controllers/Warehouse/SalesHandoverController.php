<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Company; 
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use App\Mail\SalesHandoverOtpMail;
use Illuminate\Support\Collection;
use App\Exports\Sales\SalesReportExport;
use Maatwebsite\Excel\Facades\Excel;


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
                'items.*.discount_per_unit' => ['nullable', 'integer', 'min:0'],
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
            $activeHandoverCount = SalesHandover::where('sales_id', $sales->id)
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->count();

            if ($activeHandoverCount >= 3) {
                return back()->withInput()->with(
                    'error',
                    "Sales {$sales->name} sudah punya 3 handover aktif. Silakan closing salah satu handover sebelum membuat handover baru."
                );
            }

            // Tentukan ini handover keberapa
            $handoverNumber = $activeHandoverCount + 1;

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

                    $discount = (int) ($row['discount_per_unit'] ?? 0);
                    

                    if ($discount > $unitPrice) {
                        throw new \RuntimeException("Diskon melebihi harga jual untuk {$product->name}");
                    }

                    $priceAfterDiscount = $unitPrice - $discount;

                    SalesHandoverItem::create([
                    'handover_id'  => $handover->id,
                    'product_id'   => $product->id,

                    'qty_start'    => $qty,
                    'qty_returned' => 0,
                    'qty_sold'     => 0,

                    // harga
                    'unit_price'   => $unitPrice,
                    'discount_per_unit' => $discount,
                    'unit_price_after_discount' => $priceAfterDiscount,

                    // total
                    'line_total_start' => $unitPrice * $qty,
                    'discount_total'   => $discount * $qty,
                    'line_total_after_discount' => $priceAfterDiscount * $qty,

                    'line_total_sold' => 0,
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
        if ($handover->discount_set_at) {
         // diskon sudah di-set, lanjut
        } else {
            $handover->discount_set_at = now();
            $handover->discount_set_by = auth()->id();
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
                $lineTotal = (int) $item->line_total_after_discount;
                $totalDispatched += $lineTotal;
                // Update item nilai awal
                $item->line_total_start = $lineTotal;
                $item->save();
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

            $handover->total_dispatched_amount = $handover->items->sum(
            fn($i) => (int) $i->line_total_after_discount
            );

            $handover->discount_total = $handover->items->sum(
                fn($i) => (int) $i->discount_total
            );

            $handover->grand_total = $handover->total_dispatched_amount;

            $handover->status = 'on_sales';
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
                if (! $product) continue;

                $qtyStart = (int) $item->qty_start;
                $qtyRet   = $inputReturned[$item->product_id] ?? 0;

                if ($qtyRet < 0 || $qtyRet > $qtyStart) {
                    throw new \RuntimeException("Qty kembali tidak valid untuk {$product->name}.");
                }

                $qtySold = max(0, $qtyStart - $qtyRet);

                $priceOriginal = (int) $item->unit_price;
                $priceAfter    = (int) ($item->unit_price_after_discount ?? $priceOriginal);

                $lineOriginal = $qtySold * $priceOriginal;
                $lineSold     = $qtySold * $priceAfter;

                $item->qty_returned        = $qtyRet;
                $item->qty_sold            = $qtySold;
                $item->line_total_original = $lineOriginal;
                $item->line_total_sold     = $lineSold;
                $item->save();

                // ✅ ini yang bikin cash & transfer normal
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
     * DAILY REPORT (ADMIN/WAREHOUSE VIEW)
     */
    public function warehouseSalesReport(Request $request)
    {
        return $this->reportIndex($request);
    }

    public function salesReport(Request $request)
    {
        return $this->reportIndex($request);
    }

    private function calcRealSold(SalesHandover $h): int
    {
        if (! $h->relationLoaded('items')) {
            $h->load('items');
        }

        return $h->items->sum(function ($it) {
            $discount = (int) ($it->discount_per_unit ?? 0);

            if ($discount > 0) {
                return (int) ($it->line_total_after_discount ?? 0);
            }

            return (int) ($it->line_total_sold ?? 0);
        });
    }

    private function calcOriginalSold(SalesHandover $h): int
    {
        if (! $h->relationLoaded('items')) {
            $h->load('items');
        }

        return $h->items->sum(function ($it) {
            return (int) ($it->line_total_sold ?? 0);
        });
    }

    private function calcDiscountLoss(SalesHandover $h): int
    {
        return max(0, $this->calcOriginalSold($h) - $this->calcRealSold($h));
    }

    private function reportIndex(Request $request)
    {
        $me    = auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales     = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug', 'admin') || $roles->contains('slug', 'superadmin');
        $canSeeMargin =
                        $roles->contains('slug','superadmin')
                    || $roles->contains('slug','admin')
                    || $roles->contains('slug','warehouse')
                    || $roles->contains('slug','procurement')
                    || $roles->contains('slug','ceo');


        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo   = $request->query('date_to',   now()->toDateString());
        if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

        $status      = (string) $request->query('status', 'all');
        $warehouseId = $request->query('warehouse_id');
        $salesId     = $request->query('sales_id');
        $search      = trim((string) $request->query('q', ''));

        // view: handover | sales | daily
        $view = (string) $request->query('view', 'handover');
        if (!in_array($view, ['handover','sales','daily'], true)) $view = 'handover';

        $statusOptions = [
            'all'                 => 'Semua Status',
            'draft'               => 'Draft',
            'waiting_morning_otp' => 'Menunggu OTP Pagi',
            'on_sales'            => 'On Sales',
            'waiting_evening_otp' => 'Menunggu Closing (Legacy)',
            'closed'              => 'Closed',
            'cancelled'           => 'Cancelled',
        ];

        // ===== LOCK RULES (ini yang bikin sales cuma lihat data dia) =====
        if ($isWarehouse && $me->warehouse_id && ! $isAdminLike) {
            $warehouseId = $me->warehouse_id;
        }

        if ($isSales && ! $isAdminLike && ! $isWarehouse) {
            $salesId = $me->id;
            if ($me->warehouse_id) $warehouseId = $me->warehouse_id;
        }

        // ===== QUERY HANDOVER =====
        $query = SalesHandover::with(['warehouse','sales'])
            ->whereBetween('handover_date', [$dateFrom, $dateTo]);

        if ($status !== 'all') $query->where('status', $status);
        if ($warehouseId)      $query->where('warehouse_id', $warehouseId);
        if ($salesId)          $query->where('sales_id', $salesId);

        // ===== SEARCH (FIX: jangan pernah sentuh column warehouses.name kalau kolomnya ga ada) =====
        $hasWhNameCol = Schema::hasColumn('warehouses', 'warehouse_name');
        $hasWhCodeCol = Schema::hasColumn('warehouses', 'warehouse_code');
        $hasWhNameLegacyCol = Schema::hasColumn('warehouses', 'name'); // jaga-jaga kalau DB lama masih ada

        if ($search !== '') {
            $q = "%{$search}%";
            $query->where(function ($sub) use ($q, $hasWhNameCol, $hasWhCodeCol, $hasWhNameLegacyCol) {
                $sub->where('code', 'like', $q)
                    ->orWhereHas('warehouse', function ($w) use ($q, $hasWhNameCol, $hasWhCodeCol, $hasWhNameLegacyCol) {
                        // NOTE: builder pertama pakai where, sisanya orWhere (biar ga error SQL)
                        $first = true;

                        if ($hasWhNameCol) {
                            $w->where('warehouse_name', 'like', $q);
                            $first = false;
                        }

                        if ($hasWhNameLegacyCol) {
                            $first ? $w->where('name', 'like', $q) : $w->orWhere('name', 'like', $q);
                            $first = false;
                        }

                        if ($hasWhCodeCol) {
                            $first ? $w->where('warehouse_code', 'like', $q) : $w->orWhere('warehouse_code', 'like', $q);
                        }
                    })
                    ->orWhereHas('sales', function ($s) use ($q) {
                        $s->where('name', 'like', $q);
                    });
            });
        }

        $handovers = $query->orderBy('handover_date', 'desc')->orderBy('code')->get();

        [$rows, $summary] = $this->makeReportData($handovers, $statusOptions, $dateFrom, $dateTo, $view);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'view'    => $view,
                'rows'    => $rows,
                'summary' => $summary,
            ]);
        }

        // ===== DROPDOWN DATA (FIX UTAMA: jangan select warehouses.name kalau kolom ga ada) =====
        $whQuery = Warehouse::query();

        // warehouse user => kunci ke warehouse dia
        // sales murni => kunci ke warehouse dia juga (biar UI sama & aman)
        if (($isWarehouse && $me->warehouse_id && ! $isAdminLike) || ($isSales && ! $isAdminLike && ! $isWarehouse && $me->warehouse_id)) {
            $whQuery->where('id', $warehouseId);
        }

        // orderBy aman (tanpa "name")
        $whQuery->orderBy(DB::raw('COALESCE(warehouse_name, warehouse_code, id)'));

        $whSelect = ['id'];
        if ($hasWhNameCol) $whSelect[] = 'warehouse_name';
        if ($hasWhCodeCol) $whSelect[] = 'warehouse_code';
        if ($hasWhNameLegacyCol) $whSelect[] = 'name'; // cuma kalau kolomnya beneran ada
        $warehouses = $whQuery->get($whSelect);

        $salesQuery = User::whereHas('roles', fn($q) => $q->where('slug','sales'));
        if ($warehouseId) $salesQuery->where('warehouse_id', $warehouseId);

        // sales murni => list cuma dirinya
        if ($isSales && ! $isAdminLike && ! $isWarehouse) {
            $salesQuery->where('id', $me->id);
        }

        $salesList = $salesQuery->orderBy('name')->get(['id','name']);

        return view('wh.handover_report', [
            'me'            => $me,
            'rows'          => $rows,
            'summary'       => $summary,
            'view'          => $view,

            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'status'        => $status,
            'statusOptions' => $statusOptions,

            'warehouseId'   => $warehouseId,
            'salesId'       => $salesId,
            'warehouses'    => $warehouses,
            'salesList'     => $salesList,
            'search'        => $search,
            'canSeeMargin' => $canSeeMargin,
        ]);
    }

    private function makeReportData($handovers, array $statusOptions, string $dateFrom, string $dateTo, string $view): array
    {
        // ===== SUMMARY (TOP) =====
        $totalHdo = (int) $handovers->count();

        $totalSoldClosed = (int) $handovers
        ->where('status', 'closed')
        ->sum(fn($h) => $this->calcRealSold($h));

        $totalDiscount = (int) $handovers
        ->where('status', 'closed')
        ->sum(fn($h) => $this->calcDiscountLoss($h));
        
        $totalDiff = (int) $handovers->sum(function ($h) {
            $dispatched = (int) ($h->total_dispatched_amount ?? 0);
            $sold = $this->calcRealSold($h);
            return max(0, $dispatched - $sold);
        });

        $summary = [
            'total_hdo_text'        => $totalHdo . ' HDO',
            'total_sold_formatted'  => $this->formatRp($totalSoldClosed),
            'total_discount'       => $this->formatRp($totalDiscount),
            'total_diff_formatted'  => $this->formatRp($totalDiff),
            'period_text'           => $dateFrom . ' s/d ' . $dateTo,
            'view'                  => $view,
        ];

        // ===== ROWS =====
        $rows = [];

        if ($view === 'handover') {
            foreach ($handovers->values() as $i => $h) {
                $wh = $h->warehouse;
                $warehouseLabel = $wh->warehouse_name ?? $wh->name ?? $wh->warehouse_code ?? ('Warehouse #'.$h->warehouse_id);

                $salesLabel = optional($h->sales)->name ?? ('Sales #'.$h->sales_id);

                $dispatched = (int) ($h->total_dispatched_amount ?? 0);
                $original = $this->calcOriginalSold($h);
                $real     = $this->calcRealSold($h);
                $discount = max(0, $original - $real);
                $diff     = max(0, $dispatched - $real);

                $stLabel = $statusOptions[$h->status] ?? $h->status;

                $badgeClass = 'bg-label-secondary';
                if ($h->status === 'closed') $badgeClass = 'bg-label-success';
                elseif ($h->status === 'on_sales') $badgeClass = 'bg-label-info';
                elseif ($h->status === 'waiting_morning_otp' || $h->status === 'waiting_evening_otp') $badgeClass = 'bg-label-warning';
                elseif ($h->status === 'cancelled') $badgeClass = 'bg-label-danger';

                $dateText = $h->handover_date ? (string) $h->handover_date : null;
                if ($h->handover_date instanceof \Carbon\CarbonInterface) $dateText = $h->handover_date->toDateString();

                $rows[] = [
                    'no'                 => $i + 1,
                    'id'                 => $h->id,
                    'date'               => $dateText,
                    'code'               => $h->code,
                    'warehouse'          => $warehouseLabel,
                    'sales'              => $salesLabel,
                    'status_label'       => $stLabel,
                    'status_badge_class' => $badgeClass,
                    'amount_dispatched'  => $this->formatRp($dispatched),
                    'amount_diff'        => $this->formatRp($diff),
                    'amount_original' => $this->formatRp($original),
                    'amount_sold'     => $this->formatRp($real),
                    'amount_discount' => $this->formatRp($discount),
                    'amount_diff'      => $this->formatRp(max(0, $dispatched - $real)),
                    
                ];
            }

            return [$rows, $summary];
        }

        if ($view === 'sales') {
            $grouped = $handovers->groupBy('sales_id');

            $idx = 1;
            foreach ($grouped as $salesId => $list) {
                $first = $list->first();

                $wh = optional($first)->warehouse;
                $warehouseLabel = $wh?->warehouse_name ?? $wh?->name ?? $wh?->warehouse_code ?? ('Warehouse #'.($first->warehouse_id ?? '-'));

                $salesLabel = optional($first->sales)->name ?? ('Sales #'.$salesId);

                $handoverCount = (int) $list->count();
                $sumDispatched = (int) $list->sum(fn($h) => (int) ($h->total_dispatched_amount ?? 0));
                $sumSoldClosed = (int) $list->where('status','closed')->sum(fn($h) => $this->calcRealSold($h));
                $sumSetor      = (int) $list->sum(function ($h) {
                    $cash = (int) ($h->cash_amount ?? 0);
                    $tf   = (int) ($h->transfer_amount ?? 0);
                    return $cash + $tf;
                });

                $rows[] = [
                    'no'               => $idx++,
                    'sales_id'         => (int) $salesId,
                    'sales'            => $salesLabel,
                    'warehouse'         => $warehouseLabel,
                    'handover_count'   => $handoverCount,
                    'amount_dispatched'=> $this->formatRp($sumDispatched),
                    'amount_sold'      => $this->formatRp($sumSoldClosed),
                    'amount_setor'     => $this->formatRp($sumSetor),
                ];
            }

            return [$rows, $summary];
        }

        // view === 'daily'
        $grouped = $handovers->groupBy(function ($h) {
            if ($h->handover_date instanceof \Carbon\CarbonInterface) return $h->handover_date->toDateString();
            return (string) $h->handover_date;
        });

        $dates = $grouped->keys()->sortDesc()->values();

        foreach ($dates as $i => $date) {
            $list = $grouped[$date];

            $handoverCount = (int) $list->count();
            $sumDispatched = (int) $list->sum(fn($h) => (int) ($h->total_dispatched_amount ?? 0));
            $sumSoldClosed = (int) $list->where('status','closed')->sum(fn($h) => $this->calcRealSold($h));
            $sumSetor      = (int) $list->sum(function ($h) {
                $cash = (int) ($h->cash_amount ?? 0);
                $tf   = (int) ($h->transfer_amount ?? 0);
                return $cash + $tf;
            });

            $rows[] = [
                'no'               => $i + 1,
                'date'             => $date,
                'handover_count'   => $handoverCount,
                'amount_dispatched'=> $this->formatRp($sumDispatched),
                'amount_sold'      => $this->formatRp($sumSoldClosed),
                'amount_setor'     => $this->formatRp($sumSetor),
            ];
        }

        return [$rows, $summary];
    }

    private function formatRp(int $num): string
    {
        return 'Rp ' . number_format($num, 0, ',', '.');
    }
    // =========================
    // DETAIL (JSON FOR MODAL)
    // =========================
    public function warehouseSalesReportDetail(SalesHandover $handover, Request $request)
    {
        return $this->detailJson($handover);
    }

    public function salesReportDetail(SalesHandover $handover, Request $request)
    {
        // safety: sales murni cuma boleh lihat handover dia sendiri
        $me    = auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales     = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug', 'admin') || $roles->contains('slug', 'superadmin');

        if ($isSales && ! $isAdminLike && ! $isWarehouse) {
            if ((int) $handover->sales_id !== (int) $me->id) {
                abort(403);
            }
        }

        return $this->detailJson($handover);
    }

    private function detailJson(SalesHandover $handover)
    {
        $handover->loadMissing(['warehouse','sales','items.product']);

        $wh    = $handover->warehouse;
        $sales = $handover->sales;

        // ==== Bukti transfer
        $proofPath = $handover->transfer_proof_path
            ?? $handover->transfer_proof
            ?? $handover->transfer_proof_file
            ?? null;

        if (!empty($handover->transfer_proof_url)) {
            $proofUrl = $handover->transfer_proof_url;
        } elseif ($proofPath) {
            $proofUrl = Storage::url($proofPath);
        } else {
            $proofUrl = null;
        }

        // ==== ITEMS + HITUNG TOTAL SOLD DARI ITEM
        $items = [];
        $totalSold = 0; // ← ini yg jadi sumber nilai jual yg benar

        foreach ($handover->items as $it) {
            $p = $it->product;

            $qtyStart    = (int) ($it->qty_start ?? 0);
            $qtyReturned = (int) ($it->qty_returned ?? 0);
            $qtySold     = (int) ($it->qty_sold ?? 0);

            $unitPrice = (int) ($it->unit_price ?? 0);
            $discount  = (int) ($it->discount_per_unit ?? 0);

            // Harga setelah diskon
            $unitAfter = (int) ($it->unit_price_after_discount ?? ($unitPrice - $discount));
            if ($unitAfter < 0) $unitAfter = 0;

            // Total awal kirim
            $lineStart = (int) ($it->line_total_start ?? ($qtyStart * $unitPrice));

            // 🔥 Tentukan total jual per item
            if ($discount > 0) {
                // item diskon
                $lineSold = (int) ($it->line_total_after_discount ?? ($qtySold * $unitAfter));
            } else {
                // item normal
                $lineSold = (int) ($it->line_total_sold ?? ($qtySold * $unitPrice));
            }

            // 🔥 Akumulasi total jual
            $totalSold += $lineSold;

            $items[] = [
                'product_name' => $p?->name ?? '-',
                'product_code' => $p?->product_code ?? null,

                'qty_start'    => $qtyStart,
                'qty_returned' => $qtyReturned,
                'qty_sold'     => $qtySold,

                'unit_price'   => $unitPrice,
                'discount_per_unit' => $discount,
                'unit_price_after_discount' => $unitAfter,

                'line_total_start' => $lineStart,
                'line_total_sold'  => $lineSold, // sudah mix diskon / non-diskon
            ];
        }

        // ==== TOTALS
        $totalDispatched = (int) ($handover->total_dispatched_amount ?? 0);

        // 🔥 PAKAI HASIL HITUNG DARI ITEMS
        $selisihStock = max(0, $totalDispatched - $totalSold);

        $cashAmount     = (int) ($handover->cash_amount ?? 0);
        $transferAmount = (int) ($handover->transfer_amount ?? 0);
        $setorTotal     = $cashAmount + $transferAmount;

        $selisihJualSetor = max(0, $totalSold - $setorTotal);

        return response()->json([
            'success' => true,
            'handover' => [
                'id' => $handover->id,
                'code' => $handover->code,
                'status' => $handover->status,
                'handover_date' => $handover->handover_date instanceof \Carbon\CarbonInterface
                    ? $handover->handover_date->toDateString()
                    : (string) $handover->handover_date,

                'warehouse_name' => $wh?->warehouse_name ?? $wh?->name ?? '-',
                'sales_name'     => $sales?->name ?? '-',

                'total_dispatched' => $totalDispatched,

                // 🔥 NILAI JUAL YANG SUDAH BENAR
                'total_sold' => $totalSold,

                'selisih_stock_value' => $selisihStock,

                'cash_amount'     => $cashAmount,
                'transfer_amount' => $transferAmount,
                'transfer_proof_url' => $proofUrl,

                'setor_total' => $setorTotal,
                'selisih_jual_vs_setor' => $selisihJualSetor,
            ],
            'items' => $items,
        ]);
    }
// =========================
// VIEW: DETAIL HANDOVER ROWS
// =========================
    protected function buildRowsByHandover(Collection $handovers, array $statusLabels): array
    {
        return $handovers->values()->map(function (SalesHandover $h, int $idx) use ($statusLabels) {

            $dispatched = (int) $h->total_dispatched_amount;
            $sold       = (int) $h->total_sold_amount;
            $diff       = $dispatched - $sold;

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
        })->toArray();
    }


// =========================
// VIEW: REKAP PER SALES ROWS
// =========================
    protected function buildRowsBySales(Collection $handovers): array
    {
        $group = $handovers->groupBy('sales_id');

        $rows = [];
        $i = 1;

        foreach ($group as $salesId => $list) {
            /** @var Collection $list */
            $first = $list->first();

            $salesName = optional($first->sales)->name ?? ('Sales #'.$salesId);
            $whName = optional($first->warehouse)->warehouse_name
                ?? optional($first->warehouse)->name
                ?? '-';

            $totalDispatched = (int) $list->sum('total_dispatched_amount');
            $totalSoldClosed = (int) $list->where('status','closed')->sum('total_sold_amount');
            $totalSetor      = (int) $list->sum(fn($h)=> (int)($h->cash_amount ?? 0) + (int)($h->transfer_amount ?? 0));
            $diffStock       = $totalDispatched - $totalSoldClosed;
            $diffJualSetor   = $totalSoldClosed - $totalSetor;

            $rows[] = [
                'no'                 => $i++,
                'sales_id'           => (int) $salesId,
                'warehouse_id'       => (int) ($first->warehouse_id ?? 0),
                'sales'              => $salesName,
                'warehouse'          => $whName,
                'handover_count'     => (int) $list->count(),
                'closed_count'       => (int) $list->where('status','closed')->count(),
                'amount_dispatched'  => $this->formatRupiah($totalDispatched),
                'amount_sold'        => $this->formatRupiah($totalSoldClosed),
                'amount_setor'       => $this->formatRupiah($totalSetor),
                'amount_diff_stock'  => $this->formatRupiah($diffStock),
                'amount_diff_setor'  => $this->formatRupiah($diffJualSetor),
            ];
        }

        // sort by sold desc biar enak kebaca
        usort($rows, function($a,$b){
            // strip "Rp " tidak perlu, pakai angka? yaudah fallback by string
            return 0;
        });

        return $rows;
    }


// =======================
// VIEW: REKAP PER HARI ROWS
// =======================
    protected function buildRowsByDay(Collection $handovers): array
    {
        $group = $handovers->groupBy(function($h){
            return optional($h->handover_date)->format('Y-m-d') ?? 'unknown';
        });

        $rows = [];
        $i = 1;

        foreach ($group as $date => $list) {
            /** @var Collection $list */
            $totalDispatched = (int) $list->sum('total_dispatched_amount');
            $totalSoldClosed = (int) $list->where('status','closed')->sum('total_sold_amount');
            $totalSetor      = (int) $list->sum(fn($h)=> (int)($h->cash_amount ?? 0) + (int)($h->transfer_amount ?? 0));

            $diffStock     = $totalDispatched - $totalSoldClosed;
            $diffJualSetor = $totalSoldClosed - $totalSetor;

            $rows[] = [
                'no'                 => $i++,
                'date'               => $date,
                'handover_count'     => (int) $list->count(),
                'closed_count'       => (int) $list->where('status','closed')->count(),
                'amount_dispatched'  => $this->formatRupiah($totalDispatched),
                'amount_sold'        => $this->formatRupiah($totalSoldClosed),
                'amount_setor'       => $this->formatRupiah($totalSetor),
                'amount_diff_stock'  => $this->formatRupiah($diffStock),
                'amount_diff_setor'  => $this->formatRupiah($diffJualSetor),
            ];
        }

        // sort by date desc
        usort($rows, fn($a,$b) => strcmp($b['date'],$a['date']));
        // re-number
        foreach ($rows as $k => $r) $rows[$k]['no'] = $k+1;

        return $rows;
    }

    // ================== HELPER ==================

    protected function formatRupiah(int $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    /**
     * Dipakai oleh salesReport() & warehouseSalesReport() untuk response AJAX
     */

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

        // ===== FIX UTAMA: yang masih OPEN jangan hilang karena filter tanggal =====
        $openStatuses = [
            'waiting_morning_otp',
            'on_sales',
            'waiting_evening_otp',
        ];

        $query = SalesHandover::with(['warehouse'])
            ->where('sales_id', $me->id)
            ->orderBy('handover_date', 'desc')
            ->orderBy('code');

        if ($status !== 'all') {
            $query->where('status', $status);

            // kalau status yang dipilih BUKAN open, baru pakai filter tanggal
            if (!in_array($status, $openStatuses, true)) {
                $query->whereBetween('handover_date', [$dateFrom, $dateTo]);
            }
        } else {
            // status all: tampilkan periode + SEMUA yang masih open (walau di luar periode)
            $query->where(function ($sub) use ($dateFrom, $dateTo, $openStatuses) {
                $sub->whereBetween('handover_date', [$dateFrom, $dateTo])
                    ->orWhereIn('status', $openStatuses);
            });
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
        $me = $request->user();

        $data = $request->validate([
            'decisions'              => ['required', 'array'],
            'decisions.*.status'     => ['required', 'in:approved,rejected'],
            'decisions.*.reason'     => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($handover, $data, $me) {

                $handover->load(['items.product']);

                $totalCash     = 0;
                $totalTransfer = 0;

                foreach ($handover->items as $item) {
                    if (!isset($data['decisions'][$item->id])) {
                        continue;
                    }

                    $row    = $data['decisions'][$item->id];
                    $status = $row['status'];

                    $item->payment_status = $status;

                    $item->payment_reject_reason = ($status === 'rejected')
                        ? ($row['reason'] ?? null)
                        : null;

                    // recap hanya dari yang approved
                    if ($status === 'approved') {
                        if ($item->payment_method === 'cash') {
                            $totalCash += (int) $item->payment_amount;
                        } elseif ($item->payment_method === 'transfer') {
                            $totalTransfer += (int) $item->payment_amount;
                        }
                    }

                    $item->save();
                }

                $handover->cash_amount     = $totalCash;
                $handover->transfer_amount = $totalTransfer;
                $handover->save();

                // ================================
                // AUTO CLOSE (INI YANG LO MAU)
                // Close kalau SEMUA item yg terjual (qty_sold>0) sudah APPROVED
                // ================================
                if ($handover->status !== 'closed') {

                    $itemsSold = $handover->items->where('qty_sold', '>', 0);
                    $allApproved = $itemsSold->every(fn($it) => $it->payment_status === 'approved');

                    if ($allApproved) {

                        foreach ($handover->items as $item) {
                            $product = $item->product;
                            if (!$product) continue;

                            $qtyStart = (int) $item->qty_start;

                            // safety: kalau ada mismatch, paksa konsisten
                            $qtySold = (int) $item->qty_sold;
                            if ($qtySold < 0) $qtySold = 0;
                            if ($qtySold > $qtyStart) $qtySold = $qtyStart;

                            $qtyRet = (int) $item->qty_returned;
                            $expectedRet = max(0, $qtyStart - $qtySold);
                            if ($qtyRet !== $expectedRet) {
                                $qtyRet = $expectedRet;
                                $item->qty_returned = $qtyRet;
                                $item->save();
                            }

                            // Stok sales: kurangi semua qty_start
                            $salesStock = DB::table('stock_levels')
                                ->where('owner_type', 'sales')
                                ->where('owner_id', $handover->sales_id)
                                ->where('product_id', $product->id)
                                ->lockForUpdate()
                                ->first();

                            if (!$salesStock || $salesStock->quantity < $qtyStart) {
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
                                    'note'       => "Handover {$handover->code} (return sore - auto close)",
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
                                    'note'       => "Handover {$handover->code} (sold closing - auto close)",
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
                        $handover->evening_otp_verified_at = now(); // biar konsisten field waktu closing
                        $handover->save();
                    }
                }
            });

        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal simpan approval: '.$e->getMessage());
        }

        return back()->with('success', 'Approval payment berhasil disimpan. Jika semua item terjual sudah APPROVED, handover otomatis CLOSED.');
    }
    
        public function getActiveCount(User $sales)
    {
        $count = SalesHandover::where('sales_id', $sales->id)
            ->whereNotIn('status', ['closed','cancelled'])
            ->count();

        return response()->json([
            'active' => $count,
            'next'   => $count + 1,
            'limit'  => 3
        ]);
    }

    

    public function exportSalesExcel(Request $request)
    {
        $me = auth()->user();

        // 🔥 AMBIL FILTER PERSIS SAMA reportIndex
        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo   = $request->query('date_to', now()->toDateString());
        if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

        $status      = $request->query('status', 'all');
        $warehouseId = $request->query('warehouse_id');
        $salesId     = $request->query('sales_id');
        $search      = trim((string) $request->query('q', ''));
        $view        = $request->query('view', 'handover');

        // ===== ROLE LOCK (COPY DARI reportIndex) =====
        $roles = $me->roles ?? collect();
        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales     = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug','admin') || $roles->contains('slug','superadmin');

        if ($isWarehouse && $me->warehouse_id && ! $isAdminLike) {
            $warehouseId = $me->warehouse_id;
        }

        if ($isSales && ! $isAdminLike && ! $isWarehouse) {
            $salesId = $me->id;
            if ($me->warehouse_id) $warehouseId = $me->warehouse_id;
        }

        // ===== QUERY BASE =====
        $query = SalesHandover::with(['warehouse','sales','items'])
            ->whereBetween('handover_date', [$dateFrom, $dateTo]);

        if ($status !== 'all') $query->where('status', $status);
        if ($warehouseId)      $query->where('warehouse_id', $warehouseId);
        if ($salesId)          $query->where('sales_id', $salesId);

        if ($search !== '') {
            $q = "%{$search}%";
            $query->where(function ($sub) use ($q) {
                $sub->where('code','like',$q)
                    ->orWhereHas('sales', fn($s)=>$s->where('name','like',$q));
            });
        }

        $handovers = $query
            ->orderBy('handover_date','desc')
            ->orderBy('code')
            ->get();

        // ===== META BUAT HEADER EXCEL =====
        $meta = [
            'filters' => [
                'Periode'   => "{$dateFrom} s/d {$dateTo}",
                'View'      => ucfirst($view),
                'Status'    => $status === 'all' ? 'Semua' : strtoupper($status),
                'Warehouse' => optional(Warehouse::find($warehouseId))->warehouse_name ?? 'Semua',
                'Sales'     => optional(User::find($salesId))->name ?? 'Semua',
                'Search'    => $search ?: '-',
            ]
        ];

        $me = auth()->user()->load('company');

        $company = $me->company
            ?? Company::where('is_default', true)->first()
            ?? Company::first();

        return Excel::download(
            new SalesReportExport($handovers, $view, $meta, $company),
            'SALES-REPORT-' . now()->format('Ymd_His') . '.xlsx'
        );

    }
}