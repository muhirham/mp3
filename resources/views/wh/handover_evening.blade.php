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
        }
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="fw-bold py-2 mb-3">
            <span class="text-muted fw-light">
                Warehouse /
            </span>
            Reconcile (Approval Payment)
        </h4>

        {{-- FLASH MESSAGE --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- ================== PILIH HANDOVER UNTUK APPROVAL ================== --}}
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

        {{-- ================== JIKA SUDAH PILIH HANDOVER: TAMPILKAN APPROVAL ================== --}}
        @if ($isApprovalMode)
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
                    <form method="POST" action="{{ route('warehouse.handovers.payments.approve', $handover) }}">
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

                                            <td class="text-end">{{ (int) $item->qty_start }}</td>
                                            <td class="text-end">{{ (int) $item->qty_returned }}</td>
                                            <td class="text-end">{{ (int) $item->qty_sold }}</td>

                                            {{-- Harga Satuan (Orig) --}}
                                            <td class="text-end">
                                                {{ 'Rp ' . number_format((int) $item->unit_price, 0, ',', '.') }}
                                            </td>

                                            {{-- Net Price (After Discount) --}}
                                            <td class="text-end fw-semibold text-success">
                                                {{ 'Rp ' . number_format((int) ($item->unit_price_after_discount ?: $item->unit_price), 0, ',', '.') }}
                                                @if ($item->discount_per_unit > 0)
                                                    <div class="text-danger small" style="font-size: 0.65rem;">
                                                        - {{ number_format((int) $item->discount_per_unit, 0, ',', '.') }}
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Sold Value (Total Line) --}}
                                            <td class="text-end">
                                                <div class="fw-bold">
                                                    {{ 'Rp ' . number_format((int) ($item->line_total_after_discount ?: $item->line_total_sold), 0, ',', '.') }}
                                                </div>
                                                @php
                                                    $diffVal =
                                                        (int) ($item->line_total_sold -
                                                            $item->line_total_after_discount);
                                                @endphp
                                                @if ($diffVal != 0)
                                                    <div class="text-muted" style="font-size: 0.65rem;">
                                                        Diff:
                                                        {{ ($diffVal > 0 ? '+' : '') . number_format($diffVal, 0, ',', '.') }}
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Payment Qty yang diisi sales --}}
                                            <td class="text-end fw-bold">{{ (int) $item->payment_qty }}</td>

                                            <td class="text-center">
                                                {{ $item->payment_method ? strtoupper($item->payment_method) : '-' }}
                                            </td>

                                            <td class="text-end">
                                                {{ 'Rp ' . number_format((int) $item->payment_amount, 0, ',', '.') }}
                                            </td>

                                            <td>
                                                @if ($item->payment_transfer_proof_path)
                                                    <a href="#" class="text-primary" data-bs-toggle="modal"
                                                        data-bs-target="#tfModal"
                                                        data-img="{{ asset('storage/' . $item->payment_transfer_proof_path) }}">
                                                        View Proof
                                                    </a>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
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

                                    @empty
                                        <tr>
                                            <td colspan="12" class="text-center text-muted">
                                                No items in this handover.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="modal fade" id="tfModal" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Transfer Proof</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img id="tfImage" src="" class="img-fluid"
                                                style="max-height:80vh; zoom:1; touch-action: pinch-zoom;">
                                        </div>
                                    </div>
                                </div>
                            </div>
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
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-bs-target="#tfModal"]').forEach(el => {
                el.addEventListener('click', function() {
                    document.getElementById('tfImage').src = this.dataset.img;
                });
            });
        });
    </script>



    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                html: {!! json_encode(session('success')) !!},
                allowOutsideClick: true
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                html: {!! json_encode(session('error')) !!}
            });
        </script>
    @endif
@endpush
