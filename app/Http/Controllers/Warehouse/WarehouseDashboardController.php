<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;

class WarehouseDashboardController extends Controller
{
    public function index()
    {
        $me = auth()->user();
        abort_unless($me, 403);

        $whId    = $me->warehouse_id;
        $whName  = $me->warehouse?->warehouse_name ?? 'Warehouse';
        $greeting = 'Selamat datang, Wh ' . ($me->name ?? 'User');

        // ===== Label 7 hari (Y-m-d)
        $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('Y-m-d');
        }

        // ===== STOCK SUMMARY (mengacu ke stock_levels)
        $productsTotal = 0;
        $lowStockCount = 0;
        $lowStocks     = [];

        $hasSL = Schema::hasTable('stock_levels');
        $slQtyCol = null;
        if ($hasSL) {
            $slQtyCol = Schema::hasColumn('stock_levels','quantity')
                ? 'quantity'
                : (Schema::hasColumn('stock_levels','qty') ? 'qty' : null);
        }

        if ($hasSL && $slQtyCol) {
            // subquery stok per product untuk warehouse user
            $stockSub = DB::table('stock_levels as sl')
                ->selectRaw("sl.product_id, SUM(sl.$slQtyCol) AS current_stock");

            if (Schema::hasColumn('stock_levels','warehouse_id') && $whId) {
                $stockSub->where('sl.warehouse_id', $whId);
            } elseif (
                Schema::hasColumn('stock_levels','owner_type') &&
                Schema::hasColumn('stock_levels','owner_id') && $whId
            ) {
                $stockSub->where('sl.owner_type','warehouse')
                         ->where('sl.owner_id', $whId);
            }

            $stockSub->groupBy('sl.product_id');

            // total product yang memang punya stok di gudang ini
            $productsTotal = (int) DB::table('products as p')
                ->leftJoinSub($stockSub, 'st', 'st.product_id', '=', 'p.id')
                ->whereNotNull('st.product_id')
                ->distinct('p.id')->count('p.id');

            // daftar low stock (stok <= minimum)
            $lowRows = DB::table('products as p')
                ->leftJoinSub($stockSub, 'st', 'st.product_id', '=', 'p.id')
                ->leftJoin('packages as pk','pk.id','=','p.package_id')
                ->selectRaw("
                    p.product_code as code,
                    p.name,
                    COALESCE(pk.package_name,'-') as package,
                    COALESCE(p.stock_minimum,0) as min_stock,
                    COALESCE(st.current_stock,0) as current_stock
                ")
                ->whereRaw('COALESCE(st.current_stock,0) <= COALESCE(p.stock_minimum,0)')
                ->orderByRaw('(COALESCE(p.stock_minimum,0) - COALESCE(st.current_stock,0)) DESC')
                ->limit(10)
                ->get();

            $lowStockCount = $lowRows->count();
            $lowStocks = $lowRows->map(fn($r) => [
                'code'    => $r->code,
                'name'    => $r->name,
                'package' => $r->package,
                'min'     => (int) $r->min_stock,
                'current' => (int) $r->current_stock,
            ])->toArray();
        } else {
            // fallback: hitung semua produk saja
            $productsTotal = Product::count();
        }

        // ===== IN/OUT 7 hari (stock_movements / inventory_movements)
        $todayIn = 0; $todayOut = 0;
        $seriesIn = array_fill(0, 7, 0);
        $seriesOut = array_fill(0, 7, 0);

        $movTable = Schema::hasTable('stock_movements') ? 'stock_movements'
                   : (Schema::hasTable('inventory_movements') ? 'inventory_movements' : null);

        if ($movTable && Schema::hasColumn($movTable,'created_at')) {
            $qtyCol  = Schema::hasColumn($movTable,'quantity') ? 'quantity'
                     : (Schema::hasColumn($movTable,'qty') ? 'qty' : null);

            $typeCol = null;
            foreach (['type','movement_type','direction'] as $c) {
                if (Schema::hasColumn($movTable,$c)) { $typeCol = $c; break; }
            }

            $q = DB::table($movTable)->selectRaw("
                    DATE(created_at) as d,
                    SUM(CASE ".($typeCol ? "WHEN LOWER($typeCol) IN ('in','inbound','masuk')" : "WHEN $qtyCol > 0")." THEN ABS($qtyCol) ELSE 0 END) as inbound,
                    SUM(CASE ".($typeCol ? "WHEN LOWER($typeCol) IN ('out','outbound','keluar')" : "WHEN $qtyCol < 0")." THEN ABS($qtyCol) ELSE 0 END) as outbound
                ")
                ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
                ->groupBy('d')->orderBy('d');

            // filter per warehouse
            if ($qtyCol) {
                if (Schema::hasColumn($movTable,'warehouse_id') && $whId) {
                    $q->where('warehouse_id', $whId);
                } elseif (
                    Schema::hasColumn($movTable,'owner_id') &&
                    Schema::hasColumn($movTable,'owner_type') && $whId
                ) {
                    $q->where('owner_type', 'warehouse')
                      ->where('owner_id', $whId);
                }

                $rows = $q->get()->keyBy('d');

                foreach ($labels as $i => $d) {
                    $seriesIn[$i]  = (int) ($rows[$d]->inbound  ?? 0);
                    $seriesOut[$i] = (int) ($rows[$d]->outbound ?? 0);
                }
                $t = now()->format('Y-m-d');
                $todayIn  = (int) ($rows[$t]->inbound  ?? 0);
                $todayOut = (int) ($rows[$t]->outbound ?? 0);
            }
        }

        // ===== RESTOCK PENDING (tanpa asumsi kolom)
        $restocks = []; $restockPending = 0;
        $restTable = null;
        foreach (['request_restocks','restocks','stock_requests'] as $cand) {
            if (Schema::hasTable($cand)) { $restTable = $cand; break; }
        }

        if ($restTable) {
            $q = DB::table($restTable.' as r');

            // Filter: warehouse_id atau requested_by → users.warehouse_id
            if (Schema::hasColumn($restTable,'warehouse_id') && $whId) {
                $q->where('r.warehouse_id', $whId);
            } elseif (Schema::hasColumn($restTable,'requested_by') && $whId) {
                $q->leftJoin('users as u', 'u.id', '=', 'r.requested_by')
                  ->where('u.warehouse_id', $whId);
            }

            // Join product jika ada
            if (Schema::hasColumn($restTable,'product_id')) {
                $q->leftJoin('products as p','p.id','=','r.product_id')
                  ->addSelect('p.name as product_name','p.product_code');
            }

            // status pending bila kolom ada
            if (Schema::hasColumn($restTable,'status')) {
                $q->where('r.status','pending');
            }

            // Select aman
            $q->addSelect('r.id');
            if (Schema::hasColumn($restTable,'code'))         $q->addSelect('r.code');
            if (Schema::hasColumn($restTable,'request_code')) $q->addSelect('r.request_code');
            if (Schema::hasColumn($restTable,'qty'))          $q->addSelect('r.qty');
            if (Schema::hasColumn($restTable,'quantity'))     $q->addSelect('r.quantity');
            if (Schema::hasColumn($restTable,'created_at'))   $q->addSelect('r.created_at');
            if (Schema::hasColumn($restTable,'status'))       $q->addSelect('r.status');

            $rows = $q->orderByDesc('r.id')->limit(10)->get();

            // Counter pending
            $c = DB::table($restTable);
            if (Schema::hasColumn($restTable,'status')) $c->where('status','pending');
            if (Schema::hasColumn($restTable,'warehouse_id') && $whId) {
                $c->where('warehouse_id', $whId);
            } elseif (Schema::hasColumn($restTable,'requested_by') && $whId) {
                $c->whereIn('requested_by', function($sub) use ($whId){
                    $sub->from('users')->select('id')->where('warehouse_id',$whId);
                });
            }
            $restockPending = (int) $c->count();

            // Map → variable name konsisten (snake_case)
            $restocks = $rows->map(function ($r) {
                $code = $r->code
                    ?? ($r->request_code ?? ('REQ-' . $r->id));
                $qty  = isset($r->qty) ? (int)$r->qty
                     : (isset($r->quantity) ? (int)$r->quantity : 0);
                $product = $r->product_name ?? ($r->product_code ?? 'Unknown Product');
                $status  = $r->status ?? 'pending';
                $requested_at = isset($r->created_at) ? (string)$r->created_at : '-';
                return compact('code','product','qty','status','requested_at');
            })->toArray();
        }

        // ===== Paket ke view
        $stats = [
            'products_total'  => (int) $productsTotal,
            'low_stock_count' => (int) $lowStockCount,
            'restock_pending' => (int) $restockPending,
            'today_in'        => (int) $todayIn,
            'today_out'       => (int) $todayOut,
        ];
        $inout = ['labels'=>$labels, 'in'=>$seriesIn, 'out'=>$seriesOut];

        return view('dashboard.indexWarehouse', compact(
            'me','greeting','stats','lowStocks','restocks','inout'
        ));
    }
}
