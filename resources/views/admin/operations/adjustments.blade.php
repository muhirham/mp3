    @extends('layouts.home')

    @section('content')
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $me = $me ?? auth()->user();
            $canAdjustPusat = empty($me->warehouse_id);
        @endphp

        <div class="container-xxl flex-grow-1 container-p-y">

            <div class="d-flex align-items-center mb-3">
                <h4 class="mb-0 fw-bold">Stock Adjustment</h4>

                <div class="ms-auto d-flex gap-2">
                    @if (auth()->user()->hasPermission('stock_adjustments.create'))
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlCreateAdj">
                            <i class="bx bx-plus"></i> Create Adjustment
                        </button>
                    @endif
                    @if (auth()->user()->hasPermission('stock_adjustments.export'))
                        <button type="button" id="btnExportExcel" class="btn btn-success">
                            <i class="bx bx-download"></i> Export Excel
                        </button>
                    @endif
                </div>
            </div>


            {{-- FILTER + TABLE RIWAYAT --}}
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 align-items-end">

                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1">From Date (document)</label>
                            <input id="fltFrom" type="date" class="form-control">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1">Until</label>
                            <input id="fltTo" type="date" class="form-control">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Warehouse</label>
                            <select id="fltWarehouse" class="form-select">
                                @php
                                    $canViewAll = auth()->user()->hasPermission('stock_adjustments.view_all');
                                    $myWhId = auth()->user()->warehouse_id;
                                @endphp

                                @if ($canViewAll || !$myWhId)
                                    <option value="">— All —</option>
                                    <option value="central">Central Stock</option>
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                                    @endforeach
                                @else
                                    {{-- User locked to their warehouse --}}
                                    @foreach ($warehouses as $w)
                                        @if ($w->id == $myWhId)
                                            <option value="{{ $w->id }}" selected>{{ $w->warehouse_name }}</option>
                                        @endif
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tblAdjustments" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:70px">ID</th>
                                <th>Code</th>
                                <th>Adjustment Date</th>
                                <th>Warehouse</th>
                                <th class="text-center" style="width:120px">Item Count</th>
                                <th>Created By</th>
                                <th>Input Time</th>
                                <th class="text-end" style="width:130px">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="card-body pt-3">
                </div>
            </div>

        </div>

        {{-- ================== MODAL CREATE ================== --}}
        <div class="modal fade" id="mdlCreateAdj" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-bold">Create Stock Adjustment</h5>
                            <small class="text-muted">Use <code>Alt+N</code> to add row, <code>Alt+S</code> to save (inside
                                modal)</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="frmAdjust" action="{{ route('stock-adjustments.store') }}" method="POST">
                        @csrf

                        <div class="modal-body">
                            <div class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Date (document)</label>
                                    <input type="date" name="adj_date" id="adj_date" class="form-control"
                                        value="{{ now()->toDateString() }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Warehouse</label>
                                    <select name="warehouse_id" id="warehouse_id" class="form-select"
                                        {{ !$canViewAll && !empty(auth()->user()->warehouse_id) ? 'disabled' : '' }}>
                                        @if ($canAdjustPusat)
                                            <option value="">Central Stock</option>
                                        @elseif(empty(auth()->user()->warehouse_id))
                                            <option value="">— Select —</option>
                                        @endif

                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}"
                                                {{ auth()->user()->warehouse_id == $w->id ? 'selected' : '' }}>
                                                {{ $w->warehouse_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if (!$canViewAll && !empty(auth()->user()->warehouse_id))
                                        <input type="hidden" name="warehouse_id"
                                            value="{{ auth()->user()->warehouse_id }}">
                                    @endif
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label">Catatan (global)</label>
                                    <input type="text" name="notes" id="notes_header" class="form-control"
                                        placeholder="Opsional...">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Stock Mode</label>
                                    <select name="stock_scope_mode" id="stock_scope_mode" class="form-select">
                                        <option value="single" selected>Item (manual)</option>
                                        <option value="all">All Products</option>
                                    </select>
                                    <small class="text-muted d-block">
                                        “All Products” mode will load all items according to warehouse/central.
                                    </small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Update Mode</label>
                                    <select name="price_update_mode" id="price_update_mode" class="form-select">
                                        <option value="stock" selected>Update Physical Stock</option>
                                        <option value="purchase">Update Purchase Price</option>
                                        <option value="selling">Update Selling Price</option>
                                        <option value="purchase_selling">Update Purchase & Selling Price</option>
                                        <option value="stock_purchase_selling">Update Stock + Purchase & Selling Price
                                        </option>
                                    </select>
                                    <small class="text-muted d-block">
                                        Table columns adapt to the mode.
                                    </small>
                                </div>
                            </div>

                            <div class="table-responsive items-scroll">
                                <table class="table table-bordered align-middle mb-2" id="tblItems">
                                    <thead class="table-light">
                                        <tr class="text-center">
                                            <th style="width:30%">Product</th>
                                            <th class="col-stock" style="width:12%">Physical Stock</th>
                                            <th class="col-price-beli" style="width:14%">Purchase Price</th>
                                            <th class="col-price-jual" style="width:14%">Selling Price</th>
                                            <th style="width:22%">Item Notes</th>
                                            <th style="width:8%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" id="btnAddRow" class="btn btn-outline-secondary">
                                    <i class="bx bx-plus"></i> Add Row (Alt+N)
                                </button>

                                <button type="submit" id="btnSave" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Save (Alt+S)
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ================== MODAL DETAIL (1 modal, isi via AJAX) ================== --}}
        <div class="modal fade" id="mdlAdjDetail" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-bold">STOCK ADJUSTMENT FORM</h5>
                            <small class="text-muted">Stock adjustment document detail</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="border rounded p-3 mb-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div><strong>Document No.</strong> : <span id="d_adj_code">-</span></div>
                                    <div><strong>Date</strong> : <span id="d_adj_date">-</span></div>
                                    <div><strong>Warehouse</strong> : <span id="d_wh">-</span></div>
                                </div>
                                <div class="col-md-6">
                                    <div><strong>Created by</strong> : <span id="d_creator">-</span></div>
                                    <div><strong>Created at</strong> : <span id="d_created_at">-</span></div>
                                    <div><strong>Item count</strong> : <span id="d_items_count">-</span></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <strong>Global Notes</strong><br>
                                <span class="text-muted" id="d_notes">-</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th style="width:40px">No.</th>
                                        <th>Product</th>
                                        <th class="text-end" style="width:110px">Stock Before</th>
                                        <th class="text-end" style="width:110px">Stock After</th>
                                        <th class="text-end" style="width:110px">Difference</th>
                                        <th class="text-end" style="width:160px">Purchase Price (Before → After)</th>
                                        <th class="text-end" style="width:160px">Selling Price (Before → After)</th>
                                        <th style="width:220px">Item Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="d_items_body"></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endsection

    @push('styles')
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
        <style>
            /* ================== GENERAL SMALLER UI ================== */
            .container-xxl {
                font-size: 12.5px;
            }

            .form-label {
                font-size: 12px;
            }

            .form-control,
            .form-select {
                font-size: 12.5px;
                padding: .45rem .6rem;
            }

            .btn {
                font-size: 12.5px;
                padding: .45rem .75rem;
            }

            .table {
                font-size: 12.2px;
            }

            .table thead th {
                font-size: 12px;
                font-weight: 600;
            }

            code {
                font-size: 12px;
            }

            /* ================== DATATABLE: RESPONSIVE FIX ================== */
            /* Jangan paksa fixed layout biar teks nggak tumpang tindih */
            #tblAdjustments {
                width: 100% !important;
            }

            #tblAdjustments td,
            #tblAdjustments th {
                white-space: nowrap !important; /* Biar data nggak kepotong baris baru kecuali di mobile */
                vertical-align: middle;
            }

            @media (max-width: 992px) {
                #tblAdjustments td,
                #tblAdjustments th {
                    white-space: normal !important; /* Di layar kecil baru boleh wrap */
                    word-break: break-word;
                }
            }

            /* rapihin padding cell */
            #tblAdjustments td {
                padding: .55rem .6rem;
            }

            #tblAdjustments th {
                padding: .6rem .6rem;
            }

            /* wrapper UTAMA: aktifkan scroll horizontal */
            .table-responsive {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }

            /* ================== MODAL SCROLL FIX ================== */
            #mdlCreateAdj .modal-body {
                max-height: calc(100vh - 180px);
                overflow-y: auto;
            }

            #mdlCreateAdj .items-scroll {
                max-height: 55vh;
                overflow-y: auto;
                overflow-x: hidden;
                /* penting: jangan scroll kiri-kanan */
            }

            #mdlCreateAdj .items-scroll table {
                table-layout: fixed;
                width: 100%;
            }

            #mdlCreateAdj .items-scroll td,
            #mdlCreateAdj .items-scroll th {
                white-space: normal !important;
                word-break: break-word;
            }

            /* sticky header */
            #mdlCreateAdj .items-scroll thead th {
                position: sticky;
                top: 0;
                z-index: 5;
                background: #f8f9fa;
            }

            /* ================== DATATABLE UI (clean) ================== */
            .dataTables_wrapper .dataTables_info {
                font-size: 12px;
                padding-top: .35rem;
            }

            .dataTables_wrapper .pagination .page-link {
                font-size: 12px;
                padding: .3rem .55rem;
            }

            .dataTables_wrapper .dataTables_length label {
                font-size: 12px;
            }

            .dataTables_wrapper .dataTables_length select {
                font-size: 12px;
                padding: .25rem .5rem;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            $(function() {

                const csrf = $('meta[name="csrf-token"]').attr('content');

                const datatableUrl = @json(route('stock-adjustments.datatable'));
                const storeUrl = @json(route('stock-adjustments.store'));
                const detailTpl = @json(route('stock-adjustments.detail', ['adjustment' => '__ID__']));
                const productsUrl = @json(route('stock-adjustments.products'));
                const ajaxProductsUrl = @json(route('stock-adjustments.ajax-products'));

                // =================== DATATABLE RIWAYAT ===================
                const table = $('#tblAdjustments').DataTable({
                    processing: true,
                    serverSide: true,
                    lengthChange: true,
                    pageLength: 10,
                    order: [
                        [0, 'desc']
                    ],
                    searching: false,
                    autoWidth: true, // ✅ Biar DataTables auto-manage lebar kolom yang pas
                    responsive: false, // Biar tabel utuh, tinggal di-scroll horizontal

                    // ✅ Kasih jarak (padding & gap) biar nggak mepet kontainer
                    dom: "<'row px-3 mt-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                        "<'row'<'col-sm-12'tr>>" +
                        "<'row px-3 mb-2 mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",

                    ajax: {
                        url: datatableUrl,
                        type: 'GET',
                        data: function(d) {
                            d.q = ($('#globalSearch').val() || '').trim();
                            d.warehouse_id = $('#fltWarehouse').val() || '';
                            d.date_from = $('#fltFrom').val() || '';
                            d.date_to = $('#fltTo').val() || '';
                        }
                    },

                    columns: [{
                            data: 'id',
                            className: 'text-center'
                        },
                        {
                            data: 'adj_code'
                        },
                        {
                            data: 'adj_date'
                        },
                        {
                            data: 'warehouse'
                        },
                        {
                            data: 'items_count',
                            className: 'text-center'
                        },
                        {
                            data: 'creator'
                        },
                        {
                            data: 'created_at'
                        },
                        {
                            data: 'action',
                            orderable: false,
                            searchable: false,
                            className: 'text-end'
                        },
                    ],
                });

                // debounce reload
                let tSearch;

                function reloadDebounced() {
                    clearTimeout(tSearch);
                    tSearch = setTimeout(() => table.ajax.reload(null, true), 250);
                }

                // filter date & warehouse reload
                $('#fltWarehouse, #fltFrom, #fltTo').on('change', function() {
                    table.ajax.reload(null, true);
                });

                // global search navbar jadi search utama
                $(document)
                    .off('input.stockAdjSearch', '#globalSearch')
                    .on('input.stockAdjSearch', '#globalSearch', function() {
                        reloadDebounced();
                    });

                // =================== DETAIL MODAL (AJAX) ===================
                $('#tblAdjustments').on('click', '.btn-detail', async function() {
                    const id = $(this).data('id');
                    const url = detailTpl.replace('__ID__', id);

                    try {
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const json = await res.json();
                        if (!res.ok || json.status !== 'ok') throw new Error(json.message ||
                            'Failed to fetch detail');

                        $('#d_adj_code').text(json.header.adj_code || '-');
                        $('#d_adj_date').text(json.header.adj_date || '-');
                        $('#d_wh').text(json.header.warehouse || '-');
                        $('#d_creator').text(json.header.creator || '-');
                        $('#d_created_at').text(json.header.created_at || '-');
                        $('#d_items_count').text(json.header.items_count ?? '-');
                        $('#d_notes').text(json.header.notes || '-');

                        const body = $('#d_items_body');
                        body.html('');

                        const fmt = (n) => new Intl.NumberFormat('id-ID').format(n ?? 0);
                        const fmtRp = (n) => (n === null || n === undefined) ? '-' : ('Rp' + new Intl
                            .NumberFormat('id-ID').format(n));

                        (json.items || []).forEach((it, idx) => {
                            const isMasked = (it.qty_diff === null);
                            const fmtVal = (n) => (n === null) ?
                                `<span class="text-muted italic">Hidden</span>` : fmt(n);

                            const diff = it.qty_diff;
                            let diffHtml = `<span class="text-muted">0</span>`;

                            if (isMasked) {
                                diffHtml = `<span class="text-muted small italic">Hidden</span>`;
                            } else {
                                const dVal = parseInt(diff || 0, 10);
                                if (dVal > 0) diffHtml =
                                    `<span class="text-success fw-semibold">+${fmt(dVal)}</span>`;
                                if (dVal < 0) diffHtml =
                                    `<span class="text-danger fw-semibold">${fmt(dVal)}</span>`;
                            }

                            body.append(`
          <tr>
            <td class="text-center">${idx+1}</td>
            <td>
              <div class="fw-semibold">${it.product || '-'}</div>
              <div class="text-muted small">${it.product_code || ''}</div>
            </td>
            <td class="text-end">${fmtVal(it.qty_before)}</td>
            <td class="text-end">${fmtVal(it.qty_after)}</td>
            <td class="text-end">${diffHtml}</td>
            <td class="text-end">${fmtRp(it.pb)} <span class="text-muted">→</span> ${fmtRp(it.pa)}</td>
            <td class="text-end">${fmtRp(it.sb)} <span class="text-muted">→</span> ${fmtRp(it.sa)}</td>
            <td>${it.notes || '-'}</td>
          </tr>
        `);
                        });

                        new bootstrap.Modal(document.getElementById('mdlAdjDetail')).show();
                    } catch (err) {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: err.message || 'Failed to load detail.'
                        });
                    }
                });

                // =================== CREATE MODAL (AJAX SUBMIT) ===================
                const createModalEl = document.getElementById('mdlCreateAdj');
                const frm = document.getElementById('frmAdjust');
                const tbody = document.querySelector('#tblItems tbody');
                const btnAddRow = document.getElementById('btnAddRow');
                const btnSave = document.getElementById('btnSave');

                const stockScope = document.getElementById('stock_scope_mode');
                const updateMode = document.getElementById('price_update_mode');
                const warehouseSel = document.getElementById('warehouse_id');

                let productsCache = [];
                let rowIndex = 0;
                let isSubmitting = false;

                async function loadProductsCache() {
                    const whId = warehouseSel.value || '';
                    const params = new URLSearchParams({
                        warehouse_id: whId
                    });
                    const res = await fetch(productsUrl + '?' + params.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const json = await res.json();
                    if (!res.ok || json.status !== 'ok') throw new Error('Failed to load products');
                    productsCache = json.items || [];
                }

                function productOptionsHtml() {
                    let html = `<option value="">— Select Product —</option>`;
                    for (const p of productsCache) {
                        const stockInfo = (p.qty_before !== undefined) ? ` [Stock: ${p.qty_before}]` : '';
                        html += `<option value="${p.id}" 
                data-stock="${p.qty_before}" 
                data-purchasing="${p.purchasing_price}" 
                data-selling="${p.selling_price}">
                ${p.product_code} — ${p.name}${stockInfo}
              </option>`;
                    }
                    return html;
                }

                function applyUpdateModeUI() {
                    const mode = updateMode.value;

                    const showStock = (mode === 'stock' || mode === 'stock_purchase_selling');
                    const showBeli = (mode === 'purchase' || mode === 'purchase_selling' || mode ===
                        'stock_purchase_selling');
                    const showJual = (mode === 'selling' || mode === 'purchase_selling' || mode ===
                        'stock_purchase_selling');

                    document.querySelectorAll('#mdlCreateAdj .col-stock, #mdlCreateAdj .td-stock')
                        .forEach(el => el.classList.toggle('d-none', !showStock));

                    document.querySelectorAll('#mdlCreateAdj .col-price-beli, #mdlCreateAdj .td-price-beli')
                        .forEach(el => el.classList.toggle('d-none', !showBeli));

                    document.querySelectorAll('#mdlCreateAdj .col-price-jual, #mdlCreateAdj .td-price-jual')
                        .forEach(el => el.classList.toggle('d-none', !showJual));

                    document.querySelectorAll('#mdlCreateAdj input[name$="[qty_after]"]').forEach(i => i.required =
                        showStock);
                    document.querySelectorAll('#mdlCreateAdj input[name$="[purchasing_price]"]').forEach(i => i
                        .required = showBeli);
                    document.querySelectorAll('#mdlCreateAdj input[name$="[selling_price]"]').forEach(i => i.required =
                        showJual);
                }

                function makeRowSingle(idx) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
      <td>
        <select name="items[${idx}][product_id]" class="form-select item-product" required>
          ${productOptionsHtml()}
        </select>
      </td>
      <td class="td-stock">
        <input type="number" name="items[${idx}][qty_after]" class="form-control" min="0" placeholder="Stock">
      </td>
      <td class="td-price-beli">
        <input type="number" name="items[${idx}][purchasing_price]" class="form-control" min="0" placeholder="Price">
      </td>
      <td class="td-price-jual">
        <input type="number" name="items[${idx}][selling_price]" class="form-control" min="0" placeholder="Price">
      </td>
      <td>
        <input type="text" name="items[${idx}][notes]" class="form-control" placeholder="Notes">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
          <i class="bx bx-trash"></i>
        </button>
      </td>
    `;
                    tbody.appendChild(tr);
                    applyUpdateModeUI();
                }

                function makeRowAll(idx, item) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
      <td>
        <input type="hidden" name="items[${idx}][product_id]" value="${item.id}">
        <div class="fw-semibold">${item.product_code} — ${item.name}</div>
      </td>
      <td class="td-stock">
        <input type="number" name="items[${idx}][qty_after]" class="form-control" min="0" placeholder="Current: ${item.qty_before}">
      </td>
      <td class="td-price-beli">
        <input type="number" name="items[${idx}][purchasing_price]" class="form-control" min="0" placeholder="Prev: ${item.purchasing_price}">
      </td>
      <td class="td-price-jual">
        <input type="number" name="items[${idx}][selling_price]" class="form-control" min="0" placeholder="Prev: ${item.selling_price}">
      </td>
      <td>
        <input type="text" name="items[${idx}][notes]" class="form-control" placeholder="Notes">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
          <i class="bx bx-trash"></i>
        </button>
      </td>
    `;
                    tbody.appendChild(tr);
                    applyUpdateModeUI();
                }

                function resetForm() {
                    frm.reset();
                    document.getElementById('adj_date').value = new Date().toISOString().slice(0, 10);

                    tbody.innerHTML = '';
                    rowIndex = 0;
                    stockScope.value = 'single';

                    makeRowSingle(rowIndex++);
                    btnAddRow.disabled = false;
                    applyUpdateModeUI();
                    isSubmitting = false;
                    btnSave.disabled = false;
                }

                async function loadAllProducts() {
                    const whId = warehouseSel.value || '';
                    tbody.innerHTML =
                        `<tr><td colspan="6" class="text-center text-muted">Memuat data produk...</td></tr>`;

                    const params = new URLSearchParams({
                        warehouse_id: whId
                    });
                    const res = await fetch(ajaxProductsUrl + '?' + params.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const json = await res.json();
                    if (!res.ok || json.status !== 'ok') throw new Error('Gagal load produk (all)');

                    tbody.innerHTML = '';
                    rowIndex = 0;

                    for (const item of (json.items || [])) {
                        makeRowAll(rowIndex++, item);
                    }

                    if (rowIndex === 0) {
                        tbody.innerHTML =
                            `<tr><td colspan="6" class="text-center text-muted">Tidak ada produk.</td></tr>`;
                    }
                }

                createModalEl.addEventListener('shown.bs.modal', async function() {
                    try {
                        await loadProductsCache();
                        resetForm();
                    } catch (err) {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: 'Failed to load master products.'
                        });
                    }
                });

                updateMode.addEventListener('change', applyUpdateModeUI);

                stockScope.addEventListener('change', async function() {
                    if (this.value === 'all') {
                        btnAddRow.disabled = true;
                        try {
                            await loadAllProducts();
                        } catch (err) {
                            console.error(err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: err.message || 'Failed to load products.'
                            });
                        }
                    } else {
                        btnAddRow.disabled = false;
                        tbody.innerHTML = '';
                        rowIndex = 0;
                        makeRowSingle(rowIndex++);
                    }
                });

                // Listener input produk untuk update placeholder
                tbody.addEventListener('change', function(e) {
                    if (e.target.classList.contains('item-product')) {
                        const opt = e.target.selectedOptions[0];
                        const tr = e.target.closest('tr');
                        if (!opt || !tr) return;

                        const stock = opt.dataset.stock || '0';
                        const buy = opt.dataset.purchasing || '0';
                        const sell = opt.dataset.selling || '0';

                        const inStock = tr.querySelector('input[name$="[qty_after]"]');
                        const inBuy = tr.querySelector('input[name$="[purchasing_price]"]');
                        const inSell = tr.querySelector('input[name$="[selling_price]"]');

                        if (inStock) inStock.placeholder = `Current: ${stock}`;
                        if (inBuy) inBuy.placeholder = `Prev: ${buy}`;
                        if (inSell) inSell.placeholder = `Prev: ${sell}`;
                    }
                });

                warehouseSel.addEventListener('change', async function() {
                    if (stockScope.value === 'all') {
                        try {
                            await loadAllProducts();
                        } catch (err) {
                            console.error(err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: err.message || 'Failed to load products.'
                            });
                        }
                    } else {
                        // Reload cache produk untuk Gudang ini
                        try {
                            await loadProductsCache();
                            // Update all existing dropdowns placeholder (optional context)
                            tbody.querySelectorAll('tr').forEach(tr => {
                                const sel = tr.querySelector('select.item-product');
                                if (sel) {
                                    const val = sel.value;
                                    sel.innerHTML = productOptionsHtml();
                                    sel.value = val;
                                    // update placeholder inputnya
                                    const opt = sel.selectedOptions[0];
                                    if (opt) {
                                        const stock = opt.dataset.stock || '0';
                                        const buy = opt.dataset.purchasing || '0';
                                        const sell = opt.dataset.selling || '0';

                                        const inStock = tr.querySelector(
                                            'input[name$="[qty_after]"]');
                                        const inBuy = tr.querySelector(
                                            'input[name$="[purchasing_price]"]');
                                        const inSell = tr.querySelector(
                                            'input[name$="[selling_price]"]');

                                        if (inStock) inStock.placeholder = `Current: ${stock}`;
                                        if (inBuy) inBuy.placeholder = `Prev: ${buy}`;
                                        if (inSell) inSell.placeholder = `Prev: ${sell}`;
                                    }
                                }
                            });
                        } catch (err) {
                            console.error(err);
                        }
                    }
                });

                btnAddRow.addEventListener('click', function() {
                    if (stockScope.value !== 'single') return;
                    makeRowSingle(rowIndex++);
                });

                tbody.addEventListener('click', function(e) {
                    if (e.target.closest('.btnRemoveRow')) {
                        const rows = tbody.querySelectorAll('tr');
                        if (rows.length <= 1) {
                            rows[0].querySelectorAll('input,select').forEach(el => el.value = '');
                        } else {
                            e.target.closest('tr').remove();
                        }
                    }
                });

                createModalEl.addEventListener('keydown', function(e) {
                    if (e.altKey && e.key.toLowerCase() === 'n') {
                        e.preventDefault();
                        if (stockScope.value === 'single') btnAddRow.click();
                    }
                    if (e.altKey && e.key.toLowerCase() === 's') {
                        e.preventDefault();
                        if (!isSubmitting) frm.requestSubmit();
                    }
                });

                frm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    if (isSubmitting) return;

                    isSubmitting = true;
                    btnSave.disabled = true;

                    try {
                        const fd = new FormData(frm);

                        const res = await fetch(storeUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: fd
                        });

                        const json = await res.json().catch(() => ({}));

                        if (res.status === 422) {
                            const errs = json.errors || {};
                            const list = Object.values(errs).flat().join('<br>');
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation failed',
                                html: list || 'Check your input.'
                            });
                            isSubmitting = false;
                            btnSave.disabled = false;
                            return;
                        }

                        if (!res.ok || json.status !== 'ok') {
                            throw new Error(json.message || 'Failed to save.');
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: json.message || 'Saved.'
                        });

                        bootstrap.Modal.getInstance(createModalEl).hide();
                        table.ajax.reload(null, false);
                    } catch (err) {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: err.message || 'Failed to save.'
                        });
                        isSubmitting = false;
                        btnSave.disabled = false;
                    }
                });
                const exportUrl = @json(route('stock-adjustments.exportIndexExcel'));

                function buildExportUrl() {
                    const q = ($('#globalSearch').val() || '').trim();
                    const wh = ($('#fltWarehouse').val() || '').trim();

                    let from = ($('#fltFrom').val() || '').trim();
                    let to = ($('#fltTo').val() || '').trim();

                    // kalau cuma isi salah satu, samain (biar konsisten sama parseExportRangeOptional)
                    if (from && !to) to = from;
                    if (to && !from) from = to;

                    const params = new URLSearchParams();
                    if (q) params.set('q', q);
                    if (wh) params.set('warehouse_id', wh);

                    // pakai key yang controller udah support: date_from / date_to
                    if (from) params.set('date_from', from);
                    if (to) params.set('date_to', to);

                    const qs = params.toString();
                    return qs ? `${exportUrl}?${qs}` : exportUrl;
                }

                $('#btnExportExcel').on('click', function() {
                    const q = ($('#globalSearch').val() || '').trim();
                    const wh = ($('#fltWarehouse').val() || '').trim();
                    const from = ($('#fltFrom').val() || '').trim();
                    const to = ($('#fltTo').val() || '').trim();

                    const noFilter = (!q && !wh && !from && !to);

                    if (noFilter) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Filter is empty',
                            text: 'You haven\'t selected any filters. Exporting all data can be slow. Proceed to export ALL?',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, export ALL',
                            cancelButtonText: 'Cancel'
                        }).then((r) => {
                            if (r.isConfirmed) {
                                window.location.href = buildExportUrl();
                            }
                        });
                        return;
                    }

                    // kalau ada filter => langsung export
                    window.location.href = buildExportUrl();
                });

            });
        </script>
    @endpush
