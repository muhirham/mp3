<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Events\SalesReturnUpdated;
use App\Helpers\NotificationHelper;
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
                        ->whereHas('items', function ($q) {
                            $q->whereColumn('qty_start', '>', 'qty_sold');
                        })
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
                            ->whereHas('items', function ($q) {
                                $q->whereColumn('qty_start', '>', 'qty_sold');
                            })
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
            return back()->with('error','Return is being processed or has already been approved.');
        }

        DB::transaction(function () use ($data, $me) {

            foreach ($data['items'] as $row) {

                $remaining = (int) $row['remaining'];
                $damaged   = (int) ($row['damaged'] ?? 0);
                $expired   = (int) ($row['expired'] ?? 0);
                $good      = $remaining - $damaged - $expired;

                if ($good < 0) {
                    throw new \Exception("Invalid quantity");
                }

                foreach ([
                    'good'    => $good,
                    'damaged' => $damaged,
                    'expired' => $expired,
                ] as $condition => $qty) {

                    if ($qty > 0) {
                        // Kalau barang bagus -> Pakai Note Global
                        // Kalau barang rusak/expired -> Pakai Item Note (kalau kosong baru pakai Global)
                        $itemReason = ($condition === 'good')
                            ? ($data['note'] ?? null)
                            : ($row['item_note'] ?? ($data['note'] ?? null));

                        SalesReturn::create([
                            'sales_id'     => $me->id,
                            'warehouse_id' => $me->warehouse_id,
                            'handover_id'  => $data['handover_id'],
                            'product_id'   => $row['product_id'],
                            'quantity'     => $qty,
                            'condition'    => $condition,
                            'status'       => 'pending',
                            'reason'       => $itemReason,
                        ]);
                    }
                }
            }
        });

        // 🔥 BROADCAST: Kasih tau Admin WH ada return baru
        $me = auth()->user();
        broadcast(new SalesReturnUpdated(
            $me->warehouse_id,
            $me->id,
            $data['handover_id'],
            'new_return',
            $me->name
        ))->toOthers();

        // 💾 DB NOTIF: Simpan ke tabel notifications
        NotificationHelper::notifyWarehouse(
            $me->warehouse_id,
            'new_return',
            'New Sales Return',
            $me->name . ' submitted a return.',
            '/warehouse/returns',
            'sales_return',
            $data['handover_id']
        );

        return back()->with('success','Return submitted successfully.');
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
                throw new \Exception("Already processed");
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
            } else {
                // 🔥 MOVE TO DAMAGED STOCKS POOL
                \App\Models\DamagedStock::create([
                    'product_id'   => $salesReturn->product_id,
                    'warehouse_id' => $salesReturn->warehouse_id,
                    'source_type'  => 'sales_return',
                    'source_id'    => $salesReturn->id,
                    'quantity'     => $salesReturn->quantity,
                    'condition'    => $salesReturn->condition, // 'damaged' or 'expired'
                    'status'       => 'quarantine',
                    'notes'        => "From Sales Return #{$salesReturn->id}. Reason: " . ($salesReturn->reason ?? '-'),
                ]);
            }

            $salesReturn->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        // 🔥 BROADCAST: Kasih tau Sales status-nya berubah
        broadcast(new SalesReturnUpdated(
            $salesReturn->warehouse_id,
            $salesReturn->sales_id,
            $salesReturn->handover_id,
            'status_updated',
            auth()->user()->name
        ))->toOthers();

        // Cek kalau udah nggak ada yang pending, hapus badge Admin
        $pendingCount = SalesReturn::where('handover_id', $salesReturn->handover_id)
            ->where('status', 'pending')
            ->count();
        if ($pendingCount == 0) {
            NotificationHelper::markAsReadByReference('new_return', 'sales_return', $salesReturn->handover_id);
        }

        // Hapus notif approved lama buat handover ini (biar nggak numpuk, walau sidebar nggak pake tipe ini)
        NotificationHelper::markAsReadByReference('return_approved', 'sales_return', $salesReturn->handover_id);

        // 💾 DB NOTIF
        NotificationHelper::notifySales(
            $salesReturn->sales_id,
            'return_approved',
            'Return Approved',
            'Your return has been approved by ' . auth()->user()->name . '.',
            '/sales/returns',
            'sales_return',
            $salesReturn->handover_id
        );

        return back()->with('success','Return approved successfully.');
    }
    
    public function reject(Request $request, SalesReturn $salesReturn)
    {
        if ($salesReturn->status !== 'pending') {
            return back()->with('error','Return has already been processed.');
        }

        $request->validate([
            'reject_reason' => 'required|string|max:500'
        ]);

        $salesReturn->update([
            'status' => 'rejected',
            'reason' => $request->reject_reason,
        ]);

        // 🔥 BROADCAST: Kasih tau Sales status-nya di-reject
        broadcast(new SalesReturnUpdated(
            $salesReturn->warehouse_id,
            $salesReturn->sales_id,
            $salesReturn->handover_id,
            'status_updated',
            auth()->user()->name
        ))->toOthers();

        // Cek kalau udah nggak ada yang pending, hapus badge Admin
        $pendingCount = SalesReturn::where('handover_id', $salesReturn->handover_id)
            ->where('status', 'pending')
            ->count();
        if ($pendingCount == 0) {
            NotificationHelper::markAsReadByReference('new_return', 'sales_return', $salesReturn->handover_id);
        }

        // Hapus notif rejected lama buat handover ini biar nggak duplikat di badge
        NotificationHelper::markAsReadByReference('return_rejected', 'sales_return', $salesReturn->handover_id);

        // 💾 DB NOTIF
        NotificationHelper::notifySales(
            $salesReturn->sales_id,
            'return_rejected',
            'Return Rejected',
            'Your return was rejected. Reason: ' . $request->reject_reason,
            '/sales/returns',
            'sales_return',
            $salesReturn->handover_id
        );

        return back()->with('success','Return rejected.');
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
        return back()->with('error','At least one quantity must be filled.');
    }

        DB::transaction(function () use ($request, $handoverId) {
            foreach ($request->items as $returnId => $conditions) {
                $original = SalesReturn::lockForUpdate()->find($returnId);
                if (!$original) continue;

                foreach (['good', 'damaged', 'expired'] as $condition) {
                    $qty = (int) ($conditions[$condition] ?? 0);
                    if ($qty <= 0) continue;

                    // Prioritize per-item note over global note
                    $itemNote = $conditions['note'] ?? $request->note;

                    if ($condition === $original->condition) {
                        $original->update([
                            'quantity' => $qty,
                            'status'   => 'pending',
                            'reason'   => $itemNote
                        ]);
                    } else {
                        // Create NEW record for new condition shifted from original
                        SalesReturn::create([
                            'sales_id'     => $original->sales_id,
                            'warehouse_id' => $original->warehouse_id,
                            'handover_id'  => $original->handover_id,
                            'product_id'   => $original->product_id,
                            'quantity'     => $qty,
                            'condition'    => $condition,
                            'status'       => 'pending',
                            'reason'       => $itemNote,
                        ]);
                    }
                }

                $originalQtyInForm = (int) ($conditions[$original->condition] ?? 0);
                if ($originalQtyInForm <= 0) {
                    $original->delete();
                }
            }
        });

        // 🔥 BROADCAST: Kasih tau Admin WH ada resubmit baru
        $me = auth()->user();
        broadcast(new SalesReturnUpdated(
            $me->warehouse_id,
            $me->id,
            $handoverId,
            'new_return',
            $me->name
        ))->toOthers();

        // Hapus badge notif rejected karena sales udah resubmit
        NotificationHelper::markAsReadByReference('return_rejected', 'sales_return', $handoverId);

        // 💾 DB NOTIF: Kasih tau Admin WH ada resubmit (status jadi pending lagi)
        NotificationHelper::notifyWarehouse(
            $me->warehouse_id,
            'new_return',
            'Resubmitted Sales Return',
            $me->name . ' resubmitted a rejected return.',
            '/warehouse/returns',
            'sales_return',
            $handoverId
        );

        return back()->with('success', 'Return resubmitted successfully.');
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
                } else {
                    // 🔥 MOVE TO DAMAGED STOCKS POOL
                    \App\Models\DamagedStock::create([
                        'product_id'   => $salesReturn->product_id,
                        'warehouse_id' => $salesReturn->warehouse_id,
                        'source_type'  => 'sales_return',
                        'source_id'    => $salesReturn->id,
                        'quantity'     => $salesReturn->quantity,
                        'condition'    => $salesReturn->condition,
                        'status'       => 'quarantine',
                        'notes'        => "From Bulk Sales Return approval. Reason: " . ($salesReturn->reason ?? '-'),
                    ]);
                }

                $salesReturn->update([
                    'status'      => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }

            // 🔥 BROADCAST: Kasih tau Sales semua approved (ambil info dari item pertama)
            if ($items->isNotEmpty()) {
                $first = $items->first();
                broadcast(new SalesReturnUpdated(
                    $first->warehouse_id,
                    $first->sales_id,
                    $handoverId,
                    'status_updated',
                    auth()->user()->name
                ))->toOthers();

                // Hapus notif approved lama buat handover ini
                NotificationHelper::markAsReadByReference('return_approved', 'sales_return', $handoverId);

                // 💾 DB NOTIF
                NotificationHelper::notifySales(
                    $first->sales_id,
                    'return_approved',
                    'Return Approved',
                    'All items in your return have been approved by ' . auth()->user()->name . '.',
                    '/sales/returns',
                    'sales_return',
                    $handoverId
                );
            }
            
            // Hapus badge Admin karena udah kelar semua
            NotificationHelper::markAsReadByReference('new_return', 'sales_return', $handoverId);
        });

        return back()->with('success','All returns approved successfully.');
    }

    public function rejectAll(Request $request, $handoverId)
    {
        $request->validate(['reject_reason' => 'required|string|max:500']);

        $firstItem = null;
        DB::transaction(function () use ($handoverId, $request, &$firstItem) {
            $items = SalesReturn::where('handover_id', $handoverId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            $firstItem = $items->first();

            foreach ($items as $item) {
                $item->update([
                    'status' => 'rejected',
                    'reason' => $request->reject_reason,
                ]);
            }
        });

        // 🔥 BROADCAST: Kasih tau Sales semua di-reject
        if ($firstItem) {
            broadcast(new SalesReturnUpdated(
                $firstItem->warehouse_id,
                $firstItem->sales_id,
                $handoverId,
                'status_updated',
                auth()->user()->name
            ))->toOthers();

            // Hapus notif rejected lama buat handover ini biar nggak duplikat di badge
            NotificationHelper::markAsReadByReference('return_rejected', 'sales_return', $handoverId);

            // 💾 DB NOTIF
            NotificationHelper::notifySales(
                $firstItem->sales_id,
                'return_rejected',
                'Return Rejected',
                'All items in your return were rejected. Reason: ' . $request->reject_reason,
                '/sales/returns',
                'sales_return',
                $handoverId
            );
        }

        // Hapus badge Admin karena udah kelar semua
        NotificationHelper::markAsReadByReference('new_return', 'sales_return', $handoverId);

        return response()->json(['success' => true]);
    }

    public function filterAjax(Request $r)
    {
        try {
            $me     = auth()->user();
            $draw   = $r->input('draw');
            $start  = $r->input('start', 0);
            $length = $r->input('length', 10);
            $search = $r->input('search.value');

            // 1. Base query - Grouped by handover_id
            $query = DB::table('sales_returns as sr')
                ->join('sales_handovers as h', 'h.id', '=', 'sr.handover_id')
                ->join('users as s', 's.id', '=', 'sr.sales_id')
                ->select(
                    'sr.handover_id',
                    'h.code as handover_code',
                    's.name as sales_name',
                    DB::raw("CAST(SUM(sr.quantity) AS SIGNED) as total_items"),
                    DB::raw("MIN(sr.created_at) as first_created_at"),
                    DB::raw("
                        CASE 
                            WHEN SUM(CASE WHEN sr.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                            WHEN SUM(CASE WHEN sr.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                            ELSE 'approved'
                        END as status
                    ")
                )
                ->groupBy('sr.handover_id', 'h.code', 's.name');

            // 2. Role Filtering
            if (!$me->hasRole(['admin', 'superadmin'])) {
                if ($me->hasRole(['sales'])) {
                    $query->where('sr.sales_id', $me->id);
                } elseif ($me->hasRole(['warehouse'])) {
                    $query->where('sr.warehouse_id', $me->warehouse_id);
                }
            }

            // 3. Custom Filters (Date, Status, Warehouse, Sales)
            if ($r->filled('from') && $r->filled('to')) {
                $query->whereBetween('sr.created_at', [
                    Carbon::parse($r->from)->startOfDay(),
                    Carbon::parse($r->to)->endOfDay()
                ]);
            }

            if ($r->filled('status')) {
                // If status filter is applied, we look for groups that have this specific state
                // This is a bit complex due to grouping, but usually status refers to the group's aggregate status.
                // For simplicity, we filter individual items first if a status is selected.
                $query->having('status', $r->status);
            }

            if ($r->filled('warehouse_id')) {
                $query->where('sr.warehouse_id', $r->warehouse_id);
            }

            if ($r->filled('sales_id')) {
                $query->where('sr.sales_id', $r->sales_id);
            }

            // 4. Global Search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('h.code', 'like', "%{$search}%")
                      ->orWhere('s.name', 'like', "%{$search}%");
                });
            }

            // 5. Counting (Robust method for grouped queries)
            $countSql = "SELECT COUNT(*) as aggregate FROM ({$query->toSql()}) as sub";
            $countResult = DB::select($countSql, $query->getBindings());
            $recordsTotal = $countResult[0]->aggregate ?? 0;

            // 6. Fetch paginated data
            $rows = $query->orderByDesc('first_created_at')
                ->offset($start)
                ->limit($length)
                ->get();

            $data = [];
            foreach ($rows as $row) {
                $badge = match ($row->status) {
                    'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
                    'rejected' => '<span class="badge bg-danger">Rejected</span>',
                    'approved' => '<span class="badge bg-success">Approved</span>',
                    default    => '<span class="badge bg-secondary">Unknown</span>',
                };

                $data[] = [
                    'handover_code' => $row->handover_code,
                    'sales_name'    => $row->sales_name,
                    'total_items'   => $row->total_items . ' items',
                    'status_badge'  => $badge,
                    'status'        => $row->status,
                    'date'          => Carbon::parse($row->first_created_at)->format('d M Y'),
                    'handover_id'   => $row->handover_id,
                ];
            }

            return response()->json([
                'draw' => (int)$draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsTotal, // Same as total since we applied filters in base
                'data' => $data
            ]);

        } catch (\Throwable $e) {
            \Log::error('SalesReturn filterAjax error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int)$r->input('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function filterAjaxWhApproved(Request $r)
    {
        // WH view usually mostly identical but has different role checks and shows dates with time
        return $this->filterAjax($r);
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