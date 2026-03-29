<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class StockRequestController extends Controller
{
    public function index(Request $request)
    {
        $me = auth()->user();

        $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);

        $query = StockRequest::with('product', 'warehouse')
            ->where('user_id', $me->id);

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        } else {
            $query->whereDate('created_at', now()->toDateString());
        }

        if ($request->search) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($x) =>
                    $x->where('name', 'like', "%$search%"))
                ->orWhereHas('warehouse', fn($x) =>
                    $x->where('warehouse_name', 'like', "%$search%"));
            });
        }

        $requests = $query->latest()->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d H:i:s');
            })
            ->map(function ($group) {

                $statuses = $group->pluck('status');

                if ($statuses->contains('pending')) {
                    $group->final_status = 'pending';
                } elseif ($statuses->every(fn($s) => $s === 'rejected')) {
                    $group->final_status = 'rejected';
                } else {
                    $group->final_status = 'completed';
                }

                return $group;
            });

        if ($request->ajax()) {

            $data = $requests->map(function ($group, $date) {

                return [
                    'date' => $date,
                    'warehouse' => $group->first()->warehouse->warehouse_name,
                    'count' => $group->count(),
                    'final_status' => $group->final_status,
                    'items' => $group->values()
                ];
            })->values();

            return response()->json($data);
        }

        if ($canSwitchWarehouse) {
            $warehouses = Warehouse::orderBy('warehouse_name')->get();
            $selectedWarehouseId = $request->warehouse_id;
        } else {
            $warehouses = Warehouse::where('id', $me->warehouse_id)->get();
            $selectedWarehouseId = $me->warehouse_id;
        }

        $products = Product::all();

        return view('sales.stock_requests', compact(
            'requests',
            'warehouses',
            'products',
            'selectedWarehouseId',
            'canSwitchWarehouse'
        ));
    }

    public function store(Request $request)
    {
        $me = auth()->user();

        $warehouseId = $me->hasRole(['admin', 'superadmin'])
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

            $lastRejected = StockRequest::where('user_id', $me->id)
                ->where('product_id', $productId)
                ->where('status', 'rejected')
                ->latest()
                ->first();

            if ($lastRejected && $lastRejected->created_at->diffInHours(now()) < 24) {

                $lastRejected->update([
                    'status' => 'pending',
                    'quantity_requested' => $qty,
                    'note' => $lastRejected->note .
                        "\n\n[RESUBMIT " . now()->format('Y-m-d H:i') . "]\n" .
                        $note
                ]);

                continue;
            }

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