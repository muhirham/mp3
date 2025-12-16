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
        private function roleFlags($me): array
    {
        $roles = collect($me->roles ?? []);

        $has = function(array $need) use ($roles) {
            return $roles->contains(function ($r) use ($need) {
                $key = $r->slug ?? $r->name ?? null;
                return $key && in_array($key, $need, true);
            });
        };

        return [
            'canSwitchWarehouse' => $has(['admin','superadmin']),
            'isWarehouseUser'    => $has(['warehouse']),
        ];
    }
    /* ================== INDEX (HALAMAN) ================== */

    public function index(Request $r)
    {
        $me = auth()->user();
        ['canSwitchWarehouse' => $canSwitchWarehouse, 'isWarehouseUser' => $isWarehouseUser] = $this->roleFlags($me);

        $warehouses          = collect();
        $selectedWarehouseId = null;

        if ($canSwitchWarehouse) {
            $warehouses = Warehouse::orderBy('warehouse_name')->get(['id','warehouse_name']);
            $selectedWarehouseId = $r->integer('warehouse_id') ?: null;
        } elseif ($isWarehouseUser) {
            $selectedWarehouseId = $me->warehouse_id;
        }

        $products = Product::orderBy('name')->get(['id','name','product_code']);

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
            ['canSwitchWarehouse' => $canSwitchWarehouse, 'isWarehouseUser' => $isWarehouseUser] = $this->roleFlags($me);

            // tentukan warehouse yang dipakai filter
            if ($isWarehouseUser) {
                $warehouseId = $me->warehouse_id;
            } elseif ($canSwitchWarehouse) {
                $warehouseId = $r->integer('warehouse_id') ?: null;
            } else {
                $warehouseId = null;
            }

            $draw        = (int) $r->input('draw', 1);
            $start       = (int) $r->input('start', 0);
            $length      = (int) $r->input('length', 10);
            $orderColIdx = (int) $r->input('order.0.column', 1);
            $orderDir    = $r->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $search      = trim((string) $r->input('search.value', ''));

            $orderMap = [
                1 => 'p.product_code',
                2 => 'p.name',
                3 => 'pk.package_name',
                4 => 'c.category_name',
                5 => 's.name',
                6 => 'sl.quantity',
                7 => 'p.stock_minimum',
                9 => 'p.selling_price',
            ];
            $orderBy = $orderMap[$orderColIdx] ?? 'p.product_code';

            $base = DB::table('stock_levels as sl')
                ->join('products as p', 'p.id', '=', 'sl.product_id')
                ->leftJoin('packages as pk', 'pk.id', '=', 'p.package_id')
                ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
                ->where('sl.owner_type', 'warehouse');

            if ($warehouseId) {
                $base->where('sl.owner_id', $warehouseId);
            }

            $recordsTotal = (clone $base)->count();

            if ($search !== '') {
                $like = "%{$search}%";
                $base->where(function ($q) use ($like) {
                    $q->where('p.name', 'like', $like)
                      ->orWhere('p.product_code', 'like', $like)
                      ->orWhere('c.category_name', 'like', $like)
                      ->orWhere('s.name', 'like', $like)
                      ->orWhere('pk.package_name', 'like', $like);
                });
            }

            $recordsFiltered = (clone $base)->count();

            $rows = (clone $base)
                ->select([
                    'p.product_code as product_code',
                    'p.name as product_name',
                    'pk.package_name as package_name',
                    'c.category_name as category_name',
                    's.name as supplier_name',
                    'sl.quantity as quantity',
                    'p.stock_minimum as stock_minimum',
                    'p.selling_price as selling_price',
                ])
                ->orderBy($orderBy, $orderDir)
                ->skip($start)->take($length)
                ->get()
                ->map(function ($row, $idx) use ($start) {

                    $qty = (int) ($row->quantity ?? 0);
                    $min = (int) ($row->stock_minimum ?? 0);

                    if ($qty <= 0) {
                        $badge = '<span class="badge bg-label-danger">OUT</span>';
                    } elseif ($min > 0 && $qty <= $min) {
                        $badge = '<span class="badge bg-label-warning">LOW</span>';
                    } else {
                        $badge = '<span class="badge bg-label-success">OK</span>';
                    }

                    $selling = (float) ($row->selling_price ?? 0);

                    return [
                        'rownum'        => $start + $idx + 1,
                        'product_code'  => e($row->product_code ?? '-'),
                        'product_name'  => e($row->product_name ?? '-'),
                        'package_name'  => e($row->package_name ?? '-'),
                        'category_name' => e($row->category_name ?? '-'),
                        'supplier_name' => e($row->supplier_name ?? '-'),
                        'quantity'      => number_format($qty, 0, ',', '.'),
                        'stock_minimum' => number_format($min, 0, ',', '.'),
                        'status'        => $badge,
                        'selling_price' => 'Rp' . number_format($selling, 0, ',', '.'),
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
                'error'           => $e->getMessage(), // biar kelihatan error aslinya dulu
            ], 500);
        }
    }




    /* ================== STORE REQUEST ================== */

    public function store(Request $r)
    {
        $me = auth()->user();
        ['canSwitchWarehouse' => $canSwitchWarehouse, 'isWarehouseUser' => $isWarehouseUser] = $this->roleFlags($me);

        $payload = $r->all();

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

        $warehouseId = ($isWarehouseUser && ! $canSwitchWarehouse)
            ? $me->warehouse_id
            : ($data['warehouse_id'] ?? $me->warehouse_id);

        // generate 1 code RR
        $rrCode = null;
        if (Schema::hasTable('request_restocks') && Schema::hasColumn('request_restocks', 'code')) {
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

                if ($rrCode) $insert['code'] = $rrCode;

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
