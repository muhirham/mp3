<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\RestockReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf; 

class PreOController extends Controller
{
    /** LIST PO */
    private const CEO_MIN_TOTAL = 2_000_000;
    public function index(Request $request)
    {
        $q      = trim($request->get('q', ''));
        $status = $request->get('status');

        $perPage = 10;

        $me    = auth()->user();
        $roles = $me?->roles ?? collect();

        $isSuperadmin  = $roles->contains('slug', 'superadmin'); // <== TAMBAH INI
        $isProcurement = $roles->contains('slug', 'procurement') || $isSuperadmin;
        $isCeo         = $roles->contains('slug', 'ceo')         || $isSuperadmin;

        $pos = PurchaseOrder::with([
                'supplier',
                'items.product.supplier',
                'items.warehouse',
                'restockReceipts',
                'user',
                'procurementApprover',
                'ceoApprover',
            ])
            ->withCount('items')
            ->when($q, function ($qq) use ($q) {
                $qq->where('po_code', 'like', "%{$q}%");
            })
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        // hitung gr_count dll tetap...

        return view('admin.po.index', compact(
            'pos',
            'q',
            'status',
            'isProcurement',
            'isCeo',
            'isSuperadmin'      // <== JANGAN LUPA DI-COMPACT
        ));
    }

