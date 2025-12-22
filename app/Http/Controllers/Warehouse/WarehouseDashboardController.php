<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use App\Models\Product;

class WarehouseDashboardController extends Controller
{
    public function index()
    {
        $me = auth()->user();
        abort_unless($me, 403);

        $whId     = $me->warehouse_id;
        $whName   = $me->warehouse?->warehouse_name ?? 'Warehouse';
        $greeting = 'Selamat datang, Wh ' . ($me->name ?? 'User');

        // ===== LINKS (pakai named route kalau ada, fallback ke URL)
            $links = [
            'stock_level'     => route('stocklevel.index'),
            'stock_level_low' => route('stocklevel.index', ['filter' => 'low']),

            'issue_morning'   => route('sales.handover.morning'),
            'reconcile_otp'   => route('sales.handover.evening'),

            'sales_reports'   => route('sales.report'),
            'restocks'        => route('restocks.index'),
            ];

        // ===== Label 7 hari (Y-m-d)
        $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('Y-m-d');
        }

        // ==========================================================
        // A) STOCK LEVEL (sama konsep dengan halaman Stock Level)
        // ==========================================================
        $productsTotal  = (int) Product::count();
        $lowStockCount  = 0;
        $lowStocks      = [];

        $hasSL    = Schema::hasTable('stock_levels');
        $slQtyCol = null;

        if ($hasSL) {
            $slQtyCol = Schema::hasColumn('stock_levels', 'quantity') ? 'quantity'
                : (Schema::hasColumn('stock_levels', 'qty') ? 'qty' : null);
        }

