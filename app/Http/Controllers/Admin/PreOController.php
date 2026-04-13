<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use App\Exports\PO\PoIndexWithItemsExport;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;





class PreOController extends Controller
{
    /** LIST PO */
    private const CEO_MIN_TOTAL = 1_000_001;
    public function index(Request $request)
    {
        $q              = trim((string) $request->get('q', ''));
        $status         = trim((string) $request->get('status', ''));
        $approvalStatus = trim((string) $request->get('approval_status', ''));
        $warehouseId    = trim((string) $request->get('warehouse_id', ''));

        // per_page
        $perPage = (int) $request->get('per_page', 10);
        $allowed = [10, 25, 50, 100];
        if (!in_array($perPage, $allowed, true)) $perPage = 10;

        $me    = auth()->user();
        $roles = $me?->roles ?? collect();

        $isSuperadmin  = $roles->contains('slug', 'superadmin') || (($me->role ?? '') === 'superadmin');
        $isProcurement = $roles->contains('slug', 'procurement') || (($me->role ?? '') === 'procurement');
        $isCeo         = $roles->contains('slug', 'ceo') || (($me->role ?? '') === 'ceo');

        // kolom tanggal (kalau ada po_date pakai itu, kalau nggak pakai created_at)
        $dateCol = Schema::hasColumn('purchase_orders', 'po_date') ? 'po_date' : 'created_at';

        $query = PurchaseOrder::query()
            ->with([
                'supplier',
                'items.product.supplier',
                'items.warehouse',
                'user',
                'procurementApprover',
                'ceoApprover',
            ])
            ->withCount('items')
            ->withCount([
                'restockReceipts as gr_count' => function ($q) {
                    $q->where(function ($qq) {
                        $qq->where('qty_good', '>', 0)
                        ->orWhere('qty_damaged', '>', 0);
                    });
                }
            ]);

            // ✅ role-based visibility:
            // - procurement: cuma PO yang nunggu procurement
            // - ceo       : cuma PO yang nunggu CEO
        if (! $isSuperadmin) {
            if ($isProcurement) {
                $query->where(function ($qq) {
                    $qq->whereIn('approval_status', ['waiting_procurement','waiting_ceo','approved','rejected'])
                    ->orWhereNull('approval_status')
                    ->orWhere('approval_status', 'draft');
                });
            } elseif ($isCeo) {
                $query->where('grand_total', '>', self::CEO_MIN_TOTAL);

                $query->where(function ($qq) {
                    $qq->where('approval_status', 'waiting_ceo') // yang harus CEO approve
                    ->orWhere(function ($q2) {
                        $q2->where('approval_status', 'approved')
                            ->whereNotNull('approved_by_ceo'); // track record yang CEO approve
                    })
                    ->orWhere(function ($q2) {
                        $q2->where('approval_status', 'rejected')
                            ->whereNotNull('approved_by_ceo'); // kalau reject CEO juga mau kelihatan
                    });
                });
            } else {
                $query->whereRaw('1=0');
            }
        }

        // search
        if ($q !== '') {
            $query->where('po_code', 'like', "%{$q}%");
        }

        // status logistik
        if ($status !== '') {
            $query->where('status', $status);
        }

        // approval_status (PENTING: draft di DB lu biasanya NULL)
        if ($approvalStatus !== '') {
            if ($approvalStatus === 'draft') {
                $query->where(function ($qq) {
                    $qq->whereNull('approval_status')
                    ->orWhere('approval_status', 'draft');
                });
            } else {
                $query->where('approval_status', $approvalStatus);
            }
        }

        // filter warehouse
        if ($warehouseId !== '') {
            $query->whereHas('items', function ($itQ) use ($warehouseId) {
                if ($warehouseId === 'central') {
                    $itQ->whereNull('warehouse_id');
                } else {
                    $itQ->where('warehouse_id', (int) $warehouseId);
                }
            });
        }

        // filter tanggal: inline fixed
        $from = $request->get('from');
        $to   = $request->get('to');
        if ($from || $to) {
            $fC = $from ? Carbon::parse($from)->startOfDay() : null;
            $tC = $to ? Carbon::parse($to)->endOfDay() : null;

            if ($fC && $tC) {
                $query->whereBetween($dateCol, [
                    $dateCol === 'created_at' ? $fC : $fC->toDateString(),
                    $dateCol === 'created_at' ? $tC : $tC->toDateString()
                ]);
            } elseif ($fC) {
                $query->where($dateCol, '>=', $dateCol === 'created_at' ? $fC : $fC->toDateString());
            } elseif ($tC) {
                $query->where($dateCol, '<=', $dateCol === 'created_at' ? $tC : $tC->toDateString());
            }
        }

        $pos = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        // biar blade gak query sendiri
        $warehouses = Warehouse::query()
            ->select('id', 'warehouse_name')
            ->orderBy('warehouse_name')
            ->get();

        return view('admin.po.index', compact(
            'pos',
            'q',
            'status',
            'warehouses',
            'isProcurement',
            'isCeo',
            'isSuperadmin'
        ));
    }

