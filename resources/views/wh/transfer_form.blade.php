    @extends('layouts.home')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @section('title', 'Warehouse Transfer')
    @push('styles')
        <style>
            .table-fixed {
                table-layout: fixed;
                width: 100%;
            }

            .table-fixed th,
            .table-fixed td {
                vertical-align: middle;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .col-product {
                width: 35%;
            }

            .col-stock {
                width: 10%;
                text-align: center;
            }

            .col-price {
                width: 15%;
                text-align: right;
            }

            .col-qty {
                width: 15%;
            }

            .col-total {
                width: 15%;
                text-align: right;
            }

            .col-action {
                width: 10%;
                text-align: center;
            }

            /* Select2 Custom Theme for Sneat */
            .select2-container--bootstrap-5 .select2-selection {
                border-color: #d9dee3 !important;
                border-radius: 0.375rem !important;
                height: calc(1.53em + 0.875rem + 2px) !important;
                display: flex !important;
                align-items: center !important;
            }
            .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
                color: #566a7f !important;
                padding-left: 0.875rem !important;
            }
        </style>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    @endpush


    @section('content')

        <div class="container-xxl container-p-y">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold py-3 mb-0">
                    <span class="text-muted fw-light">Warehouse /</span> Transfer Detail
                </h4>
                <a href="{{ route('warehouse-transfers.index') }}" class="btn btn-outline-secondary">
                    <i class="bx bx-chevron-left"></i> Back to List
                </a>
            </div>

            @if ($transfer->exists)
                <div class="card mb-3">
                    <div class="card-body row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Transfer Code</label>
                            <div class="fw-bold">{{ $transfer->transfer_code }}</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label><br>
                            <span id="statusBadge" class="badge bg-info text-uppercase">
                                {{ $transfer->status }}
                            </span>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Requested by</label>
                            <div>{{ $transfer->sourceWarehouse->warehouse_name }}</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Delivered by</label>
                            <div>{{ $transfer->destinationWarehouse->warehouse_name }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Created By</label>
                            <div>{{ $transfer->creator->name ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Note</label>
                            <div>{{ $transfer->note ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ================= ACTION (APPROVAL) ================= --}}
            <div id="transferActions" class="card mb-3">
                @include('wh.partials.transfer_actions')
            </div>

            <iframe id="iframePrintSJ" src="" style="display:none;"></iframe>

            {{-- ================= ITEMS (CREATE MODE) ================= --}}
            @if (!$transfer->exists)
                <form id="formTransfer" method="POST" action="{{ route('warehouse-transfer-forms.store') }}">
                    @csrf
                    <div class="card mb-3">
                        <div class="card-header"><strong>Create Transfer</strong></div>
                        <div class="card-body row g-3">
                            {{-- FROM --}}
                            <div class="col-md-4">
                                <label class="form-label">Requested by Warehouse</label>
                                <select name="from_warehouse_id" id="fromWarehouse" class="form-select">
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- TO --}}
                            <div class="col-md-4">
                                <label class="form-label">Delivered by Warehouse</label>
                                <select name="to_warehouse_id" class="form-select">
                                    @foreach ($toWarehouses as $w)
                                        <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- NOTE --}}
                            <div class="col-md-4">
                                <label class="form-label">Note</label>
                                <input type="text" name="note" class="form-control">
                            </div>
                        </div>
                    </div>
                    {{-- ITEMS --}}
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <strong>Items</strong>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddItem">
                                + Add Item
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 table-fixed" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th class="col-product">Product</th>
                                        <th class="col-stock">Stock</th>
                                        <th class="col-price">Price</th>
                                        <th class="col-qty">Qty</th>
                                        <th class="col-total">Subtotal</th>
                                        <th class="col-action"></th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Grand Total</th>
                                        <th class="text-end" id="grandTotal">0</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-primary">
                            <i class="bx bx-save"></i> Submit Transfer
                        </button>
                    </div>
                </form>
            @endif
            {{-- ================= ITEMS (VIEW MODE) ================= --}}
            @if ($transfer->exists)
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Transferred Items</strong>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 table-fixed">
                            <thead>
                                <tr>
                                    <th class="col-product">Product</th>
                                    <th class="col-qty text-end">Qty Plan</th>
                                    @if($transfer->status === 'completed')
                                        <th class="text-end" style="width:100px;">Good</th>
                                        <th class="text-end" style="width:100px;">Damaged</th>
                                    @endif
                                    <th class="col-price text-end">Unit Price</th>
                                    <th class="col-total text-end">Subtotal (Good)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalGoodCost = 0; @endphp
                                @forelse ($transfer->items as $item)
                                    @php
                                        // Jika sudah completed, subtotal dihitung dari qty_good.
                                        // Jika belum, pakai qty_transfer asumsikan semua good dulu.
                                        $qtyForSubtotal = ($transfer->status === 'completed') ? $item->qty_good : $item->qty_transfer;
                                        $subtotalGood = $qtyForSubtotal * $item->unit_cost;
                                        $totalGoodCost += $subtotalGood;
                                    @endphp
                                    <tr>
                                        <td class="col-product">
                                            {{ $item->product->product_code }} <br>
                                            <small class="text-muted">{{ $item->product->name }}</small>
                                        </td>
                                        <td class="text-end col-qty">
                                            {{ number_format($item->qty_transfer, 0, '.', ',') }}
                                        </td>
                                        @if($transfer->status === 'completed')
                                            <td class="text-end text-success fw-bold">
                                                {{ number_format($item->qty_good, 0, '.', ',') }}
                                            </td>
                                            <td class="text-end text-danger">
                                                {{ number_format($item->qty_damaged, 0, '.', ',') }}
                                            </td>
                                        @endif
                                        <td class="text-end col-price">
                                            {{ number_format($item->unit_cost, 0, '.', ',') }}
                                        </td>
                                        <td class="text-end col-total fw-bold">
                                            {{ number_format($subtotalGood, 0, '.', ',') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            No items
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                @if($transfer->status === 'completed')
                                    <tr class="table-light">
                                        <th colspan="{{ $transfer->status === 'completed' ? 5 : 3 }}" class="text-end">Total Plan Value (Full Qty)</th>
                                        <th class="text-end text-muted">
                                            {{ number_format($transfer->total_cost, 0, '.', ',') }}
                                        </th>
                                    </tr>
                                @endif
                                <tr class="table-primary">
                                    <th colspan="{{ $transfer->status === 'completed' ? 5 : 3 }}" class="text-end">
                                        {{ $transfer->status === 'completed' ? 'Total Received Value (Good Only)' : 'Estimated Total Value' }}
                                    </th>
                                    <th class="text-end fs-5">
                                        {{ number_format($totalGoodCost, 0, '.', ',') }}
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

            @if ($transfer->exists && $canGrSource)
                {{-- ================= MODAL GR ================= --}}
                <div class="modal fade mdl-gr-transfer" id="mdlGRTransfer" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Goods Received – Warehouse Transfer
                                </h5>
                                <button class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST"
                                action="{{ route('warehouse-transfer-forms.gr.source', $transfer->id) }}"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="modal-body">
                                    <p class="small mb-3">
                                        <strong>Requested by:</strong>
                                        {{ $transfer->sourceWarehouse->warehouse_name }}
                                        <br>
                                        <strong>Delivered by:</strong>
                                        {{ $transfer->destinationWarehouse->warehouse_name }}<br>
                                    </p>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Product</th>
                                                    <th>Qty Transfer</th>
                                                    <th>Qty Good</th>
                                                    <th>Qty Damaged</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($transfer->items as $i => $item)
                                                    @php $max = $item->qty_transfer; @endphp
                                                    <tr>
                                                        <td>{{ $i + 1 }}</td>
                                                        <td>
                                                            {{ $item->product->name }}<br>
                                                            <small class="text-muted">
                                                                {{ $item->product->product_code }}
                                                            </small>
                                                        </td>

                                                        <td class="js-remaining" data-remaining="{{ $max }}">
                                                            {{ $max }}
                                                        </td>

                                                        <td style="width:120px">
                                                            <input type="number"
                                                                class="form-control form-control-sm js-good"
                                                                name="items[{{ $item->id }}][good]"
                                                                value="{{ $max }}" min="0"
                                                                max="{{ $max }}">
                                                        </td>

                                                        <td style="width:120px">
                                                            <input type="number"
                                                                class="form-control form-control-sm js-damaged"
                                                                name="items[{{ $item->id }}][damaged]" value="0"
                                                                min="0" max="{{ $max }}">
                                                        </td>

                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                name="items[{{ $item->id }}][note]"
                                                                placeholder="Optional note">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- FOTO PER ITEM --}}
                                    <div class="row g-3 mt-3">
                                        @foreach ($transfer->items as $item)
                                            <div class="col-md-6">
                                                <label class="form-label">
                                                    Good Item Photo – {{ $item->product->name }}
                                                </label>
                                                <input type="file" name="photos_good[{{ $item->id }}]"
                                                    class="form-control">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">
                                                    Damaged Item Photo – {{ $item->product->name }}
                                                </label>
                                                <input type="file" name="photos_damaged[{{ $item->id }}]"
                                                    class="form-control">
                                            </div>
                                        @endforeach
                                    </div>

                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                    <button class="btn btn-primary">
                                        <i class="bx bx-save"></i> Save Goods Received
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            @endif
            {{-- ================= LOG ================= --}}
            <h5 class="mt-4">Activity Log</h5>

            <ul id="activityLogList" class="list-group">
                @forelse ($transfer->logs as $log)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>{{ strtoupper($log->action) }}</strong>
                            <small class="text-muted">
                                {{ $log->created_at->format('d M Y H:i') }}
                            </small>
                        </div>
                        <div class="small">
                            By: {{ $log->user->name ?? '-' }}
                        </div>
                        @if ($log->note)
                            <div class="text-muted small mt-1">
                                {{ $log->note }}
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">No activity</li>
                @endforelse
            </ul>

        </div>
    @endsection

    @if (!$transfer->exists)
        @push('scripts')
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {

                    /* =====================================================
                     * CREATE TRANSFER
                     * ===================================================== */
                    const formTransfer = document.getElementById('formTransfer');
                    if (formTransfer) {
                        formTransfer.addEventListener('submit', function(e) {
                            e.preventDefault();

                            Swal.fire({
                                title: 'Submit transfer?',
                                text: 'Ensure data is correct',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, submit',
                                cancelButtonText: 'Cancel'
                            }).then(result => {
                                if (!result.isConfirmed) return;

                                fetch(formTransfer.action, {
                                        method: 'POST',
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        body: new FormData(formTransfer)
                                    })
                                    .then(r => r.json())
                                    .then(res => {
                                        if (!res.id) throw {
                                            message: 'Transfer ID not found'
                                        };

                                        showSuccess(
                                            'Transfer successfully created',
                                            `/warehouse/transfer-forms/${res.id}`
                                        );
                                    })
                                    .catch(err => showError(err.message));
                            });
                        });
                    }

                    /* =====================================================
                     * ADD ITEM
                     * ===================================================== */
                    let rowId = 0;
                    const btnAddItem = document.getElementById('btnAddItem');
                    const tbody = document.querySelector('#itemsTable tbody');
                    const fromWarehouse = document.getElementById('fromWarehouse');

                    /* ================= ADD ITEM ================= */
                    btnAddItem?.addEventListener('click', () => {
                        rowId++;

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
            <td class="col-product">
                <select name="items[${rowId}][product_id]" class="form-select product-select">
                    <option value="">-- select --</option>
                </select>
            </td>
            <td class="col-stock stock text-center">0</td>
            <td class="col-price price text-end">0</td>
            <td class="col-qty">
                <input type="number" name="items[${rowId}][qty]" value="1" min="1" class="form-control qty">
            </td>
            <td class="col-total subtotal text-end">0</td>
            <td class="col-action">
                <button type="button" class="btn btn-sm btn-danger btnRemove">×</button>
            </td>
        `;

                        tbody.appendChild(tr);
                        loadProducts(tr);
                    });

                    /* ================= LOAD PRODUCT ================= */
                    function loadProducts(tr) {
                        const wid = fromWarehouse.value;

                        fetch(`{{ route('warehouse.products.search') }}?warehouse_id=${wid}`)
                            .then(r => r.json())
                            .then(products => {
                                const select = tr.querySelector('.product-select');
                                select.innerHTML = `<option value="">-- select --</option>`;

                                products.forEach(p => {
                                    const stockLabel = parseInt(p.available_stock || 0);
                                    select.innerHTML += `
                                        <option value="${p.id}"
                                            data-stock="${p.available_stock}"
                                            data-price="${p.purchasing_price}">
                                            ${p.name} (${p.product_code}) | WH Stock: ${stockLabel}
                                        </option>
                                    `;
                                });

                                // Initialize Select2
                                $(select).select2({
                                    theme: 'bootstrap-5',
                                    placeholder: '-- Select Product --',
                                    allowClear: true,
                                    width: '100%'
                                });

                                // ✅ AUTO FOCUS SEARCH: Langsung ngetik pas diklik
                                $(select).on('select2:open', function(e) {
                                    const searchField = document.querySelector('.select2-search__field');
                                    if (searchField) searchField.focus();
                                });

                                // Handle selection via Select2 event
                                $(select).on('select2:select', function(e) {
                                    const data = e.params.data.element.dataset;
                                    const tr_jq = $(this).closest('tr');

                                    const stock = parseInt(data.stock || 0);
                                    const price = parseInt(data.price || 0);

                                    tr_jq.find('.stock').text(stock.toLocaleString('en-US'));
                                    tr_jq.find('.price').text(price.toLocaleString('en-US'));

                                    calc(tr_jq[0]);
                                });
                            });
                    }

                    /* ================= EVENT DELEGATION ================= */

                    document.addEventListener('input', e => {
                        if (e.target.classList.contains('qty')) {
                            calc(e.target.closest('tr'));
                        }
                    });

                    document.addEventListener('click', e => {
                        if (e.target.classList.contains('btnRemove')) {
                            e.target.closest('tr').remove();
                            calcGrandTotal();
                        }
                    });

                    /* ================= CALC ================= */
                    function calc(tr) {
                        const qty = parseInt(tr.querySelector('.qty').value || 0);
                        const price = parseInt(tr.querySelector('.price').innerText.replace(/\D/g, '')) || 0;
                        tr.querySelector('.subtotal').innerText = (qty * price).toLocaleString('en-US');
                        calcGrandTotal();
                    }

                    function calcGrandTotal() {
                        let total = 0;
                        document.querySelectorAll('.subtotal').forEach(td => {
                            total += parseInt(td.innerText.replace(/\D/g, '')) || 0;
                        });
                        document.getElementById('grandTotal').innerText = total.toLocaleString('en-US');
                    }

                });
            </script>
        @endpush
    @endif

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                /* ================= VALIDASI GR MODAL ================= */
                const mdlGr = document.getElementById('mdlGRTransfer');
                if (mdlGr) {
                    mdlGr.addEventListener('input', function(e) {
                        if (e.target.classList.contains('js-good') || e.target.classList.contains('js-damaged')) {
                            const tr = e.target.closest('tr');
                            const total = parseInt(tr.querySelector('.js-remaining').dataset.remaining || 0);
                            const inputGood = tr.querySelector('.js-good');
                            const inputDamaged = tr.querySelector('.js-damaged');

                            let good = parseInt(inputGood.value || 0);
                            let damaged = parseInt(inputDamaged.value || 0);

                            if (e.target.classList.contains('js-good')) {
                                // Jika input good berubah, damage = sisanya
                                if (good > total) {
                                    good = total;
                                    inputGood.value = total;
                                }
                                if (good < 0) {
                                    good = 0;
                                    inputGood.value = 0;
                                }
                                inputDamaged.value = total - good;
                            } else {
                                // Jika input damaged berubah, good = sisanya
                                if (damaged > total) {
                                    damaged = total;
                                    inputDamaged.value = total;
                                }
                                if (damaged < 0) {
                                    damaged = 0;
                                    inputDamaged.value = 0;
                                }
                                inputGood.value = total - damaged;
                            }
                        }
                    });

                    // Auto-select text saat diklik (biar nol nggak nyangkut)
                    mdlGr.addEventListener('focus', function(e) {
                        if (e.target.classList.contains('js-good') || e.target.classList.contains('js-damaged')) {
                            e.target.select();
                        }
                    }, true); // use capture to catch event from bubbled inputs
                }

            });
        </script>
    @endpush

    @push('scripts')
        <script>
            /* ================= GLOBAL SUCCESS ================= */
            window.showSuccess = function(message, redirectUrl = null, delay = 1500) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: message,
                    timer: delay,
                    showConfirmButton: false
                });

                if (redirectUrl) {
                    setTimeout(() => window.location.href = redirectUrl, delay);
                }
            };

            /* ================= GLOBAL ERROR ================= */
            window.showError = function(message = 'An error occurred') {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: message
                });
            };
        </script>
    @endpush
    @push('scripts')
        @if (session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: @json(session('success')),
                        timer: 1500,
                        showConfirmButton: false
                    });
                });
            </script>
        @endif
    @endpush

    @if ($transfer->exists)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    /* ================= EVENT DELEGATION (ZERO REFRESH) ================= */
                    document.addEventListener('click', function(e) {
                        const btnApprove = e.target.closest('.btn-approve-transfer');
                        const btnReject = e.target.closest('.btn-reject-transfer');
                        const btnPrint = e.target.closest('#btnPrintSJ');

                        // 1. APPROVE
                        if (btnApprove) {
                            Swal.fire({
                                icon: 'question',
                                title: 'Approve Transfer?',
                                showCancelButton: true,
                                confirmButtonText: 'Approve',
                                cancelButtonText: 'Cancel'
                            }).then(res => {
                                if (!res.isConfirmed) return;
                                
                                // AJAX Submit
                                fetch(btnApprove.dataset.action, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                }).then(r => r.json()).then(data => {
                                    if (data.success) {
                                        showSuccess(data.message);
                                        refreshTransferActions(); // Sedot HTML baru
                                    } else {
                                        showError(data.message);
                                    }
                                });
                            });
                        }

                        // 2. REJECT
                        if (btnReject) {
                            Swal.fire({
                                title: 'Reject Transfer',
                                input: 'textarea',
                                inputPlaceholder: 'Reason is required',
                                showCancelButton: true,
                                confirmButtonText: 'Reject',
                                cancelButtonText: 'Cancel',
                                inputValidator: v => !v && 'Reason is required'
                            }).then(res => {
                                if (!res.isConfirmed) return;

                                fetch(btnReject.dataset.action, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: JSON.stringify({ reason: res.value })
                                }).then(r => r.json()).then(data => {
                                    if (data.success) {
                                        showSuccess(data.message);
                                        refreshTransferActions(); // Sedot HTML baru
                                    } else {
                                        showError(data.message);
                                    }
                                });
                            });
                        }

                        // 3. PRINT
                        if (btnPrint) {
                            const iframe = document.getElementById('iframePrintSJ');
                            if (iframe) {
                                iframe.src = "{{ route('warehouse-transfer.print-sj', $transfer->id) }}";
                                iframe.onload = function() {
                                    iframe.contentWindow.focus();
                                    iframe.contentWindow.print();
                                };
                            }
                        }
                    });

                    // 📡 Listen to Reverb signal for this specific transfer
                    // 📡 Listen to Reverb signal for this specific transfer
                    window.addEventListener('reverb:warehouse-transfer-updated', (e) => {
                        console.log('📡 [Signal] Detail Page detected update:', e.detail);
                        const currentId = @json($transfer->id);
                        
                        if (e.detail && e.detail.transferId == currentId) {
                            const newStatus = e.detail.status;
                            const badge = document.getElementById('statusBadge');

                            // 1. Update Badge Text & Color
                            if (badge) {
                                badge.innerText = newStatus;
                                badge.className = 'badge text-uppercase'; // Reset classes
                                if (['rejected', 'canceled'].includes(newStatus.toLowerCase())) {
                                    badge.classList.add('bg-danger');
                                } else if (newStatus.toLowerCase() === 'approved') {
                                    badge.classList.add('bg-success');
                                } else if (newStatus.toLowerCase() === 'completed') {
                                    badge.classList.add('bg-primary');
                                } else {
                                    badge.classList.add('bg-info');
                                }
                            }

                            // 2. Refresh Actions (Sedot HTML)
                            if (window.refreshTransferActions) window.refreshTransferActions();

                            // 3. Add to Log Dinamis
                            const logList = document.getElementById('activityLogList');
                            if (logList) {
                                const now = new Date().toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }).replace(',', '');
                                const li = document.createElement('li');
                                li.className = 'list-group-item bg-light-primary'; // Beri highlight dikit
                                li.innerHTML = `
                                    <div class="d-flex justify-content-between">
                                        <strong>${newStatus.toUpperCase()} (REAL-TIME)</strong>
                                        <small class="text-muted">${now}</small>
                                    </div>
                                    <div class="small">By: System / Reverb</div>
                                `;
                                logList.prepend(li);
                            }

                            // Optional: Sound
                            try { playNotificationSound(); } catch(err) {}

                            console.log('✅ UI updated dynamically for status:', newStatus);
                        }
                    });

                    // 🔄 Fungsi Sedot HTML Parsial
                    window.refreshTransferActions = function() {
                        fetch("{{ route('warehouse-transfer-forms.show', $transfer->id) }}?partial=actions")
                            .then(r => r.text())
                            .then(html => {
                                const container = document.getElementById('transferActions');
                                if (container) {
                                    container.innerHTML = html;
                                    console.log('✅ Action buttons refreshed seamlessly');
                                }
                            });
                    };
                });
            </script>
        @endpush
    @endif
