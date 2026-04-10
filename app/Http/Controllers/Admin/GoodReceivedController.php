<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Restocks\GoodReceivedIndexExport;
use App\Http\Controllers\Controller;
use App\Models\RestockReceipt;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GrDeleteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use Maatwebsite\Excel\Facades\Excel;
use App\Traits\RestockSyncTrait;


class GoodReceivedController extends Controller
{
    use RestockSyncTrait;

    public function index(Request $request)
    {
        $q           = trim($request->get('q', ''));
        $supplierId  = $request->get('supplier_id');
        $warehouseId = $request->get('warehouse_id');
        $grType      = $request->get('gr_type'); // Tipe Baru: po, request_stock, gr_transfer, gr_return
        $dateFrom    = $request->get('date_from');
        $dateTo      = $request->get('date_to');

        $me    = auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouseUser = $roles->contains('slug', 'warehouse');
        $isSuperadmin    = $roles->contains('slug', 'superadmin');

        // Query Utama dari RestockReceipt
        $query = RestockReceipt::with(['purchaseOrder', 'supplier', 'warehouse', 'product', 'photos', 'receiver'])
            ->select('code', 'gr_type', 'purchase_order_id', 'warehouse_id', 'supplier_id', 'received_by', 'received_at')
            ->selectRaw('MAX(request_id) as request_id, SUM(qty_good) as total_good, SUM(qty_damaged) as total_damaged, COUNT(id) as total_items')
            ->groupBy('code', 'gr_type', 'purchase_order_id', 'warehouse_id', 'supplier_id', 'received_by', 'received_at');

        // Filter User Warehouse: Warehouse Admin hanya bisa lihat gudang mereka sendiri
        if ($isWarehouseUser && $me) {
            $query->where('warehouse_id', $me->warehouse_id);
        } else {
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }
        }

        // Filter Tipe GR
        if ($grType) {
            $query->where('gr_type', $grType);
        }

