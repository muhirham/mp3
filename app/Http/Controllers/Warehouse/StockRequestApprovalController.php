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

        $dateFrom = $request->date_from ?? now()->toDateString();
        $dateTo   = $request->date_to ?? now()->toDateString();

        $warehouses = $me->hasRole('superadmin')
            ? Warehouse::orderBy('warehouse_name')->get()
            : collect();

        return view('wh.approval_stock_requests', compact(
            'warehouses',
            'me',
            'dateFrom',
            'dateTo'
        ));
    }

    public function filter(Request $request)
    {
        $me = auth()->user();

        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        // Base query for grouping
        $query = DB::table('stock_requests')
            ->join('users', 'stock_requests.user_id', '=', 'users.id')
            ->join('warehouses', 'stock_requests.warehouse_id', '=', 'warehouses.id')
            ->select(
                'stock_requests.user_id',
                'stock_requests.warehouse_id',
                'users.name as sales_name',
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
            ->whereIn('stock_requests.status', ['pending', 'approved', 'rejected']);

        if (!$me->hasRole('superadmin')) {
            $query->where('stock_requests.warehouse_id', $me->warehouse_id);
        } elseif ($request->warehouse_id) {
            $query->where('stock_requests.warehouse_id', $request->warehouse_id);
        }

        if ($dateFrom && $dateTo) {
            $query->whereBetween('stock_requests.created_at', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if ($request->search['value'] ?? null) {
            $search = $request->search['value'];
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%$search%")
                    ->orWhere('warehouses.warehouse_name', 'like', "%$search%");
            });
        }

        $query->groupBy('stock_requests.user_id', 'stock_requests.warehouse_id', 'users.name', 'warehouses.warehouse_name', 'group_time');

        // Untuk pegination di grouped query, kita bungkus lagi
        $totalData = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $results = $query->orderByDesc('group_time')
            ->offset($request->start)
            ->limit($request->length)
            ->get();

        $data = $results->map(function ($row) {
            return [
                'group_key' => "{$row->user_id}_{$row->warehouse_id}_{$row->group_time}",
                'date'      => fm_relativedate($row->group_time),
                'sales'     => $row->sales_name,
                'warehouse' => $row->warehouse_name,
                'count'     => "{$row->item_count} Item",
                'status'    => $row->max_status,
                'actions'   => '<button class="btn btn-primary btn-sm detailBtn" data-group="' . "{$row->user_id}_{$row->warehouse_id}_{$row->group_time}" . '">Detail</button>'
            ];
        });

        return response()->json([
            'draw'            => intval($request->draw),
            'recordsTotal'    => $totalData,
            'recordsFiltered' => $totalData, // search sudah masuk di query utama
            'data'            => $data
        ]);
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
                abort(400, 'Sales already has 3 active handovers, please complete them first.');
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
                abort(400, 'Insufficient stock');
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

            // Tembak sinyal real-time!
            broadcast(new \App\Events\StockRequestUpdated());

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

        // Tembak sinyal real-time!
        broadcast(new \App\Events\StockRequestUpdated());

        return response()->json([
            'success' => true,
            'message' => 'Request rejected'
        ]);
    }
    public function detail(Request $request)
    {
        [$userId, $warehouseId, $time] = explode('_', $request->group, 3);

        $items = StockRequest::with('product')
            ->where('user_id', $userId)
            ->where('warehouse_id', $warehouseId)
            ->whereRaw("DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') = ?", [$time]);

        if (!auth()->user()->hasRole('superadmin')) {
            $items->where('warehouse_id', auth()->user()->warehouse_id);
            
            // SECURITY: if sales, they can only see their own items
            if (auth()->user()->hasRole('sales')) {
                $items->where('user_id', auth()->user()->id);
            }
        }

        $items = $items->orderBy('id')->get();

        return response()->json($items);
    }
}