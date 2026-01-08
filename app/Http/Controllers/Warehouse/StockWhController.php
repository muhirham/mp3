<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Company;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\ValidationException;
use App\Exports\Restocks\RestockIndexWithItemsExport;
use Illuminate\Support\Str;


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
    $me = auth()->user();

    $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
    $isWarehouseUser    = $me->hasRole('warehouse');

    $draw   = (int) $r->input('draw', 1);
    $start  = (int) $r->input('start', 0);
    $length = (int) $r->input('length', 10);

    $orderColIdx = (int) $r->input('order.0.column', 1);
    $orderDir    = $r->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

    $search = trim((string) $r->input('search.value', ''));

    // filter dari JS kamu: from/to/status/warehouse_id
    $from       = $r->input('from'); // yyyy-mm-dd
    $to         = $r->input('to');   // yyyy-mm-dd
    $status     = trim((string) $r->input('status', ''));
    $warehouseQ = $r->input('warehouse_id');

    // scope warehouse by role
    if ($isWarehouseUser && ! $canSwitchWarehouse) {
        $warehouseId = (int) ($me->warehouse_id ?? 0);
    } else {
        $warehouseId = !empty($warehouseQ) ? (int) $warehouseQ : null;
    }

    // schema flex (biar aman)
    static $hasCode, $hasWh, $hasReqBy, $hasQtyReq, $hasQtyRcv, $hasNote, $hasDesc;
    $hasCode   ??= Schema::hasColumn('request_restocks', 'code');
    $hasWh     ??= Schema::hasColumn('request_restocks', 'warehouse_id');
    $hasReqBy  ??= Schema::hasColumn('request_restocks', 'requested_by');
    $hasQtyReq ??= Schema::hasColumn('request_restocks', 'quantity_requested');
    $hasQtyRcv ??= Schema::hasColumn('request_restocks', 'quantity_received');
    $hasNote   ??= Schema::hasColumn('request_restocks', 'note');
    $hasDesc   ??= Schema::hasColumn('request_restocks', 'description');

    $codeExpr = $hasCode ? 'rr.code' : "CONCAT('RR-', rr.id)";
    $qtyReqExpr = $hasQtyReq ? 'COALESCE(rr.quantity_requested,0)' : '0';
    $qtyRcvExpr = $hasQtyRcv ? 'COALESCE(rr.quantity_received,0)' : '0';
    $noteExpr   = $hasNote ? 'rr.note' : ($hasDesc ? 'rr.description' : "''");

    // mapping order kolom (sesuai urutan columns DataTables kamu)
    // 0 rownum (ignore)
    $orderMap = [
        1 => 'code',
        2 => 'product_name',
        3 => 'supplier_name',
        4 => 'qty_req',
        5 => 'qty_rcv',
        6 => 'status',
        7 => 'created_at',
        8 => 'warehouse',
    ];
    $orderBy = $orderMap[$orderColIdx] ?? 'created_at';

    $base = DB::table('request_restocks as rr')
        ->leftJoin('products as p', 'p.id', '=', 'rr.product_id')
        ->leftJoin('suppliers as s', 's.id', '=', 'rr.supplier_id');


    if ($hasWh) {
        $base->leftJoin('warehouses as w', 'w.id', '=', 'rr.warehouse_id');
    }
    if ($hasReqBy) {
        $base->leftJoin('users as u', 'u.id', '=', 'rr.requested_by');
    }

    // SELECT (alias harus sama kayak columns DataTables)
        $base->select([
        DB::raw('MIN(rr.id) as id'), // anchor aman
        DB::raw($codeExpr . ' as code'),

        DB::raw("GROUP_CONCAT(DISTINCT COALESCE(p.name,'-') SEPARATOR ', ') as product_name"),
        DB::raw("GROUP_CONCAT(DISTINCT COALESCE(s.name,'-') SEPARATOR ', ') as supplier_name"),

        DB::raw('SUM('.$qtyReqExpr.') as qty_req'),
        DB::raw('SUM('.$qtyRcvExpr.') as qty_rcv'),

        DB::raw("MAX(COALESCE(rr.status,'pending')) as status"),
        DB::raw('MIN(rr.created_at) as created_at'),

        DB::raw("MAX(COALESCE(w.warehouse_name,'-')) as warehouse"),

    ]);
        $base->groupBy($codeExpr);

    // total (sebelum filter/search)
        $recordsTotal = DB::query()
            ->fromSub((clone $base)->select(DB::raw('1')), 't')
            ->count();


    // FILTER WAREHOUSE
    if ($warehouseId) {
        if ($hasWh) {
            $base->where('rr.warehouse_id', $warehouseId);
        } elseif ($hasReqBy && $isWarehouseUser) {
            $base->where('rr.requested_by', $me->id);
        }
    } elseif ($isWarehouseUser && $hasReqBy && ! $hasWh) {
        $base->where('rr.requested_by', $me->id);
    }

    // FILTER STATUS
    if ($status !== '') {
        $base->where('rr.status', $status);
    }

    // FILTER TANGGAL
    if (!empty($from)) {
        $base->whereDate('rr.created_at', '>=', $from);
    }
    if (!empty($to)) {
        $base->whereDate('rr.created_at', '<=', $to);
    }

    // SEARCH GLOBAL
    if ($search !== '') {
        $like = '%' . $search . '%';
        $base->where(function ($q) use ($like, $hasCode, $noteExpr) {
            if ($hasCode) {
                $q->where('rr.code', 'like', $like);
            } else {
                $q->where('rr.id', 'like', $like);
            }

            $q->orWhere('p.name', 'like', $like)
              ->orWhere('p.product_code', 'like', $like)
              ->orWhere('s.name', 'like', $like);

            if ($noteExpr !== "''") {
                $q->orWhereRaw($noteExpr . ' LIKE ?', [$like]);
            }
        });
    }

    $recordsFiltered = (clone $base)->count();

    // ORDER
    if ($orderBy === 'code') {
        $base->orderByRaw($codeExpr . ' ' . $orderDir);
    } else {
        $base->orderBy($orderBy, $orderDir);
    }

    $rows = $base->skip($start)->take($length)->get();

    $data = [];
    foreach ($rows as $i => $row) {
        $rownum = $start + $i + 1;

        $createdAt = $row->created_at
            ? Carbon::parse($row->created_at)->format('d/m/Y')
            : '-';

        $statusRaw = $row->status ?? 'pending';
        $badgeMap = [
            'pending'   => 'secondary',
            'approved'  => 'warning',
            'ordered'   => 'info',
            'received'  => 'success',
            'cancelled' => 'danger',
        ];
        $badge = $badgeMap[$statusRaw] ?? 'secondary';
        $statusHtml = '<span class="badge bg-label-'.$badge.'">'.e(strtoupper($statusRaw)).'</span>';

        $codeSafe = e($row->code ?? ('RR-'.$row->id));

        $actions = '
            <div class="d-flex gap-1 justify-content-center">
              <button type="button"
                class="btn btn-sm btn-outline-primary js-detail"
                data-id="'.(int)$row->id.'"
                data-code="'.$codeSafe.'">
                <i class="bx bx-search-alt"></i>
              </button>
              <button type="button"
                class="btn btn-sm btn-outline-success js-receive"
                data-id="'.(int)$row->id.'"
                data-code="'.$codeSafe.'"
                data-action="'.e(route('restocks.receive', $row->id)).'">
                <i class="bx bx-package"></i>
              </button>
            </div>
        ';

        $data[] = [
            'rownum'    => $rownum,
            'code'      => $codeSafe,
            'product'   => e(($row->product_name ?? '-') ),
            'supplier'  => e(($row->supplier_name ?? '-') ),
            'qty_req'   => (int) ($row->qty_req ?? 0),
            'qty_rcv'   => (int) ($row->qty_rcv ?? 0),
            'status'    => $statusHtml,
            'created_at'=> $createdAt,
            'warehouse' => e($row->warehouse ?? '-'),
            'actions'   => $actions,
        ];
    }

    return response()->json([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}


        public function detailJson($id)
        {
            $adj = StockAdjustment::with([
                    'warehouse:id,warehouse_name',
                    'creator:id,name',
                    'items.product:id,product_code,name',
                ])
                ->withCount('items')
                ->findOrFail($id);

            return response()->json([
                'status' => 'ok',
                'header' => [
                    'adj_code'    => $adj->adj_code,
                    'adj_date'    => optional($adj->adj_date)->format('d/m/Y'),
                    'warehouse'   => $adj->warehouse_id ? ($adj->warehouse?->warehouse_name ?? '-') : 'Stock Central',
                    'created_by'  => $adj->creator?->name ?? '-',
                    'created_at'  => optional($adj->created_at)->format('d/m/Y H:i'),
                    'items_count' => (int) $adj->items_count,
                    'notes'       => $adj->notes ?: '-',
                ],
                'items' => $adj->items->map(function ($it) {
                    return [
                        'product'     => ($it->product?->product_code ?? '').' — '.($it->product?->name ?? '-'),
                        'qty_before'  => (int) $it->qty_before,
                        'qty_after'   => (int) $it->qty_after,
                        'qty_diff'    => (int) $it->qty_diff,
                        'notes'       => $it->notes ?: '-',
                    ];
                })->values(),
            ]);
        }



        public function exportExcel(Request $r)
        {
            $me = auth()->user();

            $canSwitchWarehouse = $me->hasRole(['admin', 'superadmin']);
            $isWarehouseUser    = $me->hasRole('warehouse');

            // scope warehouse
            if ($isWarehouseUser) {
                $warehouseId = $me->warehouse_id;
            } elseif ($canSwitchWarehouse) {
                $warehouseId = $r->integer('warehouse_id') ?: null;
            } else {
                $warehouseId = null;
            }

            $q      = trim((string) $r->input('q', ''));
            $status = trim((string) $r->input('status', ''));

            // tanggal opsional
            [$fromC, $toC, $key, $useDate] = $this->parseExportRangeOptional($r);

            $hasCode  = Schema::hasColumn('request_restocks', 'code');
            $hasWh    = Schema::hasColumn('request_restocks', 'warehouse_id');
            $hasReqBy = Schema::hasColumn('request_restocks', 'requested_by');

            $codeExpr = $hasCode ? 'rr.code' : "CONCAT('RR-', rr.id)";

            // qty req
            if (Schema::hasColumn('request_restocks', 'quantity_requested')) {
                $qtyReqExpr = 'COALESCE(rr.quantity_requested,0)';
            } elseif (Schema::hasColumn('request_restocks', 'qty_requested')) {
                $qtyReqExpr = 'COALESCE(rr.qty_requested,0)';
            } elseif (Schema::hasColumn('request_restocks', 'qty')) {
                $qtyReqExpr = 'COALESCE(rr.qty,0)';
            } else {
                $qtyReqExpr = '0';
            }

            // qty rcv
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

            // note/description
            if (Schema::hasColumn('request_restocks', 'note')) {
                $noteCol = 'rr.note';
            } elseif (Schema::hasColumn('request_restocks', 'description')) {
                $noteCol = 'rr.description';
            } else {
                $noteCol = "''";
            }

            $base = DB::table('request_restocks as rr')
                ->leftJoin('products as p', 'p.id', '=', 'rr.product_id')
                ->leftJoin('suppliers as s', 's.id', '=', 'rr.supplier_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'rr.warehouse_id')
                ->leftJoin('users as u', 'u.id', '=', 'rr.requested_by');

            if ($rcvJoin) {
                $sub = DB::table('restock_receipts')
                    ->selectRaw('request_id, COALESCE(SUM(qty_good),0) as qty_rcv')
                    ->groupBy('request_id');

                $base->leftJoinSub($sub, 'rcv', 'rcv.request_id', '=', 'rr.id');
            }

            // FILTER GUDANG (sama kayak datatable)
            if ($warehouseId) {
                if ($hasWh) {
                    $base->where('rr.warehouse_id', $warehouseId);
                } elseif ($hasReqBy && $isWarehouseUser) {
                    $base->where('rr.requested_by', $me->id);
                }
            } elseif ($isWarehouseUser && $hasReqBy && ! $hasWh) {
                $base->where('rr.requested_by', $me->id);
            }

            // FILTER STATUS
            if ($status !== '') {
                $base->where('rr.status', $status);
            }

            // FILTER TANGGAL (opsional)
            if ($useDate && $fromC && $toC) {
                $base->whereBetween('rr.created_at', [$fromC->copy()->startOfDay(), $toC->copy()->endOfDay()]);
            }

            // SEARCH
            if ($q !== '') {
                $like = '%' . $q . '%';
                $base->where(function ($qq) use ($like, $hasCode, $noteCol) {
                    if ($hasCode) $qq->where('rr.code', 'like', $like);
                    else $qq->where('rr.id', 'like', $like);

                    $qq->orWhere('p.name', 'like', $like)
                    ->orWhere('p.product_code', 'like', $like)
                    ->orWhere('s.name', 'like', $like);

                    if ($noteCol !== "''") {
                        $qq->orWhereRaw($noteCol . ' LIKE ?', [$like]);
                    }
                });
            }

            $rows = $base->select([
                    'rr.id',
                    DB::raw($codeExpr . ' as code'),
                    DB::raw('COALESCE(w.warehouse_name, \'-\') as warehouse_name'),
                    DB::raw('COALESCE(u.name, \'-\') as requester_name'),
                    DB::raw('COALESCE(rr.status, \'pending\') as status'),
                    'rr.created_at',
                    DB::raw('COALESCE(p.product_code, \'\') as product_code'),
                    DB::raw('COALESCE(p.name, \'-\') as product_name'),
                    DB::raw('COALESCE(s.name, \'-\') as supplier_name'),
                    DB::raw($qtyReqExpr . ' as qty_req'),
                    DB::raw($qtyRcvExpr . ' as qty_rcv'),
                    DB::raw(($noteCol === "''" ? "''" : $noteCol) . ' as note'),
                ])
                ->orderByDesc('code')
                ->orderBy('rr.id')
                ->get();

            $company = Company::where('is_default', true)
                ->where('is_active', true)
                ->first();

            $meta = [
                'filters'  => $r->query(),
                'use_date' => $useDate,
                'date_from'=> $useDate && $fromC ? $fromC->toDateString() : null,
                'date_to'  => $useDate && $toC ? $toC->toDateString() : null,
            ];

            $filename = "RESTOCKS-INDEX-DETAIL-{$key}.xlsx";

            return Excel::download(
                new RestockIndexWithItemsExport(collect($rows), $meta, $company),
                $filename
            );
        }

        private function parseExportRangeOptional(Request $request): array
        {
            $month = $request->input('month');
            $from  = $request->input('from');
            $to    = $request->input('to');

            if (!$from && !$to && !$month) {
                return [null, null, 'ALL', false];
            }

            if ($from && !$to) $to = $from;
            if ($to && !$from) $from = $to;

            if ($from && $to) {
                $fromC = Carbon::parse($from)->startOfDay();
                $toC   = Carbon::parse($to)->endOfDay();
            } else {
                $fromC = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $toC   = $fromC->copy()->endOfMonth();
            }

            if ($fromC->gt($toC)) {
                throw ValidationException::withMessages([
                    'to' => 'Tanggal "to" harus >= "from".',
                ]);
            }

            if ($fromC->format('Y-m') !== $toC->format('Y-m')) {
                throw ValidationException::withMessages([
                    'to' => 'Range maksimal 1 bulan. "from" dan "to" harus di bulan yang sama.',
                ]);
            }

            return [$fromC, $toC, $fromC->format('Y-m'), true];
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