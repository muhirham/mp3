<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WarehouseSettlement;
use App\Models\SalesHandover;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Settlement\WarehouseSettlementExport;
use App\Exports\Settlement\PendingSettlementExport;
use Carbon\Carbon;

class WarehouseSettlementController extends Controller
{
    /**
     * Display a listing of the settlements.
     */
    public function index(Request $request)
    {
        $me = auth()->user();
        $whId = $me->warehouse_id;

        $query = WarehouseSettlement::with(['warehouse', 'admin'])
            ->orderBy('created_at', 'desc');

        if ($whId && !$me->hasRole(['superadmin', 'admin'])) {
            $query->where('warehouse_id', $whId);
        }

        // Search Filter (ID / Code)
        if ($request->filled('search')) {
            $search = $request->search;
            // Clean prefix and leading zeros (e.g. SET-00001 -> 1)
            $cleanSearch = ltrim(str_replace(['SET-', 'set-', 'STL-', 'stl-', '#'], '', $search), '0');
            
            if (is_numeric($cleanSearch)) {
                $query->where('id', $cleanSearch);
            } else {
                $query->where('id', 'LIKE', "%" . str_replace(['SET-', 'set-', 'STL-', 'stl-', '#'], '', $search) . "%");
            }
        }

        // Filters (Warehouse, Date)
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('date_start')) {
            $query->whereDate('settlement_date', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->whereDate('settlement_date', '<=', $request->date_end);
        }

        $warehouses = [];
        if ($me->hasRole(['superadmin', 'admin'])) {
            $warehouses = Warehouse::orderBy('warehouse_name')->get();
        }

        $perPage = $request->get('per_page', 20);
        $settlements = $query->paginate($perPage);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'settlements' => $settlements->items(),
                'pagination' => (string) $settlements->links(),
            ]);
        }

        return view('wh.daily_deposits_index', compact('settlements', 'warehouses'));
    }

    /**
     * Show the form for creating a new settlement.
     */
    public function create(Request $request)
    {
        $me = auth()->user();
        $whId = $me->warehouse_id;

        if (!$whId && !$me->hasRole(['superadmin', 'admin'])) {
            return back()->with('error', 'You are not assigned to any warehouse.');
        }

        // Dropdowns untuk Filter (Superadmin)
        $warehouses = Warehouse::orderBy('warehouse_name')->get();

        // Ambil Transaksi yang Belum Disetor, Group berdasarkan Tanggal, Warehouse, dan Sales
        $query = SalesHandover::with(['warehouse', 'sales'])
            ->select(
                'handover_date',
                'warehouse_id',
                'sales_id',
                DB::raw('SUM(cash_amount) as total_cash'),
                DB::raw('SUM(transfer_amount) as total_transfer'),
                DB::raw('COUNT(*) as total_handovers'),
                DB::raw('GROUP_CONCAT(code SEPARATOR ", ") as hdo_codes'),
                DB::raw('MAX(updated_at) as last_updated')
            )
            ->whereNull('settlement_id')
            ->whereIn('status', ['closed']);

        // Filter Search (Code or Sales Name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhereHas('sales', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filter Warehouse
        if ($whId && !$me->hasRole(['superadmin', 'admin'])) {
            $query->where('warehouse_id', $whId);
        } elseif ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter Tanggal
        if ($request->filled('date_start')) {
            $query->whereDate('handover_date', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->whereDate('handover_date', '<=', $request->date_end);
        }

        $baseQuery = $query->groupBy('handover_date', 'warehouse_id', 'sales_id')
            ->orderBy('handover_date', 'asc');

        // Total untuk Header (Seluruh hasil filter, sebelum paginasi)
        $allUnsettled = $baseQuery->get();
        $grandCash = $allUnsettled->sum('total_cash');
        $grandTf = $allUnsettled->sum('total_transfer');
        $grandCount = $allUnsettled->sum('total_handovers');

        $perPage = $request->get('per_page', 20);
        $unsettled = $baseQuery->paginate($perPage);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'unsettled' => $unsettled->items(),
                'pagination' => (string) $unsettled->links(),
                'grand_cash' => $grandCash,
                'grand_tf' => $grandTf,
                'grand_count' => $grandCount,
            ]);
        }

        return view('wh.daily_deposits_create', compact('unsettled', 'warehouses', 'grandCash', 'grandTf', 'grandCount'));
    }

    /**
     * Store a newly created settlement in storage.
     */
    public function store(Request $request)
    {
        $me = auth()->user();
        $whId = $me->warehouse_id;

        if (!$whId && !$me->hasRole(['superadmin', 'admin'])) {
            return back()->with('error', 'You are not assigned to any warehouse.');
        }

        $request->validate([
            'handover_date' => 'nullable|date',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'sales_id' => 'nullable|exists:users,id',
            'proof_path' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $handoversQuery = SalesHandover::whereNull('settlement_id')
            ->whereIn('status', ['closed']);
            
        if ($whId) {
            $handoversQuery->where('warehouse_id', $whId);
        } elseif ($request->filled('warehouse_id')) {
            $handoversQuery->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('handover_date')) {
            $handoversQuery->whereDate('handover_date', $request->handover_date);
            $opDate = $request->handover_date;
        } else {
            $opDate = date('Y-m-d');
        }

        if ($request->filled('sales_id')) {
            $handoversQuery->where('sales_id', $request->sales_id);
        }

        $handovers = $handoversQuery->get();

        if ($handovers->isEmpty()) {
            return back()->with('error', 'No unsettled transactions found for this date.');
        }

        $totalCash = $handovers->sum('cash_amount');
        $totalTransfer = $handovers->sum('transfer_amount');

        // Tentukan Warehouse ID yang akan dicatat di settlement
        // Jika admin wh, pakai id dia. Jika superadmin, pakai wh_id dari transaksi yang ditarik.
        $targetWhId = $whId ?: $handovers->first()->warehouse_id;

        try {
            DB::beginTransaction();

            $proofPath = $request->file('proof_path')->store('settlements_proofs', 'public');

            // 1. Buat record settlement
            $settlement = WarehouseSettlement::create([
                'warehouse_id' => $targetWhId,
                'admin_id' => $me->id,
                'settlement_date' => $opDate, 
                'total_cash_amount' => $totalCash,
                'total_transfer_amount' => $totalTransfer,
                'proof_path' => $proofPath,
            ]);

            // 2. Update semua handover yang bersangkutan
            SalesHandover::whereIn('id', $handovers->pluck('id'))->update([
                'settlement_id' => $settlement->id,
            ]);

            DB::commit();

            return redirect()->route('warehouse.settlements.index')->with('success', 'Settlement created successfully. All handovers are now marked as deposited.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create settlement: ' . $e->getMessage());
        }
    }

    /**
     * API Detail untuk melihat HDO dan Item barang pembentuk setoran (Untuk sisi WH).
     */
    public function showDetail($id)
    {
        $settlement = WarehouseSettlement::with([
            'handovers.sales', 
            'handovers.items.product'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'handovers' => $settlement->handovers
        ]);
    }

    /**
     * Export Riwayat Setoran (Settled) ke Excel.
     */
    public function exportHistory(Request $request)
    {
        $query = WarehouseSettlement::with(['warehouse', 'admin'])
            ->orderBy('settlement_date', 'desc');

        // Apply filters (sama kayak di index)
        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->date_start) {
            $query->whereDate('settlement_date', '>=', $request->date_start);
        }
        if ($request->date_end) {
            $query->whereDate('settlement_date', '<=', $request->date_end);
        }

        // Role-based filtering
        $user = auth()->user();
        $whId = $user->warehouse_id;
        if ($whId && !$user->hasRole(['superadmin', 'admin'])) {
            $query->where('warehouse_id', $whId);
        }

        $filters = [
            'date_start' => $request->date_start,
            'date_end' => $request->date_end,
            'warehouse_name' => $request->warehouse_id ? \App\Models\Warehouse::find($request->warehouse_id)?->warehouse_name : 'All Warehouses'
        ];

        $data = $query->get();
        return Excel::download(new WarehouseSettlementExport($data, 'Warehouse Settlement History Report', $filters), 'settlement_history_' . now()->format('Ymd_His') . '.xlsx');
    }

    /**
     * Export Antrian Setoran (Pending) ke Excel.
     */
    public function exportPending(Request $request)
    {
        $query = SalesHandover::with(['warehouse', 'sales'])
            ->select(
                'handover_date',
                'warehouse_id',
                'sales_id',
                DB::raw('SUM(cash_amount) as total_cash'),
                DB::raw('SUM(transfer_amount) as total_transfer'),
                DB::raw('COUNT(*) as total_handovers'),
                DB::raw('MAX(updated_at) as last_updated')
            )
            ->whereNull('settlement_id')
            ->whereIn('status', ['closed']);

        // Filter warehouse
        $user = auth()->user();
        $whId = $user->warehouse_id;
        if ($whId && !$user->hasRole(['superadmin', 'admin'])) {
            $query->where('warehouse_id', $whId);
        } elseif ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->date_start) {
            $query->whereDate('handover_date', '>=', $request->date_start);
        }
        if ($request->date_end) {
            $query->whereDate('handover_date', '<=', $request->date_end);
        }

        $data = $query->groupBy('handover_date', 'warehouse_id', 'sales_id')
            ->orderBy('handover_date', 'desc')
            ->get();

        $filters = [
            'date_start' => $request->date_start,
            'date_end' => $request->date_end,
            'warehouse_name' => $request->warehouse_id ? \App\Models\Warehouse::find($request->warehouse_id)?->warehouse_name : 'All Warehouses'
        ];

        return Excel::download(new PendingSettlementExport($data, 'Pending Warehouse Deposit Report', $filters), 'pending_deposits_' . now()->format('Ymd_His') . '.xlsx');
    }
}
