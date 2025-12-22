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
use App\Models\Company;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

use App\Exports\StockAdjustments\StockAdjustmentIndexWithItemsExport;


class StockAdjustmentController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderBy('warehouse_name')->get(['id','warehouse_name']);

        return view('admin.operations.adjustments', compact('warehouses'));
    }

    /** List produk untuk dropdown (single mode) */
    public function products()
    {
        $rows = Product::orderBy('name')
            ->get(['id','product_code','name','purchasing_price','selling_price']);

        return response()->json([
            'status' => 'ok',
            'items'  => $rows,
        ]);
    }

    /** DataTable server-side untuk riwayat dokumen */
    public function datatable(Request $r)
    {
        $draw   = (int) $r->input('draw', 1);
        $start  = (int) $r->input('start', 0);
        $length = (int) $r->input('length', 10);

        $orderCol = (int) $r->input('order.0.column', 0);
        $orderDir = $r->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        // filter eksternal
        $q         = trim((string) $r->input('q', ''));
        $whFilter  = (string) $r->input('warehouse_id', '');
        $dateFrom  = $r->input('date_from');
        $dateTo    = $r->input('date_to');

        // global search dari datatables (kalau user pakai search bawaan)
        $dtSearch = trim((string) $r->input('search.value', ''));
        $search   = $dtSearch !== '' ? $dtSearch : $q;

        // subquery count items
        $itemsCountSub = DB::table('stock_adjustment_items')
            ->selectRaw('stock_adjustment_id, COUNT(*) as items_count')
            ->groupBy('stock_adjustment_id');

        $base = DB::table('stock_adjustments as sa')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sa.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'sa.created_by')
            ->leftJoinSub($itemsCountSub, 'ic', function ($j) {
                $j->on('ic.stock_adjustment_id', '=', 'sa.id');
            })
            ->selectRaw('
                sa.id, sa.adj_code, sa.adj_date, sa.created_at,
                sa.warehouse_id,
                w.warehouse_name,
                u.name as creator_name,
                COALESCE(ic.items_count,0) as items_count
            ');

        // filter warehouse
        if ($whFilter === 'central') {
            $base->whereNull('sa.warehouse_id');
        } elseif ($whFilter !== '' && ctype_digit($whFilter)) {
            $base->where('sa.warehouse_id', (int) $whFilter);
        }

        // filter tanggal dokumen (adj_date)
        if (!empty($dateFrom)) $base->whereDate('sa.adj_date', '>=', $dateFrom);
        if (!empty($dateTo))   $base->whereDate('sa.adj_date', '<=', $dateTo);

        // search
        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function ($w) use ($like) {
                $w->where('sa.adj_code', 'like', $like)
                  ->orWhere('w.warehouse_name', 'like', $like)
                  ->orWhere('u.name', 'like', $like);
            });
        }

        $recordsTotal = DB::table('stock_adjustments')->count();
        $recordsFiltered = (clone $base)->count();

        // map order
        $orderMap = [
            0 => 'sa.id',
            1 => 'sa.adj_code',
            2 => 'sa.adj_date',
            3 => 'w.warehouse_name',
            4 => 'items_count',
            5 => 'creator_name',
            6 => 'sa.created_at',
        ];
        $orderBy = $orderMap[$orderCol] ?? 'sa.id';

        $rows = (clone $base)
            ->orderBy($orderBy, $orderDir)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($row) {
                $warehouseLabel = $row->warehouse_id ? ($row->warehouse_name ?? '-') : 'Stock Central';

                return [
                    'id'          => (int) $row->id,
                    'adj_code'    => $row->adj_code,
                    'adj_date'    => Carbon::parse($row->adj_date)->format('d/m/Y'),
                    'warehouse'   => $warehouseLabel,
                    'items_count' => (int) $row->items_count,
                    'creator'     => $row->creator_name ?? '-',
                    'created_at'  => Carbon::parse($row->created_at)->format('H:i'),
                    'action'      => '<button type="button" class="btn btn-sm btn-outline-primary btn-detail" data-id="'.$row->id.'">
                                        <i class="bx bx-search-alt"></i> Detail
                                      </button>',
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    /** Detail dokumen untuk modal (AJAX) */
    public function detail(StockAdjustment $adjustment)
    {
        $adjustment->load(['warehouse:id,warehouse_name', 'creator:id,name', 'items.product:id,product_code,name']);

        $mode = $adjustment->price_update_mode;

        $header = [
            'adj_code'     => $adjustment->adj_code,
            'adj_date'     => optional($adjustment->adj_date)->format('d/m/Y'),
            'warehouse'    => $adjustment->warehouse_id ? ($adjustment->warehouse->warehouse_name ?? '-') : 'Stock Central',
            'creator'      => $adjustment->creator->name ?? '-',
            'created_at'   => optional($adjustment->created_at)->format('d/m/Y H:i'),
            'notes'        => $adjustment->notes ?: '-',
            'mode'         => $mode,
            'items_count'  => $adjustment->items->count(),
        ];

        $items = $adjustment->items->map(function (StockAdjustmentItem $it) {
            return [
                'product'      => $it->product?->name ?? '-',
                'product_code' => $it->product?->product_code ?? '',
                'qty_before'   => (int) $it->qty_before,
                'qty_after'    => (int) $it->qty_after,
                'qty_diff'     => (int) $it->qty_diff,
                'pb'           => $it->purchase_price_before,
                'pa'           => $it->purchase_price_after,
                'sb'           => $it->selling_price_before,
                'sa'           => $it->selling_price_after,
                'notes'        => $it->notes ?: '-',
            ];
        });

        return response()->json([
            'status' => 'ok',
            'header' => $header,
            'items'  => $items,
        ]);
    }

    public function store(Request $request)
    {
        $user           = auth()->user();
        $canAdjustPusat = empty($user->warehouse_id);

        $rules = [
            'adj_date'          => 'required|date',
            'notes'             => 'nullable|string',
            'stock_scope_mode'  => 'required|in:single,all',
            'price_update_mode' => 'required|in:stock,purchase,selling,purchase_selling,stock_purchase_selling',
            'warehouse_id'      => $canAdjustPusat ? 'nullable|exists:warehouses,id' : 'required|exists:warehouses,id',

            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty_after'  => 'nullable|integer|min:0',
            'items.*.notes'      => 'nullable|string',
            'items.*.purchasing_price' => 'nullable|integer|min:0',
            'items.*.selling_price'    => 'nullable|integer|min:0',
        ];

        $data = $request->validate($rules);

        try {
            $adj = DB::transaction(function () use ($request, $data, $canAdjustPusat) {
                $adjDate = Carbon::parse($data['adj_date'])->toDateString();

                $isPusatAdjust = $canAdjustPusat && empty($data['warehouse_id']);

                $warehouseIdForHeader = $isPusatAdjust ? null : (int) $data['warehouse_id'];
                $locationIdForStock   = $isPusatAdjust ? 0    : (int) $data['warehouse_id'];

                // === generate kode aman (lock last row by prefix)
                $prefix = 'ADJ-' . date('Ymd', strtotime($adjDate)) . '-';
                $last = StockAdjustment::where('adj_code', 'like', $prefix.'%')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $lastNum = 0;
                if ($last && is_string($last->adj_code)) {
                    $lastNum = (int) substr($last->adj_code, -4);
                }
                $adjCode = $prefix . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);

                $mode = $data['price_update_mode'];

                $updateStock    = in_array($mode, ['stock', 'stock_purchase_selling'], true);
                $updatePurchase = in_array($mode, ['purchase', 'purchase_selling', 'stock_purchase_selling'], true);
                $updateSelling  = in_array($mode, ['selling', 'purchase_selling', 'stock_purchase_selling'], true);

                $adj = StockAdjustment::create([
                    'adj_code'          => $adjCode,
                    'stock_scope_mode'  => $data['stock_scope_mode'],
                    'price_update_mode' => $mode,
                    'warehouse_id'      => $warehouseIdForHeader,
                    'adj_date'          => $adjDate,
                    'notes'             => $data['notes'] ?? null,
                    'created_by'        => Auth::id(),
                ]);

                foreach ($data['items'] as $row) {
                    $productId = (int) $row['product_id'];
                    $product   = Product::find($productId);

                    $qtyBefore = $this->getCurrentStock($locationIdForStock, $productId, $isPusatAdjust);
                    $qtyAfter  = $updateStock ? (int) ($row['qty_after'] ?? 0) : $qtyBefore;
                    $qtyDiff   = $qtyAfter - $qtyBefore;

                    $purchaseBefore = $product ? (int) $product->purchasing_price : null;
                    $sellingBefore  = $product ? (int) $product->selling_price    : null;

                    $purchaseAfter  = $purchaseBefore;
                    $sellingAfter   = $sellingBefore;

                    if ($updatePurchase && array_key_exists('purchasing_price', $row) && $row['purchasing_price'] !== '') {
                        $purchaseAfter = (int) $row['purchasing_price'];
                    }
                    if ($updateSelling && array_key_exists('selling_price', $row) && $row['selling_price'] !== '') {
                        $sellingAfter = (int) $row['selling_price'];
                    }

                    // skip baris "kosong" biar mode ALL gak nyimpen ribuan item yang gak berubah
                    $notesItem = trim((string)($row['notes'] ?? ''));
                    $priceChanged = ($purchaseAfter !== $purchaseBefore) || ($sellingAfter !== $sellingBefore);
                    $stockChanged = ($qtyAfter !== $qtyBefore);

                    if (! $stockChanged && ! $priceChanged && $notesItem === '') {
                        continue;
                    }

                    StockAdjustmentItem::create([
                        'stock_adjustment_id'   => $adj->id,
                        'product_id'            => $productId,
                        'qty_before'            => $qtyBefore,
                        'qty_after'             => $qtyAfter,
                        'qty_diff'              => $qtyDiff,
                        'purchase_price_before' => $purchaseBefore,
                        'purchase_price_after'  => $purchaseAfter,
                        'selling_price_before'  => $sellingBefore,
                        'selling_price_after'   => $sellingAfter,
                        'notes'                 => $notesItem !== '' ? $notesItem : null,
                    ]);

                    if ($updateStock) {
                        $this->updateStockLevel($locationIdForStock, $productId, $qtyAfter, $isPusatAdjust);
                        $this->insertStockMovement($locationIdForStock, $productId, $qtyDiff, $adj, $isPusatAdjust);
                    }

                    if ($product) {
                        $updated = false;
                        if ($updatePurchase && !is_null($purchaseAfter) && $purchaseAfter !== $purchaseBefore) {
                            $product->purchasing_price = $purchaseAfter;
                            $updated = true;
                        }
                        if ($updateSelling && !is_null($sellingAfter) && $sellingAfter !== $sellingBefore) {
                            $product->selling_price = $sellingAfter;
                            $updated = true;
                        }
                        if ($updated) $product->save();
                    }
                }

                return $adj;
            });

            // AJAX response
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status'  => 'ok',
                    'message' => 'Stock adjustment berhasil disimpan.',
                    'id'      => $adj->id,
                ]);
            }

            return redirect()->route('stock-adjustments.index')->with('success', 'Stock adjustment berhasil disimpan.');
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gagal menyimpan: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->route('stock-adjustments.index')->with('error', 'Gagal menyimpan: '.$e->getMessage());
        }
    }

    // ===== helper stock lu yang lama tetap pakai =====
    protected function getCurrentStock(int $locationId, int $productId, bool $isPusat): int
    {
        if (! Schema::hasTable('stock_levels')) return 0;

        $ownerType = $isPusat ? 'pusat' : 'warehouse';
        $ownerId   = $isPusat ? 0       : $locationId;

        $row = DB::table('stock_levels')
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('product_id', $productId)
            ->first();

        return (int) ($row->quantity ?? 0);
    }

    protected function updateStockLevel(int $locationId, int $productId, int $qtyAfter, bool $isPusat): void
    {
        if (! Schema::hasTable('stock_levels')) return;

        $ownerType = $isPusat ? 'pusat' : 'warehouse';
        $ownerId   = $isPusat ? 0       : $locationId;

        DB::table('stock_levels')->updateOrInsert(
            ['owner_type'=>$ownerType,'owner_id'=>$ownerId,'product_id'=>$productId],
            ['quantity'=>$qtyAfter,'updated_at'=>now(),'created_at'=>now()]
        );
    }

    protected function insertStockMovement(int $locationId, int $productId, int $qtyDiff, StockAdjustment $adj, bool $isPusat): void
    {
        if ($qtyDiff === 0) return;
        if (! Schema::hasTable('stock_movements')) return;

        $type = $qtyDiff > 0 ? 'adjustment_plus' : 'adjustment_minus';
        $cols = Schema::getColumnListing('stock_movements');

        $data = [];
        if (in_array('product_id',$cols,true))  $data['product_id']=$productId;
        if (in_array('warehouse_id',$cols,true))$data['warehouse_id']=$locationId;
        if (in_array('owner_type',$cols,true))  $data['owner_type']=$isPusat?'pusat':'warehouse';
        if (in_array('owner_id',$cols,true))    $data['owner_id']=$isPusat?0:$locationId;

        if (in_array('movement_date',$cols,true)) $data['movement_date']=$adj->adj_date;
        elseif (in_array('date',$cols,true))      $data['date']=$adj->adj_date;

        $qtyAbs = abs($qtyDiff);
        if (in_array('qty',$cols,true))       $data['qty']=$qtyAbs;
        elseif (in_array('quantity',$cols,true)) $data['quantity']=$qtyAbs;

        if (in_array('type',$cols,true)) $data['type']=$type;
        if (in_array('ref',$cols,true))  $data['ref']=$adj->adj_code;

        $now = now();
        if (in_array('created_at',$cols,true)) $data['created_at']=$now;
        if (in_array('updated_at',$cols,true)) $data['updated_at']=$now;

        if (!empty($data)) DB::table('stock_movements')->insert($data);
    }

    // ajaxProducts lu yang lama tetap dipakai untuk mode ALL
    public function ajaxProducts(Request $request)
    {
        $user           = auth()->user();
        $canAdjustPusat = empty($user->warehouse_id);

        $warehouseId = $request->get('warehouse_id');
        $isPusatAdjust = $canAdjustPusat && empty($warehouseId);

        $ownerType = $isPusatAdjust ? 'pusat' : 'warehouse';
        $ownerId   = $isPusatAdjust ? 0 : (int) $warehouseId;

        $stockMap = [];
        if (Schema::hasTable('stock_levels')) {
            $stockMap = DB::table('stock_levels')
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->pluck('quantity', 'product_id')
                ->toArray();
        }

        $products = Product::orderBy('name')
            ->get(['id','product_code','name','purchasing_price','selling_price']);

        $items = $products->map(function (Product $p) use ($stockMap) {
            return [
                'id'           => $p->id,
                'product_code' => $p->product_code,
                'name'         => $p->name,
                'qty_before'   => (int) ($stockMap[$p->id] ?? 0),
            ];
        })->values();

        return response()->json(['status'=>'ok','items'=>$items]);
    }

    public function exportIndexExcel(Request $request)
    {
        // ✅ range opsional (kalau kosong -> export ALL)
        [$from, $to, $key, $useDate] = $this->parseExportRangeOptional($request);

        $q          = trim((string) $request->input('q', ''));
        $warehouseId= (string) $request->input('warehouse_id', '');

        // kolom tanggal dokumen adjustment (fallback ke created_at kalau ga ada)
        $dateCol = Schema::hasColumn('stock_adjustments', 'adj_date') ? 'adj_date' : 'created_at';

        $query = StockAdjustment::query()
            ->with([
                'warehouse:id,warehouse_name',
                'creator:id,name',
                'items.product:id,product_code,name',
            ])
            ->withCount('items')
            ->orderByDesc('id');

        // search (global)
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('adj_code', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%")
                    ->orWhereHas('creator', function ($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('warehouse', function ($w) use ($q) {
                        $w->where('warehouse_name', 'like', "%{$q}%");
                    });
            });
        }

        // filter warehouse
        if ($warehouseId !== '') {
            if ($warehouseId === 'central') {
                $query->whereNull('warehouse_id');
            } else {
                $query->where('warehouse_id', (int) $warehouseId);
            }
        }

        // ✅ tanggal cuma diterapkan kalau user isi range/bulan
        if ($useDate) {
            if ($dateCol === 'created_at') {
                $query->whereBetween('created_at', [
                    $from->copy()->startOfDay(),
                    $to->copy()->endOfDay(),
                ]);
            } else {
                $query->whereBetween($dateCol, [
                    $from->toDateString(),
                    $to->toDateString(),
                ]);
            }
        }

        $adjs = $query->get();

        // ✅ company default aktif (buat kop)
        $company = Company::where('is_default', true)
            ->where('is_active', true)
            ->first();

        $meta = [
            'filters' => $request->query(),
        ];

        $filename = "STOCK-ADJUSTMENT-INDEX-DETAIL-{$key}.xlsx";

        return Excel::download(
            new StockAdjustmentIndexWithItemsExport($adjs, $meta, $dateCol, $company),
            $filename
        );
    }

    private function parseExportRangeOptional(Request $request): array
    {
        $month = $request->input('month'); // optional

        // support 2 gaya param: from/to ATAU date_from/date_to (datatable)
        $from  = $request->input('from') ?? $request->input('date_from');
        $to    = $request->input('to')   ?? $request->input('date_to');

        // ✅ kalau tidak ada range/bulan -> export ALL
        if (!$from && !$to && !$month) {
            return [null, null, 'ALL', false];
        }

        // kalau cuma salah satu, samain biar valid
        if ($from && !$to) $to = $from;
        if ($to && !$from) $from = $to;

        if ($from && $to) {
            $fromC = Carbon::parse($from)->startOfDay();
            $toC   = Carbon::parse($to)->endOfDay();
        } else {
            $fromC = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $toC   = $fromC->copy()->endOfMonth();
        }

        if ($fromC->gt($toC)) {
            throw ValidationException::withMessages([
                'to' => 'Tanggal "to" harus >= "from".',
            ]);
        }

        // optional: jaga maksimal 1 bulan (biar konsisten sama PO)
        if ($fromC->format('Y-m') !== $toC->format('Y-m')) {
            throw ValidationException::withMessages([
                'to' => 'Range maksimal 1 bulan. "from" dan "to" harus di bulan yang sama.',
            ]);
        }

        return [$fromC, $toC, $fromC->format('Y-m'), true];
    }

}
