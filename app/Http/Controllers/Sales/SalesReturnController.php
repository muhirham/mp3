<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    /**
     * SALES VIEW
     */
    public function index()
    {
        $me = auth()->user();

        // hanya HDO yang sudah closed
        $handovers = SalesHandover::where('sales_id', $me->id)
            ->where('status', 'closed')
            ->orderByDesc('handover_date')
            ->get();

        $returns = SalesReturn::with('product')
            ->where('sales_id', $me->id)
            ->latest()
            ->get();

        return view('sales.sales_returns', compact(
            'handovers',
            'returns'
        ));
    }

    /**
     * AJAX LOAD ITEMS SISA
     */
    public function loadItems($handoverId)
    {
        $items = SalesHandoverItem::with('product')
            ->where('handover_id', $handoverId)
            ->get()
            ->map(function ($item) {

                $qtyStart = (int) $item->qty_start;
                $qtySold  = (int) $item->qty_sold;

                $remaining = max(0, $qtyStart - $qtySold);

                if ($remaining <= 0) return null;

                return [
                    'id'         => $item->id,
                    'product'    => $item->product?->name ?? '-',
                    'product_id' => $item->product_id,
                    'remaining'  => $remaining,
                ];
            })
            ->filter()
            ->values();

        return response()->json($items);
    }

    /**
     * STORE MULTI RETURN
     */
    public function store(Request $request)
    {
        $me = auth()->user();

        $data = $request->validate([
            'handover_id' => 'required|exists:sales_handovers,id',
            'items'       => 'required|array|min:1',
        ]);

        DB::transaction(function () use ($data, $me) {

            foreach ($data['items'] as $row) {

                $remaining = (int) $row['remaining'];
                $damaged   = (int) ($row['damaged'] ?? 0);
                $expired   = (int) ($row['expired'] ?? 0);

                $good = $remaining - $damaged - $expired;

                if ($good < 0) {
                    throw new \Exception("Qty tidak valid");
                }

                if ($good > 0) {
                    SalesReturn::create([
                        'sales_id'     => $me->id,
                        'warehouse_id' => $me->warehouse_id,
                        'handover_id'  => $data['handover_id'],
                        'product_id'   => $row['product_id'],
                        'quantity'     => $good,
                        'condition'    => 'good',
                        'status'       => 'pending',
                    ]);
                }

                if ($damaged > 0) {
                    SalesReturn::create([
                        'sales_id'     => $me->id,
                        'warehouse_id' => $me->warehouse_id,
                        'handover_id'  => $data['handover_id'],
                        'product_id'   => $row['product_id'],
                        'quantity'     => $damaged,
                        'condition'    => 'damaged',
                        'status'       => 'pending',
                    ]);
                }

                if ($expired > 0) {
                    SalesReturn::create([
                        'sales_id'     => $me->id,
                        'warehouse_id' => $me->warehouse_id,
                        'handover_id'  => $data['handover_id'],
                        'product_id'   => $row['product_id'],
                        'quantity'     => $expired,
                        'condition'    => 'expired',
                        'status'       => 'pending',
                    ]);
                }
            }
        });

        return back()->with('success', 'Return berhasil diajukan.');
    }

    /**
     * WAREHOUSE VIEW
     */
    public function approvalList()
    {
        $returns = SalesReturn::with(['sales','product','warehouse'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('wh.approval_sales_returns', compact('returns'));
    }

    public function approve(SalesReturn $return)
    {
        if ($return->status !== 'pending') {
            return back()->with('error','Return sudah diproses.');
        }

        DB::transaction(function () use ($return) {

            // kalau GOOD → masuk stok warehouse
            if ($return->condition === 'good') {

                $stock = DB::table('stock_levels')
                    ->where('owner_type','warehouse')
                    ->where('owner_id',$return->warehouse_id)
                    ->where('product_id',$return->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    DB::table('stock_levels')
                        ->where('id',$stock->id)
                        ->update([
                            'quantity' => $stock->quantity + $return->quantity,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('stock_levels')->insert([
                        'owner_type' => 'warehouse',
                        'owner_id'   => $return->warehouse_id,
                        'product_id' => $return->product_id,
                        'quantity'   => $return->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // damaged / expired bisa lu handle beda nanti
            $return->update([
                'status'      => 'received',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('success','Return berhasil di-approve & stok diperbarui.');
    }
    
    public function reject(SalesReturn $return)
    {
        if ($return->status !== 'pending') {
            return back()->with('error','Return sudah diproses.');
        }

        $return->update([
            'status' => 'rejected'
        ]);

        return back()->with('success','Return ditolak.');
    }
}