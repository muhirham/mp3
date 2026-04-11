<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class StockRequestController extends Controller
{
    public function index(Request $request)
    {
        $me = auth()->user();

        $canSwitchWarehouse = $me->hasRole('superadmin');

        if ($canSwitchWarehouse) {
            $warehouses = Warehouse::orderBy('warehouse_name')->get();
            $selectedWarehouseId = $request->warehouse_id;
        } else {
            $warehouses = Warehouse::where('id', $me->warehouse_id)->get();
            $selectedWarehouseId = $me->warehouse_id;
        }

        $products = Product::all();

        return view('sales.stock_requests', compact(
            'warehouses',
            'products',
            'selectedWarehouseId',
            'canSwitchWarehouse'
        ));
    }

    public function filter(Request $request)
    {
        $me = auth()->user();

        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        $query = DB::table('stock_requests')
            ->join('warehouses', 'stock_requests.warehouse_id', '=', 'warehouses.id')
            ->select(
                'stock_requests.warehouse_id',
                'warehouses.warehouse_name',
                DB::raw("DATE_FORMAT(stock_requests.created_at, '%Y-%m-%d %H:%i') as group_time"),
                DB::raw("COUNT(*) as item_count"),
                DB::raw("CASE 
                    WHEN SUM(CASE WHEN stock_requests.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                    WHEN SUM(CASE WHEN stock_requests.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                    WHEN SUM(CASE WHEN stock_requests.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'completed'
                    ELSE 'rejected'
                END as max_status")
            )
            ->where('stock_requests.user_id', $me->id);

        if (!$me->hasRole('superadmin')) {
            $query->where('stock_requests.warehouse_id', $me->warehouse_id);
        }

        if ($dateFrom && $dateTo) {
            $query->whereBetween('stock_requests.created_at', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if ($request->search['value'] ?? null) {
            $search = $request->search['value'];
            $query->where('warehouses.warehouse_name', 'like', "%$search%");
        }

        $query->groupBy('stock_requests.warehouse_id', 'warehouses.warehouse_name', 'group_time');

        $totalData = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $results = $query->orderByDesc('group_time')
            ->offset($request->start)
            ->limit($request->length)
            ->get();

        $data = $results->map(function ($row) use ($me) {
            $statusBadge = 'bg-warning';
            if ($row->max_status === 'approved') $statusBadge = 'bg-success';
            if ($row->max_status === 'rejected') $statusBadge = 'bg-danger';

            return [
                'date'         => $row->group_time,
                'warehouse'    => $row->warehouse_name,
                'count'        => "{$row->item_count} Item",
                'status'       => '<span class="badge ' . $statusBadge . '">' . strtoupper($row->max_status) . '</span>',
                'actions'      => '<button class="btn btn-sm btn-outline-primary detailBtn" data-group="' . "{$me->id}_{$row->warehouse_id}_{$row->group_time}" . '">Detail</button>'
            ];
        });

        return response()->json([
            'draw'            => intval($request->draw),
            'recordsTotal'    => $totalData,
            'recordsFiltered' => $totalData,
            'data'            => $data
        ]);
    }

    public function store(Request $request)
    {
        $me = auth()->user();

        $warehouseId = $me->hasRole('superadmin')
            ? $request->warehouse_id
            : $me->warehouse_id;

        $request->validate([
            'product_id.*' => 'required|exists:products,id',
            'quantity.*'   => 'required|integer|min:1',
            'note.*'       => 'nullable|string',
        ]);

        foreach ($request->product_id as $i => $productId) {

            $qty  = $request->quantity[$i] ?? null;
            $note = $request->note[$i] ?? null;

            if (!$productId || !$qty) {
                continue;
            }

            // Discussion result: Always create new records to preserve history
            StockRequest::create([
                'user_id'            => $me->id,
                'warehouse_id'       => $warehouseId,
                'product_id'         => $productId,
                'quantity_requested' => $qty,
                'status'             => 'pending',
                'note'               => $note
            ]);
        }

        return redirect()
            ->route('sales-request.index')
            ->with('success', 'Request created successfully');
    }
}