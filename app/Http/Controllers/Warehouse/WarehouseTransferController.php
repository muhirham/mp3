<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Exports\TransferWarehouse\WarehouseTransferIndexWithItemsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Models\WarehouseTransferLog;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Company;

class WarehouseTransferController extends Controller
{
    /* ======================================================
     * INDEX
     * ====================================================== */
    protected function nextTransferCode(): string
    {
        $prefix = 'WT-' . now()->format('ymd') . '-';

        $last = DB::table('warehouse_transfers')
            ->where('transfer_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('transfer_code');

        $n = 1;
        if ($last) {
            $lastSeq = (int) substr($last, -4);
            $n       = $lastSeq + 1;
        }

        return $prefix . str_pad($n, 4, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $me = auth()->user();

        $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
        $isWarehouseUser    = $me->hasRole('warehouse');

        $warehouses = $canSwitchWarehouse
            ? Warehouse::orderBy('warehouse_name')->get()
            : Warehouse::where('id', $me->warehouse_id)->get();

        $toWarehouses = Warehouse::orderBy('warehouse_name')->get();

        return view('wh.transfer_index', compact(
            'me',
            'warehouses',
            'toWarehouses',
            'canSwitchWarehouse',
            'isWarehouseUser'
        ));
    }

    /* ======================================================
     * CREATE FORM
     * ====================================================== */
    public function create()
    {
        $me = auth()->user();
        $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);

        $warehouses = $canSwitchWarehouse
            ? Warehouse::orderBy('warehouse_name')->get()
            : Warehouse::where('id', $me->warehouse_id)->get();

        $toWarehouses = Warehouse::orderBy('warehouse_name')->get();

        return view('wh.transfer_form', [
            'me' => $me,
            'transfer' => new WarehouseTransfer(),
            'warehouses' => $warehouses,
            'toWarehouses' => $toWarehouses,
            'products' => collect(),
            'canSwitchWarehouse' => $canSwitchWarehouse,
            'canApproveSource' => false,
            'canApproveDestination' => false,
            'canGrSource' => false,
            'canCancel' => false,
            'canPrintSJ' => false, // ✅ TAMBAH INI
            'receivedLog' => null,
        ]);
    }

    /* ======================================================
     * LOAD PRODUCTS (BERDASARKAN STOK GUDANG ASAL)
     * ====================================================== */
    public function products(Request $request)
    {
        $q = trim($request->q ?? '');

        $products = Product::query()
            ->when($q, function ($qq) use ($q) {
                $qq->where(function ($x) use ($q) {
                    $x->where('name', 'like', "%{$q}%")
                        ->orWhere('product_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get([
                'id',
                'product_code',
                'name',
                'purchasing_price',
            ]);

        // optional: inject stok (biar bisa ditampilin)
        $warehouseId = $request->warehouse_id;

        if ($warehouseId) {
            $stocks = DB::table('stock_levels')
                ->where('owner_type', 'warehouse')
                ->where('owner_id', $warehouseId)
                ->groupBy('product_id')
                ->pluck(DB::raw('SUM(quantity)'), 'product_id');

            $products->transform(function ($p) use ($stocks) {
                $p->available_stock = (int) ($stocks[$p->id] ?? 0);
                return $p;
            });
        }

        return response()->json($products);
    }


    /* ======================================================
     * STORE DRAFT
     * ====================================================== */
    public function store(Request $r)
    {
        $r->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'items'             => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'       => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($r) {

            $sourceWarehouseId = auth()->user()->hasRole('warehouse')
                ? auth()->user()->warehouse_id
                : $r->from_warehouse_id;

            $transfer = WarehouseTransfer::create([
                'transfer_code' => $this->nextTransferCode(),
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $r->to_warehouse_id,
                'status' => 'pending_destination',
                'created_by' => auth()->id(),
                'note' => $r->note,
                'total_cost' => 0,
            ]);

            $total = 0;

            foreach ($r->items as $row) {
                $product = Product::findOrFail($row['product_id']);
                $subtotal = $product->purchasing_price * $row['qty'];

                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'product_id'            => $product->id,
                    'qty_transfer'          => $row['qty'],
                    'qty_good'              => 0,
                    'qty_damaged'           => 0,
                    'unit_cost'             => $product->purchasing_price,
                    'subtotal_cost'         => $subtotal,
                ]);
                $total += $subtotal;
            }

            $transfer->update(['total_cost' => $total]);
            WarehouseTransferLog::create([
                'warehouse_transfer_id' => $transfer->id,
                'performed_by' => auth()->id(),
                'action' => 'SUBMITTED',
                'note' => 'Transfer submitted to destination warehouse',
            ]);
            return response()->json([
                'success' => true,
                'id' => $transfer->id
            ]);
        });
    }


