<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class StockRequestApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = StockRequest::with('product','warehouse','user')
            ->whereIn('status', ['pending','approved','rejected']);

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        if ($request->search) {

            $search = $request->search;

            $query->where(function($q) use ($search){
                $q->whereHas('user', fn($x)=>$x->where('name','like',"%$search%"))
                ->orWhereHas('product', fn($x)=>$x->where('name','like',"%$search%"))
                ->orWhereHas('warehouse', fn($x)=>$x->where('warehouse_name','like',"%$search%"));
            });
        }

        $requests = $query->latest()->get()
                ->groupBy(function ($item) {
                    return $item->user_id . '_' .
                        $item->warehouse_id . '_' .
                        $item->created_at->format('Y-m-d H:i');
                });

            if ($request->ajax()) {
            return response()->json(
            $requests->map(function($group){

                $first = $group->first();

                return [
                    'ids' => $group->pluck('id'),
                    'date' => $first->created_at->format('Y-m-d H:i'),
                    'sales' => $first->user->name,
                    'warehouse' => $first->warehouse->warehouse_name,
                    'count' => $group->count(),
                    'items' => $group->values()
                ];
            })->values()
        );
    }

        return view('wh.approval_stock_requests', compact('requests'));
    }

    public function approve($id)
    {
        $handover = DB::transaction(function () use ($id) {

            $stockRequest = StockRequest::with('product', 'user')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($stockRequest->status !== 'pending') {
                abort(400, 'Already processed');
            }

            // =========================
            // CEK HDO AKTIF SALES
            // =========================
            $activeCount = SalesHandover::where('sales_id', $stockRequest->user_id)
                ->whereIn('status', [
                    'waiting_morning_otp',
                    'active',
                    'waiting_return'
                ])
                ->count();

            if ($activeCount >= 3) {
                abort(400, 'Sales sudah punya 3 HDO aktif, selesaikan dulu.');
            }

            // =========================
            // CEK STOCK
            // =========================
            $stock = StockLevel::where('product_id', $stockRequest->product_id)
                ->where('owner_id', $stockRequest->warehouse_id)
                ->where('owner_type', 'warehouse')
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->quantity < $stockRequest->quantity_requested) {
                abort(400, 'Stock tidak cukup');
            }

            // =========================
            // BUAT HDO BARU
            // =========================
            $today = now()->format('ymd');

            $last = SalesHandover::whereDate('handover_date', today())
                ->latest()
                ->first();

            $next = $last
                ? ((int) substr($last->code, -4)) + 1
                : 1;

            $code = 'HDO-' . $today . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);

            $handover = SalesHandover::create([
                'code'          => $code,
                'warehouse_id'  => $stockRequest->warehouse_id,
                'sales_id'      => $stockRequest->user_id,
                'handover_date' => today(),
                'status'        => 'waiting_morning_otp',
                'issued_by'     => auth()->id(),
            ]);

            // =========================
            // ITEM MASUK HDO
            // =========================
            $price = $stockRequest->product->selling_price ?? 0;
            $qty   = $stockRequest->quantity_requested;

            SalesHandoverItem::create([
                'handover_id'               => $handover->id,
                'product_id'                => $stockRequest->product_id,
                'qty_start'                 => $qty,
                'qty_returned'              => 0,
                'qty_sold'                  => 0,
                'unit_price'                => $price,
                'discount_per_unit'         => 0,
                'unit_price_after_discount' => $price,
                'line_total_start'          => $price * $qty,
                'discount_total'            => 0,
                'line_total_after_discount' => $price * $qty,
                'line_total_sold'           => 0,
            ]);

            // =========================
            // STOCK MOVEMENT
            // =========================
            $stock->decrement('quantity', $qty);

            StockMovement::create([
                'product_id'     => $stockRequest->product_id,
                'warehouse_id'   => $stockRequest->warehouse_id,
                'quantity'       => -$qty,
                'movement_type'  => 'sales_request',
                'reference_id'   => $handover->id,
                'reference_type' => 'sales_handover'
            ]);

            // =========================
            // UPDATE REQUEST
            // =========================
            $stockRequest->update([
                'status'            => 'approved',
                'approved_by'       => auth()->id(),
                'quantity_approved' => $qty,
                'sales_handover_id' => $handover->id,
            ]);

            return $handover;
        });

        return response()->json([
            'success' => true,
            'handover_id' => $handover->id
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'approval_note' => 'required'
        ]);

        $stockRequest = StockRequest::findOrFail($id);

        if ($stockRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Already processed'
            ], 400);
        }

        $oldNote = $stockRequest->note ?? '';

        $rejectNote = "\n\n[REJECTED by " . auth()->user()->name .
            " | " . now()->format('Y-m-d H:i') . "]\n" .
            $request->approval_note;

        $stockRequest->update([
            'status'      => 'rejected',
            'approved_by' => auth()->id(),
            'note'        => $oldNote . $rejectNote
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request ditolak'
        ]);
    }
    public function detail(Request $request)
    {
        [$userId, $warehouseId, $time] = explode('_', $request->group, 3);

        $items = StockRequest::with('product')
            ->where('user_id', $userId)
            ->where('warehouse_id', $warehouseId)
            ->whereRaw("DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') = ?", [$time])
            ->get();

        return response()->json($items);
    }
}