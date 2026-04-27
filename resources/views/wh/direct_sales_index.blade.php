@extends('layouts.home')

@section('title', 'Direct Warehouse Sales ')

@push('styles')
    <style>
        .pos-page .title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #4a5f7d;
        }

        .pos-page .card {
            border-radius: 18px;
            border: none;
            box-shadow: 0 8px 24px rgba(149, 157, 165, 0.1);
        }

        .pos-page .buyer-toggle .btn-check:checked+.btn {
            background-color: #696cff;
            color: #fff;
            border-color: #696cff;
        }

        .pos-page .table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #738199;
            font-weight: 700;
            background: #f8fbff;
        }

        .pos-page .table td {
            vertical-align: middle;
        }

        .pos-page .summary-box {
            background: #f8fbff;
            border: 1px solid #e4ecff;
            border-radius: 15px;
            padding: 20px;
        }

        .pos-page .grand-total {
            font-size: 2rem;
            font-weight: 800;
            color: #696cff;
        }

        .pos-page .btn-remove {
            color: #ff3e1d;
            cursor: pointer;
            transition: 0.2s;
        }

        .pos-page .btn-remove:hover {
            transform: scale(1.2);
        }

        .pos-page .js-qty-input {
            min-width: 80px;
        }

        .pos-page .discount-group {
            min-width: 170px;
        }

        .fs-tiny {
            font-size: 0.65rem !important;
        }

        .fw-extrabold {
            font-weight: 800 !important;
        }

        .pos-page .icon-box {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 10px;
            height: 40px;
            line-height: 40px;
        }
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y pos-page">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-box bg-primary-subtle p-2 rounded-3 text-primary" style="width: 40px; height: 40px;">
                    <i class="bx bx-cart-alt fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Direct Sales</h5>
                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">Manual warehouse stock transaction</p>
                </div>
            </div>
            
            <div class="d-flex gap-4 align-items-center bg-white px-3 py-1 rounded-3 shadow-sm border">
                <div class="header-item text-end">
                    <label class="text-muted d-block fs-tiny text-uppercase fw-bold mb-0">Transaction Date</label>
                    <input type="date" name="handover_date" form="directSalesForm" 
                        class="form-control form-control-sm fw-bold text-primary border-0 bg-transparent p-0" 
                        value="{{ $selectedDate }}" style="text-align: right; width: 120px; cursor: pointer; font-size: 0.9rem;">
                </div>
                
                <div class="vr" style="height: 30px; opacity: 0.1;"></div>
                
                <div class="header-item text-end">
                    <label class="text-muted d-block fs-tiny text-uppercase fw-bold mb-0">Warehouse Source</label>
                    @if(auth()->user()->hasRole(['superadmin', 'admin']))
                        <div class="dropdown-wrapper position-relative">
                            <select id="whSwitcher" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary p-0" 
                                style="text-align: right; cursor: pointer; width: auto; min-width: 150px; appearance: none; -webkit-appearance: none; font-size: 0.9rem;">
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $warehouse->id == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                            <i class="bx bx-chevron-down position-absolute text-muted" style="right: -12px; top: 50%; transform: translateY(-50%); pointer-events: none; font-size: 0.8rem;"></i>
                        </div>
                    @else
                        <span class="fw-bold text-primary" style="font-size: 0.9rem;">{{ $warehouse->warehouse_name }}</span>
                    @endif
                </div>
            </div>
        </div>

        <form id="directSalesForm" action="{{ route('warehouse.direct_sales.store') }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            <!-- Hidden warehouse context -->
            <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">
            <div class="row g-4">
                <!-- LEFT: Identitas & Items -->
                <div class="col-lg-8">
                    <!-- Identitas Pembeli -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="bx bx-user me-2"></i>Buyer Information</h6>

                            <div class="buyer-toggle d-flex gap-2 mb-4">
                                <input type="radio" class="btn-check" name="buyer_type" id="typeSales" value="sales"
                                    checked>
                                <label class="btn btn-outline-primary px-4 rounded-pill" for="typeSales">Sales
                                    Internal</label>

                                <input type="radio" class="btn-check" name="buyer_type" id="typePareto" value="pareto">
                                <label class="btn btn-outline-primary px-4 rounded-pill" for="typePareto">Pareto
                                    (VIP)</label>

                                <input type="radio" class="btn-check" name="buyer_type" id="typeUmum" value="umum">
                                <label class="btn btn-outline-primary px-4 rounded-pill" for="typeUmum">Customer
                                    Umum</label>
                            </div>

                            <div id="inputGroupSales" class="buyer-input">
                                <label class="small fw-bold text-muted mb-1">Select Sales Account</label>
                                <select name="sales_id" class="form-select select2">
                                    <option value="{{ $internalSales?->id }}" selected>
                                        {{ $internalSales?->name ?? 'Select Sales...' }} (Auto-detected Internal)
                                    </option>
                                    @foreach ($allSales as $s)
                                        @if ($s->id !== $internalSales?->id)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <div class="mt-2 text-info small">
                                    <i class="bx bx-info-circle me-1"></i>Report will be recorded under this account.
                                </div>
                            </div>

                            <div id="inputGroupCustomer" class="buyer-input d-none">
                                <label class="small fw-bold text-muted mb-1">Customer / Pareto Name</label>
                                <input type="text" name="customer_name" class="form-control form-control-lg"
                                    placeholder="Enter customer name...">
                            </div>
                        </div>
                    </div>

                    <!-- Input Barang -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="bx bx-package me-2"></i>Items to Sell</h6>
                                <button type="button" class="btn btn-primary btn-sm rounded-pill" id="btnAddItem">
                                    <i class="bx bx-plus me-1"></i>Add Item
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">Product</th>
                                            <th style="width: 20%;">Qty</th>
                                            <th style="width: 30%;">Price (Net) / Discount</th>
                                            <th style="width: 15%;">Subtotal</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamic rows here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Payment & Summary -->
                <div class="col-lg-4">
                    <div class="card sticky-top" style="top: 100px;">
                        <div class="card-body">
                            <h6 class="fw-bold mb-4"><i class="bx bx-receipt me-2"></i>Payment Summary</h6>

                            <div class="summary-box mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Items</span>
                                    <span id="totalItemsCount" class="fw-bold">0</span>
                                </div>
                                <hr class="my-2" style="opacity: 0.1;">
                                <div class="text-muted small mb-1">Grand Total</div>
                                <div class="grand-total" id="displayGrandTotal">Rp 0</div>
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1">Cash Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">Rp</span>
                                    <input type="number" name="cash_amount"
                                        class="form-control border-start-0 ps-0 js-pay-input" placeholder="0">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1">Transfer Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">Rp</span>
                                    <input type="number" name="transfer_amount"
                                        class="form-control border-start-0 ps-0 js-pay-input" placeholder="0">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="small fw-bold text-muted mb-1">Transfer Proof (Optional)</label>
                                <input type="file" name="transfer_proof" class="form-control form-control-sm">
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill shadow-sm py-3"
                                id="btnSubmit">
                                <i class="bx bx-check-circle me-2"></i>Process Sale
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Template Row (Hidden) -->
    <template id="itemRowTemplate">
        <tr class="item-row">
            <td>
                <select name="items[INDEX][product_id]" class="form-select js-product-select" required>
                    <option value="">Select Product...</option>
                    @foreach ($products as $p)
                        @php
                            $stock = $p->stockLevels->first()?->quantity ?? 0;
                            // Ambil data diskon dari kategori (Flexible Discount)
                            $discounts = $p->category?->priceCategory?->discounts ?? [];
                        @endphp
                        <option value="{{ $p->id }}" data-price="{{ $p->selling_price }}"
                            data-name="{{ $p->name }}" data-stock="{{ $stock }}"
                            data-discounts='@json($discounts)'>
                            {{ $p->name }} (Stock: {{ number_format($stock, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="number" name="items[INDEX][qty]" class="form-control js-qty-input" value="1"
                    min="1" required>
            </td>
            <td>
                <div class="small fw-bold js-price-display mb-1">Rp 0</div>
                <div class="input-group input-group-sm discount-group">
                    <select name="items[INDEX][discount_mode]" class="form-select js-disc-mode" style="max-width: 80px;">
                        <option value="unit">Unit</option>
                        <option value="fixed">Bundle</option>
                    </select>
                    <input type="number" class="form-control js-disc-input" name="items[INDEX][discount_per_unit]"
                        min="0" value="0" placeholder="Value">
                </div>
            </td>
            <td>
                <div class="fw-bold text-primary js-subtotal-display">Rp 0</div>
            </td>
            <td>
                <i class="bx bx-trash btn-remove"></i>
            </td>
        </tr>
    </template>

@endsection

@push('scripts')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            let rowIndex = 0;
            const template = document.getElementById('itemRowTemplate').innerHTML;
            const $tbody = $('#itemsTable tbody');

            function formatRupiah(val) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
            }

            let currentGrandTotal = 0;

            function calculateTotal() {
                let grandTotal = 0;
                let totalItems = 0;

                $('.item-row').each(function(idx) {
                    const $row = $(this);
                    const $sel = $row.find('.js-product-select');
                    const price = parseInt($sel.find(':selected').data('price') || 0);
                    const qty = parseInt($row.find('.js-qty-input').val() || 0);

                    const discValue = parseInt($row.find('.js-disc-input').val() || 0);
                    const mode = $row.find('.js-disc-mode').val() || 'unit';

                    // Re-map name dynamically so the controller receives the correct key
                    const $discInput = $row.find('.js-disc-input');
                    if (mode === 'fixed') {
                        $discInput.attr('name', `items[${idx}][discount_fixed_amount]`);
                    } else {
                        $discInput.attr('name', `items[${idx}][discount_per_unit]`);
                    }

                    let subtotal = 0;
                    if (mode === 'fixed') {
                        subtotal = Math.max((qty * price) - discValue, 0);
                    } else {
                        const netPrice = Math.max(0, price - discValue);
                        subtotal = qty * netPrice;
                    }

                    $row.find('.js-price-display').text(formatRupiah(price));
                    $row.find('.js-subtotal-display').text(formatRupiah(subtotal));

                    grandTotal += subtotal;
                    totalItems += qty;
                });

                currentGrandTotal = grandTotal;
                $('#displayGrandTotal').text(formatRupiah(grandTotal));
                $('#totalItemsCount').text(totalItems);
            }

            function addRow() {
                const content = template.replace(/INDEX/g, rowIndex++);
                $tbody.append(content);

                const $newRow = $tbody.find('.item-row').last();
                $newRow.find('.js-product-select').select2({
                    theme: 'bootstrap-5'
                });

                calculateTotal();
            }

            $('#btnAddItem').click(addRow);

            $(document).on('change', '.js-product-select', function() {
                calculateTotal();
            });

            $(document).on('change keyup', '.js-qty-input, .js-disc-input, .js-disc-mode', calculateTotal);

            $(document).on('click', '.btn-remove', function() {
                $(this).closest('tr').remove();
                calculateTotal();
            });

            // 🔥 VALIDASI: Pembayaran tidak boleh lebih dari Total
            $(document).on('change keyup', '.js-pay-input', function() {
                let $cash = $('input[name="cash_amount"]');
                let $transfer = $('input[name="transfer_amount"]');

                let cash = parseInt($cash.val()) || 0;
                let transfer = parseInt($transfer.val()) || 0;

                if (cash + transfer > currentGrandTotal) {
                    if ($(this).attr('name') === 'cash_amount') {
                        $cash.val(Math.max(0, currentGrandTotal - transfer));
                    } else {
                        $transfer.val(Math.max(0, currentGrandTotal - cash));
                    }
                }
            });

            // Toggle Buyer Inputs
            $('input[name="buyer_type"]').change(function() {
                const val = $(this).val();
                if (val === 'sales') {
                    $('#inputGroupSales').removeClass('d-none');
                    $('#inputGroupCustomer').addClass('d-none');
                } else {
                    $('#inputGroupSales').addClass('d-none');
                    $('#inputGroupCustomer').removeClass('d-none');
                    const placeholder = val === 'pareto' ? 'Enter Pareto name...' :
                        'Enter customer name...';
                    $('input[name="customer_name"]').attr('placeholder', placeholder);
                }
            });

            // Handle Form Submit
            $('#directSalesForm').on('submit', async function(e) {
                e.preventDefault();
                const $form = $(this);
                const formData = new FormData(this);

                if ($('.item-row').length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Empty Cart',
                        text: 'Please add at least one item.'
                    });
                    return;
                }

                const confirm = await Swal.fire({
                    title: 'Confirm Sale',
                    text: 'Are you sure you want to process this direct sale?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Process It',
                    confirmButtonColor: '#696cff'
                });

                if (!confirm.isConfirmed) return;

                Swal.fire({
                    title: 'Processing...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                try {
                    const response = await fetch($form.attr('action'), {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const result = await response.json();

                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sale Completed',
                            text: result.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: result.message
                        });
                    }
                } catch (err) {
                    console.error('Submit Error:', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to process sale.'
                    });
                }
            });

            // 🔥 WAREHOUSE SWITCHER (Khusus Admin/Superadmin)
            $('#whSwitcher').change(function() {
                const whId = $(this).val();
                const date = $('input[name="handover_date"]').val();
                window.location.href = `{{ route('warehouse.direct_sales.index') }}?warehouse_id=${whId}&handover_date=${date}`;
            });

            // Initial Row
            addRow();
        });
    </script>
@endpush