    /* ======================================================
     * SUBMIT
     * ====================================================== */
    public function submit(WarehouseTransfer $transfer)
    {
        if ($transfer->status !== 'draft') {
            abort(422, 'Invalid status');
        }

        return DB::transaction(function () use ($transfer) {

            $transfer->update([
                'transfer_code' => $transfer->transfer_code ?: $this->nextTransferCode(),
                'status' => 'pending_source',
            ]);

            WarehouseTransferLog::create([
                'warehouse_transfer_id' => $transfer->id,
                'performed_by' => auth()->id(),
                'action' => 'SUBMITTED',
                'note' => 'Transfer submitted',
            ]);

            return response()->json($transfer);
        });
    }


    /* ======================================================
     * APPROVE DESTINATION (PINDAH STOK)
     * ====================================================== */
    public function approveDestination(WarehouseTransfer $transfer)
    {
        if ($transfer->status !== 'pending_destination') abort(422);

        // hanya warehouse user yang wajib match gudang
        if (
            auth()->user()->hasRole('warehouse')
            && auth()->user()->warehouse_id !== $transfer->destination_warehouse_id
        ) {
            abort(403);
        }


        $transfer->update([
            'status' => 'approved',
            'approved_destination_by' => auth()->id(),
            'approved_destination_at' => now(),
        ]);

        WarehouseTransferLog::create([
            'warehouse_transfer_id' => $transfer->id,
            'performed_by' => auth()->id(),
            'action' => 'DEST_APPROVED',
            'note' => 'Destination warehouse approved',
        ]);

        return back()->with('success', 'Transfer approved by destination warehouse successfully');
    }

    public function rejectDestination(Request $r, WarehouseTransfer $transfer)
    {
        if ($transfer->status !== 'pending_destination') abort(422);

        $r->validate(['reason' => 'required|string']);

        $transfer->update([
            'status' => 'rejected',
        ]);

        WarehouseTransferLog::create([
            'warehouse_transfer_id' => $transfer->id,
            'performed_by' => auth()->id(),
            'action' => 'DEST_REJECTED',
            'note' => $r->reason,
        ]);

        return back()->with('success', 'Transfer rejected successfully');
    }

