@extends('layouts.home')

@push('styles')
    <style>
        /* ===============================
       TABLE COMPACT + ALIGN FIX
    ================================ */

        #itemsTable {
            font-size: 12px;
            /* kecilin global table */
        }

        #itemsTable thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-weight: 600;
            padding-top: .6rem;
            padding-bottom: .6rem;
            text-align: center;
            /* header rata tengah */
            white-space: nowrap;
        }

        #itemsTable tbody td {
            font-size: 12px;
            padding-top: .45rem;
            padding-bottom: .45rem;
            vertical-align: middle;
        }

        /* Kolom angka rata kanan */
        #itemsTable td.text-end,
        #itemsTable th.text-end {
            text-align: right !important;
        }

        /* Kolom tengah */
        #itemsTable th.text-center,
        #itemsTable td.text-center {
            text-align: center !important;
        }

        /* Product column lebih rapat */
        #itemsTable .product-name {
            font-size: 12px;
            font-weight: 600;
        }

        #itemsTable .product-code {
            font-size: 10px;
        }

        /* Input lebih kecil */
        #itemsTable input.form-control-sm,
        #itemsTable select.form-select-sm {
            font-size: 11px;
            padding: .25rem .4rem;
            height: 28px;
        }

        /* Badge lebih kecil */
        #itemsTable .badge {
            font-size: 10px;
            padding: 3px 6px;
        }

        /* Biar table lebih rapi */
        #itemsTable td,
        #itemsTable th {
            white-space: nowrap;
        }

        /* ===============================
       MOBILE OPTIMIZATION UPGRADE
    ================================ */

        /* Card info lebih rapi di mobile */
        @media (max-width: 768px) {
            .card-body .row>div {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* ====== MOBILE STACKED TABLE ====== */
        @media (max-width: 768px) {

            #itemsTable {
                border-collapse: separate;
                border-spacing: 0 0.75rem;
            }

            #itemsTable thead {
                display: none;
            }

            #itemsTable tbody tr {
                display: block;
                background: #ffffff;
                border-radius: 14px;
                padding: .75rem .9rem;
                box-shadow: 0 3px 12px rgba(15, 23, 42, 0.06);
                margin-bottom: 1rem;
                transition: 0.2s ease;
            }

            #itemsTable tbody tr:last-child {
                margin-bottom: 0;
            }

            #itemsTable td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: 0 !important;
                padding: .25rem 0;
                font-size: 12px;
            }

            /* Produk tampil paling atas */
            #itemsTable td[data-label="Produk"] {
                display: block;
                padding-bottom: .5rem;
                border-bottom: 1px dashed #eee;
                margin-bottom: .5rem;
            }

            #itemsTable td[data-label="Produk"]::before {
                content: '';
                margin: 0;
            }

            #itemsTable td[data-label="Produk"] .product-name {
                font-weight: 600;
                font-size: 13px;
            }

            #itemsTable td[data-label="Produk"] .product-code {
                font-size: 11px;
            }

            #itemsTable td::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #6c757d;
                margin-right: .75rem;
            }

            #itemsTable input.form-control-sm,
            #itemsTable select.form-select-sm {
                font-size: 12px;
                padding: .2rem .4rem;
                max-width: 140px;
            }

            #itemsTable input[type="file"] {
                font-size: 11px;
            }

            #itemsTable .badge {
                font-size: 10px;
            }

            #itemsTable .form-text {
                font-size: 10px;
            }

            /* Button submit full width */
            .text-end .btn-primary {
                width: 100%;
            }

            /* Header title lebih compact */
            .card-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: .5rem;
            }

            .card-header small {
                text-align: left !important;
            }
        }
    </style>
