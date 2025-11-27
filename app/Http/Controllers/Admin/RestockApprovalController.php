<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RequestRestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestockApprovalController extends Controller
{
    public function index()
    {
        // label dinamis
        $supLabel = Schema::hasColumn('suppliers','name')
            ? 'name'
            : (Schema::hasColumn('suppliers','supplier_name') ? 'supplier_name' : 'id');

        $whLabel = Schema::hasColumn('warehouses','warehouse_name')
            ? 'warehouse_name'
            : (Schema::hasColumn('warehouses','name') ? 'name' : 'id');

        $prdLabel = Schema::hasColumn('products','name')
            ? 'name'
            : (Schema::hasColumn('products','product_name') ? 'product_name' : 'id');

        $suppliers  = DB::table('suppliers')
            ->select('id', DB::raw("$supLabel AS label"))
            ->orderBy('label')
            ->get();

        $warehouses = DB::table('warehouses')
            ->select('id', DB::raw("$whLabel AS label"))
            ->orderBy('label')
            ->get();

        $products   = DB::table('products')
            ->select('id', DB::raw("$prdLabel AS label"))
            ->orderBy('label')
            ->get();

        return view('admin.operations.stockRequest', compact('suppliers','warehouses','products'));
    }

    public function json(Request $r)
    {
        $page   = max(1, (int)$r->get('page',1));
        $per    = min(100, max(1,(int)$r->get('per_page',10)));
        $status = $r->get('status');
        $sid    = $r->get('supplier_id');
        $wid    = $r->get('warehouse_id');
        $pid    = $r->get('product_id');
        $q      = trim((string)$r->get('search',''));
        $d1     = $r->get('date_from');
        $d2     = $r->get('date_to');

        $hasWh = Schema::hasColumn('request_restocks','warehouse_id');

        $pName = Schema::hasColumn('products','name')
            ? 'p.name'
            : (Schema::hasColumn('products','product_name') ? 'p.product_name' : "CONCAT('Product #',p.id)");

        $sName = Schema::hasColumn('suppliers','name')
            ? 's.name'
            : (Schema::hasColumn('suppliers','supplier_name') ? 's.supplier_name' : "CONCAT('Supplier #',s.id)");

        $wName = $hasWh
            ? (Schema::hasColumn('warehouses','warehouse_name')
                ? 'w.warehouse_name'
                : (Schema::hasColumn('warehouses','name') ? 'w.name' : "CONCAT('Warehouse #',w.id)"))
            : "NULL";

        $qtyReq = Schema::hasColumn('request_restocks','quantity_requested')
            ? 'rr.quantity_requested'
            : (Schema::hasColumn('request_restocks','qty_requested')
                ? 'rr.qty_requested'
                : (Schema::hasColumn('request_restocks','qty') ? 'rr.qty' : '0'));

        $totalCost = Schema::hasColumn('request_restocks','total_cost')
            ? 'rr.total_cost'
            : "(COALESCE($qtyReq,0) * COALESCE(rr.cost_per_item,0))";

        $noteCol = Schema::hasColumn('request_restocks','note')
            ? 'rr.note'
            : (Schema::hasColumn('request_restocks','description') ? 'rr.description' : "''");

        $base = DB::table('request_restocks as rr')
            ->leftJoin('products as p','p.id','=','rr.product_id')
            ->leftJoin('suppliers as s','s.id','=','rr.supplier_id');

        if ($hasWh) {
            $base->leftJoin('warehouses as w','w.id','=','rr.warehouse_id');
        }

        $base->selectRaw("
            rr.id,
            rr.created_at as request_date,
            $pName   as product_name,
            $sName   as supplier_name,
            $wName   as warehouse_name,
            COALESCE($qtyReq,0) as quantity_requested,
            $totalCost as total_cost,
            COALESCE(rr.status,'pending') as status,
            $noteCol  as description
        ");

        if ($status !== null && $status !== '')     $base->where('rr.status',$status);
        if ($sid)                                   $base->where('rr.supplier_id',$sid);
        if ($pid)                                   $base->where('rr.product_id',$pid);
        if ($wid && $hasWh)                         $base->where('rr.warehouse_id',$wid);
        if ($d1)                                    $base->whereDate('rr.created_at','>=',$d1);
        if ($d2)                                    $base->whereDate('rr.created_at','<=',$d2);

        if ($q !== '') {
            $base->where(function($w) use($q, $hasWh){
                if (Schema::hasColumn('request_restocks','note'))
                    $w->orWhere('rr.note','like',"%$q%");
                if (Schema::hasColumn('request_restocks','description'))
                    $w->orWhere('rr.description','like',"%$q%");
                if (Schema::hasColumn('products','name'))
                    $w->orWhere('p.name','like',"%$q%");
                if (Schema::hasColumn('products','product_name'))
                    $w->orWhere('p.product_name','like',"%$q%");
                if (Schema::hasColumn('suppliers','name'))
                    $w->orWhere('s.name','like',"%$q%");
                if (Schema::hasColumn('suppliers','supplier_name'))
                    $w->orWhere('s.supplier_name','like',"%$q%");
                if ($hasWh) {
                    if (Schema::hasColumn('warehouses','warehouse_name'))
                        $w->orWhere('w.warehouse_name','like',"%$q%");
                    if (Schema::hasColumn('warehouses','name'))
                        $w->orWhere('w.name','like',"%$q%");
                }
            });
        }

        $total = (clone $base)->count();
        $rows  = $base->orderByDesc('rr.id')->forPage($page,$per)->get();

        return response()->json([
            'data' => $rows,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $per,
                'last_page' => (int) ceil($total / $per),
                'total'     => $total,
            ]
        ]);
    }

    // ====== REJECT SATUAN ======
    public function reject(Request $r, $id)
    {
        $reason = (string)($r->input('reason') ?? '');
        $note   = $reason ? '[REJECT] '.$reason : '[REJECT]';

        $updated = DB::table('request_restocks')->where('id',$id)->update([
            'status'     => 'cancelled',
            'note'       => $note,
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok'      => (bool) $updated,
            'message' => $updated ? 'Rejected' : 'Not found'
        ], $updated ? 200 : 404);
    }

    // ====== BULK REVIEW → PO ======
        public function bulkPO(Request $r)
        {
            $payloadIds = $r->input('ids', $r->input('stock_request_ids', []));
            if (!is_array($payloadIds) || count($payloadIds) === 0) {
                return response()->json(['error' => 'Pilih minimal satu request.'], 200);
            }

            $ids = collect($payloadIds)->map(fn($v) => (int)$v)->filter()->unique()->values()->all();
            if (empty($ids)) {
                return response()->json(['error' => 'Data request tidak valid.'], 200);
            }

            // request yang sudah punya PO → skip
            $alreadyInPo = [];
            if (
                Schema::hasTable('purchase_order_items') &&
                Schema::hasColumn('purchase_order_items', 'request_id')
            ) {
                $alreadyInPo = PurchaseOrderItem::whereIn('request_id', $ids)
                    ->pluck('request_id')
                    ->unique()
                    ->all();
            }

            $eligibleIds = array_diff($ids, $alreadyInPo);
            if (empty($eligibleIds)) {
                return response()->json([
                    'error' => 'Semua request terpilih sudah punya PO.'
                ], 200);
            }

            // hanya PENDING yang boleh dibuat PO
            $requests = RequestRestock::with(['product', 'supplier', 'warehouse'])
                ->whereIn('id', $eligibleIds)
                ->where('status', 'pending')
                ->get();

            if ($requests->isEmpty()) {
                return response()->json([
                    'error' => 'Tidak ada request berstatus pending.'
                ], 200);
            }

            $pendingIds    = $requests->pluck('id')->all();
            $skippedStatus = array_diff($eligibleIds, $pendingIds);
            $createdPoIds  = [];

            try {
                DB::transaction(function () use (&$createdPoIds, $requests) {

                    // ======================================
                    // GROUP PER WAREHOUSE (1 warehouse = 1 PO)
                    // ======================================
                    $grouped = $requests->groupBy(function ($rr) {
                        return (int)($rr->warehouse_id ?? 0);
                    });

                    foreach ($grouped as $warehouseId => $rows) {
                        /** @var \Illuminate\Support\Collection $rows */
                        $first = $rows->first();

                        $po = new PurchaseOrder();

                        // header supplier → ambil dari request pertama (boleh diabaikan kalau nggak penting)
                        if (Schema::hasColumn('purchase_orders', 'supplier_id')) {
                            $po->supplier_id = $first->supplier_id ?? null;
                        }

                        if (Schema::hasColumn('purchase_orders', 'warehouse_id')) {
                            $po->warehouse_id = $warehouseId ?: null;
                        }

                        $po->ordered_by = auth()->id();
                        $po->status     = 'draft';

                        $code = 'PO-' . now()->format('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $po->po_code = $code;

                        $po->subtotal       = 0;
                        $po->discount_total = 0;
                        $po->grand_total    = 0;
                        $po->save();

                        $subtotal = 0;

                        foreach ($rows as $rr) {
                            $qty = (int) ($rr->quantity_requested
                                ?? $rr->qty_requested
                                ?? $rr->qty
                                ?? 0);

                            // harga: pakai products.selling_price, fallback ke request_restocks.cost_per_item
                            $unitPrice = 0;
                            if (Schema::hasColumn('products', 'selling_price') && $rr->product) {
                                $unitPrice = (float)$rr->product->selling_price;
                            } elseif (Schema::hasColumn('request_restocks', 'cost_per_item')) {
                                $unitPrice = (float)($rr->cost_per_item ?? 0);
                            }

                            $lineTotal = $qty * $unitPrice;

                            $item = $po->items()->create([
                                'request_id'    => $rr->id,
                                'product_id'    => $rr->product_id,
                                'warehouse_id'  => $rr->warehouse_id ?? ($warehouseId ?: null),
                                'qty_ordered'   => $qty,
                                'qty_received'  => 0,
                                'unit_price'    => $unitPrice,
                                'discount_type' => null,
                                'discount_value'=> 0,
                                'line_total'    => $lineTotal,
                            ]);

                            $subtotal += $item->line_total;

                            // update status request → approved (label REVIEW di UI)
                            $updateReq = [
                                'status'     => 'approved',
                                'updated_at' => now(),
                            ];
                            if (Schema::hasColumn('request_restocks', 'approved_by')) {
                                $updateReq['approved_by'] = auth()->id();
                            }
                            if (Schema::hasColumn('request_restocks', 'approved_at')) {
                                $updateReq['approved_at'] = now();
                            }
                            if (Schema::hasColumn('request_restocks', 'quantity_requested')) {
                                $updateReq['quantity_requested'] = $qty;
                            }
                            if (Schema::hasColumn('request_restocks', 'cost_per_item')) {
                                $updateReq['cost_per_item'] = $unitPrice;
                            }
                            if (Schema::hasColumn('request_restocks', 'total_cost')) {
                                $updateReq['total_cost'] = $lineTotal;
                            }

                            DB::table('request_restocks')
                                ->where('id', $rr->id)
                                ->update($updateReq);
                        }

                        $po->subtotal       = $subtotal;
                        $po->discount_total = 0;
                        $po->grand_total    = $subtotal;
                        $po->save();

                        $createdPoIds[] = $po->id;
                    }
                });
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => 'Gagal membuat PO: ' . $e->getMessage()
                ], 200);
            }

            $msg = 'PO dibuat dari request restock.';
            if (!empty($alreadyInPo)) {
                $msg .= ' Request yang sudah punya PO dilewati: ' . implode(', ', $alreadyInPo) . '.';
            }
            if (!empty($skippedStatus)) {
                $msg .= ' Request dengan status bukan pending dilewati: ' . implode(', ', $skippedStatus) . '.';
            }

            if (count($createdPoIds) === 1) {
                return response()->json([
                    'redirect' => route('po.edit', $createdPoIds[0]),
                    'message'  => $msg,
                ], 200);
            }

            return response()->json([
                'redirect' => route('po.index'),
                'message'  => $msg . ' Total PO: ' . count($createdPoIds) . '.',
            ], 200);
        }

}
