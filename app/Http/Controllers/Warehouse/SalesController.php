<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

// Model baru untuk handover
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;

// Model lama kalau masih dipakai di menu lain
use App\Models\SalesReport;
use App\Models\SalesReturn;
use App\Models\StockLevel;

class SalesController extends Controller
{
    public function dashboard(Request $request)
    {
        $me = Auth::user();

        // ===== PERIODE FILTER (default: 1 awal bulan s/d hari ini) =====
        $today        = Carbon::today();
        $firstOfMonth = $today->copy()->startOfMonth();

        $dateFrom = $request->input('date_from') ?: $firstOfMonth->toDateString();
        $dateTo   = $request->input('date_to')   ?: $today->toDateString();

        // Kalau kebalik, tukar aja
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $base = SalesHandover::query()
            ->where('sales_id', $me->id)
            ->whereBetween('handover_date', [$dateFrom, $dateTo]);

        $closedBase = (clone $base)->where('status', 'closed');

        // status aktif + closed untuk nilai dibawa
        $activeBase = (clone $base)->whereIn('status', [
            'on_sales',
            'waiting_evening_otp',
            'closed',
        ]);

        $totalSold       = (int) $closedBase->sum('total_sold_amount');
        $totalDispatched = (int) $activeBase->sum('total_dispatched_amount');
        $estStockValue   = max(0, $totalDispatched - $totalSold);

        $cashTotal     = (int) $closedBase->sum('cash_amount');
        $transferTotal = (int) $closedBase->sum('transfer_amount');

        // ===== TOTAL QTY TERJUAL (detail) =====
        $totalQtySold = (int) SalesHandoverItem::query()
            ->join('sales_handovers', 'sales_handovers.id', '=', 'sales_handover_items.handover_id')
            ->where('sales_handovers.sales_id', $me->id)
            ->whereBetween('sales_handovers.handover_date', [$dateFrom, $dateTo])
            ->where('sales_handovers.status', 'closed')
            ->sum('sales_handover_items.qty_sold');

        // ===== DATA HARIAN UNTUK CHART =====
        $rawDaily = SalesHandover::query()
            ->where('sales_id', $me->id)
            ->whereBetween('handover_date', [$dateFrom, $dateTo])
            ->where('status', 'closed')
            ->selectRaw('handover_date, SUM(total_sold_amount) as total_sold')
            ->groupBy('handover_date')
            ->orderBy('handover_date')
            ->pluck('total_sold', 'handover_date')
            ->toArray();

        $period      = CarbonPeriod::create($dateFrom, $dateTo);
        $chartLabels = [];
        $chartData   = [];

        foreach ($period as $date) {
            $key = $date->toDateString();
            $chartLabels[] = $date->format('d/m');
            $chartData[]   = (int) ($rawDaily[$key] ?? 0);
        }

        // ===== HANDOVER TERBARU =====
        $recentHandovers = SalesHandover::query()
            ->where('sales_id', $me->id)
            ->whereBetween('handover_date', [$dateFrom, $dateTo])
            ->whereIn('status', ['on_sales', 'waiting_evening_otp', 'closed'])
            ->orderByDesc('handover_date')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        // ===== TOP 5 PRODUK TERJUAL =====
        $topProducts = SalesHandoverItem::query()
            ->join('sales_handovers', 'sales_handovers.id', '=', 'sales_handover_items.handover_id')
            ->join('products', 'products.id', '=', 'sales_handover_items.product_id')
            ->where('sales_handovers.sales_id', $me->id)
            ->whereBetween('sales_handovers.handover_date', [$dateFrom, $dateTo])
            ->where('sales_handovers.status', 'closed')
            ->selectRaw('
                products.id,
                products.name,
                products.product_code,
                SUM(sales_handover_items.qty_sold) as total_qty_sold,
                SUM(sales_handover_items.line_total_sold) as total_sales_amount
            ')
            ->groupBy('products.id', 'products.name', 'products.product_code')
            ->orderByDesc('total_qty_sold')
            ->limit(5)
            ->get();

        // ====== mode AJAX untuk auto-filter ======
        if ($request->ajax()) {
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
                'draft'               => 'bg-label-secondary',
            ];

            $recentArr = $recentHandovers->map(function ($h) use ($statusLabelMap, $badgeClassMap) {
                $dispatched = (int) $h->total_dispatched_amount;
                $sold       = (int) $h->total_sold_amount;
                $diff       = max(0, $dispatched - $sold);

                return [
                    'id'                 => $h->id,
                    'date'               => optional($h->handover_date)->format('Y-m-d') ?? $h->handover_date,
                    'code'               => $h->code,
                    'status'             => $h->status,
                    'status_label'       => $statusLabelMap[$h->status] ?? $h->status,
                    'status_badge_class' => $badgeClassMap[$h->status] ?? 'bg-label-secondary',
                    'amount_dispatched'  => $dispatched,
                    'amount_sold'        => $sold,
                    'amount_diff'        => $diff,
                    'detail_url'         => route('daily.report.detail', $h->id),
                ];
            });

            $topArr = $topProducts->map(function ($p) {
                return [
                    'product_id'          => $p->id,
                    'name'                => $p->name,
                    'product_code'        => $p->product_code,
                    'total_qty_sold'      => (int) $p->total_qty_sold,
                    'total_sales_amount'  => (int) $p->total_sales_amount,
                ];
            });

            return response()->json([
                'success'    => true,
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo,
                'summary'    => [
                    'total_dispatched' => $totalDispatched,
                    'total_sold'       => $totalSold,
                    'est_stock_value'  => $estStockValue,
                    'total_qty_sold'   => $totalQtySold,
                    'cash_total'       => $cashTotal,
                    'transfer_total'   => $transferTotal,
                ],
                'chart'      => [
                    'labels' => $chartLabels,
                    'data'   => $chartData,
                ],
                'recent_handovers' => $recentArr,
                'top_products'     => $topArr,
            ]);
        }

        // ===== normal view pertama kali load =====
        return view('dashboard.indexSales', [
            'me'              => $me,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'totalDispatched' => $totalDispatched,
            'totalSold'       => $totalSold,
            'estStockValue'   => $estStockValue,
            'totalQtySold'    => $totalQtySold,
            'cashTotal'       => $cashTotal,
            'transferTotal'   => $transferTotal,
            'recentHandovers' => $recentHandovers,
            'topProducts'     => $topProducts,
            'chartLabels'     => $chartLabels,
            'chartData'       => $chartData,
        ]);
    }

    // ====== method lama, gue biarin ======

    public function report()
    {
        $user    = Auth::user();
        $reports = SalesReport::where('sales_id', $user->id)->latest()->paginate(10);

        return view('sales.reports.index', compact('reports'));
    }

    public function return()
    {
        $user    = Auth::user();
        $returns = SalesReturn::where('sales_id', $user->id)->latest()->paginate(10);

        return view('sales.returns.index', compact('returns'));
    }
}