        // Filter Supplier
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Filter Tanggal
        if ($dateFrom && $dateTo) {
            $query->whereBetween('received_at', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59',
            ]);
        }

        // Filter Search (Code atau PO Code)
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                  ->orWhereHas('purchaseOrder', function($p) use ($q) {
                      $p->where('po_code', 'like', "%{$q}%");
                  });
            });
        }

        $perPage  = (int) $request->get('per_page', 10);
        $receipts = $query->orderByDesc('received_at')->paginate($perPage);
        $receipts->appends($request->all());

        // Untuk Dropdown Filter
        $suppliers  = Supplier::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name as name']);

        return view('admin.masterdata.goodReceived', compact(
            'receipts', 
            'suppliers', 
            'warehouses', 
            'q', 
            'supplierId', 
            'warehouseId', 
            'grType',
            'dateFrom', 
            'dateTo',
            'isWarehouseUser',
            'isSuperadmin',
            'perPage'
        ));
    }

    /**
     * Export Excel raw data Goods Received
     */
    public function exportExcel(Request $r)
    {
        $filters = [
            'q'            => $r->query('q', ''),
            'supplier_id'  => $r->query('supplier_id', ''),
            'warehouse_id' => $r->query('warehouse_id', ''),
            'gr_type'      => $r->query('gr_type', ''),
            'date_from'    => $r->query('date_from', ''),
            'date_to'      => $r->query('date_to', ''),
        ];

        // Security: Lock warehouse_id for non-superadmins
        $me = auth()->user();
        if ($me && $me->hasRole('warehouse')) {
            $filters['warehouse_id'] = $me->warehouse_id;
        }

        return Excel::download(
            new GoodReceivedIndexExport($filters),
            'GOOD_RECEIVED_RAW_DATA_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /** Generator kode GR unik: GR-YYMMDD-0001 */
    protected function nextReceiptCode(): string
    {
        $prefix = 'GR-' . now()->format('ymd') . '-';

        $last = DB::table('restock_receipts')
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('code');

        $n = 1;
        if ($last) {
            $lastSeq = (int) substr($last, -4);
            $n       = $lastSeq + 1;
        }

        return $prefix . str_pad($n, 4, '0', STR_PAD_LEFT);
    }

    public function detail($code)
    {
        $me    = auth()->user();
        $roles = $me?->roles ?? collect();
        $isSuperadmin = $roles->contains('slug', 'superadmin');

        // Coba cari pake KODE dulu, kalau zonk coba cari pake ID (buat handle data lama/error)
        $receipts = RestockReceipt::with([
                'purchaseOrder.supplier', 
                'purchaseOrder.items.product', 
                'supplier', 
                'warehouse', 
                'product', 
                'photos', 
                'receiver', 
                'request.product',
                'warehouseTransfer.items.product',
                'salesReturn.product'
            ])
            ->where('code', $code)
            ->get();

        if ($receipts->isEmpty() && is_numeric($code)) {
            // Jika pencarian kode gagal, coba cari pake ID as fallback
            $fallback = RestockReceipt::find($code);
            if ($fallback) {
                $realCode = $fallback->code;
                // Ambil semua row yang punya kode yang sama dengan ID yang ditemukan
                $receipts = RestockReceipt::with([
                    'purchaseOrder.supplier', 
                    'purchaseOrder.items.product', 
                    'supplier', 
                    'warehouse', 
                    'product', 
                    'photos', 
                    'receiver', 
                    'request.product', 
                    'salesReturn.product',
                    'warehouseTransfer.items.product'
                ])
                ->where('code', $realCode ?: 'NONE') // handle if code is null
                ->orWhere('id', $code)
                ->get();
            }
        }

        if ($receipts->isEmpty()) {
            abort(404, 'Goods Received records not found.');
        }

        $first = $receipts->first();

        // Security Audit: Pastikan orang gudang gak bisa ngintip gudang orang lewat URL
        $isWarehouse = $roles->contains('slug', 'warehouse');
        if ($isWarehouse && !$isSuperadmin) {
            if ($first->warehouse_id != $me->warehouse_id) {
                abort(403, 'Unauthorized. You cannot view Goods Received records from another warehouse.');
            }
        }

        // Re-eager load to ensure we have all data for the detail modal
        $receipts->loadMissing(['product.supplier', 'supplier', 'receiver', 'request.product.supplier', 'warehouseTransfer.items.product.supplier', 'salesReturn.product.supplier']);

        $first = $receipts->first();
        $po    = $first->purchaseOrder; 

        $company = Company::where('is_default', true)
            ->where('is_active', true)
            ->first();

        $displayItems = collect();
        
        if ($po) {
            $po->load(['items.product.supplier', 'supplier']);
            $displayItems = $po->items;
        } elseif ($first->gr_type == 'gr_transfer' && $first->warehouseTransfer) {
            // Mapping WarehouseTransfer items biar polanya sama kayak PO item
            foreach($first->warehouseTransfer->items as $it) {
                $displayItems->push((object)[
                    'product_id'   => $it->product_id,
                    'product'      => $it->product,
                    'qty_ordered'  => $it->qty_transfer, // Target transfer
                    'unit_price'   => $it->unit_cost ?? 0,
                ]);
            }
        } elseif (in_array($first->gr_type, ['request_stock', 'gr_return'])) {
            // Karena Request & Return itu per item, kita mapping dari koleksi receipts-nya
            $grouped = $receipts->groupBy('product_id');
            foreach ($grouped as $productId => $rows) {
                $pRow = $rows->first();
                $displayItems->push((object)[
                    'product_id'   => $productId,
                    'product'      => $pRow->product,
                    'qty_ordered'  => $rows->sum('qty_requested'), 
                    'unit_price'   => $pRow->cost_per_item ?? 0,
                ]);
            }
        } else {
            // Fallback: ambil dari receipts langsung kalau sumbernya gak ketemu
            $grouped = $receipts->groupBy('product_id');
            foreach ($grouped as $productId => $rows) {
                $pRow = $rows->first();
                $displayItems->push((object)[
                    'product_id'   => $productId,
                    'product'      => $pRow->product,
                    'qty_ordered'  => $rows->sum('qty_requested'),
                    'unit_price'   => $pRow->cost_per_item ?? 0,
                ]);
            }
        }

        $goodByProduct = $receipts->groupBy('product_id')->map(fn($g) => $g->sum('qty_good'));
        $damagedByProduct = $receipts->groupBy('product_id')->map(fn($g) => $g->sum('qty_damaged'));

        return view('admin.masterdata.partials.goodReceivedDetail', compact(
            'receipts',
            'first',
            'po',
            'displayItems',
            'company',
            'isSuperadmin',
            'goodByProduct',
            'damagedByProduct'
        ));
    }


    /** Simpan Goods Received dari PO manual (1 form → banyak item) */
    public function storeFromPo(Request $request, PurchaseOrder $po)
    {
        // ===== GUARD: batasi siapa yang boleh GR =====
        $user  = $request->user();
        $roles = $user?->roles ?? collect();

        $isSuperadmin = $roles->contains('slug', 'superadmin');
        $isWarehouse  = $roles->contains('slug', 'warehouse');

        $po->loadMissing('items');
        $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

        if ($fromRequest) {
            // PO dari Request Restock → hanya Admin Warehouse
            if (! $isWarehouse) {
                return back()->with('error', 'GR PO dari Request Restock hanya bisa dilakukan Admin Warehouse.');
            }

            // pastiin PO ini buat warehouse dia
            $myWhId = $user->warehouse_id ?? null;
            if ($myWhId) {
                $poWhIds = $po->items->pluck('warehouse_id')->filter()->unique();
                if (! $poWhIds->contains($myWhId)) {
                    abort(403, 'PO ini bukan untuk warehouse kamu.');
                }
            }
        } else {
            // PO Central → hanya Superadmin
            if (! $isSuperadmin) {
                abort(403, 'GR PO Central hanya bisa dilakukan Superadmin.');
            }
        }

        // ✅ validasi backend (ini wajib biar data aman)
        $data = $request->validate([
            'receives'               => ['required', 'array', 'min:1'],
            'receives.*.qty_good'    => ['nullable', 'integer', 'min:0'],
            'receives.*.qty_damaged' => ['nullable', 'integer', 'min:0'],
            'receives.*.notes'       => ['nullable', 'string', 'max:500'],

            'photos_good'            => ['nullable'],
            'photos_good.*'          => ['nullable', 'image', 'max:4096'],
            'photos_damaged'         => ['nullable'],
            'photos_damaged.*'       => ['nullable', 'image', 'max:4096'],
        ]);

        $rows           = $data['receives'];
        $now            = now();
        $anyRow         = false;
        $firstReceiptId = null;

        $hasCodeColumn = Schema::hasColumn('restock_receipts', 'code');
        $grCode = $hasCodeColumn ? $this->nextReceiptCode() : null;

        $touchedRrIds   = [];
        DB::beginTransaction();
        
        try {
            foreach ($rows as $itemId => $row) {
                $item = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->where('id', $itemId)
                    ->first();

                if (! $item) continue;

                $good = (int) ($row['qty_good'] ?? 0);
                $bad  = (int) ($row['qty_damaged'] ?? 0);

                if ($good === 0 && $bad === 0) continue;

                $ordered   = (int) ($item->qty_ordered ?? 0);
                $received  = (int) ($item->qty_received ?? 0);
                $remaining = max(0, $ordered - $received);

                // ✅ safety backend (biar ga lewat remaining)
                if ($remaining > 0 && ($good + $bad) > $remaining) {
                    DB::rollBack();
                    return back()->with('error', 'Qty Good + Damaged melebihi Qty Remaining untuk salah satu item.');
                }

                // PO manual superadmin → CENTRAL (warehouse_id null)
                // PO dari request → boleh simpan warehouse_id dari item (kalau kolomnya ada)
                $warehouseId = $fromRequest ? ($item->warehouse_id ?? null) : null;

                $payload = [
                    'purchase_order_id' => $po->id,
                    'request_id'        => $item->request_id,
                    'gr_type' => $item->request_id
                        ? RestockReceipt::TYPE_REQUEST_STOCK
                        : RestockReceipt::TYPE_PO,
                    'product_id'        => $item->product_id,
                    'warehouse_id'      => $warehouseId,
                    'supplier_id'       => $po->supplier_id ?? DB::table('suppliers')->orderBy('id')->value('id'),
                    'qty_requested'     => $ordered,
                    'qty_good'          => $good,
                    'qty_damaged'       => $bad,
                    'notes'             => $row['notes'] ?? null,
                    'received_by'       => $user?->id,
                    'received_at'       => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];

                if ($hasCodeColumn) {
                    $payload['code'] = $grCode;
                }

                $receiptId = DB::table('restock_receipts')->insertGetId($payload);
                $anyRow    = true;
                if (! $firstReceiptId) $firstReceiptId = $receiptId;

                // update qty_received item
                $newReceived = $received + $good + $bad;
                DB::table('purchase_order_items')
                    ->where('id', $item->id)
                    ->update([
                        'qty_received' => $newReceived,
                        'updated_at'   => $now,
                    ]);

                if ($item->request_id) {
                    $touchedRrIds[$item->request_id] = (int) $item->request_id;
                }

                // update stok (warehouse atau central)
                if (Schema::hasTable('stock_levels')) {
                    if ($item->request_id && $warehouseId) {
                        // FIX: Request Stock wajib potong pusat sejumlah TOTAL (Bagus + Rusak)
                        // dan tambah cabang hanya yang Bagus
                        $this->adjustWarehouseStock($warehouseId, $item->product_id, $good);
                        $this->adjustCentralStock($item->product_id, -($good + $bad)); 
                    } else {
                        // PO Manual: barang datang dari luar, masuk ke pusat
                        $this->adjustCentralStock($item->product_id, $good);
                    }
                }

                // logic karantina (Opsi A): masukkan ke damaged_stocks jika ada yang rusak
                if ($bad > 0 && Schema::hasTable('damaged_stocks')) {
                    DB::table('damaged_stocks')->insert([
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $warehouseId ?? 0, // 0 = pusat/central
                        'source_type'  => $item->request_id ? 'request_stock' : 'purchase_order',
                        'source_id'    => $item->request_id ?: $po->id,
                        'quantity'     => $bad,
                        'condition'    => 'damaged',
                        'status'       => 'quarantine',
                        'notes'        => "Auto-created from GR: " . ($grCode ?? '-') . ". " . ($row['notes'] ?? ''),
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }

            if (! $anyRow) {
                DB::rollBack();
                return back()->with('error', 'Tidak ada qty yang diinput untuk Goods Received.');
            }

            // upload foto
            if ($firstReceiptId) {
                $this->storeReceiptPhotos($request->file('photos_good') ?? [],    $firstReceiptId, 'good');
                $this->storeReceiptPhotos($request->file('photos_damaged') ?? [], $firstReceiptId, 'damaged');
            }

            // ✅ UPDATE STATUS PO & RR BERDASARKAN DATA TERBARU (Satu Pintu via Trait)
            $this->recalcPoFromReceipts($po->id);

            foreach ($touchedRrIds as $rrId) {
                $this->recalcRequestRestock($rrId);
            }

            DB::commit();

            // ✅ kunci: ini yang nanti dibaca blade buat Swal sukses
            return back()->with('gr_success', 'Goods Received berhasil disimpan.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Gagal menyimpan Goods Received: ' . $e->getMessage());
        }
    }



    /**
     * Simpan foto GR ke tabel restock_receipt_photos
     * $kind = 'good' atau 'damaged'
     */
    protected function storeReceiptPhotos($files, int $receiptId, string $kind): void
    {
        if (empty($files)) {
            return;
        }

        if (! is_array($files)) {
            $files = [$files];
        }

        $now        = now();
        $hasType    = Schema::hasColumn('restock_receipt_photos', 'type');
        $hasKind    = Schema::hasColumn('restock_receipt_photos', 'kind');
        $hasCaption = Schema::hasColumn('restock_receipt_photos', 'caption');

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('restock_receipts', 'public');

            $row = [
                'receipt_id' => $receiptId,
                'path'       => $path,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasType) {
                $row['type'] = $kind;
            } elseif ($hasKind) {
                $row['kind'] = $kind;
            } elseif ($hasCaption) {
                $row['caption'] = $kind;
            }

            DB::table('restock_receipt_photos')->insert($row);
        }
    }

    // ================== DIRECT CANCEL GR (superadmin / warehouse) ==================

    /**
     * Cancel GR langsung dari halaman Goods Received
     * - Kalau GR dari supplier → pusat dikurangi, PO direcalc
     * - Kalau GR dari restock (warehouse) → stok warehouse dikurangi, stok pusat dikembalikan, PO & Request direcalc
     */
    public function cancelFromGr(Request $request, $code)
    {
        // Security Audit: Hanya Superadmin yang boleh Rollback/Cancel GR
        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized. Only Superadmins can cancel Goods Received records.');
        }

        $receipts = RestockReceipt::where('code', $code)->get();

        if ($receipts->isEmpty()) {
            return back()->with('error', 'Data Goods Received tidak ditemukan.');
        }

        try {
            DB::beginTransaction();

            $this->rollbackByCode($code);

            DB::commit();

            return back()->with('success', 'Goods Received ' . $code . ' berhasil dibatalkan dan stok telah di-rollback.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Gagal membatalkan GR: ' . $e->getMessage());
        }
    }

    /**
     * Logic rollback cerdas berdasarkan KODE GR tunggal.
     * Mendukung PO, Warehouse Transfer, dan Sales Return.
     */
    protected function rollbackByCode(string $code): void
    {
        $receipts = RestockReceipt::where('code', $code)->lockForUpdate()->get();
        if ($receipts->isEmpty()) return;

        $now = now();
        $first = $receipts->first();
        $grType = $first->gr_type;

        // 1. ROLLBACK STOK & UPDATE MODEL ASAL
        foreach ($receipts as $rr) {
            $qtyGood = (int) $rr->qty_good;
            $productId = (int) $rr->product_id;
            if ($qtyGood <= 0 && (int)$rr->qty_damaged <= 0) continue;

            // --- TIPE PO / REQUEST STOCK (RR) ---
            if (in_array($grType, [RestockReceipt::TYPE_PO, RestockReceipt::TYPE_REQUEST_STOCK])) {
                if ($rr->request_id) {
                    // Update Request Restocks (RR) - decrement manual (optional but safe)
                    $totalDeduction = $qtyGood + (int)$rr->qty_damaged;
                    
                    DB::table('request_restocks')
                        ->where('id', $rr->request_id)
                        ->decrement('quantity_received', $qtyGood);
                    
                    // recal akan diproses di akhir setelah delete receipt

                    // 2) Balikkan stok: Kurangi Cabang (Warehouse), Balikin ke Pusat (Central)
                    if ($rr->warehouse_id) {
                        $this->adjustWarehouseStock($rr->warehouse_id, $productId, -$qtyGood);
                    }
                    $this->adjustCentralStock($productId, $qtyGood);
                } else {
                    // Balikkan stok PO Pusat: Kurangi Pusat
                    $this->adjustCentralStock($productId, -$qtyGood);
                }

                // 3) Kurangi qty_received di PO Item
                DB::table('purchase_order_items')
                    ->where('purchase_order_id', $rr->purchase_order_id)
                    ->where('product_id', $productId)
                    ->decrement('qty_received', $qtyGood + (int)$rr->qty_damaged);

                // recalc status PO akan diproses di akhir
            } 
            
            // --- TIPE TRANSFER ---
            elseif ($grType == RestockReceipt::TYPE_TRANSFER) {
                $transfer = \App\Models\WarehouseTransfer::find($rr->request_id);
                if ($transfer) {
                    // Balikkan stok: Kurangi Penerima (Source), Balikin ke Pengirim (Destination)
                    $this->adjustWarehouseStock($transfer->source_warehouse_id, $productId, -$qtyGood);
                    $this->adjustWarehouseStock($transfer->destination_warehouse_id, $productId, (int)$rr->qty_requested);
                    
                    // Kembalikan status transfer agar bisa di-GR ulang oleh source warehouse
                    $transfer->update(['status' => 'approved']);
                }
            }

            // --- TIPE RETURN ---
            elseif ($grType == RestockReceipt::TYPE_RETURN) {
                // Balikkan stok good
                if ($qtyGood > 0 && $rr->warehouse_id) {
                    $this->adjustWarehouseStock($rr->warehouse_id, $productId, -$qtyGood);
                }
                
                // PEMBERSIHAN DAMAGED STOCK (Semua Tipe)
                // Jika saat GR ada barang rusak yang masuk karantina, hapus juga saat rollback
                DB::table('damaged_stocks')
                    ->where('source_id', $rr->purchase_order_id ?: $rr->request_id)
                    ->whereIn('source_type', ['purchase_order', 'request_stock', 'warehouse_transfer', 'sales_return'])
                    ->where('status', 'quarantine')
                    ->delete();

                // Kembalikan status SalesReturn
                DB::table('sales_returns')
                    ->where('id', $rr->request_id)
                    ->update(['status' => 'pending', 'approved_at' => null, 'approved_by' => null]);
            }
        }

        // 2. HAPUS FOTO & FILE
        $ids = $receipts->pluck('id')->all();
        $photos = DB::table('restock_receipt_photos')->whereIn('receipt_id', $ids)->get();
        foreach ($photos as $p) {
            if (!empty($p->path)) \Storage::disk('public')->delete($p->path);
        }
        DB::table('restock_receipt_photos')->whereIn('receipt_id', $ids)->delete();

        // 3. HAPUS DATA RECEIPT
        RestockReceipt::whereIn('id', $ids)->delete();

        // 4. RECALC STATUS (RS & PO) - Harus sesudah delete agar sum() akurat
        $touchedRrIds = $receipts->pluck('request_id')->filter()->unique();
        foreach ($touchedRrIds as $rrId) {
            $this->recalcRequestRestock((int) $rrId);
        }

        if ($first->purchase_order_id) {
            $this->recalcPoFromReceipts($first->purchase_order_id);
        }
    }


    // ================== REQUEST DELETE GR (dengan approval) ==================

    public function requestDelete(Request $r, PurchaseOrder $po)
    {
        $data = $r->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $hasPending = GrDeleteRequest::where('purchase_order_id', $po->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()->with('error', 'Masih ada permohonan delete GR yang pending untuk PO ini.');
        }

        $firstReceipt = RestockReceipt::where('purchase_order_id', $po->id)
            ->orderBy('id')
            ->first();

        if (! $firstReceipt) {
            return back()->with('error', 'PO ini tidak memiliki data Goods Received.');
        }

        GrDeleteRequest::create([
            'restock_receipt_id' => $firstReceipt->id,
            'purchase_order_id'  => $po->id,
            'requested_by'       => auth()->id(),
            'status'             => 'pending',
            'reason'             => $data['reason'],
        ]);

        return back()->with('success', 'Permohonan delete GR berhasil diajukan. Menunggu approval.');
    }

    public function handleDeleteApproval(Request $r, GrDeleteRequest $requestModel)
    {
        $data = $r->validate([
            'action'        => ['required', 'in:approve,reject'],
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($requestModel->status !== 'pending') {
            return back()->with('error', 'Permohonan ini sudah diproses sebelumnya.');
        }

        if ($data['action'] === 'reject') {
            $requestModel->update([
                'status'        => 'rejected',
                'approved_by'   => auth()->id(),
                'approval_note' => $data['approval_note'] ?? null,
            ]);

            return back()->with('success', 'Permohonan delete GR ditolak.');
        }

        try {
            $this->rollbackGoodsReceivedForPo($requestModel->purchase_order_id);

            $requestModel->update([
                'status'        => 'approved',
                'approved_by'   => auth()->id(),
                'approval_note' => $data['approval_note'] ?? null,
            ]);

            return back()->with('success', 'Goods Received berhasil dihapus dan PO dibuka kembali.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal menghapus Goods Received: ' . $e->getMessage());
        }
    }

    /**
     * Rollback SEMUA GR untuk 1 PO (dipakai saat superadmin approve permohonan delete)
     * - Kalau GR supplier → pusat dikurangi
     * - Kalau GR restock → warehouse dikurangi, pusat dikembalikan
     * - Item.qty_received dikurangi
     * - PO status jadi 'approved' lagi, received_at di-reset
     * - Request Restock di-recalc
     */
/**
 * Rollback SEMUA GR untuk 1 PO (dipakai saat superadmin approve permohonan delete)
 * - Kalau GR supplier → pusat dikurangi
 * - Kalau GR restock → warehouse dikurangi, pusat dikembalikan
 * - Item.qty_received dikurangi
 * - PO status balik ke 'draft' dan approval direset (bisa diedit & diajukan lagi)
 * - Request Restock di-recalc
 */
    protected function rollbackGoodsReceivedForPo(int $poId): void
    {
        DB::transaction(function () use ($poId) {
            $now = now();

            $receipts = RestockReceipt::where('purchase_order_id', $poId)
                ->lockForUpdate()
                ->get();

            if ($receipts->isEmpty()) {
                return;
            }

            // ============= 1. ROLLBACK STOK UNTUK SEMUA RECEIPT =============
            if (Schema::hasTable('stock_levels')) {
                foreach ($receipts as $rr) {
                    $productId   = (int) $rr->product_id;
                    $warehouseId = (int) ($rr->warehouse_id ?? 0);
                    $requestId   = (int) ($rr->request_id ?? 0);
                    $qtyGood     = (int) $rr->qty_good;

                    if (! $productId || $qtyGood <= 0) {
                        continue;
                    }

                    if ($requestId) {
                        // FIX: GR dari Restock (Pusat ke Cabang) 
                        // Rollback: Kurangi Cabang, Balikin ke Pusat
                        if ($warehouseId) {
                            $this->adjustWarehouseStock($warehouseId, $productId, -$qtyGood);
                        }
                        $this->adjustCentralStock($productId, $qtyGood);
                    } else {
                        // GR dari Supplier: Balikin stok pusat (kurangi karena barang batal masuk)
                        $this->adjustCentralStock($productId, -$qtyGood);
                    }
                }
            }

            // ============= 2. UPDATE qty_received PER ITEM PO =============
            $poItems = PurchaseOrderItem::where('purchase_order_id', $poId)->get();

            foreach ($poItems as $item) {
                $sumReceipts = $receipts
                    ->where('product_id', $item->product_id)
                    ->sum(function ($r) {
                        return (int) $r->qty_good + (int) $r->qty_damaged;
                    });

                if ($sumReceipts <= 0) {
                    continue;
                }

                $newReceived = max(0, (int) $item->qty_received - $sumReceipts);

                DB::table('purchase_order_items')
                    ->where('id', $item->id)
                    ->update([
                        'qty_received' => $newReceived,
                        'updated_at'   => $now,
                    ]);
            }

            // ============= 3. HAPUS FOTO + FILE FISIK =============
            if (Schema::hasTable('restock_receipt_photos')) {
                $ids = $receipts->pluck('id')->all();

                $photos = DB::table('restock_receipt_photos')
                    ->whereIn('receipt_id', $ids)
                    ->get();

                foreach ($photos as $p) {
                delete_file_if_exists($p->path);
                }
                

                DB::table('restock_receipt_photos')
                    ->whereIn('receipt_id', $ids)
                    ->delete();
            }

            // ============= 4. HAPUS SEMUA RECEIPT =============
            RestockReceipt::whereIn('id', $receipts->pluck('id')->all())->delete();

            // ============= 5. RE-CALC REQUEST RESTOCK (JIKA ADA) =============
            if (Schema::hasTable('request_restocks')) {
                $reqIds = $receipts->pluck('request_id')->filter()->unique()->values();

                foreach ($reqIds as $rid) {
                    $this->recalcRequestRestock((int) $rid);
                }
            }

            // ============= 6. RESET STATUS & APPROVAL PO =============
            $updatePo = [
                // status barang kembali seperti sebelum ada GR
                'status'     => 'draft',
                'approval_status' => 'draft',      // buka lagi flow approval
                'updated_at'      => $now,
            ];

            // amanin pakai Schema::hasColumn biar kalau kolom belum ada nggak error
            if (Schema::hasColumn('purchase_orders', 'approval_status')) {
                $updatePo['approval_status'] = 'draft';
            }
            if (Schema::hasColumn('purchase_orders', 'procurement_approved_by')) {
                $updatePo['procurement_approved_by'] = null;
            }
            if (Schema::hasColumn('purchase_orders', 'procurement_approved_at')) {
                $updatePo['procurement_approved_at'] = null;
            }
            if (Schema::hasColumn('purchase_orders', 'ceo_approved_by')) {
                $updatePo['ceo_approved_by'] = null;
            }
            if (Schema::hasColumn('purchase_orders', 'ceo_approved_at')) {
                $updatePo['ceo_approved_at'] = null;
            }
            if (Schema::hasColumn('purchase_orders', 'received_at')) {
                $updatePo['received_at'] = null;
            }

            DB::table('purchase_orders')
                ->where('id', $poId)
                ->update($updatePo);
        });
    }

    // ================== HELPER STOK ==================

}