    public function datatable(Request $request)
    {
        $draw   = (int) $request->input('draw');
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $q              = trim((string) $request->get('q', ''));
        $status         = trim((string) $request->get('status', ''));
        $approvalStatus = trim((string) $request->get('approval_status', ''));
        $warehouseId    = trim((string) $request->get('warehouse_id', ''));
        $from           = $request->get('from');
        $to             = $request->get('to');

        $me    = auth()->user();
        $roles = $me?->roles ?? collect();

        $isSuperadmin  = $roles->contains('slug', 'superadmin') || (($me->role ?? '') === 'superadmin');
        $isProcurement = $roles->contains('slug', 'procurement') || (($me->role ?? '') === 'procurement');
        $isCeo         = $roles->contains('slug', 'ceo') || (($me->role ?? '') === 'ceo');
        $isWarehouse   = $roles->contains('slug', 'warehouse') || (($me->role ?? '') === 'warehouse');
        $myWhId        = $me->warehouse_id ?? null;

        $dateCol = Schema::hasColumn('purchase_orders', 'po_date') ? 'po_date' : 'created_at';

        $baseQuery = PurchaseOrder::query();

        // Role-based visibility (Must apply to both counts)
        if (!$isSuperadmin) {
            if ($isProcurement) {
                $baseQuery->where(function ($qq) {
                    $qq->whereIn('approval_status', ['waiting_procurement','waiting_ceo','approved','rejected'])
                    ->orWhereNull('approval_status')
                    ->orWhere('approval_status', 'draft');
                });
            } elseif ($isCeo) {
                $baseQuery->where('grand_total', '>', self::CEO_MIN_TOTAL);
                $baseQuery->where(function ($qq) {
                    $qq->where('approval_status', 'waiting_ceo')
                    ->orWhere(function ($q2) {
                        $q2->where('approval_status', 'approved')->whereNotNull('approved_by_ceo');
                    })
                    ->orWhere(function ($q2) {
                        $q2->where('approval_status', 'rejected')->whereNotNull('approved_by_ceo');
                    });
                });
            } else {
                $baseQuery->whereRaw('1=0');
            }
        }        $recordsTotal = (clone $baseQuery)->count();

        $query = (clone $baseQuery)->with([
                'supplier',
                'items.product.supplier',
                'items.warehouse',
                'user',
                'procurementApprover',
                'ceoApprover',
            ])
            ->withCount('items')
            ->withCount([
                'restockReceipts as gr_count' => function ($q) {
                    $q->where(function ($qq) {
                        $qq->where('qty_good', '>', 0)
                        ->orWhere('qty_damaged', '>', 0);
                    });
                }
            ]);

        // Search
        if ($q !== '') {
            $query->where(function($qq) use ($q) {
                $qq->where('po_code', 'like', "%{$q}%")
                   ->orWhereHas('supplier', function($sq) use ($q) { $sq->where('name', 'like', "%{$q}%"); });
            });
        }
        if ($status !== '') $query->where('status', $status);
        if ($approvalStatus !== '') {
            if ($approvalStatus === 'draft') {
                $query->where(function ($qq) { $qq->whereNull('approval_status')->orWhere('approval_status', 'draft'); });
            } else {
                $query->where('approval_status', $approvalStatus);
            }
        }
        if ($warehouseId !== '') {
            $query->whereHas('items', function ($itQ) use ($warehouseId) {
                if ($warehouseId === 'central') $itQ->whereNull('warehouse_id');
                else $itQ->where('warehouse_id', (int) $warehouseId);
            });
        }

        if ($from || $to) {
            $fC = $from ? Carbon::parse($from)->startOfDay() : null;
            $tC = $to ? Carbon::parse($to)->endOfDay() : null;
            if ($fC && $tC) {
                $query->whereBetween($dateCol, [ $dateCol === 'created_at' ? $fC : $fC->toDateString(), $dateCol === 'created_at' ? $tC : $tC->toDateString() ]);
            } elseif ($fC) {
                $query->where($dateCol, '>=', $dateCol === 'created_at' ? $fC : $fC->toDateString());
            } elseif ($tC) {
                $query->where($dateCol, '<=', $dateCol === 'created_at' ? $tC : $tC->toDateString());
            }
        }

        $recordsFiltered = (clone $query)->count();
        $pos = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($pos as $po) {
            $hasGr = (int) ($po->gr_count ?? 0) > 0;
            $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();
            $poWhIds = $po->items->pluck('warehouse_id')->filter()->unique();
            $isMyWarehousePo = !$myWhId || $poWhIds->contains($myWhId);

            // Perm logic unification
            $isOrdered = $po->status === 'ordered';
            $isApproved = $po->approval_status === 'approved';
            $hasItems = $po->items_count > 0;

            $canReceive = !$hasGr && $isOrdered && $isApproved && $hasItems &&
                         (($fromRequest && $isWarehouse && $isMyWarehousePo) || (!$fromRequest && $isSuperadmin));

            $showBlockedReceive = $isSuperadmin && $fromRequest && !$hasGr && $isOrdered && $isApproved && $hasItems;

            // Supplier Label
            $supplierNames = collect();
            if(!empty($po->supplier?->name)) $supplierNames->push($po->supplier->name);
            foreach($po->items as $it) {
                if($it->product?->supplier?->name) $supplierNames->push($it->product->supplier->name);
            }
            $supplierNames = $supplierNames->unique()->values();
            if ($supplierNames->isEmpty()) $supplierLabel = '-';
            elseif ($supplierNames->count() === 1) $supplierLabel = $supplierNames->first();
            else $supplierLabel = $supplierNames->first() . ' + ' . ($supplierNames->count() - 1) . ' supplier';

            // Warehouse Label
            if (!$fromRequest) {
                $warehouseLabel = 'Central Stock';
            } else {
                $whNames = collect();
                foreach($po->items as $it) {
                    if($it->warehouse) $whNames->push($it->warehouse->warehouse_name ?? $it->warehouse->name);
                }
                $whNames = $whNames->filter()->unique()->values();
                if ($whNames->isEmpty()) $warehouseLabel = '-';
                elseif ($whNames->count() === 1) $warehouseLabel = $whNames->first();
                else $warehouseLabel = $whNames->first() . ' + ' . ($whNames->count() - 1) . ' wh';
            }

            // Approval Badge
            $appStatus = $po->approval_status ?: 'draft';
            $badgeColor = match($appStatus) {
                'draft' => 'secondary',
                'waiting_procurement' => 'warning',
                'waiting_ceo' => 'info',
                'approved' => 'success',
                'rejected' => 'danger',
                default => 'secondary'
            };
            $approvalBadge = '<span class="badge bg-label-'.$badgeColor.'">'.strtoupper(str_replace('_', ' ', $appStatus)).'</span>';
            $approvers = '<div class="po-muted text-muted mt-1">Proc: '.($po->procurementApprover->name ?? '-').'<br>CEO&nbsp;: '.($po->ceoApprover->name ?? '-').'</div>';

            $data[] = [
                'po_code' => $po->po_code,
                'supplier' => $supplierLabel,
                'status' => '<span class="badge bg-label-info text-uppercase">'.$po->status.'</span>' . ($hasGr ? ' <span class="badge bg-label-success">GR EXIST</span>' : ''),
                'approval' => $approvalBadge . $approvers,
                'subtotal' => number_format($po->subtotal, 0, ',', '.'),
                'discount' => number_format($po->discount_total, 0, ',', '.'),
                'grand_total' => number_format($po->grand_total, 0, ',', '.'),
                'lines' => $po->items_count,
                'warehouse' => $warehouseLabel,
                'actions' => '
                    <div class="btn-group">
                        <a class="btn btn-sm btn-primary" href="'.route('po.edit', $po->id).'">Open</a>
                        ' . ($canReceive ? '
                            <button type="button" class="btn btn-sm btn-success js-btn-receive" data-url="'.route('po.modal-gr', $po->id).'">
                                <i class="bx bx-download"></i> Receive
                            </button>' : '') . '
                        ' . ($showBlockedReceive ? '
                            <button type="button" class="btn btn-sm btn-outline-success js-gr-blocked" data-po="'.$po->po_code.'">
                                <i class="bx bx-info-circle"></i> Receive
                            </button>' : '') . '
                    </div>'
            ];
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ]);
    }

    public function modalGr(PurchaseOrder $po)
    {
        $po->load([
            'supplier',
            'items.product.supplier',
            'items.warehouse',
        ]);

        $me = auth()->user();
        $roles = $me?->roles ?? collect();
        $isSuperadmin = $roles->contains('slug', 'superadmin') || (($me->role ?? '') === 'superadmin');
        $isWarehouse = $roles->contains('slug', 'warehouse') || (($me->role ?? '') === 'warehouse');
        $myWhId = $me->warehouse_id ?? null;

        $hasGr = (int) ($po->restockReceipts()->where(function($qq){ $qq->where('qty_good','>',0)->orWhere('qty_damaged','>',0);})->count() ?? 0) > 0;
        $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

        $poWhIds = $po->items->pluck('warehouse_id')->filter()->unique();
        $isMyWarehousePo = !$myWhId || $poWhIds->contains($myWhId);

        $isOrdered = $po->status === 'ordered';
        $isApproved = $po->approval_status === 'approved';
        $hasItems = $po->items()->count() > 0;

        $canReceive = !$hasGr && $isOrdered && $isApproved && $hasItems &&
                     (($fromRequest && $isWarehouse && $isMyWarehousePo) || (!$fromRequest && $isSuperadmin));

        if (!$canReceive) {
            $reason = "You do not have permission to receive this PO.";
            if ($hasGr) $reason = "This PO has already been received (GR EXIST).";
            elseif (!$isOrdered) $reason = "This PO status is not 'ordered' (Current: {$po->status}).";
            elseif (!$isApproved) $reason = "This PO is not yet approved (Current: " . ($po->approval_status ?: 'draft') . ").";
            elseif ($fromRequest && !$isWarehouse) $reason = "This is a Restock Request PO. It must be received by a Warehouse account.";
            elseif (!$fromRequest && !$isSuperadmin) $reason = "This is a manual PO. It must be received by a Superadmin account.";
            
            return '<div class="alert alert-danger">'.$reason.'</div>';
        }

        // Supplier Label
        $supplierNames = collect();
        if(!empty($po->supplier?->name)) $supplierNames->push($po->supplier->name);
        foreach($po->items as $it) {
            if($it->product?->supplier?->name) $supplierNames->push($it->product->supplier->name);
        }
        $supplierNames = $supplierNames->unique()->values();
        if ($supplierNames->isEmpty()) $supplierLabel = '-';
        elseif ($supplierNames->count() === 1) $supplierLabel = $supplierNames->first();
        else $supplierLabel = $supplierNames->first() . ' + ' . ($supplierNames->count() - 1) . ' supplier';

        // Warehouse Label
        if (!$fromRequest) {
            $whLabel = 'Central Stock';
        } else {
            $whNames = collect();
            foreach ($po->items as $it) {
                if ($it->warehouse) {
                    $whNames->push($it->warehouse->warehouse_name ?? $it->warehouse->name);
                }
            }
            $whNames = $whNames->filter()->unique()->values();
            if ($whNames->isEmpty()) $whLabel = '-';
            elseif ($whNames->count() === 1) $whLabel = $whNames->first();
            else $whLabel = $whNames->first() . ' + ' . ($whNames->count() - 1) . ' wh';
        }

        // Render the modal body HTML
        ob_start();
        ?>
        <form action="<?= route('po.gr.store', $po) ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Goods Received – <?= e($po->po_code) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="mb-4 small">
                    <div id="grSupplier">Supplier: <strong><?= e($supplierLabel) ?></strong></div>
                    <div id="grWarehouse">Warehouse: <strong><?= e($whLabel) ?></strong></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle text-nowrap mb-4">
                        <thead style="background-color: #f8f9fa;" class="text-muted small fw-bold">
                            <tr>
                                <th class="ps-3 py-3 text-uppercase border-bottom-0">#</th>
                                <th class="py-3 text-uppercase border-bottom-0">PRODUCT</th>
                                <th class="py-3 text-uppercase text-center border-bottom-0">QTY ORDERED</th>
                                <th class="py-3 text-uppercase text-center border-bottom-0">QTY RECEIVED</th>
                                <th class="py-3 text-uppercase text-center border-bottom-0">QTY REMAINING</th>
                                <th class="py-3 text-uppercase text-center border-bottom-0" style="width: 100px;">QTY GOOD</th>
                                <th class="py-3 text-uppercase text-center border-bottom-0" style="width: 100px;">QTY DAMAGED</th>
                                <th class="pe-3 py-3 text-uppercase border-bottom-0">NOTES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($po->items as $i => $item):
                                $ordered = (int) ($item->qty_ordered ?? 0);
                                $received = (int) ($item->qty_received ?? 0);
                                $remaining = max(0, $ordered - $received);
                                $key = $item->id;
                            ?>
                                <tr>
                                    <td class="ps-3 small"><?= $i + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($item->product->name ?? '-') ?></div>
                                        <div class="text-muted small" style="font-size: 0.65rem;"><?= e($item->product->product_code ?? '') ?></div>
                                    </td>
                                    <td class="text-center"><?= $ordered ?></td>
                                    <td class="text-center"><?= $received ?></td>
                                    <td class="text-center fw-bold js-remaining" data-remaining="<?= $remaining ?>"><?= $remaining ?></td>

                                    <td style="width:100px">
                                        <input type="number" class="form-control form-control-sm text-center fw-bold border-primary js-qty-good"
                                            name="receives[<?= $key ?>][qty_good]"
                                            min="0" max="<?= $remaining ?>"
                                            value="<?= $remaining ?>">
                                    </td>
                                    <td style="width:100px">
                                        <input type="number" class="form-control form-control-sm text-center fw-bold js-qty-damaged"
                                            name="receives[<?= $key ?>][qty_damaged]"
                                            min="0" max="<?= $remaining ?>"
                                            value="0">
                                    </td>
                                    <td class="pe-3" style="width:180px">
                                        <input type="text" class="form-control form-control-sm"
                                            name="receives[<?= $key ?>][notes]"
                                            placeholder="Notes (optional)">
                                        <small class="text-danger small js-row-msg"></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-1">UPLOAD PHOTO OF GOOD ITEMS (OPTIONAL)</label>
                    <input type="file" name="photos_good[]" class="form-control mb-3" multiple accept="image/*">

                    <label class="form-label fw-bold small text-uppercase text-muted mb-1">UPLOAD PHOTO OF DAMAGED ITEMS (OPTIONAL)</label>
                    <input type="file" name="photos_damaged[]" class="form-control" multiple accept="image/*">
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal"
                    style="border-color: #d9dee3; color: #8592a3;">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bx bx-save me-1"></i> Save Goods Received
                </button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    protected function isProcurementUser($user): bool
        {
            $roles = $user?->roles ?? collect();
            return $roles->contains('slug', 'procurement') || (($user->role ?? '') === 'procurement');
        }

    protected function isCeoUser($user): bool
        {
            $roles = $user?->roles ?? collect();
            return $roles->contains('slug', 'ceo') || (($user->role ?? '') === 'ceo');
        }


        /** EDIT PO (manual / dari Restock Request) */
        public function edit(PurchaseOrder $po)
        {
            $po->load([
                'items.product.supplier',
                'items.warehouse',
                'supplier',
            ]);

            $suppliers  = Supplier::orderBy('name')->get(['id', 'name']);
            $warehouses = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']);

            $cols = ['id', 'product_code', 'name', 'supplier_id'];
            if (Schema::hasColumn('products', 'purchase_price')) $cols[] = 'purchase_price';
            if (Schema::hasColumn('products', 'buy_price'))      $cols[] = 'buy_price';
            if (Schema::hasColumn('products', 'cost_price'))     $cols[] = 'cost_price';
            if (Schema::hasColumn('products', 'selling_price'))  $cols[] = 'selling_price';

            $products = Product::where('is_active', true)
                ->with('supplier:id,name')
                ->orderBy('name')
                ->get($cols);


            $isFromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

            $user = auth()->user();

            // ✅ HANYA SUPERADMIN YANG BOLEH EDIT PO (DRAFT/REJECTED)
            $canEdit = $this->isSuperadminUser($user);

            // ✅ kalau user bukan superadmin → LOCK walaupun PO masih draft
            $isLocked = $this->poIsLocked($po, $user) || ! $canEdit;

            return view('admin.po.edit', compact(
                'po',
                'suppliers',
                'warehouses',
                'products',
                'isFromRequest',
                'isLocked'
            ));
        }


                /** SIMPAN PERUBAHAN PO */
        public function update(Request $request, PurchaseOrder $po)
        {
            $me = $request->user();

            abort_unless($this->isSuperadminUser($me), 403, 'Hanya superadmin yang boleh mengubah PO.');

            if ($this->poIsLocked($po, $me)) {
                return back()->with(
                    'error',
                    'PO sudah ORDERED/COMPLETED (atau sedang proses approval / sudah ada GR), tidak dapat diubah.'
                );
            }

            $wasFromRequest = $po->items()
                ->whereNotNull('request_id')
                ->exists();

            $oldRequestIds = [];
            if (
                $wasFromRequest &&
                Schema::hasTable('purchase_order_items') &&
                Schema::hasColumn('purchase_order_items', 'request_id') &&
                Schema::hasTable('request_restocks')
            ) {
                $oldRequestIds = DB::table('purchase_order_items')
                    ->where('purchase_order_id', $po->id)
                    ->whereNotNull('request_id')
                    ->pluck('request_id')
                    ->unique()
                    ->all();
            }

            $validated = $request->validate([
                'supplier_id'           => ['nullable', 'exists:suppliers,id'],
                'notes'                 => ['nullable', 'string'],

                'items'                 => ['array'], // boleh kosong di validation, tapi nanti kita tahan manual
                'items.*.id'            => ['nullable', 'integer', 'exists:purchase_order_items,id'],
                'items.*.product_id'    => ['required', 'exists:products,id'],
                'items.*.warehouse_id'  => ['nullable', 'exists:warehouses,id'],
                'items.*.qty'           => ['required', 'integer', 'min:1'],
                'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
                'items.*.discount_type' => ['nullable', 'in:percent,amount'],
                'items.*.discount_value'=> ['nullable', 'numeric', 'min:0'],
                'items.*.request_id'    => ['nullable', 'integer'],
            ]);

            $productIds = collect($validated['items'] ?? [])
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->all();

            if (!empty($productIds)) {

                $inactive = Product::whereIn('id', $productIds)
                    ->where('is_active', false)
                    ->pluck('name')
                    ->all();

                if (!empty($inactive)) {
                    throw ValidationException::withMessages([
                        'items' => 'Product berikut NONAKTIF dan tidak bisa dipakai: '
                            . implode(', ', $inactive),
                    ]);
                }
            }


            DB::transaction(function () use ($validated, $po, $wasFromRequest) {

                $po->supplier_id = $validated['supplier_id'] ?? Supplier::orderBy('id')->value('id');

                // ✅ RULE: kalau status REJECTED -> notes (alasan) gak boleh berubah dari form update
                if (($po->approval_status ?? 'draft') !== 'rejected') {
                    if (array_key_exists('notes', $validated)) {
                        $po->notes = $validated['notes'] ?? null;
                    }
                }

                $itemsInput = $validated['items'] ?? [];

                // ✅ VALIDASI: PO ga boleh kosong (minimal 1 item valid)
                $hasAtLeastOne = collect($itemsInput)->contains(function ($row) {
                    return !empty($row['product_id']) && (int)($row['qty'] ?? 0) > 0;
                });
                if (! $hasAtLeastOne) {
                    throw ValidationException::withMessages([
                        'items' => 'PO tidak boleh kosong. Minimal 1 item wajib diisi.',
                    ]);
                }

                $productIds = collect($itemsInput)->pluck('product_id')->filter()->unique()->all();
                $productPrices = collect();

                if ($productIds) {
                    $cols = ['id'];
                    if (Schema::hasColumn('products', 'purchase_price')) $cols[] = 'purchase_price';
                    if (Schema::hasColumn('products', 'buy_price'))      $cols[] = 'buy_price';
                    if (Schema::hasColumn('products', 'cost_price'))     $cols[] = 'cost_price';
                    if (Schema::hasColumn('products', 'selling_price'))  $cols[] = 'selling_price';

                    $rows = Product::whereIn('id', $productIds)->get($cols);

                    $productPrices = $rows->mapWithKeys(function ($p) use ($wasFromRequest) {
                        $buy  = (float) ($p->purchase_price ?? $p->buy_price ?? $p->cost_price ?? 0);
                        $sell = (float) ($p->selling_price ?? 0);

                        $price = $wasFromRequest
                            ? ($sell ?: $buy)
                            : ($buy ?: $sell);

                        return [$p->id => $price];
                    });
                }

                $existing = $po->items()->get()->keyBy('id');
                $keepIds  = [];

                $subtotal      = 0;
                $discountTotal = 0;

                foreach ($itemsInput as $row) {
                    if (empty($row['product_id']) || empty($row['qty'])) {
                        continue;
                    }

                    if (! empty($row['id']) && $existing->has($row['id'])) {
                        $item = $existing->get($row['id']);
                    } else {
                        $item = new PurchaseOrderItem();
                        $item->purchase_order_id = $po->id;
                    }

                    $item->product_id  = $row['product_id'];
                    $item->qty_ordered = (int) ($row['qty'] ?? 0);

                    if ($wasFromRequest) {
                        $item->warehouse_id = $row['warehouse_id'] ?? null;
                    } else {
                        $item->warehouse_id = null;
                    }

                    $rawPrice = array_key_exists('unit_price', $row) ? $row['unit_price'] : null;

                    if ($rawPrice === null || $rawPrice === '') {
                        $price = (float) ($productPrices[$row['product_id']] ?? 0);
                    } else {
                        $price = (float) $rawPrice;
                    }

                    $item->unit_price = $price;

                    $item->discount_type  = $row['discount_type'] ?: null;
                    $item->discount_value = (float) ($row['discount_value'] ?? 0);

                    if (! empty($row['request_id']) || $item->request_id) {
                        $item->request_id = $row['request_id'] ?? $item->request_id;
                    }

                    $lineTotal = $item->qty_ordered * $item->unit_price;

                    if ($item->discount_type === 'percent') {
                        $disc = $lineTotal * min(max($item->discount_value, 0), 100) / 100;
                    } elseif ($item->discount_type === 'amount') {
                        $disc = min($item->discount_value, $lineTotal);
                    } else {
                        $disc = 0;
                    }

                    $item->line_total = max($lineTotal - $disc, 0);
                    $item->save();

                    $keepIds[]      = $item->id;
                    $subtotal      += $lineTotal;
                    $discountTotal += $disc;
                }

                // ✅ kalau ujungnya kosong -> tahan
                if (count($keepIds) === 0) {
                    throw ValidationException::withMessages([
                        'items' => 'PO tidak boleh kosong. Minimal 1 item wajib diisi.',
                    ]);
                }

                if (count($keepIds)) {
                    $po->items()->whereNotIn('id', $keepIds)->delete();
                } else {
                    $po->items()->delete();
                }

                $po->subtotal       = $subtotal;
                $po->discount_total = $discountTotal;
                $po->grand_total    = $subtotal - $discountTotal;
                $po->save();
            });

            $po->load('items');

            $this->syncRequestsFromPo($po, $wasFromRequest, $oldRequestIds);
            $this->syncLogisticsStatusToRequests($po);

            return back()->with('success', 'PO berhasil disimpan.');
        }

            /** SET PO → ORDERED  */
        /** SET PO → ORDERED  */
/**
 * Ajukan PO ke flow approval.
 * - grand_total < 1 jt / < 2 jt → cukup Procurement (nanti di-approveProc).
 * - grand_total > 2 jt          → Procurement lalu CEO.
 */
        public function order(PurchaseOrder $po)
        {
            $user = auth()->user();
            $isSuperadmin = $this->isSuperadminUser($user);
            abort_unless($this->isSuperadminUser(auth()->user()), 403, 'Hanya superadmin yang boleh mengajukan approval PO.');


            // ORDERED/COMPLETED gak bisa diajukan lagi
            if (in_array($po->status, ['ordered', 'completed'], true)) {
                return redirect()->route('po.index')
                    ->with('info', 'PO sudah tidak bisa diajukan approval lagi.');
            }

            // CANCELLED -> hanya superadmin yang boleh "buka lagi"
            if ($po->status === 'cancelled' && ! $isSuperadmin) {
                return redirect()->route('po.index')
                    ->with('info', 'PO CANCELLED tidak dapat diajukan approval lagi.');
            }

            // kalau superadmin dan PO CANCELLED -> buka balik ke draft (biar bisa jalan lagi)
            if ($po->status === 'cancelled' && $isSuperadmin) {
                $po->status = 'draft';
                if (Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                    $po->cancelled_at = null;
                }
            }

            if (in_array($po->approval_status, ['waiting_procurement','waiting_ceo','approved'], true)) {
                return redirect()->route('po.edit', $po->id)
                    ->with('info', 'PO sudah dalam proses approval.');
            }

            if ($po->items()->count() === 0 || $po->grand_total <= 0) {
                return redirect()->route('po.edit', $po->id)
                    ->with('error', 'PO tidak boleh kosong. Isi item dan harga dulu sebelum mengajukan approval.');
            }

            $inactive = $po->items()
                ->whereHas('product', function ($q) {
                    $q->where('is_active', false);
                })
                ->exists();

            if ($inactive) {
                return redirect()->route('po.edit', $po->id)
                    ->with('error', 'PO mengandung product NONAKTIF. Tidak bisa diajukan approval.');
            }


            // logistik tetap draft
            $po->status           = 'draft';
            $po->approval_status  = 'waiting_procurement';

            $po->approved_by_procurement = null;
            $po->approved_by_ceo         = null;
            $po->approved_at_procurement = null;
            $po->approved_at_ceo         = null;

            // ✅ notes (alasan reject) JANGAN dihapus di sini
            $po->save();
            $this->syncLogisticsStatusToRequests($po);

            return redirect()->route('po.edit', $po->id)
                ->with('success', 'PO berhasil diajukan ke Procurement untuk approval.');
        }




        public function cancel(PurchaseOrder $po)
        {
            abort_unless($this->isSuperadminUser(auth()->user()), 403, 'Hanya superadmin yang boleh mengajukan approval PO.');

            // kalau sudah ordered/completed/cancelled -> stop
            if (in_array($po->status, ['ordered','completed','cancelled'], true)) {
                return back()->with('error', 'PO sudah tidak bisa di-cancel.');
            }

            // kalau lagi proses approval -> stop
            if (in_array($po->approval_status, ['waiting_procurement','waiting_ceo','approved'], true)) {
                return back()->with('error', 'PO sedang dalam proses approval, tidak dapat di-cancel.');
            }

            if ($this->poHasGR($po)) {
                return back()->with('error', 'PO sudah memiliki Goods Received, tidak dapat dibatalkan.');
            }

            DB::transaction(function () use ($po) {
                $po->status = 'cancelled';
                if (Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                    $po->cancelled_at = now();
                }
                $po->save();
                $this->syncLogisticsStatusToRequests($po);

                if (
                    Schema::hasTable('purchase_order_items') &&
                    Schema::hasColumn('purchase_order_items', 'request_id') &&
                    Schema::hasTable('request_restocks') &&
                    Schema::hasColumn('request_restocks', 'status')
                ) {
                    $requestIds = $po->items()
                        ->whereNotNull('request_id')
                        ->pluck('request_id')
                        ->unique()
                        ->all();

                    if (! empty($requestIds)) {
                        DB::table('request_restocks')
                            ->whereIn('id', $requestIds)
                            ->update([
                                'status'     => 'cancelled',
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

            return back()->with('success', 'PO dibatalkan dan request ikut CANCELLED.');
        }

        protected function appendRejectNote(PurchaseOrder $po, string $stage, string $reason): string
        {
            [$userNote, $rejectLog] = $this->splitNotes($po->notes);

            // hitung urutan reject di log (berdasarkan pola "1) ", "2) ", dst)
            $count = 0;
            if ($rejectLog !== '') {
                preg_match_all('/^\d+\)\s/m', $rejectLog, $m);
                $count = count($m[0]);
            }

            $seq   = $count + 1;
            $stamp = now()->format('Y-m-d H:i');
            $entry = "{$seq}) {$stage} ({$stamp}) : " . trim($reason);

            $newLog = $rejectLog === '' ? $entry : ($rejectLog . "\n" . $entry);

            // gabung lagi: userNote tetap, log bertambah
            return (string)$this->mergeNotes($userNote, $newLog);
        }



        public function approveProcurement(Request $request, PurchaseOrder $po)
        {
            if ($po->approval_status !== 'waiting_procurement') {
                return back()->with('error', 'PO tidak dalam status menunggu approval Procurement.');
            }

            $user = $request->user();

            // ✅ superadmin dilarang approve
            abort_if($this->isSuperadminUser($user), 403, 'Superadmin tidak boleh melakukan approval.');
            abort_unless($this->isProcurementUser($user), 403, 'Hanya Procurement yang boleh approve tahap ini.');

            $po->approved_by_procurement = $user->id;
            $po->approved_at_procurement = now();

            $grand = (int) $po->grand_total;

            if ($grand > self::CEO_MIN_TOTAL) {
                $po->approval_status = 'waiting_ceo';
            } else {
                $po->approval_status = 'approved';
                $po->status          = 'ordered';
                $po->ordered_at      = now();
                $po->notes = $this->clearRejectLogKeepUserNote($po->notes);

                // sync request_restocks status
                $requestIds = $po->items()
                    ->whereNotNull('request_id')
                    ->pluck('request_id')
                    ->unique()
                    ->all();

                if (!empty($requestIds)) {
                    DB::table('request_restocks')
                        ->whereIn('id', $requestIds)
                        ->update([
                            'status'     => 'ordered',
                            'updated_at' => now(),
                        ]);
                }
            }

            $po->save();
            $this->syncLogisticsStatusToRequests($po);

            return back()->with('success', 'Approval Procurement berhasil disimpan.');
        }



        public function rejectProcurement(Request $request, PurchaseOrder $po)
        {
            if ($po->approval_status !== 'waiting_procurement') {
                return back()->with('error', 'PO tidak dalam status menunggu approval Procurement.');
            }

            $user = $request->user();

            abort_if($this->isSuperadminUser($user), 403, 'Superadmin tidak boleh melakukan approval.');
            abort_unless($this->isProcurementUser($user), 403, 'Hanya Procurement yang boleh reject tahap ini.');

            $data = $request->validate([
                'reason' => ['required', 'string', 'max:1000'],
            ]);

            $po->approval_status = 'rejected';
            $po->notes = $this->appendRejectNote($po, 'PROCUREMENT', $data['reason']);

            $po->approved_by_procurement = $user->id;
            $po->approved_at_procurement = now();

            $po->approved_by_ceo = null;
            $po->approved_at_ceo = null;

            $po->status = 'draft';
            $po->save();

            return redirect()->route('po.edit', $po->id)
                ->with('error', 'PO ditolak Procurement.');
        }



        public function approveCeo(Request $request, PurchaseOrder $po)
        {
            if ($po->approval_status !== 'waiting_ceo') {
                return back()->with('error', 'PO tidak dalam status menunggu approval CEO.');
            }

            $user = $request->user();

            abort_if($this->isSuperadminUser($user), 403, 'Superadmin tidak boleh melakukan approval.');
            abort_unless($this->isCeoUser($user), 403, 'Hanya CEO yang boleh approve tahap ini.');

            $po->approved_by_ceo = $user->id;
            $po->approved_at_ceo = now();

            $po->approval_status = 'approved';
            $po->notes = $this->clearRejectLogKeepUserNote($po->notes);
            $po->status          = 'ordered';
            $po->ordered_at      = now();

            // sync request_restocks status
            $requestIds = $po->items()
                ->whereNotNull('request_id')
                ->pluck('request_id')
                ->unique()
                ->all();

            if (!empty($requestIds)) {
                DB::table('request_restocks')
                    ->whereIn('id', $requestIds)
                    ->update([
                        'status'     => 'ordered',
                        'updated_at' => now(),
                    ]);
            }

            $po->save();
            $this->syncLogisticsStatusToRequests($po);

            return back()->with('success', 'PO disetujui CEO dan status di-set ORDERED.');
        }


        public function rejectCeo(Request $request, PurchaseOrder $po)
        {
            if ($po->approval_status !== 'waiting_ceo') {
                return back()->with('error', 'PO tidak dalam status menunggu approval CEO.');
            }

            $user = $request->user();

            abort_if($this->isSuperadminUser($user), 403, 'Superadmin tidak boleh melakukan approval.');
            abort_unless($this->isCeoUser($user), 403, 'Hanya CEO yang boleh reject tahap ini.');

            $data = $request->validate([
                'reason' => ['required', 'string', 'max:1000'],
            ]);

            $po->approval_status = 'rejected';
            $po->notes = $this->appendRejectNote($po, 'CEO', $data['reason']);

            $po->approved_by_ceo = $user->id;
            $po->approved_at_ceo = now();

            $po->status = 'draft';
            $po->save();

            return redirect()->route('po.edit', $po->id)
                ->with('error', 'PO ditolak CEO.');
        }

        // ===== NOTES HELPER (user note + reject log dalam 1 kolom) =====
    private const REJECT_MARKER = "\n\n--- REJECT LOG ---\n";

    protected function splitNotes(?string $notes): array
    {
        $notes = (string)($notes ?? '');
        $pos = strpos($notes, self::REJECT_MARKER);

        if ($pos === false) {
            return [trim($notes), '']; // [userNote, rejectLog]
        }

        $user = trim(substr($notes, 0, $pos));
        $log  = trim(substr($notes, $pos + strlen(self::REJECT_MARKER)));

        return [$user, $log];
    }

    protected function mergeNotes(string $userNote, string $rejectLog): ?string
    {
        $userNote  = trim($userNote);
        $rejectLog = trim($rejectLog);

        if ($rejectLog !== '') {
            $merged = $userNote . self::REJECT_MARKER . $rejectLog;
            return trim($merged) !== '' ? trim($merged) : null;
        }

        return $userNote !== '' ? $userNote : null;
    }

    protected function clearRejectLogKeepUserNote(?string $notes): ?string
    {
        [$userNote, $rejectLog] = $this->splitNotes($notes);
        // buang reject log, simpan user note saja
        return $this->mergeNotes($userNote, '');
    }




        public function store(Request $r)
        {
            abort_unless($this->isSuperadminUser(auth()->user()), 403, 'Hanya superadmin yang boleh membuat PO.');

            $code = $this->generateManualPoCode();

            $supplierId = Supplier::orderBy('id')->value('id');

            $po = PurchaseOrder::create([
                'po_code'         => $code,
                'supplier_id'     => $supplierId,
                'ordered_by'      => auth()->id(),
                'status'          => 'draft',
                'subtotal'        => 0,
                'discount_total'  => 0,
                'grand_total'     => 0,
                'notes'           => null,
            ]);

            return redirect()
                ->route('po.edit', $po->id)
                ->with('success', 'PO baru berhasil dibuat, silakan isi item.');
        }


    protected function generateManualPoCode(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';

        $lastCode = PurchaseOrder::where('po_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('po_code');

        $next = 1;
        if ($lastCode) {
            $lastSeq = (int) substr($lastCode, strlen($prefix));
            $next    = $lastSeq + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    protected function getCentralWarehouseId(): ?int
    {
        if (! Schema::hasTable('warehouses')) {
            return null;
        }

        $query = Warehouse::query();

        if (Schema::hasColumn('warehouses', 'is_central')) {
            $query->where('is_central', 1);
        } elseif (Schema::hasColumn('warehouses', 'warehouse_name')) {
            $query->where('warehouse_name', 'Central Stock');
        }

        $id = $query->value('id');

        if (! $id) {
            $id = Warehouse::orderBy('id')->value('id');
        }

        return $id ?: null;
    }

    protected function isSuperadminUser($user): bool
    {
        $roles = $user?->roles ?? collect();
        return $roles->contains('slug', 'superadmin') || (($user->role ?? '') === 'superadmin');
    }

    protected function poHasGR(PurchaseOrder $po): bool
    {
        if (
            !Schema::hasTable('restock_receipts') ||
            !Schema::hasColumn('restock_receipts', 'purchase_order_id')
        ) {
            return false;
        }

        return DB::table('restock_receipts')
            ->where('purchase_order_id', $po->id)
            ->exists();
    }


    protected function poIsLocked(PurchaseOrder $po, $user = null): bool
    {
        $isSuperadmin = $this->isSuperadminUser($user);

        // 1) ORDERED / COMPLETED selalu lock
        if (in_array($po->status, ['ordered', 'completed'], true)) {
            return true;
        }

        // 2) Kalau sudah ada GR -> lock (siapa pun)
        if ($this->poHasGR($po)) {
            return true;
        }

        // 3) Sedang proses approval / sudah approved -> lock
        if (in_array($po->approval_status, ['waiting_procurement', 'waiting_ceo', 'approved'], true)) {
            return true;
        }

        // 4) CANCELLED: default lock, tapi superadmin boleh buka lagi (selama belum ada GR & bukan approval locked)
        if ($po->status === 'cancelled') {
            return !$isSuperadmin;
        }

        // Draft / Rejected -> boleh edit
        return false;
    }





    // ====== SYNC PO → request_restocks ======
/**
 * Sinkron data PO (FROM REQUEST) ke tabel request_restocks:
 * - update qty & harga untuk item yang masih terhubung
 * - buat baris baru untuk item tambahan (request_id null)
 * - tambahkan note "Cancelled di PO" untuk request yang item-nya dihapus dari PO
 */
    protected function syncRequestsFromPo(
        PurchaseOrder $po,
        bool $wasFromRequest = false,
        array $oldRequestIds = []
    ): void {
        if (!$wasFromRequest) {
            // PO manual → nggak ada hubungan ke request_restocks
            return;
        }

        if (
            !Schema::hasTable('purchase_order_items') ||
            !Schema::hasColumn('purchase_order_items', 'product_id') ||
            !Schema::hasTable('request_restocks')
        ) {
            return;
        }

        $po->loadMissing('items');

        $poNote   = trim((string) $po->notes);
        $hasCode  = Schema::hasColumn('request_restocks', 'code');
        $hasNote  = Schema::hasColumn('request_restocks', 'note');
        $hasDate  = Schema::hasColumn('request_restocks', 'request_date');

        // — Ambil 1 baris RF lama sebagai template (code, requester, tanggal, dll)
        $baseHeader = null;
        if (!empty($oldRequestIds)) {
            $selectCols = ['id', 'warehouse_id', 'requested_by'];
            if ($hasCode) $selectCols[] = 'code';
            if ($hasDate) $selectCols[] = 'request_date';
            if ($hasNote) $selectCols[] = 'note';

            $baseHeader = DB::table('request_restocks')
                ->whereIn('id', $oldRequestIds)
                ->orderBy('id')
                ->first($selectCols);
        }

        // ---------------- 1) UPDATE ITEM YANG MASIH TERHUBUNG ----------------
        if (Schema::hasColumn('purchase_order_items', 'request_id')) {
            $itemsWithReq = $po->items()
                ->whereNotNull('request_id')
                ->get(['request_id', 'qty_ordered', 'unit_price']);

            foreach ($itemsWithReq as $row) {
                $qty   = (int) $row->qty_ordered;
                $price = (float) $row->unit_price;

                $update = [
                    'quantity_requested' => $qty,
                    'cost_per_item'      => $price,
                    'total_cost'         => $qty * $price,
                    'updated_at'         => now(),
                ];

                if ($poNote !== '' && $hasNote) {
                    $update['note'] = $poNote;
                }

                DB::table('request_restocks')
                    ->where('id', $row->request_id)
                    ->update($update);
            }
        }

        // ---------------- 2) BUAT REQUEST BARU UNTUK ITEM TAMBAHAN ----------------
        if (Schema::hasColumn('purchase_order_items', 'request_id')) {
            $extraItems = $po->items()
                ->whereNull('request_id')
                ->get(['id', 'product_id', 'warehouse_id', 'qty_ordered', 'unit_price']);

            if ($extraItems->isNotEmpty()) {
                $productSuppliers = Product::whereIn(
                    'id',
                    $extraItems->pluck('product_id')->filter()->unique()
                )->pluck('supplier_id', 'id');

                foreach ($extraItems as $item) {
                    $productId   = (int) $item->product_id;
                    $warehouseId = (int) $item->warehouse_id;
                    $qty         = (int) $item->qty_ordered;
                    $price       = (float) $item->unit_price;

                    if (!$productId || !$warehouseId || $qty <= 0) {
                        continue;
                    }

                    $supplierId = (int) ($productSuppliers[$productId] ?? $po->supplier_id);

                    $rfStatus = in_array($po->status, ['ordered', 'completed'], true)
                        ? 'ordered'
                        : 'approved';

                    $insert = [
                        'supplier_id'        => $supplierId ?: null,
                        'product_id'         => $productId,
                        'warehouse_id'       => $baseHeader->warehouse_id ?? $warehouseId,
                        'requested_by'       => $baseHeader->requested_by ?? ($po->ordered_by ?: auth()->id()),
                        'quantity_requested' => $qty,
                        'quantity_received'  => 0,
                        'cost_per_item'      => $price,
                        'total_cost'         => $qty * $price,
                        'status'             => $rfStatus,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];

                    if ($hasCode && $baseHeader && !empty($baseHeader->code)) {
                        // pakai code dokumen RR yang sama → tetap 1 dokumen
                        $insert['code'] = $baseHeader->code;
                    }

                    if ($hasDate) {
                        $insert['request_date'] = $baseHeader->request_date ?? now()->toDateString();
                    }

                    if ($hasNote && $poNote !== '') {
                        $insert['note'] = $poNote;
                    }

                    $reqId = DB::table('request_restocks')->insertGetId($insert);

                    // kalau lu masih pengen auto generate code sendiri ketika awalnya nggak ada
                    if ($hasCode && (!$baseHeader || empty($baseHeader->code))) {
                        $code = 'RR-' . $reqId;
                        DB::table('request_restocks')
                            ->where('id', $reqId)
                            ->update([
                                'code'       => $code,
                                'updated_at' => now(),
                            ]);
                    }

                    // link balik ke PO item
                    DB::table('purchase_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'request_id' => $reqId,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        // ---------------- 3) NOTE "Cancelled di PO" UNTUK ITEM YANG DIHAPUS ----------------
        if (!empty($oldRequestIds) && $hasNote) {
            $currentReqIds = $po->items()
                ->whereNotNull('request_id')
                ->pluck('request_id')
                ->unique()
                ->all();

            $removedReqIds = array_diff($oldRequestIds, $currentReqIds);

            if (!empty($removedReqIds)) {
                $rows = DB::table('request_restocks')
                    ->whereIn('id', $removedReqIds)
                    ->get(['id', 'note']);

                $marker = 'Cancelled di PO';

                foreach ($rows as $rf) {
                    $note = trim((string)($rf->note ?? ''));

                    if ($note === '') {
                        $newNote = $marker;
                    } elseif (stripos($note, $marker) === false) {
                        $newNote = $note . ' | ' . $marker;
                    } else {
                        $newNote = $note; // sudah ada
                    }

                    DB::table('request_restocks')
                        ->where('id', $rf->id)
                        ->update([
                            'note'       => $newNote,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }


    /** Barang MASUK ke CENTRAL dari PO manual (status completed) */
    protected function applyStockFromManualPo(PurchaseOrder $po): void
    {
        if (!Schema::hasTable('stock_levels')) {
            return;
        }
        if (
            !Schema::hasColumn('stock_levels', 'product_id') ||
            !Schema::hasColumn('stock_levels', 'owner_type') ||
            !Schema::hasColumn('stock_levels', 'owner_id') ||
            !Schema::hasColumn('stock_levels', 'quantity')
        ) {
            return;
        }

        $items = $po->items()->whereNull('request_id')->get();

        foreach ($items as $item) {
            $productId = $item->product_id;
            $qty       = (int) $item->qty_ordered;

            if (!$productId || $qty <= 0) {
                continue;
            }

            $level = DB::table('stock_levels')
                ->where('owner_type', 'pusat')
                ->where('product_id', $productId)
                ->first();

            if ($level) {
                DB::table('stock_levels')
                    ->where('id', $level->id)
                    ->update([
                        'quantity'   => (int) $level->quantity + $qty,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_levels')->insert([
                    'owner_type' => 'pusat',
                    'owner_id'   => 0,
                    'product_id' => $productId,
                    'quantity'   => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // SINKRONISASI KE TABEL products (Superadmin Dashboard)
            DB::table('products')->where('id', $productId)->increment('stock', $qty);
        }
    }

    /** Barang KELUAR dari CENTRAL ke WAREHOUSE (PO dari request gudang) */
    protected function applyStockForWarehousePo(PurchaseOrder $po): void
    {
        // SEKARANG DIPANGGIL DARI PROSES GR (BUKAN LAGI DARI order())
        if (!Schema::hasTable('stock_levels')) {
            return;
        }
        if (
            !Schema::hasColumn('stock_levels', 'product_id') ||
            !Schema::hasColumn('stock_levels', 'owner_type') ||
            !Schema::hasColumn('stock_levels', 'owner_id') ||
            !Schema::hasColumn('stock_levels', 'quantity')
        ) {
            return;
        }

        $items = $po->items()->whereNotNull('request_id')->get();

        foreach ($items as $item) {
            $productId   = $item->product_id;
            $warehouseId = $item->warehouse_id;
            $qty         = (int) $item->qty_ordered;

            if (!$productId || !$warehouseId || $qty <= 0) {
                continue;
            }

            // Kurangi PUSAT
            $central = DB::table('stock_levels')
                ->where('owner_type', 'pusat')
                ->where('product_id', $productId)
                ->first();

            if ($central) {
                $newQty = max(0, (int) $central->quantity - $qty);
                DB::table('stock_levels')
                    ->where('id', $central->id)
                    ->update([
                        'quantity'   => $newQty,
                        'updated_at' => now(),
                    ]);
            }

            // SINKRONISASI KE TABEL products (Superadmin Dashboard)
            DB::table('products')->where('id', $productId)->decrement('stock', $qty);

            // Tambah ke WAREHOUSE
            $levelWh = DB::table('stock_levels')
                ->where('owner_type', 'warehouse')
                ->where('owner_id', $warehouseId)
                ->where('product_id', $productId)
                ->first();

            if ($levelWh) {
                DB::table('stock_levels')
                    ->where('id', $levelWh->id)
                    ->update([
                        'quantity'   => (int) $levelWh->quantity + $qty,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_levels')->insert([
                    'owner_type' => 'warehouse',
                    'owner_id'   => $warehouseId,
                    'product_id' => $productId,
                    'quantity'   => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function approve(Request $r, PurchaseOrder $po)
        {
            $user  = auth()->user();
            $roles = $user?->roles ?? collect();

            $isProcurement = $roles->contains('slug', 'procurement') || $roles->contains('slug', 'superadmin');
            $isCeo         = $roles->contains('slug', 'ceo') || $roles->contains('slug', 'superadmin');

            $total = (float) $po->grand_total;

            // Normalisasi kalau approval_status masih null
            if (! $po->approval_status) {
                $po->approval_status = 'waiting_procurement';
            }

            // Tahap 1: Procurement
            if ($po->approval_status === 'waiting_procurement') {
                if (! $isProcurement) {
                    abort(403, 'Anda tidak berhak meng-approve tahap Procurement.');
                }

                $po->approved_by_procurement = $user->id;
                $po->approved_at_procurement = now();

                // RULE:
                //  - total <= 1.000.000  → beres di Procurement
                //  - total  > 1.000.000  → lanjut ke CEO
                if ($total <= 1000000) {
                    $po->approval_status = 'approved';
                } else {
                    $po->approval_status = 'waiting_ceo';
                }

                $po->save();

                $msg = $po->approval_status === 'approved'
                    ? 'PO disetujui Procurement (final).'
                    : 'PO disetujui Procurement, menunggu approval CEO.';

                return back()->with('success', $msg);
            }

            // Tahap 2: CEO
            if ($po->approval_status === 'waiting_ceo') {
                if (! $isCeo) {
                    abort(403, 'Anda tidak berhak meng-approve tahap CEO.');
                }

                $po->approved_by_ceo = $user->id;
                $po->approved_at_ceo = now();
                $po->approval_status = 'approved';
                $po->save();

                return back()->with('success', 'PO disetujui CEO (final).');
            }

            return back()->with('info', 'PO ini sudah tidak dalam status menunggu approval.');
        }


    public function exportPdf(Request $request, PurchaseOrder $po)
    {
        // 1) pilih template: default / partner
        $tpl  = $request->query('tpl', 'default');
        $view = $tpl === 'partner'
            ? 'admin.po.print_partner'
            : 'admin.po.print';

        // 2) ambil company default
        $company = Company::where('is_default', true)
            ->where('is_active', true)
            ->first();

        // 3) eager load relasi
        $po->load([
            'supplier',
            'items.product.supplier',
            'items.warehouse',
            'user',
            'procurementApprover',
            'ceoApprover',
        ]);

        $isDraft = $po->approval_status !== 'approved';

        // 4) balikin VIEW biasa, bukan PDF
        return view($view, [
            'po'        => $po,
            'company'   => $company,
            'isDraft'   => $isDraft,
            'autoPrint' => $request->query('autoprint', 1) == 1,   // flag buat auto window.print()
        ]);
    }



public function exportIndexExcel(Request $request)
{
    // range opsional (kalau kosong -> tidak filter tanggal)
    [$from, $to, $key, $useDate] = $this->parseExportRangeOptional($request);

    $q              = trim((string) $request->input('q', ''));
    $status         = (string) $request->input('status', '');
    $approvalStatus = (string) $request->input('approval_status', '');
    $warehouseId    = (string) $request->input('warehouse_id', '');

    $dateCol = Schema::hasColumn('purchase_orders', 'po_date') ? 'po_date' : 'created_at';

    $query = PurchaseOrder::query()
        ->with([
            'supplier',
            'items.product.supplier',
            'items.warehouse',
            'procurementApprover',
            'ceoApprover',
        ])
        ->withCount('items')
        ->withCount([
            'restockReceipts as gr_count' => function ($q) {
                $q->where(function ($qq) {
                    $qq->where('qty_good', '>', 0)->orWhere('qty_damaged', '>', 0);
                });
            }
        ])
        ->orderByDesc('id');

    if ($q !== '') $query->where('po_code', 'like', "%{$q}%");
    if ($status !== '') $query->where('status', $status);
    if ($approvalStatus !== '') $query->where('approval_status', $approvalStatus);

    if ($warehouseId !== '') {
        $query->whereHas('items', function ($itQ) use ($warehouseId) {
            if ($warehouseId === 'central') $itQ->whereNull('warehouse_id');
            else $itQ->where('warehouse_id', (int) $warehouseId);
        });
    }

    // ✅ tanggal cuma diterapkan kalau user isi range/bulan
    if ($useDate) {
        if ($dateCol === 'created_at') {
            $query->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        } else {
            $query->whereBetween($dateCol, [$from->toDateString(), $to->toDateString()]);
        }
    }

    $pos = $query->get();

    // ✅ ambil company default aktif (buat kop surat di excel)

        $company = Company::where('is_default', true)
            ->where('is_active', true)
            ->first();

        // meta lu tetap (yang penting "filters" tetep bisa kebaca)
        $meta = [
            'filters' => $request->query(),
        ];

        $filename = "PO-INDEX-DETAIL-{$key}.xlsx";

        return Excel::download(
            new PoIndexWithItemsExport($pos, $meta, $dateCol, $company), // ✅ tambah $company
            $filename
        );

}


    private function parseExportRangeOptional(Request $request): array
    {
        $month = $request->input('month'); // optional
        $from  = $request->input('from');
        $to    = $request->input('to');

        // ✅ kalau tidak ada range/bulan -> export ALL (tanpa filter tanggal)
        if (!$from && !$to && !$month) {
            return [null, null, 'ALL', false];
        }

        // kalau cuma salah satu, samain biar valid
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

        // optional: tetap jaga 1 bulan (biar konsisten sama validasi JS)
        if ($fromC->format('Y-m') !== $toC->format('Y-m')) {
            throw ValidationException::withMessages([
                'to' => 'Range maksimal 1 bulan. "from" dan "to" harus di bulan yang sama.',
            ]);
        }

        return [$fromC, $toC, $fromC->format('Y-m'), true];
    }

    protected function syncLogisticsStatusToRequests(PurchaseOrder $po): void
    {
        if (!Schema::hasTable('request_restocks')) return;

        $requestIds = $po->items()
            ->whereNotNull('request_id')
            ->pluck('request_id')
            ->unique()
            ->all();

        if (empty($requestIds)) return;

        // Tentukan status target RR berdasarkan status PO
        // PO: ordered, partially_received, completed -> RR: ordered
        // PO: cancelled -> RR: cancelled
        // PO: draft/waiting... -> RR: approved (reviewed)
        
        $targetStatus = 'approved'; 
        if (in_array($po->status, ['ordered', 'partially_received', 'completed'])) {
            $targetStatus = 'ordered';
        } elseif ($po->status === 'cancelled') {
            $targetStatus = 'cancelled';
        }

        DB::table('request_restocks')
            ->whereIn('id', $requestIds)
            ->update([
                'status' => $targetStatus,
                'updated_at' => now()
            ]);
    }


}