        if ($hasSL && $slQtyCol) {
            $stockSub = DB::table('stock_levels as sl')
                ->selectRaw("sl.product_id, SUM(sl.$slQtyCol) AS current_stock")
                ->when(Schema::hasColumn('stock_levels', 'warehouse_id') && $whId, fn($q) => $q->where('sl.warehouse_id', $whId))
                ->when(
                    Schema::hasColumn('stock_levels', 'owner_type') && Schema::hasColumn('stock_levels', 'owner_id') && $whId,
                    fn($q) => $q->where('sl.owner_type', 'warehouse')->where('sl.owner_id', $whId)
                )
                ->groupBy('sl.product_id');

            $baseLow = DB::table('products as p')
                ->leftJoinSub($stockSub, 'st', 'st.product_id', '=', 'p.id')
                ->leftJoin('packages as pk', 'pk.id', '=', 'p.package_id')
                ->whereRaw('COALESCE(p.stock_minimum,0) > 0')
                ->whereRaw('COALESCE(st.current_stock,0) <= COALESCE(p.stock_minimum,0)');

            $lowStockCount = (int) (clone $baseLow)->count('p.id');

            $lowRows = (clone $baseLow)
                ->selectRaw("
                    p.product_code as code,
                    p.name,
                    COALESCE(pk.package_name,'-') as package,
                    COALESCE(p.stock_minimum,0) as min_stock,
                    COALESCE(st.current_stock,0) as current_stock
                ")
                ->orderByRaw('(COALESCE(p.stock_minimum,0) - COALESCE(st.current_stock,0)) DESC')
                ->limit(10)
                ->get();

            $lowStocks = $lowRows->map(fn($r) => [
                'code'    => $r->code,
                'name'    => $r->name,
                'package' => $r->package,
                'min'     => (int) $r->min_stock,
                'current' => (int) $r->current_stock,
            ])->toArray();
        }

        // ==========================================================
        // B) TOTAL PENJUALAN CLOSED (HARI INI)
        // ==========================================================
        [$closedSales, $closedSalesIsMoney] = $this->sumClosedSalesToday($whId);

        // ==========================================================
        // C) SALES USERS COUNT (Sales + Admin Sales)
        // ==========================================================
        $salesCount      = $this->countUsersByRoleSlugs($whId, ['sales']);
        $adminSalesCount = $this->countUsersByRoleSlugs($whId, ['admin_sales', 'sales_admin']);
        $salesStats = [
            'sales'       => (int) $salesCount,
            'admin_sales' => (int) $adminSalesCount,
            'total'       => (int) ($salesCount + $adminSalesCount),
        ];

        // ==========================================================
        // D) IN/OUT 7 hari (stock_movements / inventory_movements)
        // ==========================================================
        $todayIn = 0; $todayOut = 0;
        $seriesIn  = array_fill(0, 7, 0);
        $seriesOut = array_fill(0, 7, 0);
        $usedSource = 'none';

        $movTable = Schema::hasTable('stock_movements') ? 'stock_movements'
            : (Schema::hasTable('inventory_movements') ? 'inventory_movements' : null);

        if ($movTable && Schema::hasColumn($movTable, 'created_at')) {
            $qtyCol = Schema::hasColumn($movTable, 'quantity') ? 'quantity'
                : (Schema::hasColumn($movTable, 'qty') ? 'qty' : null);

            $typeCol = null;
            foreach (['type', 'movement_type', 'direction'] as $c) {
                if (Schema::hasColumn($movTable, $c)) { $typeCol = $c; break; }
            }

            if ($qtyCol) {
                $q = DB::table($movTable)->selectRaw("
                        DATE(created_at) as d,
                        SUM(CASE ".($typeCol ? "WHEN LOWER($typeCol) IN ('in','inbound','masuk')" : "WHEN $qtyCol > 0")." THEN ABS($qtyCol) ELSE 0 END) as inbound,
                        SUM(CASE ".($typeCol ? "WHEN LOWER($typeCol) IN ('out','outbound','keluar')" : "WHEN $qtyCol < 0")." THEN ABS($qtyCol) ELSE 0 END) as outbound
                    ")
                    ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
                    ->when(Schema::hasColumn($movTable, 'warehouse_id') && $whId, fn($qq) => $qq->where('warehouse_id', $whId))
                    ->when(
                        Schema::hasColumn($movTable, 'owner_type') && Schema::hasColumn($movTable, 'owner_id') && $whId,
                        fn($qq) => $qq->where('owner_type', 'warehouse')->where('owner_id', $whId)
                    )
                    ->groupBy('d')->orderBy('d');

                $rows = $q->get()->keyBy('d');

                foreach ($labels as $i => $d) {
                    $seriesIn[$i]  = (int) ($rows[$d]->inbound  ?? 0);
                    $seriesOut[$i] = (int) ($rows[$d]->outbound ?? 0);
                }
                $t = now()->format('Y-m-d');
                $todayIn  = (int) ($rows[$t]->inbound  ?? 0);
                $todayOut = (int) ($rows[$t]->outbound ?? 0);

                $usedSource = $movTable;
            }
        }

        // ==========================================================
        // E) RESTOCK PENDING (biarin)
        // ==========================================================
        $restocks = [];
        $restockPending = 0;
        $restTable = null;
        foreach (['request_restocks', 'restocks', 'stock_requests'] as $cand) {
            if (Schema::hasTable($cand)) { $restTable = $cand; break; }
        }

        if ($restTable) {
            $q = DB::table($restTable.' as r');

            if (Schema::hasColumn($restTable, 'warehouse_id') && $whId) {
                $q->where('r.warehouse_id', $whId);
            } elseif (Schema::hasColumn($restTable, 'requested_by') && $whId) {
                $q->leftJoin('users as u', 'u.id', '=', 'r.requested_by')
                  ->where('u.warehouse_id', $whId);
            }

            if (Schema::hasColumn($restTable, 'product_id')) {
                $q->leftJoin('products as p', 'p.id', '=', 'r.product_id')
                  ->addSelect('p.name as product_name', 'p.product_code');
            }

            if (Schema::hasColumn($restTable, 'status')) {
                $q->where('r.status', 'pending');
            }

            $q->addSelect('r.id');
            if (Schema::hasColumn($restTable, 'code'))         $q->addSelect('r.code');
            if (Schema::hasColumn($restTable, 'request_code')) $q->addSelect('r.request_code');
            if (Schema::hasColumn($restTable, 'qty'))          $q->addSelect('r.qty');
            if (Schema::hasColumn($restTable, 'quantity'))     $q->addSelect('r.quantity');
            if (Schema::hasColumn($restTable, 'created_at'))   $q->addSelect('r.created_at');
            if (Schema::hasColumn($restTable, 'status'))       $q->addSelect('r.status');

            $rows = $q->orderByDesc('r.id')->limit(10)->get();

            $c = DB::table($restTable);
            if (Schema::hasColumn($restTable, 'status')) $c->where('status','pending');
            if (Schema::hasColumn($restTable, 'warehouse_id') && $whId) {
                $c->where('warehouse_id', $whId);
            } elseif (Schema::hasColumn($restTable, 'requested_by') && $whId) {
                $c->whereIn('requested_by', function($sub) use ($whId){
                    $sub->from('users')->select('id')->where('warehouse_id', $whId);
                });
            }
            $restockPending = (int) $c->count();

            $restocks = $rows->map(function ($r) {
                $code = $r->code ?? ($r->request_code ?? ('REQ-' . $r->id));
                $qty  = isset($r->qty) ? (int)$r->qty : (isset($r->quantity) ? (int)$r->quantity : 0);
                $product = $r->product_name ?? ($r->product_code ?? 'Unknown Product');
                $status  = $r->status ?? 'pending';
                $requested_at = isset($r->created_at) ? (string)$r->created_at : '-';
                return compact('code','product','qty','status','requested_at');
            })->toArray();
        }

        $stats = [
            'products_total'  => (int) $productsTotal,
            'low_stock_count' => (int) $lowStockCount,
            'closed_sales'    => $closedSales,
            'closed_is_money' => $closedSalesIsMoney ? 1 : 0,
            'restock_pending' => (int) $restockPending,
            'today_in'        => (int) $todayIn,
            'today_out'       => (int) $todayOut,
        ];

        $inout = ['labels' => $labels, 'in' => $seriesIn, 'out' => $seriesOut];

        return view('dashboard.indexWarehouse', compact(
            'me','greeting','whName','stats','lowStocks','restocks','inout',
            'links','salesStats','usedSource'
        ));
    }

    private function sumClosedSalesToday($whId): array
    {
        $table = Schema::hasTable('sales_handovers') ? 'sales_handovers' : null;
        if (!$table) return [0, false];

        $q = DB::table("$table as h")
            ->whereBetween('h.created_at', [now()->startOfDay(), now()->endOfDay()])
            ->when(Schema::hasColumn($table, 'warehouse_id') && $whId, fn($qq) => $qq->where('h.warehouse_id', $whId));

        // closed condition (fleksibel)
        if (Schema::hasColumn($table, 'status')) {
            $q->whereIn(DB::raw('LOWER(h.status)'), ['closed','done','finished','complete','completed']);
        } elseif (Schema::hasColumn($table, 'closed_at')) {
            $q->whereNotNull('h.closed_at');
        } elseif (Schema::hasColumn($table, 'is_closed')) {
            $q->where('h.is_closed', 1);
        }

        // prioritas ambil NOMINAL kalau ada
        $moneyCols = ['total_sold_amount','total_sold_value','total_amount','grand_total','sold_total'];
        foreach ($moneyCols as $col) {
            if (Schema::hasColumn($table, $col)) {
                return [(int) $q->sum("h.$col"), true];
            }
        }

        // fallback qty / count
        $qtyCols = ['total_sold_qty','total_qty','sold_qty'];
        foreach ($qtyCols as $col) {
            if (Schema::hasColumn($table, $col)) {
                return [(int) $q->sum("h.$col"), false];
            }
        }

        return [(int) $q->count('h.id'), false];
    }

    private function countUsersByRoleSlugs($whId, array $slugs): int
    {
        $slugs = array_values(array_filter($slugs));
        if (!$slugs) return 0;

        if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            $roleKey = Schema::hasColumn('roles', 'slug') ? 'slug' : (Schema::hasColumn('roles', 'name') ? 'name' : null);
            if (!$roleKey) return 0;

            $q = DB::table('users as u')
                ->join('model_has_roles as mhr', 'mhr.model_id', '=', 'u.id')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->whereIn("r.$roleKey", $slugs)
                ->when($whId, fn($qq) => $qq->where('u.warehouse_id', $whId));

            if (Schema::hasColumn('model_has_roles', 'model_type')) {
                $q->where('mhr.model_type', 'App\\Models\\User');
            }

            return (int) $q->distinct('u.id')->count('u.id');
        }

        if (Schema::hasTable('role_user') && Schema::hasTable('roles')) {
            $roleKey = Schema::hasColumn('roles', 'slug') ? 'slug' : (Schema::hasColumn('roles', 'name') ? 'name' : null);
            if (!$roleKey) return 0;

            return (int) DB::table('users as u')
                ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
                ->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->whereIn("r.$roleKey", $slugs)
                ->when($whId, fn($qq) => $qq->where('u.warehouse_id', $whId))
                ->distinct('u.id')->count('u.id');
        }

        return 0;
    }
}
