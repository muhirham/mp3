<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait RestockSyncTrait
 * Satukan logic sinkronisasi status dan stok untuk GR, PO, dan Request Restock.
 */
trait RestockSyncTrait
{
    /**
     * Update stok di level WAREHOUSE (lokal).
     */
    protected function adjustWarehouseStock(int $whId, int $productId, int $deltaQty): void
    {
        if ($deltaQty === 0 || !Schema::hasTable('stock_levels')) return;

        $q = DB::table('stock_levels')
            ->where('owner_type', 'warehouse')
            ->where('owner_id', $whId)
            ->where('product_id', $productId)
            ->lockForUpdate();

        if ($existing = $q->first()) {
            $q->update([
                'quantity'   => max(0, (int) $existing->quantity + $deltaQty),
                'updated_at' => now(),
            ]);
        } elseif ($deltaQty > 0) {
            DB::table('stock_levels')->insert([
                'owner_type' => 'warehouse',
                'owner_id'   => $whId,
                'product_id' => $productId,
                'quantity'   => $deltaQty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Update stok di level PUSAT (central).
     */
    protected function adjustCentralStock(int $productId, int $deltaQty): void
    {
        if ($deltaQty === 0 || !Schema::hasTable('stock_levels')) return;

        $q = DB::table('stock_levels')
            ->where('owner_type', 'pusat')
            ->where('owner_id', 0)
            ->where('product_id', $productId)
            ->lockForUpdate();

        $row = $q->first();

        if ($row) {
            $q->update([
                'quantity'   => max(0, (int) $row->quantity + $deltaQty),
                'updated_at' => now(),
            ]);
        } elseif ($deltaQty > 0) {
            DB::table('stock_levels')->insert([
                'owner_type' => 'pusat',
                'owner_id'   => 0,
                'product_id' => $productId,
                'quantity'   => $deltaQty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Sinkronisasi ke tabel products (fallback stok superadmin)
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'stock')) {
            if ($deltaQty > 0) {
                DB::table('products')->where('id', $productId)->increment('stock', $deltaQty);
            } else {
                DB::table('products')->where('id', $productId)->decrement('stock', abs($deltaQty));
            }
        }
    }

    /**
     * Hitung ulang quantity_received & status untuk satu request_restocks.
     */
    protected function recalcRequestRestock(int $requestId): void
    {
        $req = DB::table('request_restocks')->where('id', $requestId)->first();
        if (!$req) return;

        $reqQty = (int) ($req->quantity_requested ?? $req->qty_requested ?? $req->qty ?? 0);

        $sumGood = (int) DB::table('restock_receipts')
            ->where('request_id', $requestId)
            ->sum('qty_good');

        $sumBad = (int) DB::table('restock_receipts')
            ->where('request_id', $requestId)
            ->sum('qty_damaged');

        $sumAll = $sumGood + $sumBad;

        $status = $req->status ?? 'approved';
        $receivedAt = $req->received_at;

        if ($reqQty > 0) {
            if ($sumAll >= $reqQty) {
                $status = 'received';
                $receivedAt = $receivedAt ?: now();
            } elseif ($sumAll === 0) {
                // Balikin ke 'ordered' biar tombol GR muncul lagi di Warehouse dashboard
                $status = 'ordered'; 
                $receivedAt = null;
            } else {
                // Partially received, status 'ordered' biar tombol GR tetep ada
                $status = 'ordered';
                $receivedAt = null;
            }
        }

        DB::table('request_restocks')
            ->where('id', $requestId)
            ->update([
                'quantity_received' => $sumGood,
                'status'            => $status,
                'received_at'       => $receivedAt,
                'updated_at'        => now(),
            ]);
    }

    /**
     * Hitung ulang status PO berdasarkan data receipt terbaru.
     */
    protected function recalcPoFromReceipts(int $poId): void
    {
        if (!Schema::hasTable('restock_receipts') || !Schema::hasTable('purchase_order_items')) return;

        $hasWhCol = Schema::hasColumn('restock_receipts', 'warehouse_id');

        $rcvRows = DB::table('restock_receipts')
            ->where('purchase_order_id', $poId)
            ->selectRaw('product_id' . ($hasWhCol ? ', warehouse_id' : '') . ', SUM(qty_good + qty_damaged) as qty_rcv')
            ->groupBy('product_id')
            ->when($hasWhCol, function($q) { return $q->groupBy('warehouse_id'); })
            ->get();

        $rcvIndex = [];
        foreach ($rcvRows as $row) {
            $key = $row->product_id . '-' . ($hasWhCol ? ($row->warehouse_id ?? 0) : 0);
            $rcvIndex[$key] = (int) $row->qty_rcv;
        }

        $items = DB::table('purchase_order_items')->where('purchase_order_id', $poId)->get();
        $allFull = true;
        $anyReceived = false;

        foreach ($items as $it) {
            $key = $it->product_id . '-' . ($hasWhCol ? ($it->warehouse_id ?? 0) : 0);
            $qtyRcv = $rcvIndex[$key] ?? 0;
            $ordered = (int) $it->qty_ordered;

            DB::table('purchase_order_items')->where('id', $it->id)->update([
                'qty_received' => $qtyRcv,
                'updated_at'   => now(),
            ]);

            if ($qtyRcv > 0) $anyReceived = true;
            if ($qtyRcv < $ordered) $allFull = false;
        }

        $newStatus = ($allFull && $anyReceived) ? 'completed' : ($anyReceived ? 'partially_received' : 'ordered');
        
        $updateData = ['status' => $newStatus, 'updated_at' => now()];
        if ($newStatus === 'completed' && Schema::hasColumn('purchase_orders', 'received_at')) {
            $updateData['received_at'] = now();
        }

        DB::table('purchase_orders')->where('id', $poId)->update($updateData);
    }
}
