<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        // daftar warehouse buat form
        $warehouses = Warehouse::orderBy('warehouse_name')->get();

        // summary header adjustment + detail items (untuk modal)
        $adjustments = StockAdjustment::with([
                'warehouse',
                'creator',
                'items.product',
            ])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(10);

        // log detail per item (kalau mau dipakai di bawah / tab lain)
        $itemLogs = StockAdjustmentItem::with([
                'product:id,product_code,name',
                'adjustment.warehouse:id,warehouse_name',
                'adjustment.creator:id,name',
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('admin.operations.adjustments', compact('warehouses', 'adjustments', 'itemLogs'));
    }

    /**
     * AJAX - load semua produk + stok saat ini sesuai warehouse/pusat.
     */
    public function ajaxProducts(Request $request)
    {
        $user           = auth()->user();
        $canAdjustPusat = empty($user->warehouse_id);

        $warehouseId = $request->get('warehouse_id');

        // kalau user pusat & tidak pilih warehouse => pusat
        $isPusatAdjust = $canAdjustPusat && empty($warehouseId);

        if ($isPusatAdjust) {
            $ownerType = 'pusat';
            $ownerId   = 0;
        } else {
            $ownerType = 'warehouse';
            $ownerId   = (int) $warehouseId;
        }

        // Ambil stok per produk dari stock_levels (kalau tabel ada)
        $stockMap = [];
        if (Schema::hasTable('stock_levels')) {
            $stockMap = DB::table('stock_levels')
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->pluck('quantity', 'product_id')
                ->toArray();
        }

        $products = Product::orderBy('name')
            ->get(['id', 'product_code', 'name', 'purchasing_price', 'selling_price']);

        $items = $products->map(function ($p) use ($stockMap) {
            return [
                'id'               => $p->id,
                'product_code'     => $p->product_code,
                'name'             => $p->name,
                'qty_before'       => (int) ($stockMap[$p->id] ?? 0),
                'purchasing_price' => (int) $p->purchasing_price,
                'selling_price'    => (int) $p->selling_price,
            ];
        })->values();

        return response()->json([
            'status' => 'ok',
            'items'  => $items,
        ]);
    }

    public function store(Request $request)
    {
        $user           = auth()->user();
        $canAdjustPusat = empty($user->warehouse_id);

        $rules = [
            'adj_date'           => 'required|date',
            'notes'              => 'nullable|string',
            'stock_scope_mode'   => 'required|in:single,all',
            'price_update_mode'  => 'required|in:none,update_purchase,update_selling,update_both',

            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty_after'  => 'required|integer|min:0',
            'items.*.notes'      => 'nullable|string',

            'items.*.purchasing_price' => 'nullable|integer|min:0',
            'items.*.selling_price'    => 'nullable|integer|min:0',
        ];

        // Gudang wajib pilih warehouse, pusat boleh kosong
        if ($canAdjustPusat) {
            $rules['warehouse_id'] = 'nullable|exists:warehouses,id';
        } else {
            $rules['warehouse_id'] = 'required|exists:warehouses,id';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $canAdjustPusat) {
            $adjDate = Carbon::parse($request->adj_date)->toDateString();

            // kalau user pusat & warehouse kosong → adjust PUSAT (stock central)
            $isPusatAdjust = $canAdjustPusat && empty($request->warehouse_id);

            // === ID untuk HEADER (FK ke warehouses)
            // pusat: NULL (Stock Central), gudang: id gudang yang dipilih
            $warehouseIdForHeader = $isPusatAdjust
                ? null
                : (int) $request->warehouse_id;

            // === ID untuk HITUNG STOK (stock_levels & stock_movements)
            // pusat: pakai 0 (owner_id=0), gudang: id gudang
            $locationIdForStock = $isPusatAdjust ? 0 : (int) $request->warehouse_id;

            // kode dokumen
            $nextNumber = (StockAdjustment::max('id') ?? 0) + 1;
            $adjCode    = 'ADJ-' . date('Ymd', strtotime($adjDate)) . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $priceMode = $request->price_update_mode;

            /** @var StockAdjustment $adj */
            $adj = StockAdjustment::create([
                'adj_code'          => $adjCode,
                'stock_scope_mode'  => $request->stock_scope_mode,
                'price_update_mode' => $priceMode,
                'warehouse_id'      => $warehouseIdForHeader, // <— bisa NULL sekarang
                'adj_date'          => $adjDate,
                'notes'             => $request->notes,
                'created_by'        => Auth::id(),
            ]);

            foreach ($request->items as $row) {
                $productId = (int) $row['product_id'];
                $qtyAfter  = (int) $row['qty_after'];

                $qtyBefore = $this->getCurrentStock($locationIdForStock, $productId, $isPusatAdjust);
                $qtyDiff   = $qtyAfter - $qtyBefore;

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adj->id,
                    'product_id'          => $productId,
                    'qty_before'          => $qtyBefore,
                    'qty_after'           => $qtyAfter,
                    'qty_diff'            => $qtyDiff,
                    'notes'               => $row['notes'] ?? null,
                ]);

                // update stock_levels (pusat vs gudang)
                $this->updateStockLevel($locationIdForStock, $productId, $qtyAfter, $isPusatAdjust);

                // log ke stock_movements
                $this->insertStockMovement($locationIdForStock, $productId, $qtyDiff, $adj, $isPusatAdjust);

                // ==== UPDATE HARGA PRODUK (kalau mode harga != none) ====
                if ($priceMode !== 'none') {
                    $purchPrice = isset($row['purchasing_price']) && $row['purchasing_price'] !== ''
                        ? (int) $row['purchasing_price']
                        : null;

                    $sellPrice  = isset($row['selling_price']) && $row['selling_price'] !== ''
                        ? (int) $row['selling_price']
                        : null;

                    if (!is_null($purchPrice) || !is_null($sellPrice)) {
                        $product = Product::find($productId);
                        if ($product) {
                            if (in_array($priceMode, ['update_purchase', 'update_both'], true) && !is_null($purchPrice)) {
                                $product->purchasing_price = $purchPrice;
                            }
                            if (in_array($priceMode, ['update_selling', 'update_both'], true) && !is_null($sellPrice)) {
                                $product->selling_price = $sellPrice;
                            }
                            $product->save();
                        }
                    }
                }
            }
        });

        return redirect()
            ->route('stock-adjustments.index')
            ->with('success', 'Stock adjustment berhasil disimpan & stok / harga sudah diperbarui.');
    }

    /** Ambil stok dari stock_levels (pusat vs warehouse) */
    protected function getCurrentStock(int $locationId, int $productId, bool $isPusat): int
    {
        if (! Schema::hasTable('stock_levels')) {
            return 0;
        }

        $ownerType = $isPusat ? 'pusat' : 'warehouse';
        $ownerId   = $isPusat ? 0       : $locationId;

        $row = DB::table('stock_levels')
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('product_id', $productId)
            ->first();

        return (int) ($row->quantity ?? 0);
    }

    /** Update / insert stok ke stock_levels */
    protected function updateStockLevel(int $locationId, int $productId, int $qtyAfter, bool $isPusat): void
    {
        if (! Schema::hasTable('stock_levels')) {
            return;
        }

        $ownerType = $isPusat ? 'pusat' : 'warehouse';
        $ownerId   = $isPusat ? 0       : $locationId;

        $where = [
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
            'product_id' => $productId,
        ];

        DB::table('stock_levels')->updateOrInsert(
            $where,
            [
                'quantity'   => $qtyAfter,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /** Log pergerakan stok ke stock_movements, fleksibel ngikutin kolom yang ada */
    protected function insertStockMovement(
        int $locationId,
        int $productId,
        int $qtyDiff,
        StockAdjustment $adj,
        bool $isPusat
    ): void {
        if ($qtyDiff === 0) {
            return;
        }

        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        $type = $qtyDiff > 0 ? 'adjustment_plus' : 'adjustment_minus';

        $cols = Schema::getColumnListing('stock_movements');
        $data = [];

        if (in_array('product_id', $cols, true)) {
            $data['product_id'] = $productId;
        }

        if (in_array('warehouse_id', $cols, true)) {
            $data['warehouse_id'] = $locationId;
        }

        if (in_array('owner_type', $cols, true)) {
            $data['owner_type'] = $isPusat ? 'pusat' : 'warehouse';
        }
        if (in_array('owner_id', $cols, true)) {
            $data['owner_id'] = $isPusat ? 0 : $locationId;
        }

        if (in_array('movement_date', $cols, true)) {
            $data['movement_date'] = $adj->adj_date;
        } elseif (in_array('date', $cols, true)) {
            $data['date'] = $adj->adj_date;
        }

        $qtyAbs = abs($qtyDiff);
        if (in_array('qty', $cols, true)) {
            $data['qty'] = $qtyAbs;
        } elseif (in_array('quantity', $cols, true)) {
            $data['quantity'] = $qtyAbs;
        }

        if (in_array('type', $cols, true)) {
            $data['type'] = $type;
        }
        if (in_array('ref', $cols, true)) {
            $data['ref'] = $adj->adj_code;
        }

        $now = now();
        if (in_array('created_at', $cols, true)) {
            $data['created_at'] = $now;
        }
        if (in_array('updated_at', $cols, true)) {
            $data['updated_at'] = $now;
        }

        if (empty($data)) {
            return;
        }

        DB::table('stock_movements')->insert($data);
    }
}
