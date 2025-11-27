<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RestockReceipt;
use App\Models\GrDeleteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GRController extends Controller
{
    /** SIMPAN GOODS RECEIVED DARI PO (CENTRAL / GUDANG) */
    public function store(Request $request, PurchaseOrder $po)
    {
        $request->validate([
            'receives'               => 'required|array|min:1',
            'receives.*.qty_good'    => 'required|integer|min:0',
            'receives.*.qty_damaged' => 'required|integer|min:0',
            'receives.*.notes'       => 'nullable|string',

            // foto good
            'photos_good'            => 'nullable',
            'photos_good.*'          => 'nullable|file|image|max:8192',

            // foto damaged
            'photos_bad'             => 'nullable',
            'photos_bad.*'           => 'nullable|file|image|max:8192',
        ]);

        DB::transaction(function () use ($request, $po) {

            $firstReceipt = null;

            // preload item PO, di-key pakai ID item
            $items = PurchaseOrderItem::where('purchase_order_id', $po->id)
                ->with('product')
                ->get()
                ->keyBy('id');

            foreach ($request->receives as $itemId => $rcv) {
                /** @var PurchaseOrderItem|null $it */
                $it = $items->get($itemId);
                if (! $it) {
                    continue;
                }

                $good = (int) ($rcv['qty_good'] ?? 0);
                $bad  = (int) ($rcv['qty_damaged'] ?? 0);
                $qty  = $good + $bad;

                if ($qty === 0) {
                    continue;
                }

                $ordered   = (int) ($it->qty_ordered ?? 0);
                $received  = (int) ($it->qty_received ?? 0);
                $remaining = max(0, $ordered - $received);

                // ===== VALIDASI: good + bad ≤ remaining =====
                if ($qty > $remaining) {
                    abort(422, "Qty terima melebihi sisa untuk item #{$it->id}");
                }

                $grCode = 'GR-' . now()->format('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

                // simpan ke restock_receipts (1 baris per item)
                $receipt = RestockReceipt::create([
                    'purchase_order_id' => $po->id,
                    'code'              => $grCode,

                    // manual PO → tidak punya request_id
                    'request_id'        => null,

                    'product_id'        => $it->product_id,
                    'warehouse_id'      => $it->warehouse_id,
                    'qty_requested'     => $qty,    // good + damaged
                    'qty_good'          => $good,
                    'qty_damaged'       => $bad,
                    'notes'             => $rcv['notes'] ?? null,
                    'received_by'       => auth()->id(),
                    'received_at'       => now(),
                ]);

                if (! $firstReceipt) {
                    $firstReceipt = $receipt;
                }

                // update progress item di PO (sementara tambah langsung)
                $it->qty_received = $received + $qty;
                $it->save();

                // ====== stock_levels (masuk cuma yang GOOD) ======
                if (Schema::hasTable('stock_levels')) {

                    // 1) TAMBAH stok di warehouse tujuan
                    $sl = DB::table('stock_levels')->where([
                        'product_id' => $it->product_id,
                        'owner_type' => 'warehouse',   // stok cabang
                        'owner_id'   => $it->warehouse_id,
                    ]);

                    if ($sl->exists()) {
                        DB::table('stock_levels')->where([
                            'product_id' => $it->product_id,
                            'owner_type' => 'warehouse',
                            'owner_id'   => $it->warehouse_id,
                        ])->update([
                            'quantity'   => DB::raw('quantity + ' . $good),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('stock_levels')->insert([
                            'product_id' => $it->product_id,
                            'owner_type' => 'warehouse',
                            'owner_id'   => $it->warehouse_id,
                            'quantity'   => $good,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // 2) KURANGI stok CENTRAL / PUSAT (owner_type = 'pusat')
                    //    -> ini yang sebelumnya belum ada, jadi stok pusat nggak berkurang.
                    $central = DB::table('stock_levels')
                        ->where('product_id', $it->product_id)
                        ->where('owner_type', 'pusat')
                        ->lockForUpdate()
                        ->first();

                    if ($central) {
                        $newQty = max(0, (int) $central->quantity - $good);

                        DB::table('stock_levels')
                            ->where('id', $central->id)
                            ->update([
                                'quantity'   => $newQty,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }

            // ====== SIMPAN FOTO GOOD & DAMAGED (kalau ada) ======
            if ($firstReceipt && Schema::hasTable('restock_receipt_photos')) {

                // --- normalisasi array untuk foto bagus ---
                $filesGood = $request->file('photos_good');
                if ($filesGood && ! is_array($filesGood)) {
                    $filesGood = [$filesGood];
                }

                if ($filesGood) {
                    foreach ($filesGood as $file) {
                        if (! $file) continue;

                        $path = $file->store('gr_photos/good', 'public');

                        $insert = [
                            'receipt_id' => $firstReceipt->id,
                            'path'       => $path,
                            'caption'    => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (Schema::hasColumn('restock_receipt_photos', 'type')) {
                            $insert['type'] = 'good';
                        }

                        DB::table('restock_receipt_photos')->insert($insert);
                    }
                }

                // --- normalisasi array untuk foto rusak ---
                $filesBad = $request->file('photos_bad');
                if ($filesBad && ! is_array($filesBad)) {
                    $filesBad = [$filesBad];
                }

                if ($filesBad) {
                    foreach ($filesBad as $file) {
                        if (! $file) continue;

                        $path = $file->store('gr_photos/damaged', 'public');

                        $insert = [
                            'receipt_id' => $firstReceipt->id,
                            'path'       => $path,
                            'caption'    => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (Schema::hasColumn('restock_receipt_photos', 'type')) {
                            $insert['type'] = 'damaged';
                        }

                        DB::table('restock_receipt_photos')->insert($insert);
                    }
                }
            }

            // ====== update status PO (completed / partially_received / ordered) ======
            $this->recalcPoFromReceipts($po->id);
        });

        return back()->with('success', 'Goods Received tersimpan & stok diperbarui.');
    }

    /**
     * Helper: hitung ulang qty_received per item + status PO
     * berdasarkan semua restock_receipts yang masih aktif.
     */
    protected function recalcPoFromReceipts(int $poId): void
    {
        if (
            ! Schema::hasTable('restock_receipts') ||
            ! Schema::hasTable('purchase_order_items') ||
            ! Schema::hasTable('purchase_orders')
        ) {
            return;
        }

        $hasWhCol = Schema::hasColumn('restock_receipts', 'warehouse_id');

        // agregat qty GR per product (+ optional warehouse)
        $rcvQuery = DB::table('restock_receipts')
            ->where('purchase_order_id', $poId)
            ->selectRaw('product_id' . ($hasWhCol ? ', warehouse_id' : '') . ', SUM(qty_good + qty_damaged) as qty_rcv')
            ->groupBy('product_id');

        if ($hasWhCol) {
            $rcvQuery->groupBy('warehouse_id');
        }

        $rcvRows = $rcvQuery->get();

        $rcvIndex = [];
        foreach ($rcvRows as $row) {
            $key = $row->product_id . '-' . ($hasWhCol ? ($row->warehouse_id ?? 0) : 0);
            $rcvIndex[$key] = (int) $row->qty_rcv;
        }

        $items = DB::table('purchase_order_items')
            ->where('purchase_order_id', $poId)
            ->get(['id', 'product_id', 'warehouse_id', 'qty_ordered']);

        $allFull     = true;
        $anyReceived = false;

        foreach ($items as $it) {
            $key     = $it->product_id . '-' . ($hasWhCol ? ($it->warehouse_id ?? 0) : 0);
            $qtyRcv  = $rcvIndex[$key] ?? 0;
            $ordered = (int) $it->qty_ordered;

            DB::table('purchase_order_items')
                ->where('id', $it->id)
                ->update([
                    'qty_received' => $qtyRcv,
                    'updated_at'   => now(),
                ]);

            if ($qtyRcv > 0) {
                $anyReceived = true;
            }
            if ($qtyRcv < $ordered) {
                $allFull = false;
            }
        }

        $updatePo = [];
        if ($allFull && $anyReceived) {
            $updatePo['status'] = 'completed';
            if (Schema::hasColumn('purchase_orders', 'received_at')) {
                $updatePo['received_at'] = now();
            }
        } elseif ($anyReceived) {
            $updatePo['status'] = 'partially_received';
        } else {
            // belum ada GR sama sekali → balik ke ordered
            $updatePo['status'] = 'ordered';
        }

        $updatePo['updated_at'] = now();

        DB::table('purchase_orders')
            ->where('id', $poId)
            ->update($updatePo);
    }

    /* ==========================================================
     *  REQUEST DELETE GR  (user gudang)
     * ========================================================== */
    public function requestDelete(Request $request, RestockReceipt $receipt)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        // cek kalau sudah ada pending request
        $exists = GrDeleteRequest::where('restock_receipt_id', $receipt->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return back()->with('error', 'Sudah ada permintaan hapus GR ini yang masih pending.');
        }

        GrDeleteRequest::create([
            'restock_receipt_id' => $receipt->id,
            'purchase_order_id'  => $receipt->purchase_order_id,
            'requested_by'       => auth()->id(),
            'status'             => 'pending',
            'reason'             => $data['reason'],
        ]);

        return back()->with('success', 'Permintaan hapus GR sudah dikirim untuk approval.');
    }

    /* ==========================================================
     *  APPROVAL DELETE GR (superadmin / purchasing)
     * ========================================================== */
    public function processDeleteApproval(Request $request, GrDeleteRequest $grReq)
    {
        // boleh tambahkan gate/role check di sini
        $data = $request->validate([
            'action'        => 'required|in:approve,reject',
            'approval_note' => 'nullable|string',
        ]);

        if ($grReq->status !== 'pending') {
            return back()->with('error', 'Permintaan ini sudah diproses sebelumnya.');
        }

        if ($data['action'] === 'reject') {
            $grReq->status        = 'rejected';
            $grReq->approved_by   = auth()->id();
            $grReq->approval_note = $data['approval_note'] ?? null;
            $grReq->save();

            return back()->with('success', 'Permintaan hapus GR ditolak.');
        }

        // ============= APPROVE (rollback stok & buka PO) =============
        DB::transaction(function () use ($grReq, $data) {

            $receipt = $grReq->receipt()->first();
            if (! $receipt) {
                // kalau GR sudah hilang entah kenapa, cukup tandai approved saja
                $grReq->status        = 'approved';
                $grReq->approved_by   = auth()->id();
                $grReq->approval_note = $data['approval_note'] ?? null;
                $grReq->save();
                return;
            }

            $productId   = $receipt->product_id;
            $warehouseId = $receipt->warehouse_id;
            $poId        = $receipt->purchase_order_id;
            $good        = (int) $receipt->qty_good;

            // 1) Balikkan stok (kurangi quantity untuk qty_good)
            if (Schema::hasTable('stock_levels') && $warehouseId && $productId) {
                $q = DB::table('stock_levels')
                    ->where('owner_type', 'warehouse')
                    ->where('owner_id', $warehouseId)
                    ->where('product_id', $productId);

                if ($row = $q->first()) {
                    $newQty = max(0, (int) $row->quantity - $good);
                    $q->update([
                        'quantity'   => $newQty,
                        'updated_at' => now(),
                    ]);
                }
            }

            // 2) Hapus foto2 GR (dan file fisiknya)
            if (Schema::hasTable('restock_receipt_photos')) {
                $photos = DB::table('restock_receipt_photos')
                    ->where('receipt_id', $receipt->id)
                    ->get();

                foreach ($photos as $p) {
                    if (! empty($p->path)) {
                        Storage::disk('public')->delete($p->path);
                    }
                }

                DB::table('restock_receipt_photos')
                    ->where('receipt_id', $receipt->id)
                    ->delete();
            }

            // 3) Hapus row GR
            DB::table('restock_receipts')
                ->where('id', $receipt->id)
                ->delete();

            // 4) Hitung ulang qty_received & status PO
            if ($poId) {
                $this->recalcPoFromReceipts($poId);
            }

            // 5) Tandai approval
            $grReq->status        = 'approved';
            $grReq->approved_by   = auth()->id();
            $grReq->approval_note = $data['approval_note'] ?? null;
            $grReq->save();
        });

        return back()->with('success', 'GR berhasil dihapus, stok & status PO sudah diperbarui.');
    }
}
