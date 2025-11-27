<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\RestockReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreOController extends Controller
{
    /** LIST PO */
     public function index(Request $request)
    {
        $q      = trim($request->get('q', ''));
        $status = $request->get('status');

        // berapa data per halaman
        $perPage = 10;

        // PO + relasi yang dipakai di list & modal
        $pos = PurchaseOrder::with([
                'supplier',
                'items.product.supplier', // summary supplier
                'items',                  // untuk modal GR
                'restockReceipts',
            ])
            ->withCount('items')
            ->when($q, function ($qq) use ($q) {
                $qq->where('po_code', 'like', "%{$q}%");
            })
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        // hitung jumlah GR per PO (buat toggle tombol Receive)
        $pos->getCollection()->each(function ($po) {
            $po->gr_count = 0;
        });

        if (
            Schema::hasTable('restock_receipts') &&
            Schema::hasColumn('restock_receipts', 'purchase_order_id')
        ) {
            $poIds = $pos->pluck('id')->all();

            if (!empty($poIds)) {
                $grCounts = DB::table('restock_receipts')
                    ->select('purchase_order_id', DB::raw('COUNT(*) as c'))
                    ->whereIn('purchase_order_id', $poIds)
                    ->groupBy('purchase_order_id')
                    ->pluck('c', 'purchase_order_id');

                $pos->getCollection()->transform(function ($po) use ($grCounts) {
                    $po->gr_count = (int) ($grCounts[$po->id] ?? 0);
                    return $po;
                });
            }
        }

        // ====== REQUEST AJAX: balikin partial table saja ======
        if ($request->ajax()) {
            return view('admin.po._table', compact('pos', 'q', 'status'));
        }

        // ====== REQUEST BIASA: balikin full page ======
        return view('admin.po.index', compact('pos', 'q', 'status'));
    }

    /** EDIT PO (manual / dari Restock Request) */
    public function edit(PurchaseOrder $po)
    {
        // load relasi termasuk supplier per product
        $po->load(['items.product.supplier', 'items.warehouse', 'supplier']);

        $suppliers  = Supplier::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']);

        // produk + supplier sekalian
        $products = Product::with('supplier:id,name')
            ->orderBy('name')
            ->get(['id', 'product_code', 'name', 'selling_price', 'supplier_id']);

        $isFromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

        // ---- cek apakah PO sudah di-lock (COMPLETED & punya GR) ----
        $hasGr = false;
        if (
            Schema::hasTable('restock_receipts') &&
            Schema::hasColumn('restock_receipts', 'purchase_order_id')
        ) {
            $hasGr = DB::table('restock_receipts')
                ->where('purchase_order_id', $po->id)
                ->exists();
        }

        $isLocked = ($po->status === 'completed') && $hasGr;

        return view('admin.po.edit', compact(
            'po',
            'suppliers',
            'warehouses',
            'products',
            'isFromRequest',
            'isLocked'
        ));
    }

    /** SIMPAN PERUBAHAN PO */
    public function update(Request $request, PurchaseOrder $po)
    {
        // kalau sudah locked, tolak update
        if ($this->poIsLocked($po)) {
            return back()->with('error', 'PO sudah COMPLETED dan memiliki GR, tidak dapat diubah.');
        }

        $validated = $request->validate([
            // header
            'supplier_id'           => ['nullable', 'exists:suppliers,id'],
            'notes'                 => ['nullable', 'string'],

            // items
            'items'                 => ['array'],
            'items.*.id'            => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.warehouse_id'  => ['nullable', 'exists:warehouses,id'],
            'items.*.qty'           => ['required', 'integer', 'min:1'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percent,amount'],
            'items.*.discount_value'=> ['nullable', 'numeric', 'min:0'],
            'items.*.request_id'    => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $po) {
            $po->supplier_id = $validated['supplier_id'] ?? null;
            $po->notes       = $validated['notes'] ?? null;

            $itemsInput    = $validated['items'] ?? [];
            $isFromRequest = $po->items()->whereNotNull('request_id')->exists();

            // default warehouse utk PO manual
            $centralWarehouseId = $isFromRequest ? null : $this->getCentralWarehouseId();

            // preload harga jual utk fallback kalau harga TIDAK DIISI
            $productIds    = collect($itemsInput)->pluck('product_id')->filter()->unique()->all();
            $productPrices = $productIds
                ? Product::whereIn('id', $productIds)->pluck('selling_price', 'id')
                : collect();

            $existing = $po->items()->get()->keyBy('id');
            $keepIds  = [];

            $subtotal      = 0;
            $discountTotal = 0;

            foreach ($itemsInput as $row) {
                if (empty($row['product_id']) || empty($row['qty'])) {
                    continue;
                }

                // ambil / buat item
                if (!empty($row['id']) && $existing->has($row['id'])) {
                    $item = $existing->get($row['id']);
                } else {
                    $item = new PurchaseOrderItem();
                    $item->purchase_order_id = $po->id;
                }

                $item->product_id  = $row['product_id'];
                $item->qty_ordered = (int)($row['qty'] ?? 0);

                // ==== WAREHOUSE ====
                $warehouseId = $row['warehouse_id'] ?? null;
                if (!$isFromRequest && !$warehouseId && $centralWarehouseId) {
                    $warehouseId = $centralWarehouseId;
                }
                $item->warehouse_id = $warehouseId;

                // ==== HARGA ====
                $rawPrice = array_key_exists('unit_price', $row) ? $row['unit_price'] : null;

                if ($rawPrice === null || $rawPrice === '') {
                    $price = (float)($productPrices[$row['product_id']] ?? 0);
                } else {
                    $price = (float)$rawPrice;
                }

                $item->unit_price = $price;

                // ==== DISKON ====
                $item->discount_type  = $row['discount_type'] ?: null;
                $item->discount_value = (float)($row['discount_value'] ?? 0);

                // request_id (kalau dari RF)
                if (!empty($row['request_id']) || $item->request_id) {
                    $item->request_id = $row['request_id'] ?? $item->request_id;
                }

                // HITUNG LINE TOTAL
                $lineTotal = $item->qty_ordered * $item->unit_price;

                if ($item->discount_type === 'percent') {
                    $disc = $lineTotal * min(max($item->discount_value, 0), 100) / 100;
                } elseif ($item->discount_type === 'amount') {
                    $disc = min($item->discount_value, $lineTotal);
                } else {
                    $disc = 0;
                }

                $item->line_total = max($lineTotal - $disc, 0);
                $item->save();

                $keepIds[]      = $item->id;
                $subtotal      += $lineTotal;
                $discountTotal += $disc;
            }

            // hapus item yang di-remove dari form
            if (count($keepIds)) {
                $po->items()->whereNotIn('id', $keepIds)->delete();
            } else {
                $po->items()->delete();
            }

            $po->subtotal       = $subtotal;
            $po->discount_total = $discountTotal;
            $po->grand_total    = $subtotal - $discountTotal;
            $po->save();
        });

        // sync ke request_restocks (kalau ada)
        $this->syncRequestsFromPo($po);

        return back()->with('success', 'PO berhasil disimpan.');
    }

    /** SET PO → ORDERED  */
    public function order(PurchaseOrder $po)
    {
        // kalau sudah locked, jangan bisa diapa2in
        if ($this->poIsLocked($po)) {
            return back()->with('info', 'PO sudah COMPLETED dan memiliki GR, tidak dapat diubah statusnya.');
        }

        $fromRequest = $po->items()->whereNotNull('request_id')->exists();

        // ===== PO DARI RESTOCK REQUEST =====
        if ($fromRequest) {
            if ($po->status === 'ordered') {
                return back()->with('info', 'PO dari Restock Request sudah ORDERED.');
            }

            $po->status = 'ordered';
            if (Schema::hasColumn('purchase_orders', 'ordered_at')) {
                $po->ordered_at = now();
            }
            $po->save();

            // update status request_restocks → ordered
            if (
                Schema::hasTable('purchase_order_items') &&
                Schema::hasColumn('purchase_order_items', 'request_id') &&
                Schema::hasTable('request_restocks') &&
                Schema::hasColumn('request_restocks', 'status')
            ) {
                $requestIds = $po->items()
                    ->whereNotNull('request_id')
                    ->pluck('request_id')
                    ->unique()
                    ->all();

                if (!empty($requestIds)) {
                    DB::table('request_restocks')
                        ->whereIn('id', $requestIds)
                        ->update([
                            'status'     => 'ordered',
                            'updated_at' => now(),
                        ]);
                }
            }

            // ⚠️ TIDAK ADA PERUBAHAN STOK DI SINI
            // Stok baru berpindah saat proses Goods Received (GR), bukan saat ORDER.
            $this->syncRequestsFromPo($po);

            return back()->with('success', 'PO dari Restock Request diset ORDERED. Stok akan berubah setelah dibuat Goods Received.');
        }

        // ===== PO MANUAL =====
        if (in_array($po->status, ['ordered', 'completed', 'cancelled'], true)) {
            return back()->with('info', 'Status PO manual saat ini: ' . strtoupper($po->status) . '.');
        }

        $po->status = 'ordered';
        if (Schema::hasColumn('purchase_orders', 'ordered_at')) {
            $po->ordered_at = now();
        }
        $po->save();

        // stok CENTRAL akan ditambah saat input Goods Received (GR), bukan di sini
        return back()->with('success', 'PO manual diset ORDERED. Silakan input Goods Received ketika barang datang.');
    }

    public function cancel(PurchaseOrder $po)
    {
        // kalau sudah locked, jangan bisa cancel
        if ($this->poIsLocked($po)) {
            return back()->with('error', 'PO sudah COMPLETED dan memiliki GR, tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($po) {
            $po->status = 'cancelled';
            if (Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                $po->cancelled_at = now();
            }
            $po->save();

            if (
                Schema::hasTable('purchase_order_items') &&
                Schema::hasColumn('purchase_order_items', 'request_id') &&
                Schema::hasTable('request_restocks') &&
                Schema::hasColumn('request_restocks', 'status')
            ) {
                $requestIds = $po->items()
                    ->whereNotNull('request_id')
                    ->pluck('request_id')
                    ->unique()
                    ->all();

                if (!empty($requestIds)) {
                    DB::table('request_restocks')
                        ->whereIn('id', $requestIds)
                        ->update([
                            'status'     => 'cancelled',
                            'updated_at' => now(),
                        ]);
                }
            }
        });

        return back()->with('success', 'PO dibatalkan dan request ikut CANCELLED.');
    }

    /** Create PO manual (draft kosong) */
    public function store(Request $r)
    {
        $code = $this->generateManualPoCode();

        $po = PurchaseOrder::create([
            'po_code'        => $code,
            'supplier_id'    => null,
            'ordered_by'     => auth()->id(),
            'status'         => 'draft',
            'subtotal'       => 0,
            'discount_total' => 0,
            'grand_total'    => 0,
            'notes'          => null,
        ]);

        return redirect()
            ->route('po.edit', $po->id)
            ->with('success', 'PO baru berhasil dibuat, silakan isi item.');
    }

    /** Generator kode PO manual: PO-YYYYMMDD-0001 */
    protected function generateManualPoCode(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';

        $lastCode = PurchaseOrder::where('po_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('po_code');

        $next = 1;
        if ($lastCode) {
            $lastSeq = (int) substr($lastCode, strlen($prefix));
            $next    = $lastSeq + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /** Ambil ID gudang Central Stock / gudang pertama */
    protected function getCentralWarehouseId(): ?int
    {
        if (!Schema::hasTable('warehouses')) {
            return null;
        }

        $query = Warehouse::query();

        if (Schema::hasColumn('warehouses', 'is_central')) {
            $query->where('is_central', 1);
        } elseif (Schema::hasColumn('warehouses', 'warehouse_name')) {
            $query->where('warehouse_name', 'Central Stock');
        }

        $id = $query->value('id');

        if (!$id) {
            $id = Warehouse::orderBy('id')->value('id');
        }

        return $id ?: null;
    }

    // ====== helper: cek PO locked atau tidak ======
    protected function poIsLocked(PurchaseOrder $po): bool
    {
        if ($po->status !== 'completed') {
            return false;
        }

        if (
            Schema::hasTable('restock_receipts') &&
            Schema::hasColumn('restock_receipts', 'purchase_order_id')
        ) {
            return DB::table('restock_receipts')
                ->where('purchase_order_id', $po->id)
                ->exists();
        }

        return false;
    }

    // ====== SYNC PO → request_restocks ======
    protected function syncRequestsFromPo(PurchaseOrder $po): void
    {
        if (
            !Schema::hasTable('purchase_order_items') ||
            !Schema::hasColumn('purchase_order_items', 'product_id') ||
            !Schema::hasTable('request_restocks')
        ) {
            return;
        }

        // cek dulu: beneran PO "FROM REQUEST" nggak?
        $hasRequestItems = $po->items()
            ->whereNotNull('request_id')
            ->exists();

        if (!$hasRequestItems) {
            return;
        }

        $poNote = trim((string) $po->notes);

        // ---------- 1) UPDATE RF YANG SUDAH ADA ----------
        if (Schema::hasColumn('purchase_order_items', 'request_id')) {
            $itemsWithReq = $po->items()
                ->whereNotNull('request_id')
                ->get(['request_id', 'qty_ordered', 'unit_price']);

            foreach ($itemsWithReq as $row) {
                $qty   = (int) $row->qty_ordered;
                $price = (float) $row->unit_price;

                $update = [
                    'quantity_requested' => $qty,
                    'cost_per_item'      => $price,
                    'total_cost'         => $qty * $price,
                    'updated_at'         => now(),
                ];

                if ($poNote !== '') {
                    $update['note'] = $poNote;
                }

                DB::table('request_restocks')
                    ->where('id', $row->request_id)
                    ->update($update);
            }
        }

        // ---------- 2) BUAT RF BARU UNTUK ITEM TAMBAHAN ----------
        if (!Schema::hasColumn('purchase_order_items', 'request_id')) {
            return;
        }

        $extraItems = $po->items()
            ->whereNull('request_id')
            ->get(['id', 'product_id', 'warehouse_id', 'qty_ordered', 'unit_price']);

        if ($extraItems->isEmpty()) {
            return;
        }

        $productSuppliers = Product::whereIn(
            'id',
            $extraItems->pluck('product_id')->filter()->unique()
        )->pluck('supplier_id', 'id');

        foreach ($extraItems as $item) {
            $productId   = (int) $item->product_id;
            $warehouseId = (int) $item->warehouse_id;
            $qty         = (int) $item->qty_ordered;
            $price       = (float) $item->unit_price;

            if (!$productId || !$warehouseId || $qty <= 0) {
                continue;
            }

            $supplierId = (int) ($productSuppliers[$productId] ?? $po->supplier_id);

            $rfStatus = in_array($po->status, ['ordered', 'completed'], true)
                ? 'ordered'
                : 'approved';

            $insert = [
                'supplier_id'        => $supplierId ?: null,
                'product_id'         => $productId,
                'warehouse_id'       => $warehouseId,
                'requested_by'       => $po->ordered_by ?: auth()->id(),
                'quantity_requested' => $qty,
                'quantity_received'  => 0,
                'cost_per_item'      => $price,
                'total_cost'         => $qty * $price,
                'status'             => $rfStatus,
                'note'               => $poNote !== '' ? $poNote : null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            $reqId = DB::table('request_restocks')->insertGetId($insert);

            if (Schema::hasColumn('request_restocks', 'code')) {
                DB::table('request_restocks')
                    ->where('id', $reqId)
                    ->update([
                        'code'       => 'RR-' . $reqId,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('purchase_order_items')
                ->where('id', $item->id)
                ->update([
                    'request_id' => $reqId,
                    'updated_at' => now(),
                ]);
        }
    }

    /** Barang MASUK ke CENTRAL dari PO manual (status completed) */
    protected function applyStockFromManualPo(PurchaseOrder $po): void
    {
        if (!Schema::hasTable('stock_levels')) {
            return;
        }
        if (
            !Schema::hasColumn('stock_levels', 'product_id') ||
            !Schema::hasColumn('stock_levels', 'owner_type') ||
            !Schema::hasColumn('stock_levels', 'owner_id') ||
            !Schema::hasColumn('stock_levels', 'quantity')
        ) {
            return;
        }

        $items = $po->items()->whereNull('request_id')->get();

        foreach ($items as $item) {
            $productId = $item->product_id;
            $qty       = (int) $item->qty_ordered;

            if (!$productId || $qty <= 0) {
                continue;
            }

            $level = DB::table('stock_levels')
                ->where('owner_type', 'central')
                ->where('product_id', $productId)
                ->first();

            if ($level) {
                DB::table('stock_levels')
                    ->where('id', $level->id)
                    ->update([
                        'quantity'   => (int) $level->quantity + $qty,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_levels')->insert([
                    'owner_type' => 'central',
                    'owner_id'   => 0,
                    'product_id' => $productId,
                    'quantity'   => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Barang KELUAR dari CENTRAL ke WAREHOUSE (PO dari request gudang) */
    protected function applyStockForWarehousePo(PurchaseOrder $po): void
    {
        // SEKARANG DIPANGGIL DARI PROSES GR (BUKAN LAGI DARI order())
        if (!Schema::hasTable('stock_levels')) {
            return;
        }
        if (
            !Schema::hasColumn('stock_levels', 'product_id') ||
            !Schema::hasColumn('stock_levels', 'owner_type') ||
            !Schema::hasColumn('stock_levels', 'owner_id') ||
            !Schema::hasColumn('stock_levels', 'quantity')
        ) {
            return;
        }

        $items = $po->items()->whereNotNull('request_id')->get();

        foreach ($items as $item) {
            $productId   = $item->product_id;
            $warehouseId = $item->warehouse_id;
            $qty         = (int) $item->qty_ordered;

            if (!$productId || !$warehouseId || $qty <= 0) {
                continue;
            }

            // Kurangi CENTRAL
            $central = DB::table('stock_levels')
                ->where('owner_type', 'central')
                ->where('product_id', $productId)
                ->first();

            if ($central) {
                $newQty = max(0, (int) $central->quantity - $qty);
                DB::table('stock_levels')
                    ->where('id', $central->id)
                    ->update([
                        'quantity'   => $newQty,
                        'updated_at' => now(),
                    ]);
            }

            // Tambah ke WAREHOUSE
            $levelWh = DB::table('stock_levels')
                ->where('owner_type', 'warehouse')
                ->where('owner_id', $warehouseId)
                ->where('product_id', $productId)
                ->first();

            if ($levelWh) {
                DB::table('stock_levels')
                    ->where('id', $levelWh->id)
                    ->update([
                        'quantity'   => (int) $levelWh->quantity + $qty,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_levels')->insert([
                    'owner_type' => 'warehouse',
                    'owner_id'   => $warehouseId,
                    'product_id' => $productId,
                    'quantity'   => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function exportPdf(PurchaseOrder $po)
    {
        return back()->with('info', 'Export PDF belum diaktifkan.');
    }

    public function exportExcel(PurchaseOrder $po)
    {
        return back()->with('info', 'Export Excel belum diaktifkan.');
    }
}
