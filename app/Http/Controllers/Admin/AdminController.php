<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesHandover;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    public function index()
    {
        $me = auth()->user();
        abort_unless($me, 403);

        // =========================
        // RANGE: BULAN INI
        // =========================
        $startMonth = now()->startOfMonth()->startOfDay();
        $endMonth   = now()->endOfMonth()->endOfDay();

        // =========================
        // SALES HANDOVER DATE COL (aman)
        // =========================
        $handoverTable = (new SalesHandover)->getTable();
        $handoverDateCol = Schema::hasColumn($handoverTable, 'handover_date') ? 'handover_date' : 'created_at';

        // =========================
        // KPI TOP CARDS
        // =========================
        $usersTotal      = (int) User::count();
        $productsTotal   = (int) Product::count();
        $warehousesTotal = Schema::hasTable('warehouses') ? (int) Warehouse::count() : 0;

        $closedCountMonth = (int) SalesHandover::query()
            ->whereRaw('LOWER(status) = ?', ['closed'])
            ->whereBetween($handoverDateCol, [$startMonth, $endMonth])
            ->count();

        $closedAmountMonth = 0;
        if (Schema::hasColumn($handoverTable, 'total_sold_amount')) {
            $closedAmountMonth = (int) SalesHandover::query()
                ->whereRaw('LOWER(status) = ?', ['closed'])
                ->whereBetween($handoverDateCol, [$startMonth, $endMonth])
                ->sum('total_sold_amount');
        }

        // =========================
        // CHART 1: YEARLY (SUM CLOSED PER BULAN)
        // =========================
        $year = now()->year;

        $monthlyMap = [];
        if (Schema::hasColumn($handoverTable, 'total_sold_amount')) {
            $monthlyMap = SalesHandover::query()
                ->whereRaw('LOWER(status) = ?', ['closed'])
                ->whereYear($handoverDateCol, $year)
                ->selectRaw("MONTH($handoverDateCol) as m, SUM(total_sold_amount) as total")
                ->groupBy('m')
                ->pluck('total', 'm')
                ->toArray();
        }

        $monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $monthSeries = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthSeries[] = (int) ($monthlyMap[$m] ?? 0);
        }

        // =========================
        // CHART 2: STATUS DONUT (BULAN INI)
        // =========================
        $statusRows = SalesHandover::query()
            ->whereBetween($handoverDateCol, [$startMonth, $endMonth])
            ->selectRaw('LOWER(status) as st, COUNT(*) as c')
            ->groupBy('st')
            ->pluck('c', 'st')
            ->toArray();

        $statusOrder = [
            'draft',
            'waiting_morning_otp',
            'on_sales',
            'waiting_evening_otp',
            'closed',
            'cancelled',
        ];

        $statusLabelMap = [
            'draft'               => 'Draft',
            'waiting_morning_otp' => 'Menunggu OTP Pagi',
            'on_sales'            => 'On Sales',
            'waiting_evening_otp' => 'Menunggu OTP Sore',
            'closed'              => 'Closed',
            'cancelled'           => 'Cancelled',
        ];

        $statusLabels = [];
        $statusSeries = [];
        foreach ($statusOrder as $st) {
            $statusLabels[] = $statusLabelMap[$st] ?? $st;
            $statusSeries[] = (int) ($statusRows[$st] ?? 0);
        }

        // =========================
        // CHART 3: CLOSED PER HARI (LAST 12 DAYS)
        // =========================
        $from = now()->subDays(11)->startOfDay();
        $to   = now()->endOfDay();

        $dailyRows = SalesHandover::query()
            ->whereRaw('LOWER(status) = ?', ['closed'])
            ->whereBetween($handoverDateCol, [$from, $to])
            ->selectRaw("DATE($handoverDateCol) as d, COUNT(*) as c")
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->toArray();

        $dailyLabels = [];
        $dailySeries = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $dailyLabels[] = Carbon::parse($d)->format('d/m');
            $dailySeries[] = (int) ($dailyRows[$d] ?? 0);
        }

        // ==========================================================
        // PO KPI (FIX FINAL) - pakai schema migration lu:
        // purchase_orders.approval_status + grand_total + created_at
        // ==========================================================
        $poTable = (new PurchaseOrder)->getTable();
        $poItemsTable = Schema::hasTable('purchase_order_items') ? 'purchase_order_items' : null;

        // KUNCI: jangan auto detect po_date/order_date (bisa bikin 0 kalau kolom ada tapi null/string)
        $poDateCol = 'created_at';

        $approvalCol = Schema::hasColumn($poTable, 'approval_status') ? 'approval_status' : null;
        $totalCol    = Schema::hasColumn($poTable, 'grand_total') ? 'grand_total' : null;

        $whereLowerIn = function ($q, string $col, array $vals) {
            $vals = array_map('strtolower', $vals);
            return $q->whereIn(DB::raw("LOWER($col)"), $vals);
        };

        $poBaseMonth = PurchaseOrder::query()
            ->whereBetween($poDateCol, [$startMonth, $endMonth]);

        // Pending Procurement
        $poPendingProcQuery = (clone $poBaseMonth);
        if ($approvalCol) {
            $whereLowerIn($poPendingProcQuery, $approvalCol, [
                'waiting_procurement', 'pending_procurement'
            ]);
        } else {
            // fallback (kalau suatu hari approval_status ga ada)
            $poPendingProcQuery->whereRaw('1=0');
        }
        $poPendingProcCount = (int) (clone $poPendingProcQuery)->count();

        // Pending CEO
        $poPendingCeoQuery = (clone $poBaseMonth);
        if ($approvalCol) {
            $whereLowerIn($poPendingCeoQuery, $approvalCol, [
                'waiting_ceo', 'pending_ceo'
            ]);
        } else {
            $poPendingCeoQuery->whereRaw('1=0');
        }
        $poPendingCeoCount = (int) (clone $poPendingCeoQuery)->count();

        // Approved (ini yang lu butuhin biar kebaca sama label APPROVED di index PO)
        $poApprovedQuery = (clone $poBaseMonth);
        if ($approvalCol) {
            $whereLowerIn($poApprovedQuery, $approvalCol, ['approved']);
        } else {
            $poApprovedQuery->whereRaw('1=0');
        }

        $poApprovedCount = (int) (clone $poApprovedQuery)->count();
        $poApprovedTotal = 0;
        if ($totalCol) {
            $poApprovedTotal = (int) (clone $poApprovedQuery)->sum($totalCol);
        }

        // Restock = PO yang item-nya punya request_id (dan kita hitung yang approved biar nilai masuk akal)
        $restockPoIdsSub = null;
        if ($poItemsTable
            && Schema::hasColumn($poItemsTable, 'purchase_order_id')
            && Schema::hasColumn($poItemsTable, 'request_id')
        ) {
            $restockPoIdsSub = DB::table($poItemsTable)
                ->whereNotNull('request_id')
                ->select('purchase_order_id')
                ->distinct();
        }

        $poRestockQuery = (clone $poBaseMonth);
        if ($restockPoIdsSub) {
            $poRestockQuery->whereIn($poTable.'.id', $restockPoIdsSub);

            if ($approvalCol) {
                $whereLowerIn($poRestockQuery, $approvalCol, ['approved']);
            }
        } else {
            $poRestockQuery->whereRaw('1=0');
        }

        $poRestockCount = (int) (clone $poRestockQuery)->count();
        $poRestockTotal = 0;
        if ($totalCol) {
            $poRestockTotal = (int) (clone $poRestockQuery)->sum($totalCol);
        }

        // =========================
        // ACCESS FLAGS (buat disable card)
        // =========================
        $roleSlugs = $me->roles()->pluck('slug')->map(fn($x) => strtolower($x))->toArray();

        $isProc = in_array('procurement', $roleSlugs, true);
        $isCeo  = in_array('ceo', $roleSlugs, true);

        $canOpenPoMenu = method_exists($me, 'canSeeMenu') ? (bool) $me->canSeeMenu('po') : true;

        $access = [
            'can_open_pending_proc' => $canOpenPoMenu && $isProc,
            'can_open_pending_ceo'  => $canOpenPoMenu && $isCeo,
        ];

        // =========================
        // PACK to VIEW (KEY NYA DISAMAIN KE BLADE)
        // =========================
        $stats = [
            'users_total'         => $usersTotal,
            'products_total'      => $productsTotal,
            'warehouses_total'    => $warehousesTotal,
            'closed_count_month'  => $closedCountMonth,
            'closed_amount_month' => $closedAmountMonth,

            'po_pending_proc_count' => $poPendingProcCount,
            'po_pending_ceo_count'  => $poPendingCeoCount,

            'po_approved_count' => $poApprovedCount,
            'po_approved_total' => $poApprovedTotal,

            'po_restock_count'  => $poRestockCount,
            'po_restock_total'  => $poRestockTotal,
        ];

        $charts = [
            'yearly' => ['labels' => $monthLabels, 'series' => $monthSeries],
            'status' => ['labels' => $statusLabels, 'series' => $statusSeries],
            'daily'  => ['labels' => $dailyLabels,  'series' => $dailySeries],
        ];

        return view('dashboard.indexAdmin', compact('me','stats','charts','year','access'));
    }
}
