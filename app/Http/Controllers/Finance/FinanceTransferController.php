<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\SalesHandover;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class FinanceTransferController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo   = $request->query('date_to', now()->toDateString());
        if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

        $warehouseId = $request->query('warehouse_id');
        $search      = trim((string) $request->query('q', ''));

        // Query handovers that have AT LEAST 1 item with transfer payment
        $handovers = SalesHandover::query()
            ->with(['items.product', 'warehouse', 'sales'])
            ->whereHas('items', function ($q) {
                $q->where('payment_method', 'transfer')
                  ->where('payment_amount', '>', 0);
            })
            ->when($dateFrom && $dateTo, function ($q) use ($dateFrom, $dateTo) {
                // Ensure date string covers midnight logic safely if datetime field
                $q->whereBetween('handover_date', [$dateFrom, $dateTo]);
            })
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'LIKE', '%' . $search . '%')
                        ->orWhereHas('sales', function ($uQuery) use ($search) {
                            $uQuery->where('name', 'LIKE', '%' . $search . '%');
                        });
                });
            })
            ->orderByDesc('handover_date')
            ->orderByDesc('id')
            ->get();

        $warehouses = Warehouse::orderBy('warehouse_name')->get();

        // Calculate summaries
        $totalHdoCount = $handovers->count();
        $totalTransferNominal = 0;

        $rows = [];
        foreach ($handovers as $h) {
            $transferItems = $h->items->filter(function ($it) {
                return $it->payment_method === 'transfer' && $it->payment_amount > 0;
            })->values();

            $nominalForThisHdo = $transferItems->sum('payment_amount');
            $totalTransferNominal += $nominalForThisHdo;

            $rows[] = [
                'id' => $h->id,
                'date' => $h->handover_date,
                'code' => $h->code,
                'warehouse' => $h->warehouse->warehouse_name ?? $h->warehouse->name ?? 'Unknown',
                'sales' => optional($h->sales)->name ?? 'Unknown',
                'status' => $h->status,
                'transfer_nominal' => $nominalForThisHdo,
                'items' => $transferItems
            ];
        }

        return view('finance.transfer_checks', compact(
            'rows',
            'warehouses',
            'dateFrom',
            'dateTo',
            'warehouseId',
            'search',
            'totalHdoCount',
            'totalTransferNominal'
        ));
    }
}