        /** EDIT PO (manual / dari Restock Request) */
    public function edit(PurchaseOrder $po)
        {
            $po->load([
                'items.product.supplier',
                'items.warehouse',
                'supplier',
            ]);

            $suppliers  = Supplier::orderBy('name')->get(['id', 'name']);
            $warehouses = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']);

            $cols = ['id', 'product_code', 'name', 'supplier_id'];
            if (Schema::hasColumn('products', 'purchase_price')) $cols[] = 'purchase_price';
            if (Schema::hasColumn('products', 'buy_price'))      $cols[] = 'buy_price';
            if (Schema::hasColumn('products', 'cost_price'))     $cols[] = 'cost_price';
            if (Schema::hasColumn('products', 'selling_price'))  $cols[] = 'selling_price';

            $products = Product::with('supplier:id,name')
                ->orderBy('name')
                ->get($cols);

            $isFromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();
            $isLocked      = $this->poIsLocked($po);

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
            if ($this->poIsLocked($po)) {
                return back()->with(
                    'error',
                    'PO sudah ORDERED dan memiliki Goods Received, tidak dapat diubah.'
                );
            }

            $wasFromRequest = $po->items()
                ->whereNotNull('request_id')
                ->exists();

            $oldRequestIds = [];
            if (
                $wasFromRequest &&
                Schema::hasTable('purchase_order_items') &&
                Schema::hasColumn('purchase_order_items', 'request_id') &&
                Schema::hasTable('request_restocks')
            ) {
                $oldRequestIds = DB::table('purchase_order_items')
                    ->where('purchase_order_id', $po->id)
                    ->whereNotNull('request_id')
                    ->pluck('request_id')
                    ->unique()
                    ->all();
            }

            $validated = $request->validate([
                'supplier_id'           => ['nullable', 'exists:suppliers,id'],
                'notes'                 => ['nullable', 'string'],

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

            DB::transaction(function () use ($validated, $po, $wasFromRequest) {
                $po->supplier_id = $validated['supplier_id'] ?? null;
                $po->notes       = $validated['notes'] ?? null;

                $itemsInput = $validated['items'] ?? [];

                $productIds = collect($itemsInput)->pluck('product_id')->filter()->unique()->all();
                $productPrices = collect();

                if ($productIds) {
                    $cols = ['id'];
                    if (Schema::hasColumn('products', 'purchase_price')) $cols[] = 'purchase_price';
                    if (Schema::hasColumn('products', 'buy_price'))      $cols[] = 'buy_price';
                    if (Schema::hasColumn('products', 'cost_price'))     $cols[] = 'cost_price';
                    if (Schema::hasColumn('products', 'selling_price'))  $cols[] = 'selling_price';

                    $rows = Product::whereIn('id', $productIds)->get($cols);

                    $productPrices = $rows->mapWithKeys(function ($p) use ($wasFromRequest) {
                        $buy  = (float) ($p->purchase_price ?? $p->buy_price ?? $p->cost_price ?? 0);
                        $sell = (float) ($p->selling_price ?? 0);

                        $price = $wasFromRequest
                            ? ($sell ?: $buy)
                            : ($buy ?: $sell);

                        return [$p->id => $price];
                    });
                }

                $existing = $po->items()->get()->keyBy('id');
                $keepIds  = [];

                $subtotal      = 0;
                $discountTotal = 0;

                foreach ($itemsInput as $row) {
                    if (empty($row['product_id']) || empty($row['qty'])) {
                        continue;
                    }

                    if (! empty($row['id']) && $existing->has($row['id'])) {
                        $item = $existing->get($row['id']);
                    } else {
                        $item = new PurchaseOrderItem();
                        $item->purchase_order_id = $po->id;
                    }

                    $item->product_id  = $row['product_id'];
                    $item->qty_ordered = (int) ($row['qty'] ?? 0);

                    if ($wasFromRequest) {
                        $item->warehouse_id = $row['warehouse_id'] ?? null;
                    } else {
                        $item->warehouse_id = null;
                    }

                    $rawPrice = array_key_exists('unit_price', $row) ? $row['unit_price'] : null;

                    if ($rawPrice === null || $rawPrice === '') {
                        $price = (float) ($productPrices[$row['product_id']] ?? 0);
                    } else {
                        $price = (float) $rawPrice;
                    }

                    $item->unit_price = $price;

                    $item->discount_type  = $row['discount_type'] ?: null;
                    $item->discount_value = (float) ($row['discount_value'] ?? 0);

                    if (! empty($row['request_id']) || $item->request_id) {
                        $item->request_id = $row['request_id'] ?? $item->request_id;
                    }

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

            $po->load('items');

            $this->syncRequestsFromPo($po, $wasFromRequest, $oldRequestIds);

            return back()->with('success', 'PO berhasil disimpan.');
        }
            /** SET PO → ORDERED  */
        /** SET PO → ORDERED  */
/**
 * Ajukan PO ke flow approval.
 * - grand_total < 1 jt / < 2 jt → cukup Procurement (nanti di-approveProc).
 * - grand_total > 2 jt          → Procurement lalu CEO.
 */
        public function order(PurchaseOrder $po)
        {
            if (in_array($po->status, ['ordered', 'completed', 'cancelled'], true)) {
                return redirect()->route('po.index')
                    ->with('info', 'PO sudah tidak bisa diajukan approval lagi.');
            }

            if (in_array($po->approval_status, ['waiting_procurement','waiting_ceo','approved'], true)) {
                return redirect()->route('po.edit', $po->id)
                    ->with('info', 'PO sudah dalam proses approval.');
            }

            if ($po->items()->count() === 0 || $po->grand_total <= 0) {
                return redirect()->route('po.edit', $po->id)
                    ->with('error', 'Isi item dan harga dulu sebelum mengajukan approval.');
            }

            // TEKANKAN: logistik tetap draft
            $po->status           = 'draft';
            $po->approval_status  = 'waiting_procurement';
            $po->approved_by_procurement = null;
            $po->approved_by_ceo         = null;
            $po->approved_at_procurement = null;
            $po->approved_at_ceo         = null;

            $po->save();

            return redirect()->route('po.edit', $po->id)
                ->with('success', 'PO berhasil diajukan ke Procurement untuk approval.');
        }



        public function cancel(PurchaseOrder $po)
        {
            if ($this->poIsLocked($po)) {
                return back()->with('error', 'PO sudah ORDERED/COMPLETED, tidak dapat dibatalkan.');
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

                    if (! empty($requestIds)) {
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


        public function approveProcurement(Request $request, PurchaseOrder $po)
        {
            if ($po->approval_status !== 'waiting_procurement') {
                return back()->with('error', 'PO tidak dalam status menunggu approval Procurement.');
            }

            $user = $request->user();

            $po->approved_by_procurement = $user->id;
            $po->approved_at_procurement = now();
            // kalau sebelumnya pernah reject, notes (alasan) di- clear
            $po->notes = null;

            $grand = (int) $po->grand_total;

            if ($grand > self::CEO_MIN_TOTAL) {
                // > 2jt → lanjut ke CEO
                $po->approval_status = 'waiting_ceo';
                // status tetap draft, belum bisa GR
            } else {
                // <= 2jt → cukup Procurement → langsung ORDERED
                $po->approval_status = 'approved';
                $po->status          = 'ordered';
                $po->ordered_at      = now();
            }

            $po->save();

            return back()->with('success', 'Approval Procurement berhasil disimpan.');
        }

    public function rejectProcurement(Request $request, PurchaseOrder $po)
    {
        if ($po->approval_status !== 'waiting_procurement') {
            return back()->with('error', 'PO tidak dalam status menunggu approval Procurement.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $po->approval_status         = 'rejected';
        $po->notes                   = $data['reason'];        // simpan alasan di notes
        $po->approved_by_procurement = $user->id;
        $po->approved_at_procurement = now();
        $po->approved_by_ceo         = null;
        $po->approved_at_ceo         = null;

        // balik ke draft biar bisa diedit & diajukan ulang
        $po->status = 'draft';

        $po->save();

        return redirect()
            ->route('po.edit', $po->id)
            ->with('error', 'PO ditolak Procurement: ' . $data['reason']);
    }

    public function approveCeo(Request $request, PurchaseOrder $po)
{
    if ($po->approval_status !== 'waiting_ceo') {
        return back()->with('error', 'PO tidak dalam status menunggu approval CEO.');
    }

    $user = $request->user();

    $po->approved_by_ceo  = $user->id;
    $po->approved_at_ceo  = now();
    $po->approval_status  = 'approved';
    $po->notes            = null;  // bersihkan alasan reject lama
    $po->status           = 'ordered';
    $po->ordered_at       = now();

    $po->save();

    return back()->with('success', 'PO disetujui CEO dan status di-set ORDERED.');
}

    public function rejectCeo(Request $request, PurchaseOrder $po)
    {
        if ($po->approval_status !== 'waiting_ceo') {
            return back()->with('error', 'PO tidak dalam status menunggu approval CEO.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $po->approval_status = 'rejected';
        $po->notes           = $data['reason'];   // simpan alasan di notes
        $po->approved_by_ceo = $user->id;
        $po->approved_at_ceo = now();

        // balik ke draft biar bisa diedit & diajukan ulang
        $po->status = 'draft';

        $po->save();

        return redirect()
            ->route('po.edit', $po->id)
            ->with('error', 'PO ditolak CEO: ' . $data['reason']);
    }


        public function store(Request $r)
        {
            $code = $this->generateManualPoCode();

            $po = PurchaseOrder::create([
                'po_code'         => $code,
                'supplier_id'     => null,
                'ordered_by'      => auth()->id(),
                'status'          => 'draft',      // status logistik = DRAFT        // <== BELUM MASUK FLOW APPROVAL
                'subtotal'        => 0,
                'discount_total'  => 0,
                'grand_total'     => 0,
                'notes'           => null,
            ]);

            return redirect()
                ->route('po.edit', $po->id)
                ->with('success', 'PO baru berhasil dibuat, silakan isi item.');
        }


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

    protected function getCentralWarehouseId(): ?int
    {
        if (! Schema::hasTable('warehouses')) {
            return null;
        }

        $query = Warehouse::query();

        if (Schema::hasColumn('warehouses', 'is_central')) {
            $query->where('is_central', 1);
        } elseif (Schema::hasColumn('warehouses', 'warehouse_name')) {
            $query->where('warehouse_name', 'Central Stock');
        }

        $id = $query->value('id');

        if (! $id) {
            $id = Warehouse::orderBy('id')->value('id');
        }

        return $id ?: null;
    }

    protected function poIsLocked(PurchaseOrder $po): bool
    {
        // 1) Status logistik tertentu → locked
        if (in_array($po->status, ['ordered', 'completed', 'cancelled'], true)) {
            return true;
        }

        // 2) Sudah ada GR
        if (
            Schema::hasTable('restock_receipts') &&
            Schema::hasColumn('restock_receipts', 'purchase_order_id') &&
            DB::table('restock_receipts')
                ->where('purchase_order_id', $po->id)
                ->exists()
        ) {
            return true;
        }

        // 3) Sedang proses approval / sudah approved → form edit dikunci
        if (in_array($po->approval_status, ['waiting_procurement', 'waiting_ceo', 'approved'], true)) {
            return true;
        }

        // Draft / Rejected → boleh diedit
        return false;
    }




    // ====== SYNC PO → request_restocks ======
/**
 * Sinkron data PO (FROM REQUEST) ke tabel request_restocks:
 * - update qty & harga untuk item yang masih terhubung
 * - buat baris baru untuk item tambahan (request_id null)
 * - tambahkan note "Cancelled di PO" untuk request yang item-nya dihapus dari PO
 */
    protected function syncRequestsFromPo(
        PurchaseOrder $po,
        bool $wasFromRequest = false,
        array $oldRequestIds = []
    ): void {
        if (!$wasFromRequest) {
            // PO manual → nggak ada hubungan ke request_restocks
            return;
        }

        if (
            !Schema::hasTable('purchase_order_items') ||
            !Schema::hasColumn('purchase_order_items', 'product_id') ||
            !Schema::hasTable('request_restocks')
        ) {
            return;
        }

        $po->loadMissing('items');

        $poNote   = trim((string) $po->notes);
        $hasCode  = Schema::hasColumn('request_restocks', 'code');
        $hasNote  = Schema::hasColumn('request_restocks', 'note');
        $hasDate  = Schema::hasColumn('request_restocks', 'request_date');

        // — Ambil 1 baris RF lama sebagai template (code, requester, tanggal, dll)
        $baseHeader = null;
        if (!empty($oldRequestIds)) {
            $selectCols = ['id', 'warehouse_id', 'requested_by'];
            if ($hasCode) $selectCols[] = 'code';
            if ($hasDate) $selectCols[] = 'request_date';
            if ($hasNote) $selectCols[] = 'note';

            $baseHeader = DB::table('request_restocks')
                ->whereIn('id', $oldRequestIds)
                ->orderBy('id')
                ->first($selectCols);
        }

        // ---------------- 1) UPDATE ITEM YANG MASIH TERHUBUNG ----------------
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

                if ($poNote !== '' && $hasNote) {
                    $update['note'] = $poNote;
                }

                DB::table('request_restocks')
                    ->where('id', $row->request_id)
                    ->update($update);
            }
        }

        // ---------------- 2) BUAT REQUEST BARU UNTUK ITEM TAMBAHAN ----------------
        if (Schema::hasColumn('purchase_order_items', 'request_id')) {
            $extraItems = $po->items()
                ->whereNull('request_id')
                ->get(['id', 'product_id', 'warehouse_id', 'qty_ordered', 'unit_price']);

            if ($extraItems->isNotEmpty()) {
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
                        'warehouse_id'       => $baseHeader->warehouse_id ?? $warehouseId,
                        'requested_by'       => $baseHeader->requested_by ?? ($po->ordered_by ?: auth()->id()),
                        'quantity_requested' => $qty,
                        'quantity_received'  => 0,
                        'cost_per_item'      => $price,
                        'total_cost'         => $qty * $price,
                        'status'             => $rfStatus,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];

                    if ($hasCode && $baseHeader && !empty($baseHeader->code)) {
                        // pakai code dokumen RR yang sama → tetap 1 dokumen
                        $insert['code'] = $baseHeader->code;
                    }

                    if ($hasDate) {
                        $insert['request_date'] = $baseHeader->request_date ?? now()->toDateString();
                    }

                    if ($hasNote && $poNote !== '') {
                        $insert['note'] = $poNote;
                    }

                    $reqId = DB::table('request_restocks')->insertGetId($insert);

                    // kalau lu masih pengen auto generate code sendiri ketika awalnya nggak ada
                    if ($hasCode && (!$baseHeader || empty($baseHeader->code))) {
                        $code = 'RR-' . $reqId;
                        DB::table('request_restocks')
                            ->where('id', $reqId)
                            ->update([
                                'code'       => $code,
                                'updated_at' => now(),
                            ]);
                    }

                    // link balik ke PO item
                    DB::table('purchase_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'request_id' => $reqId,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        // ---------------- 3) NOTE "Cancelled di PO" UNTUK ITEM YANG DIHAPUS ----------------
        if (!empty($oldRequestIds) && $hasNote) {
            $currentReqIds = $po->items()
                ->whereNotNull('request_id')
                ->pluck('request_id')
                ->unique()
                ->all();

            $removedReqIds = array_diff($oldRequestIds, $currentReqIds);

            if (!empty($removedReqIds)) {
                $rows = DB::table('request_restocks')
                    ->whereIn('id', $removedReqIds)
                    ->get(['id', 'note']);

                $marker = 'Cancelled di PO';

                foreach ($rows as $rf) {
                    $note = trim((string)($rf->note ?? ''));

                    if ($note === '') {
                        $newNote = $marker;
                    } elseif (stripos($note, $marker) === false) {
                        $newNote = $note . ' | ' . $marker;
                    } else {
                        $newNote = $note; // sudah ada
                    }

                    DB::table('request_restocks')
                        ->where('id', $rf->id)
                        ->update([
                            'note'       => $newNote,
                            'updated_at' => now(),
                        ]);
                }
            }
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

    public function approve(Request $r, PurchaseOrder $po)
        {
            $user  = auth()->user();
            $roles = $user?->roles ?? collect();

            $isProcurement = $roles->contains('slug', 'procurement') || $roles->contains('slug', 'superadmin');
            $isCeo         = $roles->contains('slug', 'ceo') || $roles->contains('slug', 'superadmin');

            $total = (float) $po->grand_total;

            // Normalisasi kalau approval_status masih null
            if (! $po->approval_status) {
                $po->approval_status = 'waiting_procurement';
            }

            // Tahap 1: Procurement
            if ($po->approval_status === 'waiting_procurement') {
                if (! $isProcurement) {
                    abort(403, 'Anda tidak berhak meng-approve tahap Procurement.');
                }

                $po->approved_by_procurement = $user->id;
                $po->approved_at_procurement = now();

                // RULE:
                //  - total <= 1.000.000  → beres di Procurement
                //  - total  > 1.000.000  → lanjut ke CEO
                if ($total <= 1000000) {
                    $po->approval_status = 'approved';
                } else {
                    $po->approval_status = 'waiting_ceo';
                }

                $po->save();

                $msg = $po->approval_status === 'approved'
                    ? 'PO disetujui Procurement (final).'
                    : 'PO disetujui Procurement, menunggu approval CEO.';

                return back()->with('success', $msg);
            }

            // Tahap 2: CEO
            if ($po->approval_status === 'waiting_ceo') {
                if (! $isCeo) {
                    abort(403, 'Anda tidak berhak meng-approve tahap CEO.');
                }

                $po->approved_by_ceo = $user->id;
                $po->approved_at_ceo = now();
                $po->approval_status = 'approved';
                $po->save();

                return back()->with('success', 'PO disetujui CEO (final).');
            }

            return back()->with('info', 'PO ini sudah tidak dalam status menunggu approval.');
        }


    public function exportPdf(Request $request, PurchaseOrder $po)
    {
        // 1) Matikan debugbar biar nggak ikut ngumpulin output PDF
        if (class_exists(\Barryvdh\Debugbar\Facade::class)) {
            \Barryvdh\Debugbar\Facade::disable();
        }

        // 2) Naikin batas waktu buat proses PDF ini
        @set_time_limit(120);   // 120 detik, kalau mau bisa dinaikin lagi

        // 3) Pilih template: default / partner
        $tpl  = $request->query('tpl', 'default');
        $view = $tpl === 'partner'
            ? 'admin.po.print_partner'
            : 'admin.po.print';

        // 4) Ambil company default
        $company = Company::where('is_default', true)
            ->where('is_active', true)
            ->first();

        // 5) Eager load relasi yang dipakai di blade
        $po->load([
            'supplier',
            'items.product.supplier',
            'items.warehouse',
            'user',
            'procurementApprover',
            'ceoApprover',
        ]);

        $isDraft = $po->approval_status !== 'approved';

        // 6) Generate PDF
        $pdf = Pdf::loadView($view, [
            'po'      => $po,
            'company' => $company,
            'isDraft' => $isDraft,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('PO-'.$po->po_code.'.pdf');
    }

    public function exportExcel(PurchaseOrder $po)
    {
        return back()->with('info', 'Export Excel belum diaktifkan.');
    }
}
