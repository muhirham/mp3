<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesHandover;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $me = auth()->user();
        abort_unless($me, 403);

        // =========================
        // PERIOD FILTER (FINAL)
        // =========================
            $period = request('period','month');

            if ($period === 'day') {
                $start = now()->startOfDay();
                $end   = now()->endOfDay();
                $label = now()->format('d F Y');

            } elseif ($period === 'week') {
                $end   = now()->endOfDay();
                $start = now()->copy()->subDays(6)->startOfDay();

                $label = $start->format('d F').' - '.$end->format('d F Y');
            } else {
                $month = (int) request('month', now()->month);
                $year  = (int) request('year', now()->year);

                $start = Carbon::create($year,$month,1)->startOfMonth();
                $end   = Carbon::create($year,$month,1)->endOfMonth();

                $label = Carbon::create()->month($month)->format('F').' '.$year;
            }


        // =========================
        // SALES HANDOVER DATE COL
        // =========================
        $handoverTable = (new SalesHandover)->getTable();
        $handoverDate  = Schema::hasColumn($handoverTable, 'handover_date')
            ? 'handover_date'
            : 'created_at';

        // =========================
        // KPI TOP
        // =========================
        $usersTotal      = (int) User::count();
        $productsTotal   = (int) Product::count();
        $warehousesTotal = Schema::hasTable('warehouses') ? (int) Warehouse::count() : 0;

        $stockValue = (int) StockLevel::query()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->whereIn('stock_levels.owner_type', ['warehouse', 'sales'])
            ->selectRaw('SUM(stock_levels.quantity * products.selling_price) as total')
            ->value('total');

        $productsTotal   = (int) Product::count();
        $warehousesTotal = Schema::hasTable('warehouses') ? (int) Warehouse::count() : 0;

        $closedCount = SalesHandover::whereRaw('LOWER(status)="closed"')
            ->whereBetween($handoverDate, [$start, $end])
            ->count();

        $closedAmount = Schema::hasColumn($handoverTable,'total_sold_amount')
            ? (int) SalesHandover::whereRaw('LOWER(status)="closed"')
                ->whereBetween($handoverDate, [$start, $end])
                ->sum('total_sold_amount')
            : 0;

            // =========================
            // TOP 10 BEST SELLING PRODUCT (Moved to AJAX)
            // =========================
            $warehouseOptions = Warehouse::orderBy('warehouse_name')->get();

        // =========================
        // CHART 1: YEARLY (MONTHLY)
        // =========================
        $year = now()->year;

        $monthlyMap = Schema::hasColumn($handoverTable,'total_sold_amount')
            ? SalesHandover::whereRaw('LOWER(status)="closed"')
                ->whereYear($handoverDate, $year)
                ->selectRaw("MONTH($handoverDate) m, SUM(total_sold_amount) total")
                ->groupBy('m')
                ->pluck('total','m')
                ->toArray()
            : [];

        $monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $monthSeries = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthSeries[] = (int)($monthlyMap[$m] ?? 0);
        }

        // =========================
        // CHART 2: STATUS DONUT
        // =========================
        $statusRows = SalesHandover::whereBetween($handoverDate, [$start, $end])
            ->selectRaw('LOWER(status) st, COUNT(*) c')
            ->groupBy('st')
            ->pluck('c','st')
            ->toArray();

        $statusOrder = [
            'draft','waiting_morning_otp','on_sales',
            'waiting_evening_otp','closed','cancelled'
        ];

        $statusLabelMap = [
            'draft'=>'Draft',
            'waiting_morning_otp'=>'Menunggu OTP Pagi',
            'on_sales'=>'On Sales',
            'waiting_evening_otp'=>'Menunggu OTP Sore',
            'closed'=>'Closed',
            'cancelled'=>'Cancelled',
        ];

        $statusLabels = [];
        $statusSeries = [];
        foreach ($statusOrder as $st) {
            $statusLabels[] = $statusLabelMap[$st];
            $statusSeries[] = (int)($statusRows[$st] ?? 0);
        }

        // =========================
        // CHART 3: DAILY (AUTO RANGE)
        // =========================
        $dailyRows = SalesHandover::whereRaw('LOWER(status)="closed"')
            ->whereBetween($handoverDate, [$start, $end])
            ->selectRaw("DATE($handoverDate) d, COUNT(*) c")
            ->groupBy('d')
            ->pluck('c','d')
            ->toArray();

        $dailyLabels = [];
        $dailySeries = [];

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $dailyLabels[] = $cursor->format('d/m');
            $dailySeries[] = (int)($dailyRows[$key] ?? 0);
            $cursor->addDay();
        }

        // =========================
        // PO KPI
        // =========================
        $poTable   = (new PurchaseOrder)->getTable();
        $poDateCol = 'created_at';
        $approvalCol = Schema::hasColumn($poTable,'approval_status') ? 'approval_status' : null;
        $totalCol    = Schema::hasColumn($poTable,'grand_total') ? 'grand_total' : null;

        $poBase = PurchaseOrder::whereBetween($poDateCol, [$start, $end]);

        $poPendingProc = $approvalCol
            ? (clone $poBase)->whereRaw('LOWER(approval_status) IN ("waiting_procurement","pending_procurement")')->count()
            : 0;

        $poPendingCeo = $approvalCol
            ? (clone $poBase)->whereRaw('LOWER(approval_status) IN ("waiting_ceo","pending_ceo")')->count()
            : 0;

        $poApprovedQuery = $approvalCol
            ? (clone $poBase)->whereRaw('LOWER(approval_status)="approved"')
            : null;

        $poApprovedCount = $poApprovedQuery ? $poApprovedQuery->count() : 0;
        $poApprovedTotal = ($poApprovedQuery && $totalCol) ? (int)$poApprovedQuery->sum($totalCol) : 0;

        $poItemsTable = 'purchase_order_items';

        $restockIds = Schema::hasTable($poItemsTable)
            ? DB::table($poItemsTable)->whereNotNull('request_id')->pluck('purchase_order_id')
            : [];

        $poRestockQuery = PurchaseOrder::whereIn('id', $restockIds)
            ->whereBetween($poDateCol, [$start, $end])
            ->whereRaw('LOWER(approval_status)="approved"');

        $poRestockCount = (int) $poRestockQuery->count();
        $poRestockTotal = $totalCol ? (int) $poRestockQuery->sum($totalCol) : 0;

        // =========================
        // FINAL DATA
        // =========================
        $stats = [
            'stock_value' => $stockValue,
            'products_total'        => $productsTotal,
            'warehouses_total'      => $warehousesTotal,
            'closed_count_month'    => $closedCount,
            'closed_amount_month'   => $closedAmount,
            'po_pending_proc_count' => $poPendingProc,
            'po_pending_ceo_count'  => $poPendingCeo,
            'po_approved_count'     => $poApprovedCount,
            'po_approved_total'     => $poApprovedTotal,
            'po_restock_count'      => $poRestockCount,
            'po_restock_total'      => $poRestockTotal,
        ];

        $charts = [
            'yearly' => ['labels' => $monthLabels, 'series' => $monthSeries],
            'status' => ['labels' => $statusLabels, 'series' => $statusSeries],
            'daily'  => ['labels' => $dailyLabels, 'series' => $dailySeries],
        ];

        $roleSlugs = $me->roles()
            ->pluck('slug')
            ->map(fn($s) => strtolower($s))
            ->toArray();

        $access = [
            'can_open_pending_proc' => collect($roleSlugs)->intersect(['procurement','superadmin'])->isNotEmpty(),
            'can_open_pending_ceo'  => collect($roleSlugs)->intersect(['ceo','superadmin'])->isNotEmpty(),
        ];




        return view('dashboard.indexAdmin', compact(
            'me',
            'stats',
            'charts',
            'period',
            'label',
            'access',
            'warehouseOptions'
        ));
    }

    public function kpi(Request $request)
    {
        $range = $request->get('range', 'month'); // day | week | month

        // =========================
        // RANGE DATE
        // =========================
        if ($range === 'day') {
            $start = now()->startOfDay();
            $end   = now()->endOfDay();
        }elseif ($period === 'week') {
            $end   = now()->endOfDay();
            $start = now()->copy()->subDays(6)->startOfDay();

            $label = $start->format('d F').' - '.$end->format('d F Y');
        } else {
            $start = now()->startOfMonth();
            $end   = now()->endOfMonth();
        }

        // =========================
        // SALES HANDOVER
        // =========================
        $handoverTable = (new SalesHandover)->getTable();
        $handoverDate  = Schema::hasColumn($handoverTable,'handover_date')
            ? 'handover_date'
            : 'created_at';

        $closedCount = SalesHandover::whereRaw('LOWER(status)="closed"')
            ->whereBetween($handoverDate, [$start, $end])
            ->count();

        $closedTotal = Schema::hasColumn($handoverTable,'total_sold_amount')
            ? SalesHandover::whereRaw('LOWER(status)="closed"')
                ->whereBetween($handoverDate, [$start, $end])
                ->sum('total_sold_amount')
            : 0;

        // =========================
        // PO
        // =========================
        $poTable   = (new PurchaseOrder)->getTable();
        $poDateCol = 'created_at';

        $approvedQuery = PurchaseOrder::whereBetween($poDateCol, [$start,$end])
            ->whereRaw('LOWER(approval_status)="approved"');

        $poApprovedCount = $approvedQuery->count();
        $poApprovedTotal = Schema::hasColumn($poTable,'grand_total')
            ? $approvedQuery->sum('grand_total')
            : 0;

        // =========================
        // RESTOCK PO
        // =========================
        $poItemsTable = 'purchase_order_items';

        $restockPoIds = DB::table($poItemsTable)
            ->whereNotNull('request_id')
            ->pluck('purchase_order_id');

        $restockQuery = PurchaseOrder::whereIn('id',$restockPoIds)
            ->whereBetween($poDateCol, [$start,$end])
            ->whereRaw('LOWER(approval_status)="approved"');

        $poRestockCount = $restockQuery->count();
        $poRestockTotal = Schema::hasColumn($poTable,'grand_total')
            ? $restockQuery->sum('grand_total')
            : 0;

        return response()->json([
            'closed' => [
                'count' => (int)$closedCount,
                'total' => (int)$closedTotal
            ],
            'approved' => [
                'count' => (int)$poApprovedCount,
                'total' => (int)$poApprovedTotal
            ],
            'restock' => [
                'count' => (int)$poRestockCount,
                'total' => (int)$poRestockTotal
            ],
        ]);
    }
    public function topSelling(Request $request)
    {
        $selectedWarehouse = $request->warehouse_id;
        $period = $request->get('period', 'month');

        if ($period === 'day') {
            $start = now()->startOfDay();
            $end   = now()->endOfDay();
            $label = now()->format('d F Y');
        } elseif ($period === 'week') {
            $end   = now()->endOfDay();
            $start = now()->copy()->subDays(6)->startOfDay();
            $label = $start->format('d M') . ' - ' . $end->format('d M Y');
        } else {
            $month = (int) $request->get('month', now()->month);
            $year  = (int) $request->get('year', now()->year);
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end   = Carbon::create($year, $month, 1)->endOfMonth();
            $label = Carbon::create()->month($month)->format('F') . ' ' . $year;
        }

        $handoverTable = (new SalesHandover)->getTable();
        $handoverDate  = Schema::hasColumn($handoverTable, 'handover_date')
            ? 'sh.handover_date'
            : 'sh.created_at';

        $topSellingQuery = DB::table('sales_handover_items as shi')
            ->join('sales_handovers as sh', 'sh.id', '=', 'shi.handover_id')
            ->join('products as p', 'p.id', '=', 'shi.product_id')
            ->where('sh.status', 'closed')
            ->whereBetween($handoverDate, [$start, $end]);

        if (!empty($selectedWarehouse)) {
            $topSellingQuery->where('sh.warehouse_id', $selectedWarehouse);
        }

        $topSellingProducts = $topSellingQuery
            ->selectRaw("
                p.id,
                p.name,
                SUM(shi.qty_sold) as sold_qty,
                SUM(shi.line_total_sold) as sold_amount
            ")
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('sold_amount')
            ->limit(10)
            ->get();

        $totalTopRevenue = $topSellingProducts->sum('sold_amount');

        return view('dashboard.partials.topSelling', compact('topSellingProducts', 'totalTopRevenue', 'label'));
    }
}




