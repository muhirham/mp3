<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\Warehouse;


class StockLevelController extends Controller
{
    /** Halaman Stock Gudang */
    public function index(Request $r)
    {
        $me = auth()->user();

        // role pusat yang boleh lihat banyak gudang
        // pake hasRole() aja, dia bisa nerima array
        $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
        $isWarehouseUser    = $me->hasRole('warehouse');

        $warehouses          = collect();
        $selectedWarehouseId = null;

        if ($canSwitchWarehouse) {
            // superadmin/admin: bisa pilih gudang apa aja
            $warehouses = Warehouse::orderBy('warehouse_name')
                ->get(['id', 'warehouse_name']);

            $selectedWarehouseId = $r->integer('warehouse_id') ?: null;
        } elseif ($isWarehouseUser) {
            // admin WH: terkunci ke gudangnya sendiri
            $selectedWarehouseId = $me->warehouse_id;
        }

        return view('wh.stockGudang', compact(
            'me',
            'warehouses',
            'selectedWarehouseId',
            'canSwitchWarehouse',
            'isWarehouseUser'
        ));
    }

    /** DataTables server-side */
    public function datatable(Request $r)
    {
        try {
            if (!Schema::hasTable('stock_levels')) {
                return response()->json([
                    'draw'            => (int) $r->input('draw', 1),
                    'recordsTotal'    => 0,
                    'recordsFiltered' => 0,
                    'data'            => [],
                    'error'           => 'Tabel stock_levels belum ada.',
                ]);
            }

            $me = auth()->user();

            $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
            $isWarehouseUser    = $me->hasRole('warehouse');

            // ==== Tentukan warehouse_id yang dipakai ====
            if ($isWarehouseUser) {
                // admin WH: pakai warehouse dia sendiri, nggak bisa diganti
                $warehouseId = $me->warehouse_id;
            } else {
                // superadmin/admin pusat: boleh kirim filter warehouse_id dari UI
                $warehouseId = $r->integer('warehouse_id') ?: null;
            }

            // Master tables
            $hasProducts   = Schema::hasTable('products');
            $hasPackages   = Schema::hasTable('packages');
            $hasCategories = Schema::hasTable('categories');
            $hasSuppliers  = Schema::hasTable('suppliers');

            if (!$hasProducts) {
                return response()->json([
                    'draw'            => (int) $r->input('draw', 1),
                    'recordsTotal'    => 0,
                    'recordsFiltered' => 0,
                    'data'            => [],
                    'error'           => 'Tabel products tidak tersedia.',
                ]);
            }

            // Nama kolom fleksibel
            $pkgNameCol = $hasPackages
                ? (Schema::hasColumn('packages', 'name')
                    ? 'name'
                    : (Schema::hasColumn('packages', 'package_name') ? 'package_name' : null))
                : null;

            $catNameCol = $hasCategories
                ? (Schema::hasColumn('categories', 'category_name')
                    ? 'category_name'
                    : (Schema::hasColumn('categories', 'name') ? 'name' : null))
                : null;

            // ==== Param dari DataTables ====
            $draw        = (int) $r->input('draw', 1);
            $start       = (int) $r->input('start', 0);
            $length      = (int) $r->input('length', 10);
            $orderColIdx = (int) $r->input('order.0.column', 1);
            $orderDir    = $r->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $search      = trim((string) $r->input('search.value', ''));

            // ==== Agregasi stok per product di warehouse yang dipilih ====
            $stockSub = DB::table('stock_levels as sl')
                ->selectRaw('sl.product_id, SUM(sl.quantity) as quantity')
                ->where('sl.owner_type', 'warehouse')
                ->when($warehouseId, fn($q) => $q->where('sl.owner_id', $warehouseId))
                ->groupBy('sl.product_id');

            // Base query
            $base = DB::table('products')
                ->joinSub($stockSub, 'st', 'st.product_id', '=', 'products.id');

            if ($hasPackages) {
                $base->leftJoin('packages as pk', 'pk.id', '=', 'products.package_id');
            }
            if ($hasCategories) {
                $base->leftJoin('categories as c', 'c.id', '=', 'products.category_id');
            }
            if ($hasSuppliers) {
                $base->leftJoin('suppliers as s', 's.id', '=', 'products.supplier_id');
            }

            $base->select([
                DB::raw('products.id as product_id'),
                DB::raw('products.product_code'),
                DB::raw('products.name as product_name'),

                DB::raw('COALESCE(products.standard_cost,0) as standard_cost'),
                DB::raw("COALESCE(products.product_type,'normal') as product_type"),

                $pkgNameCol
                    ? DB::raw("COALESCE(pk.$pkgNameCol, '-') as package_name")
                    : DB::raw("'-' as package_name"),

                $catNameCol
                    ? DB::raw("COALESCE(c.$catNameCol, '-') as category_name")
                    : DB::raw("'-' as category_name"),

                $hasSuppliers
                    ? DB::raw("COALESCE(s.name, '-') as supplier_name")
                    : DB::raw("'-' as supplier_name"),

                DB::raw('COALESCE(st.quantity,0) as quantity'),
                DB::raw('COALESCE(products.stock_minimum,0) as stock_minimum'),
                DB::raw('COALESCE(products.selling_price,0) as selling_price'),
            ]);
            // ==== Search ====
            if ($search !== '') {
                $like = "%{$search}%";
                $base->where(function ($q) use ($like, $pkgNameCol, $catNameCol, $hasSuppliers) {
                    $q->where('products.product_code', 'like', $like)
                      ->orWhere('products.name', 'like', $like);

                    if ($pkgNameCol) {
                        $q->orWhere("pk.$pkgNameCol", 'like', $like);
                    }
                    if ($catNameCol) {
                        $q->orWhere("c.$catNameCol", 'like', $like);
                    }
                    if ($hasSuppliers) {
                        $q->orWhere('s.name', 'like', $like);
                    }
                });
            }

            // ==== Total & filtered ====
            $totalBase = DB::table('stock_levels as sl')
                ->join('products', 'products.id', '=', 'sl.product_id')
                ->where('sl.owner_type', 'warehouse')
                ->when($warehouseId, fn($q) => $q->where('sl.owner_id', $warehouseId))
                ->groupBy('sl.product_id');

            $recordsTotal    = $totalBase->count();
            $recordsFiltered = (clone $base)->count();

            // ==== Ordering ====
                $orderMap = [
                1 => 'products.product_code',
                2 => 'product_name',
                3 => 'product_type',
                4 => 'package_name',
                5 => 'category_name',
                6 => 'supplier_name',
                7 => 'quantity',
                8 => 'stock_minimum',
                10 => 'standard_cost',
                11 => 'selling_price',
            ];

            $orderCol = $orderMap[$orderColIdx] ?? 'products.product_code';
            $base->orderBy($orderCol, $orderDir);

            // ==== Paging + render ====
                $rows = $base->skip($start)->take($length)->get()
                    ->map(function ($r, $idx) use ($start) {

                        $qty = (int) ($r->quantity ?? 0);
                        $min = (int) ($r->stock_minimum ?? 0);

                        if ($qty <= 0) {
                            $badge = '<span class="badge bg-label-danger">OUT</span>';
                        } elseif ($min > 0 && $qty <= $min) {
                            $badge = '<span class="badge bg-label-warning">LOW</span>';
                        } else {
                            $badge = '<span class="badge bg-label-success">OK</span>';
                        }

                        $selling = (float) ($r->selling_price ?? 0);

                        return [
                            'rownum'        => $start + $idx + 1,
                            'product_code'  => e($r->product_code),
                            'product_name'  => e($r->product_name),
                            'product_type'  => ucfirst($r->product_type),
                            'package_name'  => e($r->package_name ?? '-'),
                            'category_name' => e($r->category_name ?? '-'),
                            'supplier_name' => e($r->supplier_name ?? '-'),
                            'quantity'      => number_format($qty, 0, ',', '.'),
                            'stock_minimum' => number_format($min, 0, ',', '.'),
                            'status'        => $badge,
                            'hpp'           => 'Rp' . number_format((float)$r->standard_cost, 0, ',', '.'),
                            'selling_price' => 'Rp' . number_format($selling, 0, ',', '.'),
                        ];
                    });


            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data'            => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('StockLevel.datatable error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'draw'            => (int) $r->input('draw', 1),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Server error: ' . $e->getMessage(),
            ]);
        }
    }
}
