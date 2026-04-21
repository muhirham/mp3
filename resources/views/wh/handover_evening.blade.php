@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $me = $me ?? auth()->user();
        $handover = $handover ?? null;
        $handoverList = $handoverList ?? collect();

        $roles = $me?->roles ?? collect();
        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug', 'admin') || $roles->contains('slug', 'superadmin');

        $isApprovalMode = !is_null($handover);

        $statusLabelMap = [
            'draft' => 'Draft',
            'waiting_morning_otp' => 'Waiting Morning OTP',
            'on_sales' => 'On Sales',
            'waiting_evening_otp' => 'Waiting Evening OTP',
            'closed' => 'Closed',
            'cancelled' => 'Cancelled',
        ];

        $badgeClassMap = [
            'closed' => 'bg-label-success',
            'on_sales' => 'bg-label-info',
            'waiting_morning_otp' => 'bg-label-warning',
            'waiting_evening_otp' => 'bg-label-warning',
            'cancelled' => 'bg-label-danger',
            'default' => 'bg-label-secondary',
        ];

        if ($isApprovalMode) {
            $statusKey = $handover->status;
            $statusLabel = $statusLabelMap[$statusKey] ?? $statusKey;
            $badgeClass = $badgeClassMap[$statusKey] ?? $badgeClassMap['default'];

            // edit approval hanya kalau belum closed
            $canEdit = $handover->status !== 'closed';

            $summaryIssued = (int) $handover->items->sum('qty_start');
            $summaryReturned = (int) $handover->items->sum('qty_returned');
            $summarySold = (int) $handover->items->sum('qty_sold');
            $summaryCash = (int) $handover->items->sum(function ($it) {
                return (int) ($it->payment_cash_amount ?? 0);
            });
            $summaryTransfer = (int) $handover->items->sum(function ($it) {
                return (int) ($it->payment_transfer_amount ?? 0);
            });
            $summaryTotal = $summaryCash + $summaryTransfer;
        }
    @endphp

    <style>
        .approval-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 1rem;
        }

        .approval-summary-card {
            border: 1px solid #e8edf6;
            border-radius: 16px;
            padding: 14px 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .approval-summary-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #8a97aa;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .approval-summary-value {
            font-size: 22px;
            font-weight: 800;
            color: #33425d;
            line-height: 1.1;
        }

        .approval-summary-note {
            margin-top: 6px;
            font-size: 12px;
            color: #8090a5;
        }

        .approval-helper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            border: 1px solid #e6edff;
            border-radius: 16px;
            background: #f6f9ff;
            padding: 14px 16px;
            margin-bottom: 1rem;
        }

        .approval-helper h6 {
            margin: 0 0 4px;
            font-weight: 800;
            color: #3d4e6b;
        }

        .approval-helper p {
            margin: 0;
            font-size: 12px;
            color: #72819a;
        }

        .table-approval .cell-main {
            font-weight: 700;
            color: #33425d;
        }

        .table-approval .cell-sub {
            font-size: 11px;
            color: #8390a6;
            margin-top: 3px;
            line-height: 1.35;
        }

        .table-approval .cell-good {
            color: #2fbb47;
            font-weight: 700;
        }

        .table-approval .proof-link {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .detail-proof-thumb {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #e7ecf5;
            background: #fff;
        }

        @media (max-width: 991.98px) {
            .approval-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .approval-summary-grid {
                grid-template-columns: 1fr;
            }

            .approval-helper {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>

    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="fw-bold py-2 mb-3">
            <span class="text-muted fw-light">
                Warehouse /
            </span>
            Reconcile (Approval Payment)
        </h4>

        {{-- Flash messages handled by SweetAlert below --}}

        <div id="eveningReconciliationContainer">
            {{-- ================== PILIH HANDOVER UNTUK APPROVAL ================== --}}
            <div id="eveningHandoverListContainer">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Select Handover for Payment Approval</h5>
                        <small class="text-muted">
                            Handovers will appear once filled by sales.
                        </small>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label">Handover</label>
                                <select name="handover_id" class="form-select">
                                    <option value="">— Select —</option>
                                    @foreach ($handoverList as $h)
                                        @php
                                            $stKey = $h->status;
                                            $stLabel = $statusLabelMap[$stKey] ?? $stKey;
                                        @endphp
                                        <option value="{{ $h->id }}" @selected($handover && $handover->id === $h->id)>
                                            {{ $h->code }}
                                            — {{ $h->sales->name ?? 'Sales #' . $h->sales_id }}
                                            ({{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }})
                                            [{{ $stLabel }}]
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">View</button>
                            </div>

                            <div class="col-md-3">
                                @if ($handover)
                                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary w-100">
                                        Clear Selection
                                    </a>
                                @endif
                            </div>
                        </form>

                        <div class="form-text mt-2">
                            If the handover doesn't appear here, ensure sales has filled in sales &amp; payment.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================== JIKA SUDAH PILIH HANDOVER: TAMPILKAN APPROVAL ================== --}}
            @if ($isApprovalMode)
                <div class="approval-summary-grid">
                    <div class="approval-summary-card">
                        <div class="approval-summary-label">Issued Qty</div>
                        <div class="approval-summary-value">{{ $summaryIssued }}</div>
                        <div class="approval-summary-note">Total units carried by sales</div>
                    </div>
                    <div class="approval-summary-card">
                        <div class="approval-summary-label">Returned Qty</div>
                        <div class="approval-summary-value">{{ $summaryReturned }}</div>
                        <div class="approval-summary-note">Units returned</div>
                    </div>
                    <div class="approval-summary-card">
                        <div class="approval-summary-label">Sold Qty</div>
                        <div class="approval-summary-value">{{ $summarySold }}</div>
                        <div class="approval-summary-note">Units sold entered by sales</div>
                    </div>
                    <div class="approval-summary-card">
                        <div class="approval-summary-label">Cash Total</div>
                        <div class="approval-summary-value" style="font-size:18px">Rp
                            {{ number_format($summaryCash, 0, ',', '.') }}</div>
                        <div class="approval-summary-note">Accumulated cash payments</div>
                    </div>
                    <div class="approval-summary-card">
                        <div class="approval-summary-label">Transfer Total</div>
                        <div class="approval-summary-value" style="font-size:18px">Rp
                            {{ number_format($summaryTransfer, 0, ',', '.') }}</div>
                        <div class="approval-summary-note">Total transfer amount</div>
                    </div>
                </div>

                {{-- HEADER HANDOVER --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <div class="small text-muted fw-semibold">Handover Code</div>
                                <div class="fs-6 fw-bold">{{ $handover->code }}</div>
                            </div>

                            <div class="col-md-4 mb-2">
                                <div class="small text-muted fw-semibold">Date</div>
                                <div>{{ optional($handover->handover_date)->format('Y-m-d') }}</div>
                            </div>

                            <div class="col-md-4 mb-2">
                                <div class="small text-muted fw-semibold">Warehouse</div>
                                <div>
                                    {{ optional($handover->warehouse)->warehouse_name ?? (optional($handover->warehouse)->name ?? '-') }}
                                </div>
                            </div>

                            <div class="col-md-4 mb-2">
                                <div class="small text-muted fw-semibold">Sales</div>
                                <div>{{ optional($handover->sales)->name ?? '-' }}</div>
                            </div>

                            <div class="col-md-4 mb-2">
                                <div class="small text-muted fw-semibold">Status</div>
                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                            </div>

                            <div class="col-md-4 mb-2">
                                <div class="small text-muted fw-semibold">Morning OTP Info</div>
                                <div class="small">
                                    Morning OTP Sent:
                                    {{ optional($handover->morning_otp_sent_at)->format('Y-m-d H:i') ?? '-' }}<br>
                                    Morning OTP Verified:
                                    {{ optional($handover->morning_otp_verified_at)->format('Y-m-d H:i') ?? '-' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- APPROVAL PAYMENT PER ITEM --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">Payment Approval Per Item</h5>
                        <small class="text-muted">
                            Approve / reject payments entered by sales.
                            Items already <strong>APPROVED</strong> cannot be modified.
                        </small>
                    </div>

                    <div class="card-body">
                        <div class="approval-helper">
                            <span class="badge bg-label-info">Total payment: Rp
                                {{ number_format($summaryTotal, 0, ',', '.') }}</span>
                        </div>

                        <form id="approvalForm" method="POST" action="{{ route('warehouse.handovers.payments.approve', $handover) }}">
                            @csrf

                            <style>
                                .table-approval {
                                    table-layout: fixed !important;
                                    width: 100% !important;
                                }

                                .table-approval th,
                                .table-approval td {
                                    font-size: 0.78rem !important;
                                    padding: 0.4rem 0.2rem !important;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                }

                                .table-approval input.form-control-sm {
                                    font-size: 0.75rem !important;
                                    padding: 0.2rem 0.4rem !important;
                                    min-height: 28px !important;
                                }

                                .table-approval .form-check-label {
                                    font-size: 0.75rem !important;
                                }
                            </style>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle table-approval">
                                    <thead>
                                        <tr>
                                            <th style="width:13%">Product</th>
                                            <th class="text-end" style="width:4%">Iss.</th>
                                            <th class="text-end" style="width:4%">Ret.</th>
                                            <th class="text-end" style="width:4.5%">Sold</th>
                                            <th class="text-end" style="width:8%">Price</th>
                                            <th class="text-end" style="width:8.5%">Net Price</th>
                                            <th class="text-end" style="width:8.5%">Sold Val.</th>
                                            <th class="text-end" style="width:5.5%">Pay.Qty</th>
                                            <th class="text-center" style="width:5.5%">Method</th>
                                            <th class="text-end" style="width:9%">Amount</th>
                                            <th style="width:5.5%">Proof</th>
                                            <th style="width:8%">Decision</th>
                                            <th style="width:16.5%">Rejection Reason</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @forelse($handover->items as $item)
                                            @php
                                                $status = $item->payment_status ?? 'pending';
                                                $statusBadge = match ($status) {
                                                    'approved' => 'bg-label-success',
                                                    'rejected' => 'bg-label-danger',
                                                    default => 'bg-label-warning',
                                                };
                                                $lockItem = !$canEdit || in_array($status, ['approved', 'rejected']);
                                                $cashQty = (int) ($item->payment_cash_qty ?? 0);
                                                $transferQty = (int) ($item->payment_transfer_qty ?? 0);
                                                $cashAmount = (int) ($item->payment_cash_amount ?? 0);
                                                $transferAmount = (int) ($item->payment_transfer_amount ?? 0);
                                                $proofPaths = $item->payment_transfer_proof_meta ?? [];

                                                if ($cashQty === 0 && $transferQty === 0 && (int) $item->payment_qty > 0) {
                                                    if ($item->payment_method === 'cash') {
                                                        $cashQty = (int) $item->payment_qty;
                                                        $cashAmount = (int) $item->payment_amount;
                                                    } elseif ($item->payment_method === 'transfer') {
                                                        $transferQty = (int) $item->payment_qty;
                                                        $transferAmount = (int) $item->payment_amount;
                                                    }
                                                }

                                                $paymentMethodLabel = match (true) {
                                                    $cashQty > 0 && $transferQty > 0 => 'MIXED',
                                                    $transferQty > 0 => 'TRANSFER',
                                                    $cashQty > 0 => 'CASH',
                                                    default => '-',
                                                };
                                            @endphp

                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">
                                                        {{ $item->product->name ?? ($item->product->product_name ?? '-') }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        {{ $item->product->product_code ?? '' }}
                                                    </div>
                                                </td>

                                                <td class="text-end">
                                                    <div class="cell-main">{{ (int) $item->qty_start }}</div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="cell-main">{{ (int) $item->qty_returned }}</div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="cell-main">{{ (int) $item->qty_sold }}</div>
                                                </td>

                                                {{-- Harga Satuan (Orig) --}}
                                                <td class="text-end">
                                                    <div class="cell-main">
                                                        {{ 'Rp ' . number_format((int) $item->unit_price, 0, ',', '.') }}</div>
                                                </td>

                                                {{-- Net Price (After Discount) --}}
                                                <td class="text-end fw-semibold text-success">
                                                    <div class="cell-main cell-good">
                                                        {{ 'Rp ' . number_format((int) ($item->unit_price_after_discount ?: $item->unit_price), 0, ',', '.') }}
                                                    </div>
                                                    @if ($item->discount_per_unit > 0)
                                                        <div class="cell-sub text-danger">
                                                            - {{ number_format((int) $item->discount_per_unit, 0, ',', '.') }}
                                                        </div>
                                                    @endif
                                                </td>

                                                {{-- Sold Value (Total Line) --}}
                                                <td class="text-end">
                                                    <div class="cell-main">
                                                        {{ 'Rp ' . number_format((int) ($item->line_total_after_discount ?: $item->line_total_sold), 0, ',', '.') }}
                                                    </div>
                                                    @php
                                                        $diffVal =
                                                            (int) ($item->line_total_sold -
                                                                $item->line_total_after_discount);
                                                    @endphp
                                                    @if ($diffVal != 0)
                                                        <div class="cell-sub">
                                                            Diff:
                                                            {{ ($diffVal > 0 ? '+' : '') . number_format($diffVal, 0, ',', '.') }}
                                                        </div>
                                                    @endif
                                                </td>

                                                {{-- Payment Qty yang diisi sales --}}
                                                <td class="text-end">
                                                    <div class="cell-main">{{ (int) $item->payment_qty }}</div>
                                                    @if ($cashQty > 0 || $transferQty > 0)
                                                        <div class="cell-sub">
                                                            Cash: {{ $cashQty }}<br>
                                                            Transfer: {{ $transferQty }}
                                                        </div>
                                                    @endif
                                                </td>

                                                <td class="text-center">
                                                    {{ $paymentMethodLabel }}
                                                </td>

                                                <td class="text-end">
                                                    <div class="cell-main">
                                                        {{ 'Rp ' . number_format((int) $item->payment_amount, 0, ',', '.') }}
                                                    </div>
                                                    @if ($cashAmount > 0 || $transferAmount > 0)
                                                        <div class="cell-sub">
                                                            Cash: Rp {{ number_format($cashAmount, 0, ',', '.') }}<br>
                                                            Transfer: Rp {{ number_format($transferAmount, 0, ',', '.') }}
                                                        </div>
                                                    @endif
                                                </td>

                                                <td>
                                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#detailModal-{{ $item->id }}">
                                                        Detail
                                                    </button>
                                                </td>

                                                <td>
                                                    <span class="badge {{ $statusBadge }} mb-1 d-inline-block">
                                                        {{ strtoupper($status) }}
                                                    </span>

                                                    @if ($canEdit)
                                                        <div class="d-flex flex-column mt-1">
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio"
                                                                    name="decisions[{{ $item->id }}][status]"
                                                                    id="approve-{{ $item->id }}" value="approved"
                                                                    @checked($status === 'approved')
                                                                    @if ($lockItem) disabled @endif>
                                                                <label class="form-check-label"
                                                                    for="approve-{{ $item->id }}">
                                                                    Approve
                                                                </label>
                                                            </div>

                                                            <div class="form-check form-check-inline mt-1">
                                                                <input class="form-check-input" type="radio"
                                                                    name="decisions[{{ $item->id }}][status]"
                                                                    id="reject-{{ $item->id }}" value="rejected"
                                                                    @checked($status === 'rejected')
                                                                    @if ($lockItem) disabled @endif>
                                                                <label class="form-check-label"
                                                                    for="reject-{{ $item->id }}">
                                                                    Reject
                                                                </label>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>

                                                <td>
                                                    @if ($status === 'rejected' && $item->payment_reject_reason)
                                                        <div class="small text-danger mb-1">
                                                            {{ $item->payment_reject_reason }}
                                                        </div>
                                                    @endif

                                                    @if ($canEdit && !$lockItem)
                                                        <input type="text" class="form-control form-control-sm"
                                                            name="decisions[{{ $item->id }}][reason]"
                                                            value="{{ old("decisions.$item->id.reason", $item->payment_reject_reason) }}"
                                                            placeholder="Reason if rejected">
                                                    @endif
                                                </td>
                                            </tr>

                                            <div class="modal fade" id="detailModal-{{ $item->id }}" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Payment Detail -
                                                                {{ $item->product->name ?? ($item->product->product_name ?? '-') }}
                                                            </h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row g-3 mb-3">
                                                                <div class="col-md-6">
                                                                    <div class="small text-muted fw-semibold">Qty Breakdown
                                                                    </div>
                                                                    <div class="cell-main">Total:
                                                                        {{ (int) $item->payment_qty }}</div>
                                                                    <div class="cell-sub">Cash: {{ $cashQty }}</div>
                                                                    <div class="cell-sub">Transfer: {{ $transferQty }}</div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="small text-muted fw-semibold">Amount Breakdown
                                                                    </div>
                                                                    <div class="cell-main">Total: Rp
                                                                        {{ number_format((int) $item->payment_amount, 0, ',', '.') }}
                                                                    </div>
                                                                    <div class="cell-sub">Cash: Rp
                                                                        {{ number_format($cashAmount, 0, ',', '.') }}</div>
                                                                    <div class="cell-sub">Transfer: Rp
                                                                        {{ number_format($transferAmount, 0, ',', '.') }}</div>
                                                                </div>
                                                            </div>

                                                            <div class="small text-muted fw-semibold mb-2">Transfer Proofs
                                                            </div>
                                                            @if (count($proofPaths))
                                                                <div class="row g-3">
                                                                    @foreach ($proofPaths as $proofIndex => $proofMeta)
                                                                        @php
                                                                            $proofPath = $proofMeta['path'] ?? null;
                                                                            $proofRoute = route(
                                                                                'warehouse.handover-items.payment-proof',
                                                                                [
                                                                                    'item' => $item->id,
                                                                                    'index' => $proofIndex,
                                                                                ],
                                                                            );
                                                                            $proofExt = strtolower(
                                                                                pathinfo($proofPath, PATHINFO_EXTENSION),
                                                                            );
                                                                            $proofType =
                                                                                $proofExt === 'pdf' ? 'pdf' : 'image';
                                                                            $proofQty = (int) ($proofMeta['qty'] ?? 0);
                                                                            $proofAmount =
                                                                                (int) ($proofMeta['amount'] ?? 0);
                                                                            $proofSavedAt = $proofMeta['saved_at'] ?? null;
                                                                            $proofLabel =
                                                                                $proofMeta['label'] ??
                                                                                'Transfer ' . ($proofIndex + 1);
                                                                        @endphp
                                                                        <div class="col-md-4 col-6">
                                                                            <div
                                                                                class="border rounded-3 p-2 h-100 text-center">
                                                                                <div class="small fw-semibold mb-2">
                                                                                    {{ $proofLabel }}</div>
                                                                                @if ($proofType === 'pdf')
                                                                                    <div class="border rounded-3 d-flex align-items-center justify-content-center mb-2"
                                                                                        style="height:96px;">
                                                                                        <span
                                                                                            class="fw-semibold text-muted">PDF</span>
                                                                                    </div>
                                                                                @else
                                                                                    <img src="{{ $proofRoute }}?v={{ $item->updated_at?->timestamp ?? time() }}"
                                                                                        alt="Proof {{ $proofIndex + 1 }}"
                                                                                        class="detail-proof-thumb mb-2">
                                                                                @endif
                                                                                <div class="small text-muted">Qty TF:
                                                                                    {{ $proofQty }}</div>
                                                                                <div class="small text-muted">Nominal: Rp
                                                                                    {{ number_format($proofAmount, 0, ',', '.') }}
                                                                                </div>
                                                                                @if ($proofSavedAt)
                                                                                    <div class="small text-muted mb-2">
                                                                                        {{ $proofSavedAt }}</div>
                                                                                @else
                                                                                    <div class="small text-muted mb-2">
                                                                                        {{ strtoupper($proofExt) }}</div>
                                                                                @endif
                                                                                <a href="{{ $proofRoute }}" target="_blank"
                                                                                    class="btn btn-outline-primary btn-sm w-100">
                                                                                    Open
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <div class="text-muted small">No transfer proof uploaded.</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        @empty
                                            <tr>
                                                <td colspan="12" class="text-center text-muted">
                                                    No items in this handover.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3 text-end">
                                <a href="{{ url()->current() }}" class="btn btn-outline-secondary">
                                    Back to list
                                </a>

                                @if ($canEdit && $handover->items->count())
                                    <button type="submit" class="btn btn-primary">
                                        Save Approval
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            @else
                {{-- BELUM PILIH HANDOVER --}}
                <div class="card mb-4">
                    <div class="card-body text-center text-muted">
                        Select a handover above to perform payment approval.
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                html: {!! json_encode(session('success')) !!},
                confirmButtonText: 'OK',
                allowOutsideClick: true,
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                html: {!! json_encode(session('error')) !!},
                confirmButtonText: 'OK',
            });
        </script>
    @endif

    <script>
        // 🔥 Fungsi Global buat Real-time (DI LUAR READY BIAR CEPET SIAP)
        window.refreshEveningList = async function() {
            try {
                const res = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Segerin SELURUH area Reconciliation (List + Header + Summary + Table)
                const newContent = doc.querySelector('#eveningReconciliationContainer');
                if (newContent) {
                    document.querySelector('#eveningReconciliationContainer').innerHTML = newContent.innerHTML;
                }
            } catch (err) { console.error('Failed to refresh evening list:', err); }
        };

        $(document).ready(function() {
            // 🔥 Event Delegation buat AJAX Submission Approval (BIAR AWET BIARPUN DI-REFRESH)
            document.addEventListener('submit', async function(e) {
                const form = e.target;
                if (form.id !== 'approvalForm') return; // Cuma tangkap form approval

                e.preventDefault(); // Stop reload
                
                // 🔥 Konfirmasi Sesuai Desain Premium Lo Cok (Fixing Button Style)
                const resultConfirm = await Swal.fire({
                    title: 'Process payment decisions?',
                    text: 'Approved items will be finalized to inventory. Rejected items will be sent back to sales.',
                    icon: 'warning',
                    iconColor: '#ffab00',
                    showCancelButton: true,
                    confirmButtonColor: '#696cff',
                    cancelButtonColor: '#8592a3',
                    confirmButtonText: 'Yes, Process Now',
                    cancelButtonText: 'Review Again'
                });

                if (!resultConfirm.isConfirmed) {
                    // Balikin tombol kalau batal
                    if (window.resetSubmitButton) window.resetSubmitButton(form);
                    return;
                }
                
                // Double click protection
                if (form.dataset.submittingLocal === 'true') return;
                form.dataset.submittingLocal = 'true';
                
                const formData = new FormData(form);
                
                Swal.fire({
                    title: 'Processing Decision...',
                    text: 'Please wait, updating inventory & payments.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const result = await response.json();

                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Status Updated!',
                            text: result.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        // SEGERIN TABEL
                        if (window.refreshEveningList) await window.refreshEveningList();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Oops...', text: result.message || 'Error occurred.' });
                    }
                } catch (err) {
                    console.error('AJAX Error:', err);
                    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Check your connection.' });
                } finally {
                    form.dataset.submittingLocal = 'false';
                    // 🔥 BALIKIN TOMBOL KE ASAL (MATIIN SPINNER)
                    if (window.resetSubmitButton) window.resetSubmitButton(form);
                }
            });

            // GLOBAL SEARCH FILTER
            $('#globalSearch').on('keyup', function() {
                const val = $(this).val().toLowerCase();
                // Target the items table rows
                $('.table-approval tbody tr').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(val) > -1);
                });
            });
        });
    </script>
@endpush
