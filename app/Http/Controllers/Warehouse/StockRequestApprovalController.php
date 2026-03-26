<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class StockRequestApprovalController extends Controller
{
    public function index(Request $request)
    {
            $me = auth()->user();

            $query = StockRequest::with('product','warehouse','user')
                ->whereIn('status', ['pending','approved','rejected']);

            if (!$me->hasRole(['admin', 'superadmin'])) {
                $query->where('warehouse_id', $me->warehouse_id);
            }

            if ($me->hasRole(['admin','superadmin']) && filled($request->warehouse_id)) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

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
        $warehouses = $me->hasRole(['admin','superadmin'])
            ? Warehouse::orderBy('warehouse_name')->get()
            : collect();

        return view('wh.approval_stock_requests', compact('requests','warehouses','me'));
    }

    public function approve(Request $request, $id)
    {
        $handover = DB::transaction(function () use ($id, $request) {

            $stockRequest = StockRequest::with('product', 'user')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($stockRequest->status !== 'pending') {
                abort(400, 'Already processed');
            }

            // =========================
            // LIMIT HDO AKTIF MAX 3
            // =========================
            $activeCount = SalesHandover::where('sales_id', $stockRequest->user_id)
                ->whereIn('status', [
                    'waiting_morning_otp',
                    'on_sales',
                    'waiting_evening_otp'
                ])
                ->lockForUpdate()
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
            // SELALU BUAT HDO BARU
            // =========================
            $handover = null;

            if ($request->handover_id) {
                $handover = SalesHandover::lockForUpdate()
                    ->where('id', $request->handover_id)
                    ->where('status', 'draft')
                    ->first();
            }

            $today = now()->format('ymd');

            $last = SalesHandover::whereDate('handover_date', today())
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $next = $last
                ? ((int) substr($last->code, -4)) + 1
                : 1;

            $code = 'HDO-' . $today . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);

                if (!$handover) {

                    $today = now()->format('ymd');

                    $last = SalesHandover::whereDate('handover_date', today())
                        ->lockForUpdate()
                        ->latest('id')
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
                        'status' => 'draft',
                        'issued_by'     => auth()->id(),
                    ]);
                }

            // =========================
            // ITEM MASUK HDO
            // =========================
            $price = $stockRequest->product->selling_price ?? 0;
            $qty   = $stockRequest->quantity_requested;

            $existing = SalesHandoverItem::where('handover_id',$handover->id)
                ->where('product_id',$stockRequest->product_id)
                ->first();

            if($existing){
                $existing->increment('qty_start',$qty);
            } else {
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
            }
                
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
            ->whereRaw("DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') = ?", [$time]);

        if (!auth()->user()->hasRole(['admin','superadmin'])) {
            $items->where('warehouse_id', auth()->user()->warehouse_id);
        }

        $items = $items->orderBy('id')->get();

        return response()->json($items);
    }
}