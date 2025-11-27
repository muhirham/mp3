<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class StockWhController extends Controller
{
    /* ================== INDEX (HALAMAN) ================== */

    public function index(Request $r)
    {
        $me = auth()->user();

        $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
        $isWarehouseUser    = $me->hasRole('warehouse');

        $warehouses          = collect();
        $selectedWarehouseId = null;

        if ($canSwitchWarehouse) {
            $warehouses = Warehouse::orderBy('warehouse_name')
                ->get(['id', 'warehouse_name']);

            $selectedWarehouseId = $r->integer('warehouse_id') ?: null;
        } elseif ($isWarehouseUser) {
            $selectedWarehouseId = $me->warehouse_id;
        }

        // cuma product (supplier nanti diambil dari kolom di tabel products)
        $products = Product::orderBy('name')
            ->get(['id', 'name', 'product_code']);

        return view('wh.restocks', compact(
            'me',
            'warehouses',
            'selectedWarehouseId',
            'canSwitchWarehouse',
            'isWarehouseUser',
            'products'
        ));
    }

    /* ================== DATATABLE ================== */

        public function datatable(Request $r)
        {
            try {
                $me = auth()->user();

                $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
                $isWarehouseUser    = $me->hasRole('warehouse');

                // tentukan warehouse yang dipakai filter
                if ($isWarehouseUser) {
                    $warehouseId = $me->warehouse_id;
                } elseif ($canSwitchWarehouse) {
                    $warehouseId = $r->integer('warehouse_id') ?: null;
                } else {
                    $warehouseId = null;
                }

                // parameter DataTables
                $draw        = (int) $r->input('draw', 1);
                $start       = (int) $r->input('start', 0);
                $length      = (int) $r->input('length', 10);
                $orderColIdx = (int) $r->input('order.0.column', 1);
                $orderDir    = $r->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
                $search      = trim((string) $r->input('search.value', ''));

                $hasCode  = Schema::hasColumn('request_restocks', 'code');
                $hasWh    = Schema::hasColumn('request_restocks', 'warehouse_id');
                $hasReqBy = Schema::hasColumn('request_restocks', 'requested_by');

                // ekspresi qty request (per baris item)
                if (Schema::hasColumn('request_restocks', 'quantity_requested')) {
                    $qtyReqExpr = 'COALESCE(rr.quantity_requested,0)';
                } elseif (Schema::hasColumn('request_restocks', 'qty_requested')) {
                    $qtyReqExpr = 'COALESCE(rr.qty_requested,0)';
                } elseif (Schema::hasColumn('request_restocks', 'qty')) {
                    $qtyReqExpr = 'COALESCE(rr.qty,0)';
                } else {
                    $qtyReqExpr = '0';
                }

                // ekspresi qty received (per baris item)
                $rcvJoin = false;
                if (Schema::hasColumn('request_restocks', 'quantity_received')) {
                    $qtyRcvExpr = 'COALESCE(rr.quantity_received,0)';
                } elseif (Schema::hasColumn('request_restocks', 'qty_received')) {
                    $qtyRcvExpr = 'COALESCE(rr.qty_received,0)';
                } elseif (Schema::hasTable('restock_receipts') && Schema::hasColumn('restock_receipts', 'qty_good')) {
                    $rcvJoin    = true;
                    $qtyRcvExpr = 'COALESCE(rcv.qty_rcv,0)';
                } else {
                    $qtyRcvExpr = '0';
                }

                // kolom note / description
                if (Schema::hasColumn('request_restocks', 'note')) {
                    $noteCol = 'rr.note';
                } elseif (Schema::hasColumn('request_restocks', 'description')) {
                    $noteCol = 'rr.description';
                } else {
                    $noteCol = "''"; // kosong
                }

                // mapping kolom order dari DataTables
                $orderMap = [
                    1 => 'code',
                    2 => 'product_name',
                    3 => 'supplier_name',
                    4 => 'qty_req',
                    5 => 'qty_rcv',
                    6 => 'status',
                    7 => 'created_at',
                    8 => 'note',
                ];
                $orderKey = $orderMap[$orderColIdx] ?? 'code';

                // ekspresi code
                $codeExpr = $hasCode
                    ? 'rr.code'
                    : "CONCAT('RR-', rr.id)";

                $base = DB::table('request_restocks as rr')
                    ->leftJoin('products as p', 'p.id', '=', 'rr.product_id')
                    ->leftJoin('suppliers as s', 's.id', '=', 'rr.supplier_id');

                // join qty received (opsional)
                if ($rcvJoin) {
                    $sub = DB::table('restock_receipts')
                        ->selectRaw('request_id, COALESCE(SUM(qty_good),0) as qty_rcv')
                        ->groupBy('request_id');

                    $base->leftJoinSub($sub, 'rcv', 'rcv.request_id', '=', 'rr.id');
                }

                // FILTER GUDANG
                if ($warehouseId) {
                    if ($hasWh) {
                        $base->where('rr.warehouse_id', $warehouseId);
                    } elseif ($hasReqBy && $isWarehouseUser) {
                        $base->where('rr.requested_by', $me->id);
                    }
                } elseif ($isWarehouseUser && $hasReqBy && ! $hasWh) {
                    $base->where('rr.requested_by', $me->id);
                }

                // GROUP BY CODE (1 dokumen = 1 baris)
                if ($hasCode) {
                    $base->groupBy('rr.code');
                } else {
                    $base->groupBy('rr.id');
                }

                // total sebelum search
                $recordsTotal = (clone $base)->count();

                // SEARCH
                if ($search !== '') {
                    $like = '%' . $search . '%';

                    $base->where(function ($q) use ($like, $hasCode, $noteCol) {
                        if ($hasCode) {
                            $q->where('rr.code', 'like', $like);
                        } else {
                            $q->where('rr.id', 'like', $like);
                        }

                        $q->orWhere('p.name', 'like', $like)
                        ->orWhere('p.product_code', 'like', $like)
                        ->orWhere('s.name', 'like', $like);

                        if ($noteCol !== "''") {
                            $q->orWhereRaw($noteCol . ' LIKE ?', [$like]);
                        }
                    });
                }

                $recordsFiltered = (clone $base)->count();

                // SELECT agregat per dokumen RR
                $selects = [
                    DB::raw('MIN(rr.id) as id'),
                    DB::raw($codeExpr . ' as code'),
                    DB::raw('MIN(p.product_code) as product_code'),
                    DB::raw('MIN(p.name) as product_name'),
                    DB::raw('MIN(s.name) as supplier_name'),
                    DB::raw('COUNT(*) as item_count'),
                    DB::raw('SUM(' . $qtyReqExpr . ') as qty_req'),
                    DB::raw('SUM(' . $qtyRcvExpr . ') as qty_rcv'),
                    DB::raw("MIN(COALESCE(rr.status,'pending')) as status"),
                    DB::raw('MIN(rr.created_at) as created_at'),
                ];

                if ($noteCol === "''") {
                    $selects[] = DB::raw("'' as note");
                } else {
                    $selects[] = DB::raw('MIN(' . $noteCol . ') as note');
                }

                $base->select($selects);

                // ORDER BY
                switch ($orderKey) {
                    case 'qty_req':
                        $base->orderBy('qty_req', $orderDir);
                        break;
                    case 'qty_rcv':
                        $base->orderBy('qty_rcv', $orderDir);
                        break;
                    case 'product_name':
                        $base->orderBy('product_name', $orderDir);
                        break;
                    case 'supplier_name':
                        $base->orderBy('supplier_name', $orderDir);
                        break;
                    case 'status':
                        $base->orderBy('status', $orderDir);
                        break;
                    case 'created_at':
                        $base->orderBy('created_at', $orderDir);
                        break;
                    case 'note':
                        $base->orderBy('note', $orderDir);
                        break;
                    case 'code':
                    default:
                        $base->orderBy('code', 'desc');
                        break;
                }

                // PAGING + MAPPING
                $rows = $base->skip($start)->take($length)->get()
                    ->map(function ($r, $idx) use ($start) {
                        $status = strtolower($r->status ?? 'pending');

                        if ($status === 'approved') {
                            $badge = '<span class="badge bg-label-info">REVIEW</span>';
                        } elseif ($status === 'ordered') {
                            $badge = '<span class="badge bg-label-secondary">ORDERED</span>';
                        } elseif ($status === 'received') {
                            $badge = '<span class="badge bg-label-primary">RECEIVED</span>';
                        } elseif ($status === 'cancelled') {
                            $badge = '<span class="badge bg-label-dark">CANCELLED</span>';
                        } else {
                            $badge = '<span class="badge bg-label-warning">PENDING</span>';
                        }

                        $qtyReq  = (int) ($r->qty_req ?? 0);
                        $qtyRcv  = (int) ($r->qty_rcv ?? 0);
                        $itemCnt = (int) ($r->item_count ?? 1);
                        $qtyRemaining = max($qtyReq - $qtyRcv, 0);

                        $canReceive = ! in_array($status, ['received', 'cancelled'], true);

                        $subLine = trim((string) ($r->product_code ?? ''));
                        if ($itemCnt > 1) {
                            $extra = $itemCnt - 1;
                            $extraText = '+ ' . $extra . ' item lain';
                            $subLine = $subLine !== ''
                                ? $subLine . ' · ' . $extraText
                                : $extraText;
                        }

                        $productHtml = e($r->product_name ?? '-') .
                            '<div class="small text-muted">' . e($subLine) . '</div>';

                        $detailUrl = route('restocks.items', $r->id);

                        $actions  = '<div class="d-flex gap-1">';
                        $actions .= '<button class="btn btn-sm btn-outline-secondary js-detail"
                                data-id="' . $r->id . '"
                                data-code="' . e($r->code) . '"
                                data-url="' . e($detailUrl) . '">
                                <i class="bx bx-search-alt"></i></button>';

                        if ($canReceive) {
                            $actions .= '<button class="btn btn-sm btn-outline-primary js-receive"
                                data-id="' . $r->id . '"
                                data-code="' . e($r->code) . '"
                                data-action="' . e(route('restocks.receive', $r->id)) . '">
                                <i class="bx bx-download"></i></button>';
                        }

                        $actions .= '</div>';

                        return [
                            'rownum'     => $start + $idx + 1,
                            'code'       => e($r->code),
                            'product'    => $productHtml,
                            'supplier'   => e($r->supplier_name ?? '-'),
                            'qty_req'    => number_format($qtyReq, 0, ',', '.'),
                            'qty_rcv'    => number_format($qtyRcv, 0, ',', '.'),
                            'status'     => $badge,
                            'created_at' => $r->created_at
                                ? Carbon::parse($r->created_at)->format('Y-m-d')
                                : '-',
                            'note'       => e($r->note ?? ''),
                            'actions'    => $actions,
                        ];
                    });

                return response()->json([
                    'draw'            => $draw,
                    'recordsTotal'    => $recordsTotal,
                    'recordsFiltered' => $recordsFiltered,
                    'data'            => $rows,
                ]);
            } catch (\Throwable $e) {
                \Log::error('restocks.datatable: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'draw'            => (int) $r->input('draw', 1),
                    'recordsTotal'    => 0,
                    'recordsFiltered' => 0,
                    'data'            => [],
                    'error'           => 'Server error',
                ]);
            }
        }


    /* ================== STORE REQUEST ================== */

    public function store(Request $r)
    {
        $me = auth()->user();

        $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
        $isWarehouseUser    = $me->hasRole('warehouse');

        $payload = $r->all();

        // ==== VALIDASI: multi item ====
        $rules = [
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'exists:products,id'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
            'items.*.note'               => ['nullable', 'string', 'max:500'],
        ];

        if ($canSwitchWarehouse) {
            $rules['warehouse_id'] = ['required', 'exists:warehouses,id'];
        }

        $data = validator($payload, $rules)->validate();

        // Tentukan warehouse yang dipakai
        if ($isWarehouseUser && ! $canSwitchWarehouse) {
            $warehouseId = $me->warehouse_id;
        } else {
            $warehouseId = $data['warehouse_id'] ?? $me->warehouse_id;
        }

        // ============== GENERATE 1 KODE RR UNTUK SEMUA ITEM ==============
        $rrCode = null;
        if (Schema::hasColumn('request_restocks', 'code')) {
            $prefix   = 'RR-' . now()->format('ymd') . '-';
            $lastCode = DB::table('request_restocks')
                ->where('code', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->value('code');

            $lastNum = $lastCode ? (int) substr($lastCode, -4) : 0;
            $rrCode  = $prefix . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }

        DB::transaction(function () use ($data, $warehouseId, $me, $rrCode) {
            foreach ($data['items'] as $row) {
                // Ambil supplier dari product
                $product = Product::select('id','supplier_id')->findOrFail($row['product_id']);
                $qty     = (int) $row['quantity_requested'];

                $insert = [
                    'supplier_id'        => $product->supplier_id,
                    'product_id'         => $product->id,
                    'warehouse_id'       => $warehouseId,
                    'requested_by'       => $me->id,
                    'quantity_requested' => $qty,
                    'quantity_received'  => 0,
                    'cost_per_item'      => 0,
                    'total_cost'         => 0,
                    'status'             => 'pending',
                    'note'               => $row['note'] ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];

                // pakai SATU kode RR untuk semua baris item
                if ($rrCode) {
                    $insert['code'] = $rrCode;
                }

                DB::table('request_restocks')->insert($insert);
            }
        });

        return back()->with('success', 'Request restock berhasil dibuat.');
    }

    /* ================== RECEIVE BARANG ================== */

    private function nextReceiptCode(): string
    {
        $prefix = 'GR-' . now()->format('ymd') . '-';

        $last = DB::table('restock_receipts')
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('code');

        $n = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($n, 4, '0', STR_PAD_LEFT);
    }

    public function receive(Request $r, $id)
    {
        $user = auth()->user();

        // VALIDASI INPUT MULTI ITEM
        $data = $r->validate([
            'items'                   => 'required|array|min:1',
            'items.*.qty_good'        => 'nullable|integer|min:0',
            'items.*.qty_damaged'     => 'nullable|integer|min:0',
            'items.*.notes'           => 'nullable|string',

            'photos_good'             => 'nullable',
            'photos_good.*'           => 'nullable|image|max:4096',
            'photos_damaged'          => 'nullable',
            'photos_damaged.*'        => 'nullable|image|max:4096',
        ]);

        $itemsInput = $data['items'] ?? [];

        // minimal ada satu item yang punya qty > 0
        $hasQty = false;
        foreach ($itemsInput as $row) {
            $g = (int) ($row['qty_good'] ?? 0);
            $d = (int) ($row['qty_damaged'] ?? 0);
            if ($g > 0 || $d > 0) {
                $hasQty = true;
                break;
            }
        }

        if (! $hasQty) {
            return back()->with('error', 'Minimal satu item harus memiliki Qty Good atau Qty Damaged lebih dari 0.');
        }

        // header RR (pakai salah satu baris sebagai anchor)
        $header = DB::table('request_restocks')->where('id', $id)->first();
        if (! $header) {
            return back()->with('error', 'Restock request tidak ditemukan.');
        }

        $hasCode = Schema::hasColumn('request_restocks', 'code');
        $groupCode = $hasCode ? ($header->code ?? null) : null;

        // id item yang diajukan di form
        $itemIds = array_map('intval', array_keys($itemsInput));

        // ambil semua row RR yang termasuk dokumen ini
        $rrQuery = DB::table('request_restocks');
        if ($groupCode) {
            $rrQuery->where('code', $groupCode)->whereIn('id', $itemIds);
        } else {
            $rrQuery->whereIn('id', $itemIds);
        }
        $rrRows = $rrQuery->get();

        if ($rrRows->isEmpty()) {
            return back()->with('error', 'Item restock yang dipilih tidak ditemukan.');
        }

        $rrIds = $rrRows->pluck('id')->all();

        // cek total existing receipt per request_id (buat validasi tidak melebihi qty request)
        $existing = DB::table('restock_receipts')
            ->whereIn('request_id', $rrIds)
            ->selectRaw('request_id, COALESCE(SUM(qty_good),0) as sum_good, COALESCE(SUM(qty_damaged),0) as sum_bad')
            ->groupBy('request_id')
            ->get()
            ->keyBy('request_id');

        // helper untuk baca qty request dari berbagai nama kolom
        $getReqQty = function ($row) {
            if (isset($row->quantity_requested)) return (int) $row->quantity_requested;
            if (isset($row->qty_requested))      return (int) $row->qty_requested;
            if (isset($row->qty))               return (int) $row->qty;
            if (isset($row->quantity))          return (int) $row->quantity;
            return 0;
        };

        // VALIDASI: jangan sampai total melebihi qty request per item
        foreach ($rrRows as $rr) {
            $reqId = (int) $rr->id;

            // kalau item ini tidak dikirim dari form, lewati
            if (! isset($itemsInput[$reqId])) {
                continue;
            }

            $input      = $itemsInput[$reqId];
            $qtyGood    = (int) ($input['qty_good'] ?? 0);
            $qtyDamaged = (int) ($input['qty_damaged'] ?? 0);

            // kalau dua-duanya 0, lewati
            if ($qtyGood === 0 && $qtyDamaged === 0) {
                continue;
            }

            $reqQty = $getReqQty($rr);

            // ==== FIX: handle kalau belum pernah ada GR sama sekali ====
            $existRow = $existing->get($reqId); // bisa null
            $sumGood  = $existRow ? (int) $existRow->sum_good : 0;
            $sumBad   = $existRow ? (int) $existRow->sum_bad  : 0;
            // ===========================================================

            $already  = $sumGood + $sumBad;
            $newTotal = $already + $qtyGood + $qtyDamaged;

            if ($reqQty > 0 && $newTotal > $reqQty) {
                return back()->with('error', 'Total penerimaan untuk salah satu item melebihi qty request.');
            }
        }


DB::beginTransaction();

try {
    $now         = now();
    $warehouseId = $user->warehouse_id ?? ($header->warehouse_id ?? null);

    // ❌ TIDAK LAGI generate 1 kode untuk semua item
    // $grCode = null;
    // if (Schema::hasColumn('restock_receipts', 'code')) {
    //     $grCode = $this->nextReceiptCode();
    // }

    $mainReceiptId = null;
    $touchedPoIds  = [];

    foreach ($rrRows as $rr) {
        $reqId = (int) $rr->id;

        if (! isset($itemsInput[$reqId])) {
            continue;
        }

        $input      = $itemsInput[$reqId];
        $qtyGood    = (int) ($input['qty_good'] ?? 0);
        $qtyDamaged = (int) ($input['qty_damaged'] ?? 0);
        $noteItem   = $input['notes'] ?? null;

        if ($qtyGood === 0 && $qtyDamaged === 0) {
            continue;
        }

        $reqQty     = $getReqQty($rr);
        $productId  = (int) ($rr->product_id ?? 0);
        $supplierId = $rr->supplier_id ?? null;
        $whId       = $warehouseId ?: ($rr->warehouse_id ?? null);

        // cari PO yang terkait dengan request_id ini
        $poId = null;
        if (
            Schema::hasTable('purchase_order_items') &&
            Schema::hasColumn('purchase_order_items', 'request_id')
        ) {
            $poId = DB::table('purchase_order_items')
                ->where('request_id', $reqId)
                ->value('purchase_order_id');

            if ($poId) {
                $touchedPoIds[$poId] = true;
            }
        }

        $payload = [
            'purchase_order_id' => $poId,
            'request_id'        => $reqId,
            'product_id'        => $productId,
            'warehouse_id'      => $whId,
            'supplier_id'       => $supplierId,
            'qty_requested'     => $reqQty,
            'qty_good'          => $qtyGood,
            'qty_damaged'       => $qtyDamaged,
            'notes'             => $noteItem,
            'received_by'       => $user->id ?? null,
            'received_at'       => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        // ✅ KODE GR UNIK PER ROW (aman kalau kolom `code` unique)
        if (Schema::hasColumn('restock_receipts', 'code')) {
            $payload['code'] = $this->nextReceiptCode();
        }

        $receiptId = DB::table('restock_receipts')->insertGetId($payload);

        if (! $mainReceiptId) {
            $mainReceiptId = $receiptId;
        }

        // ... (lanjutan update status request_restocks, stok, dll TETAP SAMA)


                // UPDATE STATUS request_restocks untuk item ini
                $nowGood = (int) DB::table('restock_receipts')->where('request_id', $reqId)->sum('qty_good');
                $nowBad  = (int) DB::table('restock_receipts')->where('request_id', $reqId)->sum('qty_damaged');
                $nowAll  = $nowGood + $nowBad;

                $status = ($reqQty > 0 && $nowAll >= $reqQty)
                    ? 'received'
                    : ($rr->status ?? 'ordered');

                DB::table('request_restocks')->where('id', $reqId)->update([
                    'quantity_received' => $nowGood,
                    'status'            => $status,
                    'received_at'       => $status === 'received'
                        ? $now
                        : ($rr->received_at ?? null),
                    'updated_at'        => $now,
                ]);

                // UPDATE STOCK LEVELS
                if (Schema::hasTable('stock_levels') && $productId && $qtyGood > 0) {

                    // 1) Kurangi stok di CENTRAL (pusat)
                    $centralQ = DB::table('stock_levels')
                        ->where('owner_type', 'pusat')
                        ->where('owner_id', 0)
                        ->where('product_id', $productId)
                        ->lockForUpdate();

                    if ($central = $centralQ->first()) {
                        $centralQ->update([
                            'quantity'   => max(0, (int) $central->quantity - $qtyGood),
                            'updated_at' => $now,
                        ]);
                    }

                    // 2) Tambah stok di warehouse penerima
                    if ($whId) {
                        $q = DB::table('stock_levels')
                            ->where('owner_type', 'warehouse')
                            ->where('owner_id', $whId)
                            ->where('product_id', $productId)
                            ->lockForUpdate();

                        if ($existing = $q->first()) {
                            $qtyNow = (int) ($existing->quantity ?? 0);
                            $q->update([
                                'quantity'   => $qtyNow + $qtyGood,
                                'updated_at' => $now,
                            ]);
                        } else {
                            DB::table('stock_levels')->insert([
                                'owner_type' => 'warehouse',
                                'owner_id'   => $whId,
                                'product_id' => $productId,
                                'quantity'   => $qtyGood,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            } // end foreach item RR

            // SIMPAN FOTO ke receipt utama (kalau ada)
            if ($mainReceiptId && Schema::hasTable('restock_receipt_photos')) {
                $hasType    = Schema::hasColumn('restock_receipt_photos', 'type');
                $hasCaption = Schema::hasColumn('restock_receipt_photos', 'caption');

                $storePhotos = function ($files, string $kind) use ($mainReceiptId, $now, $hasType, $hasCaption) {
                    if (empty($files)) return;

                    if (! is_array($files)) {
                        $files = [$files];
                    }

                    foreach ($files as $file) {
                        if (! $file || ! $file->isValid()) continue;

                        $dir  = $kind === 'good' ? 'gr_photos/good' : 'gr_photos/damaged';
                        $path = $file->store($dir, 'public');

                        $row = [
                            'receipt_id' => $mainReceiptId,
                            'path'       => $path,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if ($hasType) {
                            $row['type'] = $kind;
                        }
                        if ($hasCaption) {
                            $row['caption'] = $kind;
                        }

                        DB::table('restock_receipt_photos')->insert($row);
                    }
                };

                $storePhotos($r->file('photos_good')    ?? [], 'good');
                $storePhotos($r->file('photos_damaged') ?? [], 'damaged');
            }

            // UPDATE STATUS PO (untuk semua PO yang tersentuh)
            if (! empty($touchedPoIds)
                && Schema::hasTable('purchase_orders')
                && Schema::hasTable('purchase_order_items')
            ) {
                $hasWhCol = Schema::hasColumn('restock_receipts', 'warehouse_id');

                foreach (array_keys($touchedPoIds) as $poId) {
                    // hitung total qty rcv semua produk utk PO ini
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
                        ->get(['id', 'product_id', 'warehouse_id', 'qty_ordered', 'qty_received']);

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
                                'updated_at'   => $now,
                            ]);

                        if ($qtyRcv > 0)        $anyReceived = true;
                        if ($qtyRcv < $ordered) $allFull    = false;
                    }

                    $updatePo = [];
                    if ($allFull && $anyReceived) {
                        $updatePo['status'] = 'completed';
                        if (Schema::hasColumn('purchase_orders', 'received_at')) {
                            $updatePo['received_at'] = $now;
                        }
                    } elseif ($anyReceived) {
                        $updatePo['status'] = 'partially_received';
                    }

                    if (! empty($updatePo)) {
                        $updatePo['updated_at'] = $now;
                        DB::table('purchase_orders')
                            ->where('id', $poId)
                            ->update($updatePo);
                    }
                }
            }

            DB::commit();
            return back()->with('success', 'Penerimaan multi item berhasil disimpan. Stok pusat & warehouse sudah diperbarui.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Gagal menyimpan penerimaan: ' . $e->getMessage());
        }
    }


    /* ================== DETAIL SEMUA ITEM DALAM 1 RR ================== */

    public function items($id)
    {
        $row = DB::table('request_restocks as rr')
            ->leftJoin('warehouses as w', 'w.id', '=', 'rr.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'rr.requested_by')
            ->where('rr.id', $id)
            ->select('rr.*', 'w.warehouse_name', 'u.name as requester_name')
            ->first();

        if (! $row) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Request restock tidak ditemukan.',
            ], 404);
        }

        $hasCode = Schema::hasColumn('request_restocks', 'code');

        $q = DB::table('request_restocks as rr')
            ->leftJoin('products as p', 'p.id', '=', 'rr.product_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'rr.supplier_id')
            ->where(function ($w) use ($row, $hasCode) {
                if ($hasCode && ! empty($row->code)) {
                    $w->where('rr.code', $row->code);
                } else {
                    $w->where('rr.id', $row->id);
                }
            })
            ->orderBy('rr.id');

        $items = $q->get([
                'rr.id',
                'rr.quantity_requested',
                'rr.quantity_received',
                'rr.status',
                'rr.note',
                'p.product_code',
                'p.name as product_name',
                's.name as supplier_name',
            ])
            ->map(function ($it) {
                $qtyReq = (int) ($it->quantity_requested ?? 0);
                $qtyRcv = (int) ($it->quantity_received ?? 0);
                $remaining = max($qtyReq - $qtyRcv, 0);

                return [
                    'id'            => $it->id,
                    'product'       => $it->product_name,
                    'product_code'  => $it->product_code,
                    'supplier'      => $it->supplier_name,
                    'qty_req'       => $qtyReq,
                    'qty_rcv'       => $qtyRcv,
                    'qty_remaining' => $remaining,
                    'status'        => $it->status ?? 'pending',
                    'note'          => $it->note,
                ];
            });

        $createdAt = $row->created_at
            ? Carbon::parse($row->created_at)->format('Y-m-d')
            : null;

        $header = [
            'code'        => $hasCode ? ($row->code ?? ('RR-'.$row->id)) : ('RR-'.$row->id),
            'request_date'=> $createdAt,
            'warehouse'   => $row->warehouse_name ?? '-',
            'requester'   => $row->requester_name ?? '-',
            'status'      => $row->status ?? 'pending',
            'total_items' => $items->count(),
            'total_qty'   => $items->sum('qty_req'),
        ];

        return response()->json([
            'status' => 'ok',
            'header' => $header,
            'items'  => $items,
        ]);
    }
}
