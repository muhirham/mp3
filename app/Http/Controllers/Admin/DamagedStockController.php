<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedStock;
use App\Models\DamagedStockPhoto;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DamagedStockController extends Controller
{
    /**
     * View for Admin WH: List with filtering (Single Table).
     * key: wh_damaged_stocks
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isWarehouse = $user->hasRole('warehouse');
        $isSuperadmin = $user->hasRole('superadmin');

        $warehouses = Warehouse::orderBy('warehouse_name')->get();

        return view('wh.stock_damage', compact('warehouses', 'isWarehouse', 'isSuperadmin'));
    }

    public function indexData(Request $request)
    {
        $user = auth()->user();
        $isWarehouse = $user->hasRole('warehouse');
        $isSuperadmin = $user->hasRole('superadmin');

        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) $request->input('search.value', ''));

        $query = DamagedStock::with(['product.supplier', 'warehouse', 'requester', 'approver', 'resolver', 'photos']);

        // Base total count
        $recordsTotal = DamagedStock::count();

        // WH filter
        if ($isWarehouse && !$isSuperadmin) {
            $query->where('warehouse_id', $user->warehouse_id);
        } elseif ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->status) $query->where('status', $request->status);
        if ($request->condition) $query->where('condition', $request->condition);

        if ($request->keyword || $search) {
            $kw = $request->keyword ?: $search;
            $query->whereHas('product', function($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('product_code', 'like', "%{$kw}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $items = $query->orderByDesc('created_at')
                       ->offset($start)
                       ->limit($length)
                       ->get();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $items
        ]);
    }

    /**
     * View for Superadmin/History: Management & Audit.
     * key: approval_stock_damage
     */
    public function approval(Request $request)
    {
        $user = auth()->user();
        $isWarehouse = $user->hasRole('warehouse');
        $isSuperadmin = $user->hasRole('superadmin');

        $warehouses = Warehouse::orderBy('warehouse_name')->get();

        return view('admin.operations.approval_stock_damage', compact('warehouses', 'isWarehouse', 'isSuperadmin'));
    }

    public function approvalData(Request $request)
    {
        $user = auth()->user();
        $isWarehouse = $user->hasRole('warehouse');
        $isSuperadmin = $user->hasRole('superadmin');

        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) $request->input('search.value', ''));

        $query = DamagedStock::with(['product.supplier', 'warehouse', 'requester', 'approver', 'resolver', 'photos']);

        $recordsTotal = DamagedStock::count();

        if ($isWarehouse && !$isSuperadmin) {
            $query->where('warehouse_id', $user->warehouse_id);
        } elseif ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->status) $query->where('status', $request->status);
        if ($request->start_date) $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->end_date) $query->whereDate('created_at', '<=', $request->end_date);

        if ($request->keyword || $search) {
            $kw = $request->keyword ?: $search;
            $query->whereHas('product', function($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('product_code', 'like', "%{$kw}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $items = $query->orderByRaw("status = 'pending_approval' DESC")
                       ->orderByDesc('created_at')
                       ->offset($start)
                       ->limit($length)
                       ->get();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $items
        ]);
    }

    /**
     * Admin WH requests an action (Return/Dispose) for a quarantined item.
     */
    public function requestAction(Request $request, DamagedStock $damagedStock)
    {
        $request->validate([
            'action' => 'required|in:return_to_supplier,repair,dispose,other',
            'notes'  => 'nullable|string|max:1000',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:4096'
        ]);

        if (!in_array($damagedStock->status, ['quarantine', 'rejected'])) {
            return back()->with('error', 'Item is already being processed.');
        }

        DB::transaction(function () use ($request, $damagedStock) {
            $damagedStock->update([
                'action'       => $request->action,
                'status'       => 'pending_approval',
                'notes'        => $request->notes,
                'requested_by' => auth()->id(),
            ]);

            if ($request->hasFile('photos')) {
                // 🔥 REPLACE LOGIC: Delete old proof photos from storage & DB if resubmitting with new photos
                $oldPhotos = $damagedStock->photos()->where('kind', 'action_proof')->get();
                foreach ($oldPhotos as $old) {
                    if (Storage::disk('public')->exists($old->path)) {
                        Storage::disk('public')->delete($old->path);
                    }
                    $old->delete();
                }

                foreach ($request->file('photos') as $photo) {
                    // 🔥 OPTIMIZED SAVE (Global Helper: Resize & Compress)
                    $path = save_optimized_image($photo, 'damaged_stock_proofs');
                    DamagedStockPhoto::create([
                        'damaged_stock_id' => $damagedStock->id,
                        'path'             => $path,
                        'kind'             => 'action_proof'
                    ]);
                }
            }
        });

        return back()->with('success', 'Action request submitted for approval.');
    }

    /**
     * Admin WH requests actions for MULTIPLE items at once.
     */
    public function bulkRequestAction(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:damaged_stocks,id',
            'items.*.action' => 'required|in:return_to_supplier,repair,dispose,other',
            'items.*.notes' => 'nullable|string|max:1000',
            'items.*.photos' => 'nullable|array',
            'items.*.photos.*' => 'image|max:10240',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $itemData) {
                $damagedStock = DamagedStock::lockForUpdate()->find($itemData['id']);
                
                // Only process if still in quarantine or rejected
                if (in_array($damagedStock->status, ['quarantine', 'rejected'])) {
                    
                    // Smart Replacement per item
                    if (isset($itemData['photos'])) {
                        foreach ($damagedStock->photos as $old) {
                            if (Storage::disk('public')->exists($old->path)) {
                                Storage::disk('public')->delete($old->path);
                            }
                            $old->delete();
                        }
                    }

                    $damagedStock->update([
                        'action'       => $itemData['action'],
                        'status'       => 'pending_approval',
                        'notes'        => $itemData['notes'],
                        'requested_by' => auth()->id(),
                    ]);

                    // Save new photos PER ITEM
                    if (isset($itemData['photos'])) {
                        foreach ($itemData['photos'] as $photo) {
                            $path = save_optimized_image($photo, 'damaged_stock_proofs');
                            \App\Models\DamagedStockPhoto::create([
                                'damaged_stock_id' => $damagedStock->id,
                                'path'             => $path,
                                'kind'             => 'action_proof'
                            ]);
                        }
                    }
                }
            }
        });

        return back()->with('success', 'Bulk action requests submitted for approval.');
    }

    /**
     * Superadmin approves the requested action.
     */
    public function approveAction(Request $request, DamagedStock $damagedStock)
    {
        $request->validate([
            'status' => 'required|in:in_progress,rejected',
            'notes'  => 'nullable|string|max:1000'
        ]);

        if ($damagedStock->status !== 'pending_approval') {
            return back()->with('error', 'No pending request found for this item.');
        }

        $notes = $damagedStock->notes;
        if ($request->status === 'rejected' && $request->notes) {
            $notes = ($notes ? $notes . "\n" : "") . "[REJECTED]: " . $request->notes;
        } elseif ($request->notes) {
            $notes = $request->notes;
        }

        $damagedStock->update([
            'status'      => $request->status,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'notes'       => $notes,
        ]);

        $msg = $request->status === 'in_progress' ? 'Action approved and in progress.' : 'Action request rejected.';
        return back()->with('success', $msg);
    }

    /**
     * Admin WH resolves the case (Receives replacement or confirms disposal).
     * Strictly restricted to WH Role.
     */
    public function resolveAction(Request $request, DamagedStock $damagedStock)
    {
        if (!auth()->user()->hasRole('warehouse')) {
            return back()->with('error', 'Unauthorized. Only Warehouse Admin can receive replacement stocks.');
        }

        if ($damagedStock->status !== 'in_progress') {
            return back()->with('error', 'Action must be in progress to resolve.');
        }

        $request->validate([
            'qty_good'         => 'nullable|integer|min:0|max:' . $damagedStock->quantity,
            'qty_damaged'      => 'nullable|integer|min:0|max:' . $damagedStock->quantity,
            'notes'            => 'nullable|string|max:1000',
            'photos_good'      => 'nullable|array',
            'photos_good.*'    => 'image|max:4096',
            'photos_damaged'   => 'nullable|array',
            'photos_damaged.*' => 'image|max:4096',
        ]);

        DB::transaction(function () use ($request, $damagedStock) {
            $qtyGood = (int) ($request->qty_good ?? $damagedStock->quantity);
            $qtyDamaged = (int) ($request->qty_damaged ?? 0);

            if ($damagedStock->action !== 'dispose' && $qtyGood > 0) {
                $this->increaseGoodStockByQty($damagedStock, $qtyGood);
                $this->logStockMovementByQty($damagedStock, $qtyGood);
            }

            if ($request->hasFile('photos_good')) {
                foreach ($request->file('photos_good') as $photo) {
                    $path = save_optimized_image($photo, 'damaged_stock_resolutions');
                    DamagedStockPhoto::create([
                        'damaged_stock_id' => $damagedStock->id,
                        'path'             => $path,
                        'kind'             => 'resolved'
                    ]);
                }
            }

            if ($request->hasFile('photos_damaged')) {
                foreach ($request->file('photos_damaged') as $photo) {
                    $path = save_optimized_image($photo, 'damaged_stock_resolutions');
                    DamagedStockPhoto::create([
                        'damaged_stock_id' => $damagedStock->id,
                        'path'             => $path,
                        'kind'             => 'resolved'
                    ]);
                }
            }

            $auditNotes = $damagedStock->notes;
            if ($request->notes) {
                $auditNotes = ($auditNotes ? $auditNotes . "\n" : "") . "[GR RESOLVE]: " . $request->notes;
            }
            if ($qtyDamaged > 0) {
                $auditNotes .= "\n[ALERT]: " . $qtyDamaged . " items received but still DAMAGED.";
            }

            if ($qtyGood > 0 || $qtyDamaged > 0) {
                $grCode = 'GR-DR-' . now()->format('Ymd') . '-' . strtoupper(\Str::random(5));
                DB::table('restock_receipts')->insert([
                    'purchase_order_id' => null,
                    'request_id'        => $damagedStock->id, // link ke damaged_stocks
                    'warehouse_id'      => $damagedStock->warehouse_id,
                    'product_id'        => $damagedStock->product_id,
                    'gr_type'           => \App\Models\RestockReceipt::TYPE_RETURN,
                    'code'              => $grCode,
                    'qty_requested'     => $damagedStock->quantity,
                    'qty_good'          => $qtyGood,
                    'qty_damaged'       => $qtyDamaged,
                    'notes'             => "Resolution for #{$damagedStock->id}. " . ($request->notes ?? ''),
                    'received_by'       => auth()->id(),
                    'received_at'       => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            $damagedStock->update([
                'status'      => 'resolved',
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'notes'       => $auditNotes
            ]);
        });

        return back()->with('success', 'Resolution successful. Stock updated for good items.');
    }

    private function increaseGoodStockByQty(DamagedStock $damagedStock, int $qty)
    {
        $stock = DB::table('stock_levels')
            ->where('owner_type', 'warehouse')
            ->where('owner_id', $damagedStock->warehouse_id)
            ->where('product_id', $damagedStock->product_id)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            DB::table('stock_levels')->where('id', $stock->id)->update([
                'quantity'   => $stock->quantity + $qty,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('stock_levels')->insert([
                'owner_type' => 'warehouse',
                'owner_id'   => $damagedStock->warehouse_id,
                'product_id' => $damagedStock->product_id,
                'quantity'   => $qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function logStockMovementByQty(DamagedStock $damagedStock, int $qty)
    {
        if (!Schema::hasTable('stock_movements')) return;
        $ref = "DMG-#{$damagedStock->id}";
        DB::table('stock_movements')->insert([
            'product_id' => $damagedStock->product_id,
            'from_type'  => 'supplier',
            'from_id'    => 0,
            'to_type'    => 'warehouse',
            'to_id'      => $damagedStock->warehouse_id,
            'quantity'   => $qty,
            'status'     => 'completed',
            'note'       => "Resolution for {$ref}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
