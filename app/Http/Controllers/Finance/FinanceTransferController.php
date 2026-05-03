<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\SalesHandover;
use App\Models\Warehouse;
use App\Models\WarehouseSettlement;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Settlement\WarehouseSettlementExport;

class FinanceTransferController extends Controller
{
    /**
     * Tampilan utama untuk Finance (Modul Settlement Verifications).
     * Akan menampilkan list Setoran Harian dari Gudang yang dikelompokkan berdasarkan tabel `warehouse_settlements`.
     */
    public function settlementsIndex(Request $request)
    {
        $query = WarehouseSettlement::with(['warehouse', 'admin'])
            ->orderBy('settlement_date', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            // Clean prefix and leading zeros (e.g. SET-00001 -> 1)
            $cleanSearch = ltrim(str_replace(['SET-', 'set-', 'STL-', 'stl-', '#'], '', $search), '0');
            
            $query->where(function($q) use ($search, $cleanSearch) {
                if (is_numeric($cleanSearch) && $cleanSearch !== '') {
                    $q->where('id', $cleanSearch);
                }
                $q->orWhereHas('warehouse', function($wh) use ($search) {
                    $wh->where('warehouse_name', 'LIKE', "%$search%");
                })->orWhereHas('admin', function($ad) use ($search) {
                    $ad->where('name', 'LIKE', "%$search%");
                });
            });
        }

        if ($request->filled('date_start')) {
            $query->whereDate('settlement_date', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->whereDate('settlement_date', '<=', $request->date_end);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        $perPage = $request->get('per_page', 20);
        $settlements = $query->paginate($perPage);
        $warehouses = Warehouse::orderBy('warehouse_name')->get();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'settlements' => $settlements->items(),
                'pagination' => (string) $settlements->links(),
            ]);
        }

        return view('finance.settlement_verifications', compact('settlements', 'warehouses'));
    }

    /**
     * API / View Detail untuk melihat transaksi HDO pembentuk setoran.
     */
    public function settlementDetail($id)
    {
        $settlement = WarehouseSettlement::with([
            'warehouse', 
            'admin', 
            'handovers.sales', 
            'handovers.items.product'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'settlement' => $settlement,
            'handovers' => $settlement->handovers
        ]);
    }

    /**
     * Export Riwayat Setoran untuk Finance (Semua Gudang).
     */
    public function exportSettlements(Request $request)
    {
        $query = WarehouseSettlement::with(['warehouse', 'admin'])
            ->orderBy('settlement_date', 'desc');

        if ($request->filled('date_start')) {
            $query->whereDate('settlement_date', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->whereDate('settlement_date', '<=', $request->date_end);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        $filters = [
            'date_start' => $request->date_start,
            'date_end' => $request->date_end,
            'warehouse_name' => $request->warehouse_id ? \App\Models\Warehouse::find($request->warehouse_id)?->warehouse_name : 'All Warehouses'
        ];

        $data = $query->get();
        return Excel::download(new WarehouseSettlementExport($data, 'Warehouse Settlement Verification Report', $filters), 'settlement_verification_' . now()->format('Ymd_His') . '.xlsx');
    }

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