    public function grSource(Request $request, WarehouseTransfer $transfer)
    {
        // ===============================
        // VALIDASI AKSES & STATUS
        // ===============================
        abort_if(
            $transfer->status !== 'approved',
            403,
            'Transfer not yet approved by destination warehouse'
        );
        // hanya warehouse user yang wajib cocok gudang
        abort_if(
            auth()->user()->hasRole('warehouse')
                && auth()->user()->warehouse_id !== $transfer->source_warehouse_id,
            403,
            'Not the source warehouse'
        );
        // ===============================
        // VALIDASI INPUT
        // ===============================
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.good' => 'required|integer|min:0',
            'items.*.damaged' => 'required|integer|min:0',
            'items.*.note' => 'nullable|string',
            'photos_good.*' => 'nullable|mimes:jpg,jpeg,png|max:2048',
            'photos_damaged.*' => 'nullable|mimes:jpg,jpeg,png|max:2048',
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $transfer) {

            $firstReceiptId = null;
            $grCode = 'GR-WT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            foreach ($transfer->items as $item) {

                $input = $request->items[$item->id] ?? null;
                if (! $input) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => 'Item not found in request'
                    ]);
                }

                $good = (int) $input['good'];
                $damaged = (int) $input['damaged'];

                // ===============================
                // RULE WAJIB
                // ===============================
                if ($good + $damaged !== (int) $item->qty_transfer) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" =>
                        "Total of good + damaged quantity must equal the transfer quantity"
                    ]);
                }

                // ===============================
                // UPDATE ITEM (LOCK)
                // ===============================
                $item = WarehouseTransferItem::where('id', $item->id)
                    ->lockForUpdate()
                    ->first();
                // foto index sejajar item loop
                $goodPhoto   = $request->file('photos_good')[$item->id] ?? null;
                $damagedPhoto = $request->file('photos_damaged')[$item->id] ?? null;

                $item->qty_good = $good;
                $item->qty_damaged = $damaged;

                $item->photo_good = replace_uploaded_file(
                    $item->photo_good,
                    $goodPhoto,
                    "warehouse_transfer/{$transfer->id}/good"
                );

                $item->photo_damaged = replace_uploaded_file(
                    $item->photo_damaged,
                    $damagedPhoto,
                    "warehouse_transfer/{$transfer->id}/damaged"
                );

                $item->save();

                // ===============================
                // SIMPAN KE restock_receipts (CENTRALIZED)
                // ===============================
                $receiptId = DB::table('restock_receipts')->insertGetId([
                    'purchase_order_id' => null, // bukan dari PO
                    'request_id'        => $transfer->id, // link ke transfer
                    'warehouse_id'      => $transfer->source_warehouse_id,
                    'supplier_id'       => null,
                    'product_id'        => $item->product_id,
                    'gr_type'           => \App\Models\RestockReceipt::TYPE_TRANSFER,
                    'code'              => $grCode,
                    'qty_requested'     => $item->qty_transfer,
                    'qty_good'          => $good,
                    'qty_damaged'       => $damaged,
                    'notes'             => $input['note'] ?? null,
                    'received_by'       => auth()->id(),
                    'received_at'       => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                if (!$firstReceiptId) $firstReceiptId = $receiptId;

                // SIMPAN FOTO KE restock_receipt_photos (SINKRON)
                if ($item->photo_good) {
                    DB::table('restock_receipt_photos')->insert([
                        'receipt_id' => $receiptId,
                        'path'       => $item->photo_good,
                        'type'       => 'good',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                if ($item->photo_damaged) {
                    DB::table('restock_receipt_photos')->insert([
                        'receipt_id' => $receiptId,
                        'path'       => $item->photo_damaged,
                        'type'       => 'damaged',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('stock_levels')
                    ->where('owner_type', 'warehouse')
                    ->where('owner_id', $transfer->destination_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->decrement('quantity', $item->qty_transfer);

                // GUDANG REQUESTER = source_warehouse_id
                $sourceStock = DB::table('stock_levels')
                    ->where('owner_type', 'warehouse')
                    ->where('owner_id', $transfer->source_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($sourceStock) {
                    DB::table('stock_levels')
                        ->where('id', $sourceStock->id)
                        ->increment('quantity', $good);
                } else {
                    DB::table('stock_levels')->insert([
                        'owner_type' => 'warehouse',
                        'owner_id'   => $transfer->source_warehouse_id,
                        'product_id' => $item->product_id,
                        'quantity'   => $good,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // logic karantina: masukkan ke damaged_stocks jika ada yang rusak
                if ($damaged > 0 && Schema::hasTable('damaged_stocks')) {
                    DB::table('damaged_stocks')->insert([
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $transfer->source_warehouse_id, // gudang penerima
                        'source_type'  => 'warehouse_transfer',
                        'source_id'    => $transfer->id,
                        'quantity'     => $damaged,
                        'condition'    => 'damaged',
                        'status'       => 'quarantine',
                        'notes'        => "Auto-created from Transfer GR: {$grCode}. " . ($input['note'] ?? ''),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            // ===============================
            // UPDATE TRANSFER
            // ===============================
            $transfer->update([
                'status' => 'completed',
            ]);

            // ===============================
            // LOG
            // ===============================
            $transfer->logs()->create([
                'action'       => 'RECEIVED',
                'performed_by' => auth()->id(),
                'note'         => $request->note,
            ]);
        });

        return back()->with('success', 'Source warehouse Goods Received saved successfully');
    }

    /* ======================================================
     * CANCEL
     * ====================================================== */
    public function cancel(WarehouseTransfer $transfer)
    {
        if (!in_array($transfer->status, ['draft', 'pending_source'])) {
            abort(422);
        }

        $transfer->update([
            'status' => 'canceled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);

        WarehouseTransferLog::create([
            'warehouse_transfer_id' => $transfer->id,
            'performed_by' => auth()->id(),   // ✅ BENAR
            'action' => 'CANCELED',
        ]);

        return back();
    }

    public function data(Request $r)
    {
        try {
            $draw   = $r->input('draw');
            $start  = $r->input('start', 0);
            $length = $r->input('length', 10);
            $search = $r->input('search.value');

            // 1. Base Query for counting totals
            $query = DB::table('warehouse_transfers as wt');

            if (auth()->user()->hasRole('warehouse')) {
                $wid = auth()->user()->warehouse_id;
                $query->where(function ($q) use ($wid) {
                    $q->where('wt.source_warehouse_id', $wid)
                        ->orWhere('wt.destination_warehouse_id', $wid);
                });
            }
            $recordsTotal = (clone $query)->count();

            // 2. Apply Filters
            if ($r->filled('status')) {
                $query->where('wt.status', $r->status);
            }
            if ($r->filled('from_warehouse')) {
                $query->where('wt.source_warehouse_id', $r->from_warehouse);
            }
            if ($r->filled('to_warehouse')) {
                $query->where('wt.destination_warehouse_id', $r->to_warehouse);
            }

            // 3. Search Filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('wt.transfer_code', 'like', "%{$search}%")
                      ->orWhereExists(function ($qe) use ($search) {
                          $qe->select(DB::raw(1))
                             ->from('warehouse_transfer_items as wti_s')
                             ->join('products as p_s', 'p_s.id', '=', 'wti_s.product_id')
                             ->whereColumn('wti_s.warehouse_transfer_id', 'wt.id')
                             ->where(function ($qp) use ($search) {
                                 $qp->where('p_s.name', 'like', "%{$search}%")
                                    ->orWhere('p_s.product_code', 'like', "%{$search}%");
                             });
                      });
                });
            }

            $recordsFiltered = (clone $query)->count();

            // 4. Detailed Fetch with Grouping & Pagination
            $rows = $query->leftJoin('warehouses as wf', 'wf.id', '=', 'wt.source_warehouse_id')
                ->leftJoin('warehouses as wtg', 'wtg.id', '=', 'wt.destination_warehouse_id')
                ->leftJoin('warehouse_transfer_items as wti', 'wti.warehouse_transfer_id', '=', 'wt.id')
                ->leftJoin('products as p', 'p.id', '=', 'wti.product_id')
                ->select(
                    'wt.id',
                    'wt.transfer_code',
                    'wf.warehouse_name as from_warehouse',
                    'wtg.warehouse_name as to_warehouse',
                    'wt.total_cost',
                    'wt.status',
                    'wt.created_at',
                    DB::raw("SUM(COALESCE(wti.qty_transfer, 0)) as total_qty"),
                    DB::raw("GROUP_CONCAT(DISTINCT CONCAT(p.product_code, ' - ', p.name) SEPARATOR '<br>') as product_list")
                )
                ->groupBy(
                    'wt.id',
                    'wt.transfer_code',
                    'wf.warehouse_name',
                    'wtg.warehouse_name',
                    'wt.total_cost',
                    'wt.status',
                    'wt.created_at'
                )
                ->orderByDesc('wt.id')
                ->offset($start)
                ->limit($length)
                ->get();

            $data = [];
            foreach ($rows as $row) {
                $badge = match ($row->status) {
                    'draft'               => 'secondary',
                    'pending_source'      => 'warning',
                    'pending_destination' => 'info',
                    'approved'            => 'primary',
                    'completed'           => 'success',
                    'rejected'            => 'danger',
                    'canceled'            => 'dark',
                    default               => 'secondary',
                };

                $data[] = [
                    'code'           => $row->transfer_code ?? '-',
                    'products'       => $row->product_list ?: '-',
                    'from_warehouse' => $row->from_warehouse ?? '-',
                    'to_warehouse'   => $row->to_warehouse ?? '-',
                    'total_qty'      => (int) $row->total_qty,
                    'total_cost'     => number_format($row->total_cost, 0, ',', '.'),
                    'status_badge'   => '<span class="badge bg-label-' . $badge . '">' . strtoupper($row->status) . '</span>',
                    'created_at'     => \Carbon\Carbon::parse($row->created_at)->format('d/m/Y'),
                    'action'         => '<a href="' . route('warehouse-transfer-forms.show', $row->id) . '" class="btn btn-sm btn-primary">Open</a>',
                ];
            }

            return response()->json([
                'draw' => (int)$draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            \Log::error('WarehouseTransfer datatable error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int)$r->input('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show(WarehouseTransfer $transfer)
    {
        $me = auth()->user();

        $transfer->load([
            'items.product',
            'logs.user',
            'sourceWarehouse',
            'destinationWarehouse',
            'creator', // 🔥 INI YANG HILANG
        ]);

        // ❌ TIDAK ADA APPROVE SOURCE LAGI
        $canApproveSource = false;

        // ✅ APPROVE OLEH GUDANG TUJUAN
        $canApproveDestination =
            $transfer->status === 'pending_destination'
            && (
                // warehouse user WAJIB cocok gudang tujuan€
                ($me->hasRole('warehouse') && $me->warehouse_id === $transfer->destination_warehouse_id)

                // admin & superadmin bebas approve
                || $me->hasRole(['admin', 'superadmin'])
            );
        // ✅ GR OLEH GUDANG ASAL
        $canGrSource =
            $transfer->status === 'approved'
            && (
                // warehouse user wajib match gudang asal
                ($me->hasRole('warehouse') && $me->warehouse_id === $transfer->source_warehouse_id)

                // admin & superadmin bebas GR
                || $me->hasRole(['admin', 'superadmin'])
            );

        $canPrintSJ =
            (
                // SUPERADMIN BOLEH SELAMA BELUM DIBATALKAN
                $me->hasRole('superadmin')
                && in_array($transfer->status, ['approved', 'completed'])) ||
            (
                // GUDANG PENGIRIM HANYA SAAT APPROVED
                $me->hasRole('warehouse')
                && $me->warehouse_id === $transfer->destination_warehouse_id
                && $transfer->status === 'approved'
            );



        $canCancel = false;

        $receivedLog = $transfer->logs
            ->where('action', 'RECEIVED')
            ->sortByDesc('created_at')
            ->first();


        return view('wh.transfer_form', [
            'me' => $me,
            'transfer' => $transfer,
            'warehouses' => collect(),
            'toWarehouses' => collect(),
            'canSwitchWarehouse' => false,
            'canApproveSource' => $canApproveSource,
            'canApproveDestination' => $canApproveDestination,
            'canGrSource' => $canGrSource,
            'canCancel' => $canCancel,
            'canPrintSJ' => $canPrintSJ,
            'receivedLog' => $receivedLog,
        ]);
    }

    public function printSJ(Request $request, WarehouseTransfer $transfer)
    {
        $me = auth()->user();

        abort_if(
            !$me->hasRole('superadmin')
                && !(
                    $me->hasRole('warehouse')
                    && $me->warehouse_id === $transfer->destination_warehouse_id
                    && $transfer->status === 'approved'
                ),
            403
        );

        $company = Company::where('is_default', true)
            ->where('is_active', true)
            ->first();

        $transfer->load([
            'items.product',
            'sourceWarehouse',
            'destinationWarehouse',
            'creator',
        ]);

        $receivedLog = $transfer->logs()
            ->where('action', 'RECEIVED')
            ->latest()
            ->first();


        return view('wh.transfer_printSJ', [
            'transfer'  => $transfer,
            'company'   => $company,
            'isDraft'   => false,
            'receivedLog' => $receivedLog,
        ]);
    }

    public function exportIndexExcel(Request $request)
    {
        $q      = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $fromWh = (string) $request->input('from_warehouse_id', '');
        $toWh   = (string) $request->input('to_warehouse_id', '');

        $from = $request->input('from_date');
        $to   = $request->input('to_date');
        $useDate = $from && $to;

        $key = now()->format('YmdHis');

        $query = WarehouseTransfer::query()
            ->with([
                'items.product',
                'sourceWarehouse',
                'destinationWarehouse',
                'creator',
            ])
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where('transfer_code', 'like', "%{$q}%");
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($fromWh !== '') {
            $query->where('source_warehouse_id', $fromWh);
        }

        if ($toWh !== '') {
            $query->where('destination_warehouse_id', $toWh);
        }

        if ($useDate) {
            $query->whereBetween(
                'created_at',
                [
                    \Carbon\Carbon::parse($from)->startOfDay(),
                    \Carbon\Carbon::parse($to)->endOfDay()
                ]
            );
        }

        $transfers = $query->get();

        $company = Company::where('is_default', true)
            ->where('is_active', true)
            ->first();

        $meta = [
                'filters' => [
                    'Status' => $request->status ?: 'All',
                    'From Warehouse' => optional(
                        \App\Models\Warehouse::find($request->from_warehouse_id)
                    )->warehouse_name ?: 'All',
                    'To Warehouse' => optional(
                        \App\Models\Warehouse::find($request->to_warehouse_id)
                    )->warehouse_name ?: 'All',
                    'Search' => $request->q ?: '-',
                ]
            ];

        $filename = "WT-INDEX-DETAIL-{$key}.xlsx";

        return Excel::download(
            new WarehouseTransferIndexWithItemsExport(
                $transfers,
                $meta,
                $company
            ),
            $filename
        );
    }

}
