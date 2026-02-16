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
        </style>
    @endpush


    @section('content')

        <div class="container-xxl container-p-y">

            @if ($transfer->exists)
                <div class="card mb-3">
                    <div class="card-body row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Transfer Code</label>
                            <div class="fw-bold">{{ $transfer->transfer_code }}</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label><br>
                            <span class="badge bg-info text-uppercase">
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
            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap gap-2">

                    {{-- APPROVE + REJECT (PADANG) --}}
                    @if ($canApproveDestination)
                        <button class="btn btn-success btn-approve-transfer"
                            data-action="{{ route('warehouse-transfer-forms.approve.destination', $transfer->id) }}">
                            <i class="bx bx-check-circle"></i> Approve
                        </button>

                        <button class="btn btn-outline-danger btn-reject-transfer"
                            data-action="{{ route('warehouse-transfer-forms.reject.destination', $transfer->id) }}">
                            <i class="bx bx-x-circle"></i> Reject
                        </button>
                    @endif


                    {{-- PRINT SJ (PADANG) --}}
                    @if ($canPrintSJ)
                        <button id="btnPrintSJ" class="btn btn-outline-primary">
                            <i class="bx bx-printer"></i> Surat Jalan
                        </button>
                    @endif

                    {{-- GR (BUKITTINGGI) --}}
                    @if ($canGrSource)
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlGRTransfer">
                            <i class="bx bx-download"></i> Goods Received
                        </button>
                    @endif

                </div>
            </div>

            @if ($canPrintSJ)
                <iframe id="iframePrintSJ" src="" style="display:none;"></iframe>
            @endif

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
                                    <th class="col-qty text-end">Qty</th>
                                    <th class="col-price text-end">Unit Price</th>
                                    <th class="col-total text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($transfer->items as $item)
                                    <tr>
                                        <td class="col-product">
                                            {{ $item->product->product_code }} <br>
                                            <small class="text-muted">{{ $item->product->name }}</small>
                                        </td>
                                        <td class="text-end col-qty">
                                            {{ number_format($item->qty_transfer, 0, ',', '.') }}
                                        </td>
                                        <td class="text-end col-price">
                                            {{ number_format($item->unit_cost, 0, ',', '.') }}
                                        </td>
                                        <td class="text-end col-total">
                                            {{ number_format($item->subtotal_cost, 0, ',', '.') }}
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
                                <tr>
                                    <th colspan="3" class="text-end">Total</th>
                                    <th class="text-end">
                                        {{ number_format($transfer->total_cost, 0, ',', '.') }}
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

            <ul class="list-group">
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
                                text: 'Pastikan data sudah benar',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Ya, submit',
                                cancelButtonText: 'Batal'
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
                                            message: 'ID transfer tidak ditemukan'
                                        };

                                        showSuccess(
                                            'Transfer berhasil dibuat',
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
                                    select.innerHTML += `
                        <option value="${p.id}"
                            data-stock="${p.available_stock}"
                            data-price="${p.purchasing_price}">
                            ${p.product_code} - ${p.name}
                        </option>
                    `;
                                });
                            });
                    }

                    /* ================= EVENT DELEGATION ================= */
                    document.addEventListener('change', e => {
                        if (e.target.classList.contains('product-select')) {
                            const tr = e.target.closest('tr');
                            const opt = e.target.selectedOptions[0];

                            const stock = parseInt(opt.dataset.stock || 0);
                            const price = parseInt(opt.dataset.price || 0);

                            tr.querySelector('.stock').innerText = stock.toLocaleString('id-ID');
                            tr.querySelector('.price').innerText = price.toLocaleString('id-ID');

                            calc(tr);
                        }
                    });

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
                        tr.querySelector('.subtotal').innerText = (qty * price).toLocaleString('id-ID');
                        calcGrandTotal();
                    }

                    function calcGrandTotal() {
                        let total = 0;
                        document.querySelectorAll('.subtotal').forEach(td => {
                            total += parseInt(td.innerText.replace(/\D/g, '')) || 0;
                        });
                        document.getElementById('grandTotal').innerText = total.toLocaleString('id-ID');
                    }

                });
            </script>
        @endpush
    @endif

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {

                /* ================= APPROVE ================= */
                document.querySelectorAll('.btn-approve-transfer').forEach(btn => {
                    btn.addEventListener('click', () => {
                        Swal.fire({
                            icon: 'question',
                            title: 'Approve Transfer?',
                            showCancelButton: true,
                            confirmButtonText: 'Approve',
                            cancelButtonText: 'Batal'
                        }).then(res => {
                            if (!res.isConfirmed) return;

                            const f = document.createElement('form');
                            f.method = 'POST';
                            f.action = btn.dataset.action;
                            f.innerHTML = `
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    `;
                            document.body.appendChild(f);
                            f.submit();
                        });
                    });
                });

                /* ================= REJECT ================= */
                document.querySelectorAll('.btn-reject-transfer').forEach(btn => {
                    btn.addEventListener('click', () => {
                        Swal.fire({
                            title: 'Reject Transfer',
                            input: 'textarea',
                            inputPlaceholder: 'Alasan wajib diisi',
                            showCancelButton: true,
                            confirmButtonText: 'Reject',
                            cancelButtonText: 'Batal',
                            inputValidator: v => !v && 'Alasan wajib diisi'
                        }).then(res => {
                            if (!res.isConfirmed) return;

                            const f = document.createElement('form');
                            f.method = 'POST';
                            f.action = btn.dataset.action;
                            f.innerHTML = `
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="reason" value="${res.value}">
                    `;
                            document.body.appendChild(f);
                            f.submit();
                        });
                    });
                });

            });
        </script>
    @endpush

    @push('scripts')
        <script>
            /* ================= GLOBAL SUCCESS ================= */
            window.showSuccess = function(message, redirectUrl = null, delay = 1500) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: message,
                    timer: delay,
                    showConfirmButton: false
                });

                if (redirectUrl) {
                    setTimeout(() => window.location.href = redirectUrl, delay);
                }
            };

            /* ================= GLOBAL ERROR ================= */
            window.showError = function(message = 'Terjadi kesalahan') {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: message
                });
            };
        </script>
    @endpush
    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: @json(session('success')),
                    timer: 1500,
                    showConfirmButton: false
                });
            });
        </script>
    @endif

    @if ($transfer->exists && $canPrintSJ)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const btn = document.getElementById('btnPrintSJ');
                    const iframe = document.getElementById('iframePrintSJ');

                    if (!btn || !iframe) return;

                    btn.addEventListener('click', function() {
                        iframe.src = "{{ route('warehouse-transfer.print-sj', $transfer->id) }}";

                        iframe.onload = function() {
                            iframe.contentWindow.focus();
                            iframe.contentWindow.print();
                        };
                    });
                });
            </script>
        @endpush
    @endif