@endpush

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="container-xxl flex-grow-1 container-p-y">

        <div class="d-flex align-items-center mb-3">
            <h4 class="mb-0 fw-bold">Items Dispatched Today</h4>
        </div>

        @php
            $statusLabelMap = [
                'draft' => 'Draft',
                'waiting_morning_otp' => 'Waiting for Morning OTP',
                'on_sales' => 'On Sales',
                'waiting_evening_otp' => 'Waiting for Evening OTP',
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

            $paymentBadgeMap = [
                'draft' => 'bg-label-secondary',
                'pending' => 'bg-label-warning',
                'approved' => 'bg-label-success',
                'rejected' => 'bg-label-danger',
            ];

            $statusKey = $handover?->status;
            $statusLabel = $statusLabelMap[$statusKey] ?? ($statusKey ?? '-');
            $badgeClass = $badgeClassMap[$statusKey] ?? $badgeClassMap['default'];
        @endphp

        {{-- INFO HANDOVER / NO DATA --}}
        <div class="card mb-3">
            <div class="card-body">
                @if (!$handover)
                    <div class="text-center text-muted">
                        <div class="fw-semibold mb-1">No active handover for today.</div>
                        <div>Please contact the warehouse admin if items should have been assigned.</div>
                    </div>
                @else
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
                            <div class="small text-muted fw-semibold">Morning OTP Verified</div>
                            <div id="otpStatusText">
                                @if ($isOtpVerified)
                                    ✅ <span class="text-success">Verified in this menu</span>
                                @else
                                    ❌ <span class="text-muted">Not yet verified</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            @if ($handover->status === 'on_sales')
                                <button type="button" class="btn btn-sm btn-warning" id="btnInputOtp">
                                    Enter OTP
                                </button>
                            @endif
                        </div>

                    </div>
                @endif
            </div>
        </div>

        {{-- TABEL ITEM + PAYMENT --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Today's Dispatched Items</h5>
                <small class="text-muted text-end">
                    Fill in payment details per item after sales.
                    Once submitted (status <strong>PENDING</strong>), items will be locked
                    until <strong>REJECTED</strong> by warehouse admin.
                </small>
            </div>
            <div class="card-body">
                @if (!$handover)
                    <div class="text-center text-muted">
                        No active handover today.
                    </div>
                @else
                    @if (!$isOtpVerified)
                        <div class="text-center text-muted">
                            Please enter the morning OTP first to view the items list.
                        </div>
                    @else
                        <form id="paymentForm" method="POST" action="{{ route('sales.otp.items.payments.save') }}"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="handover_id" value="{{ $handover->id }}">

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th style="width:20%">Product</th>
                                            <th class="text-end" style="width:7%">Dispatched</th>
                                            <th class="text-end" style="width:7%">Returned</th>
                                            <th class="text-end" style="width:7%">Returned</th>
                                            <th class="text-end" style="width:10%">Unit Price</th>
                                            <th class="text-end" style="width:10%">Dispatched Value</th>
                                            <th class="text-end" style="width:10%">Sales Value</th>

                                            <th class="text-end" style="width:7%">Payment Qty</th>
                                            <th class="text-center" style="width:10%">Payment Method</th>
                                            <th class="text-end" style="width:10%">Amount</th>
                                            <th style="width:15%">Transfer Proof</th>
                                            <th style="width:12%">Payment Status</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($items as $row)
                                            @php
                                                $unitPrice = (int) $row->unit_price;

                                                // ✅ HARGA FINAL (SETELAH DISKON)
                                                $effectiveUnitPrice =
                                                    $row->discount_per_unit > 0
                                                        ? $unitPrice - (int) $row->discount_per_unit
                                                        : $unitPrice;

                                                // qty logic lu tetep
                                                $maxQty = (int) $row->qty_sold;
                                                if ($maxQty <= 0) {
                                                    $maxQty = (int) ($row->payment_qty ?? 0);
                                                }
                                                if ($maxQty <= 0) {
                                                    $maxQty = (int) $row->qty_start;
                                                }

                                                // ⚠️ PAKE HARGA FINAL
                                                $maxNominal = $effectiveUnitPrice * $maxQty;

                                                $productName = $row->product?->name ?? 'Product #' . $row->product_id;
                                                $productCode = $row->product?->product_code ?? '';

                                                $lineStart =
                                                    (int) ($row->line_total_start ?? $row->qty_start * $unitPrice);
                                                $lineSold =
                                                    (int) ($row->line_total_sold ?? $row->qty_sold * $unitPrice);

                                                $payStatusKey = $row->payment_status ?: 'draft';
                                                $payBadge =
                                                    $paymentBadgeMap[$payStatusKey] ?? $paymentBadgeMap['draft'];

                                                // ✅ LOCK: pending/approved terkunci, rejected/draft boleh edit
                                                $isLocked =
                                                    !$canEditPayment ||
                                                    in_array($payStatusKey, ['pending', 'approved'], true);
                                            @endphp

                                            <tr class="js-payment-row" data-unit-price="{{ $effectiveUnitPrice }}"
                                                data-max-qty="{{ $maxQty }}"
                                                data-max-amount="{{ $effectiveUnitPrice * $maxQty }}">


                                                {{-- PRODUK --}}
                                                <td data-label="Product">
                                                    <div class="product-name">{{ $productName }}</div>
                                                    @if ($productCode)
                                                        <div class="product-code text-muted">{{ $productCode }}</div>
                                                    @endif
                                                </td>

                                                {{-- DIBAWA --}}
                                                <td class="text-end" data-label="Dispatched">{{ (int) $row->qty_start }}
                                                </td>

                                                {{-- KEMBALI --}}
                                                <td class="text-end" data-label="Returned">{{ (int) $row->qty_returned }}
                                                </td>

                                                {{-- TERJUAL --}}
                                                <td class="text-end" data-label="Sold">{{ (int) $row->qty_sold }}</td>

                                                {{-- HARGA --}}
                                                <td class="text-end" data-label="Unit Price">
                                                    {{ number_format($unitPrice, 0, ',', '.') }}</td>

                                                {{-- NILAI DIBAWA --}}
                                                <td class="text-end" data-label="Dispatched Value">
                                                    {{ number_format($lineStart, 0, ',', '.') }}</td>

                                                <td class="text-end" data-label="Sales Value">

                                                    {{-- nilai normal --}}
                                                    <div>
                                                        Rp {{ number_format($row->line_total_sold, 0, ',', '.') }}
                                                    </div>

                                                    @if ($row->discount_per_unit > 0)
                                                        {{-- diskon --}}
                                                        <div class="small text-muted">
                                                            Discount:
                                                            Rp {{ number_format($row->discount_per_unit, 0, ',', '.') }}
                                                        </div>

                                                        {{-- nilai setelah diskon --}}
                                                        <div class="small text-success">
                                                            Discounted Price: Rp
                                                            {{ number_format($row->unit_price_after_discount, 0, ',', '.') }}

                                                        </div>
                                                    @endif

                                                </td>



                                                {{-- QTY BAYAR --}}
                                                <td data-label="Payment Qty">
                                                    <input type="number" class="form-control form-control-sm js-qty-bayar"
                                                        name="items[{{ $row->id }}][payment_qty]" min="0"
                                                        max="{{ $maxQty }}"
                                                        value="{{ old("items.$row->id.payment_qty", $row->payment_qty) }}"
                                                        @disabled($isLocked)>
                                                </td>

                                                {{-- METODE --}}
                                                <td data-label="Payment Method">
                                                    @php
                                                        $oldMethod = old("items.$row->id.payment_method");
                                                        $valMethod =
                                                            $oldMethod !== null ? $oldMethod : $row->payment_method;
                                                    @endphp
                                                    <select name="items[{{ $row->id }}][payment_method]"
                                                        class="form-select form-select-sm" @disabled($isLocked)>
                                                        <option value="">- Pilih -</option>
                                                        <option value="cash" @selected($valMethod === 'cash')>Cash</option>
                                                        <option value="transfer" @selected($valMethod === 'transfer')>Transfer
                                                        </option>
                                                    </select>
                                                </td>

                                                {{-- NOMINAL --}}
                                                <td data-label="Amount">
                                                    <input type="number" class="form-control form-control-sm js-nominal"
                                                        name="items[{{ $row->id }}][payment_amount]" min="0"
                                                        max="{{ $maxNominal }}"
                                                        value="{{ old("items.$row->id.payment_amount", $row->payment_amount) }}"
                                                        @disabled($isLocked)>
                                                </td>

                                                {{-- BUKTI TF --}}
                                                <td data-label="Transfer Proof">
                                                    @if ($row->payment_transfer_proof_path)
                                                        <div class="mb-1">
                                                            <a href="{{ asset('storage/' . $row->payment_transfer_proof_path) }}"
                                                                target="_blank">
                                                                View Proof
                                                            </a>
                                                        </div>
                                                    @endif
                                                    <input type="file" class="form-control form-control-sm"
                                                        name="items[{{ $row->id }}][payment_proof]"
                                                        @disabled($isLocked)>
                                                </td>

                                                {{-- STATUS PAYMENT --}}
                                                <td data-label="Status Payment">
                                                    <span class="badge {{ $payBadge }}">
                                                        {{ strtoupper($payStatusKey) }}
                                                    </span>
                                                    @if ($row->payment_status === 'rejected' && $row->payment_reject_reason)
                                                        <div class="small text-danger mt-1">
                                                            {{ $row->payment_reject_reason }}
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table> {{-- ✅ ini yang tadi ketutup --}}
                            </div>

                            @if ($canEditPayment && $items->count())
                                <div class="mt-3 text-end">
                                    <button type="submit" class="btn btn-primary">
                                        Save Payment
                                    </button>
                                </div>
                            @endif
                        </form>
                    @endif
                @endif
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hasHandover = @json((bool) $handover);
            const isOtpVerified = @json($isOtpVerified);
            const handoverId = @json($handover->id ?? null);
            const verifyUrl = @json(route('sales.otp.items.verify'));
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const token = tokenMeta ? tokenMeta.getAttribute('content') : '';
            const btnOtp = document.getElementById('btnInputOtp');
            const handoverStatus = @json($handover->status ?? null);

            if (hasHandover && !isOtpVerified) {
                setTimeout(() => {
                    showOtpModal();
                }, 300);
            }


            async function submitOtp(code) {
                const res = await fetch(verifyUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        otp_code: code,

                    })
                });

                let json;
                try {
                    json = await res.json();
                } catch (e) {
                    throw new Error('Server returned an invalid response.');
                }

                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'OTP verification failed.');
                }
                return json;
            }

            async function showOtpModal() {
                const {
                    value: otpCode,
                    isConfirmed
                } = await Swal.fire({
                    title: 'Enter Morning OTP Code',
                    text: 'The OTP code has been sent to your email. Please enter the code provided by the warehouse admin.',
                    input: 'text',
                    inputLabel: 'OTP Code',
                    inputPlaceholder: 'Contoh: 123456',
                    inputAttributes: {
                        maxlength: 10,
                        autocapitalize: 'off',
                        autocorrect: 'off'
                    },
                    allowOutsideClick: false,
                    showCancelButton: false,
                    confirmButtonText: 'Verify',
                    showLoaderOnConfirm: true,
                    preConfirm: async (value) => {
                        const code = (value || '').trim();
                        if (!code) {
                            Swal.showValidationMessage('OTP code cannot be empty.')
                            return false;
                        }
                        try {
                            await submitOtp(code);
                        } catch (err) {
                            Swal.showValidationMessage(err.message);
                            return false;
                        }
                    }
                });

                if (isConfirmed && otpCode) {
                    sessionStorage.removeItem('otp-popup-shown-' + handoverId);
                    await Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'OTP verified successfully. The page will reload.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    window.location.reload();
                }
            }


            if (btnOtp) {
                btnOtp.addEventListener('click', function() {
                    showOtpModal();
                });
            }

            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    html: {!! json_encode(session('success')) !!}
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: {!! json_encode(session('error')) !!}
                });
            @endif

            @if ($errors->any())
                const errorList = {!! json_encode($errors->all()) !!};
                let errorHtml = '<ul style="text-align:left;margin:0;padding-left:20px;">';
                errorList.forEach(function(msg) {
                    errorHtml += '<li>' + msg + '</li>';
                });
                errorHtml += '</ul>';

                Swal.fire({
                    icon: 'error',
                    title: 'Validation Failed',
                    html: errorHtml
                });
            @endif
        });

        (function() {
            const form = document.getElementById('paymentForm');
            if (!form) return;

            const rows = document.querySelectorAll('.js-payment-row');

            function clampNumber(value, min, max) {
                let v = parseInt(value || 0, 10);
                if (isNaN(v)) v = 0;
                if (v < min) v = min;
                if (v > max) v = max;
                return v;
            }

            rows.forEach(row => {
                const unitPrice = parseInt(row.dataset.unitPrice || '0', 10);
                const maxQty = parseInt(row.dataset.maxQty || '0', 10);
                const maxAmount = parseInt(row.dataset.maxAmount || '0', 10);
                const qtyInput = row.querySelector('.js-qty-bayar');
                const nominalInput = row.querySelector('.js-nominal');

                if (!qtyInput || !nominalInput) return;

                qtyInput.addEventListener('input', function() {
                    let qty = clampNumber(this.value, 0, maxQty);
                    this.value = qty;

                    let nominal = qty * unitPrice;
                    if (nominal > maxAmount) nominal = maxAmount;
                    if (nominal < 0) nominal = 0;

                    nominalInput.value = nominal;
                });

                nominalInput.addEventListener('input', function() {
                    let val = clampNumber(this.value, 0, maxAmount);
                    this.value = val;
                });
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Save sales data?',
                    text: 'Please ensure quantity and amount are correct. Once saved, the data will be locked for admin approval.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Save',
                    cancelButtonText: 'Review Again'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        })();
    </script>
@endpush
