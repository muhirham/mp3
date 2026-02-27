<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\SalesHandover;
use App\Models\SalesHandoverItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReturnController extends Controller
{
    /**
     * SALES VIEW
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $isAdmin = $user->hasRole(['admin','superadmin']);

        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfDay();

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $query = SalesReturn::with(['handover','sales'])
            ->whereBetween('created_at', [$from, $to]);

        // 🔥 Kalau sales biasa → lock ke dirinya
        if (!$isAdmin) {
            $query->where('sales_id', $user->id);
        }

        // 🔥 Kalau admin bisa filter warehouse
        if ($isAdmin && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // 🔥 Filter sales (dropdown kedua)
        if ($isAdmin && $request->sales_id) {
            $query->where('sales_id', $request->sales_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $groupedReturns  = $query->get()
            ->groupBy('handover_id')
            ->map(function ($items) {

                $first = $items->first();

                if ($items->contains('status','pending')) {
                    $status = 'pending';
                } elseif ($items->contains('status','rejected')) {
                    $status = 'rejected';
                } else {
                    $status = 'approved';
                }

                return [
                    'items'  => $items,
                    'status' => $status,
                    'date'   => $first->created_at,
                    'sales'  => $first->sales,
                    'handover' => $first->handover,
                ];
            });

        $warehouses = $isAdmin
            ? Warehouse::orderBy('warehouse_name')->get()
            : collect();

                    // 🔥 HANDOVERS (buat form create return)
                if (!$isAdmin) {
                    $handovers = SalesHandover::where('sales_id', $user->id)
                        ->where('status', 'closed')
                        ->whereDoesntHave('salesReturns', function ($q) {
                            $q->whereIn('status', ['pending','approved']);
                        })
                        ->orderByDesc('handover_date')
                        ->get();
                } else {

                    // kalau admin belum pilih sales → kosongin dulu
                    if ($request->sales_id) {
                        $handovers = SalesHandover::where('sales_id', $request->sales_id)
                            ->where('status', 'closed')
                            ->whereDoesntHave('salesReturns', function ($q) {
                                $q->whereIn('status', ['pending','approved']);
                            })
                            ->orderByDesc('handover_date')
                            ->get();
                    } else {
                        $handovers = collect();
                    }
                }

        return view('sales.sales_returns', compact(
            'groupedReturns',
            'warehouses',
            'isAdmin',
            'handovers'
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
            'note'      => 'nullable|string|max:500',
        ]);

        // ❌ BLOCK kalau masih ada pending atau approved
        $blocked = SalesReturn::where('handover_id', $data['handover_id'])
            ->whereIn('status', ['pending','approved'])
            ->exists();

        if ($blocked) {
            return back()->with('error','Return sedang diproses atau sudah disetujui.');
        }

        DB::transaction(function () use ($data, $me) {

            foreach ($data['items'] as $row) {

                $remaining = (int) $row['remaining'];
                $damaged   = (int) ($row['damaged'] ?? 0);
                $expired   = (int) ($row['expired'] ?? 0);
                $good      = $remaining - $damaged - $expired;

                if ($good < 0) {
                    throw new \Exception("Qty tidak valid");
                }

                foreach ([
                    'good'    => $good,
                    'damaged' => $damaged,
                    'expired' => $expired,
                ] as $condition => $qty) {

                    if ($qty > 0) {
                        SalesReturn::create([
                        'sales_id'     => $me->id,
                        'warehouse_id' => $me->warehouse_id,
                        'handover_id'  => $data['handover_id'],
                        'product_id'   => $row['product_id'],
                        'quantity'     => $qty,
                        'condition'    => $condition,
                        'status'       => 'pending',
                        'reason' => $data['note'] ?? null,
                    ]);
                    }
                }
            }
        });

        return back()->with('success','Return berhasil diajukan.');
    }
    /**
     * WAREHOUSE VIEW
     */
    public function approvalList(Request $request)
    {
        $user = auth()->user();

        $canSwitchWarehouse = $user->hasRole(['admin', 'superadmin']);
        $isWarehouseUser    = $user->hasRole('warehouse');

        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfDay();

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $query = SalesReturn::with([
            'sales',
            'handover',
            'approvedByUser'
        ])
        ->whereBetween('created_at', [$from, $to]);

        // 🔥 ROLE LOGIC
        if ($isWarehouseUser) {
            $query->where('warehouse_id', $user->warehouse_id);
        } elseif ($canSwitchWarehouse && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $returns = $query->orderByDesc('created_at')
            ->get()
            ->groupBy('handover_id')
            ->map(function ($items) {

                $first = $items->first();

                if ($items->contains('status','pending')) {
                    $status = 'pending';
                } elseif ($items->contains('status','rejected')) {
                    $status = 'rejected';
                } else {
                    $status = 'approved';
                }

                return [
                    'items'    => $items,
                    'status'   => $status,
                    'sales'    => $first->sales,
                    'handover' => $first->handover,
                    'date'     => $first->created_at,
                ];
            });

        $warehouses = $canSwitchWarehouse
            ? \App\Models\Warehouse::orderBy('warehouse_name')->get()
            : collect();

        return view('wh.approval_sales_returns', compact(
            'returns',
            'warehouses',
            'canSwitchWarehouse'
        ));
    }

    public function detailHdo($handoverId)
    {
        return SalesReturn::with(['sales','product','approvedByUser','handover'])
            ->where('handover_id',$handoverId)
            ->get();
    }

    public function approve(SalesReturn $salesReturn)
    {
        DB::transaction(function () use ($salesReturn) {

            $salesReturn = SalesReturn::lockForUpdate()
                ->where('id', $salesReturn->id)
                ->first();

            if ($salesReturn->status !== 'pending') {
                throw new \Exception("Sudah diproses");
            }

            if ($salesReturn->condition === 'good') {

                $stock = DB::table('stock_levels')
                    ->where('owner_type','warehouse')
                    ->where('owner_id',$salesReturn->warehouse_id)
                    ->where('product_id',$salesReturn->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    DB::table('stock_levels')
                        ->where('id',$stock->id)
                        ->update([
                            'quantity' => $stock->quantity + $salesReturn->quantity,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('stock_levels')->insert([
                        'owner_type' => 'warehouse',
                        'owner_id'   => $salesReturn->warehouse_id,
                        'product_id' => $salesReturn->product_id,
                        'quantity'   => $salesReturn->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $salesReturn->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('success','Return berhasil di-approve.');
    }
    
    public function reject(Request $request, SalesReturn $salesReturn)
    {
        if ($salesReturn->status !== 'pending') {
            return back()->with('error','Return sudah diproses.');
        }

        $request->validate([
            'reject_reason' => 'required|string|max:500'
        ]);

        $salesReturn->update([
            'status' => 'rejected',
            'reason' => $request->reject_reason,
        ]);

        return back()->with('success','Return ditolak.');
    }

    public function getRejected($handoverId)
    {
        $returns = SalesReturn::with('product')
            ->where('handover_id', $handoverId)
            ->where('status','rejected')
            ->get();

        $grouped = [];

        foreach ($returns as $r) {
            $grouped[$r->product_id][] = [
                'condition' => $r->condition,
                'quantity'  => $r->quantity,
                'product'   => [
                    'name' => $r->product ? $r->product->name : ''
                ]
            ];
        }

        return response()->json($grouped);
    }

    public function updateRejected(Request $request, $handoverId)
    {
        $request->validate([
            'items' => 'required|array|min:1'
        ]);

            $filtered = collect($request->items)
        ->filter(function ($row) {
            return ($row['good'] ?? 0) > 0
                || ($row['damaged'] ?? 0) > 0
                || ($row['expired'] ?? 0) > 0;
        });

    if ($filtered->isEmpty()) {
        return back()->with('error','Minimal satu qty harus diisi.');
    }

        DB::transaction(function () use ($request, $handoverId) {

            foreach ($request->items as $productId => $conditions) {

                foreach (['good','damaged','expired'] as $condition) {

                    $qty = (int) ($conditions[$condition] ?? 0);

                    $return = SalesReturn::where('handover_id', $handoverId)
                        ->where('product_id', $productId)
                        ->where('condition', $condition)
                        ->where('status', 'rejected')
                        ->lockForUpdate()
                        ->first();

                    if ($return) {

                        // Kalau qty jadi 0 → hapus record
                        if ($qty <= 0) {
                            $return->delete();
                            continue;
                        }

                        $return->update([
                            'quantity' => $qty,
                            'status'   => 'pending',
                            'reason' => $request->note
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Return berhasil di-resubmit.');
    }

    public function getHdoDetails($handoverId)
    {
        return SalesReturn::with([
            'product',
            'approvedByUser',
            'handover'
        ])
        ->where('handover_id', $handoverId)
        ->orderBy('created_at')
        ->get();
    }

    public function approveAll($handoverId)
    {
        DB::transaction(function () use ($handoverId) {

            $items = SalesReturn::where('handover_id',$handoverId)
                ->where('status','pending')
                ->lockForUpdate()
                ->get();

            foreach ($items as $salesReturn) {

                if ($salesReturn->condition === 'good') {

                    $stock = DB::table('stock_levels')
                        ->where('owner_type','warehouse')
                        ->where('owner_id',$salesReturn->warehouse_id)
                        ->where('product_id',$salesReturn->product_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        DB::table('stock_levels')
                            ->where('id',$stock->id)
                            ->update([
                                'quantity' => $stock->quantity + $salesReturn->quantity,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('stock_levels')->insert([
                            'owner_type' => 'warehouse',
                            'owner_id'   => $salesReturn->warehouse_id,
                            'product_id' => $salesReturn->product_id,
                            'quantity'   => $salesReturn->quantity,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $salesReturn->update([
                    'status'      => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }
        });

        return back()->with('success','Semua return berhasil di-approve.');
    }

    public function filterAjax(Request $request)
    {
        $me = auth()->user();

        $query = SalesReturn::with(['handover','sales']);

        // 🔥 APPLY TANGGAL HANYA KALAU DIISI
        if ($request->from && $request->to) {

            $from = Carbon::parse($request->from)->startOfDay();
            $to   = Carbon::parse($request->to)->endOfDay();

            $query->whereBetween('created_at', [$from, $to]);
        }

        // 🔥 LOCK SALES
        if (!$me->hasRole(['admin','superadmin'])) {
            $query->where('sales_id', $me->id);
        }

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->sales_id) {
            $query->where('sales_id', $request->sales_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $returns = $query->get()
            ->groupBy('handover_id')
            ->map(function ($items) {

                $first = $items->first();

                if ($items->contains('status','pending')) {
                    $status = 'pending';
                } elseif ($items->contains('status','rejected')) {
                    $status = 'rejected';
                } else {
                    $status = 'approved';
                }

                return [
                    'handover_code' => optional($first->handover)->code ?? '-',
                    'total_items'   => $items->count(),
                    'status'        => $status,
                    'date'          => optional($first->created_at)->format('d M Y'),
                    'handover_id'   => $first->handover_id
                ];
            })
            ->values();

        return response()->json($returns);
    }

    public function filterAjaxWhApproved(Request $request)
    {
        $user = auth()->user();

        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfDay();

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $query = SalesReturn::with(['handover','sales'])
            ->whereBetween('created_at', [$from, $to]);

        // ✅ SAMA dengan approvalList
            $canSwitchWarehouse = $user->hasRole(['admin','superadmin']);
            $isWarehouseUser    = $user->hasRole('warehouse');

            if ($isWarehouseUser) {
                $query->where('warehouse_id', $user->warehouse_id);
            } elseif ($canSwitchWarehouse && $request->warehouse_id) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $returns = $query->get()
            ->groupBy('handover_id')
            ->map(function ($items) {

                $first = $items->first();

                if (!$first) return null;

                if ($items->contains('status','pending')) {
                    $status = 'pending';
                } elseif ($items->contains('status','rejected')) {
                    $status = 'rejected';
                } else {
                    $status = 'approved';
                }

                return [
                    'handover_code' => optional($first->handover)->code ?? '-',
                    'sales_name'    => optional($first->sales)->name ?? '-',
                    'total_items'   => $items->count(),
                    'status'        => $status,
                    'date'          => optional($first->created_at)->format('d M Y H:i') ?? '-',
                    'handover_id'   => $first->handover_id,
                ];
            })
            ->filter()
            ->values();

        return response()->json($returns);
    }

    public function getSalesByWarehouse($warehouseId)
    {
        return \App\Models\User::where('warehouse_id',$warehouseId)
            ->whereHas('roles', fn($q) => $q->where('name','sales'))
            ->select('id','name')
            ->orderBy('name')
            ->get();
    }
}