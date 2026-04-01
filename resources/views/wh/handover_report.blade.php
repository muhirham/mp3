@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        /* ================= DESKTOP BASE ================= */

        .swal2-container {
            z-index: 2005 !important;
        }

        .table {
            font-size: 11.5px;
            table-layout: auto;
            width: 100% !important;
            margin-bottom: 0;
        }

        .table thead th {
            font-size: 11px;
            white-space: nowrap;
            vertical-align: middle;
        }

        .table tbody td {
            padding-top: 6px;
            padding-bottom: 6px;
            vertical-align: middle;
        }

        .badge {
            font-size: 9.5px;
            padding: 3.5px 7px;
        }

        .table td.text-wrap-name {
            white-space: normal !important;
            min-width: 120px;
            line-height: 1.2;
        }

        .table td:not(.text-wrap-name),
        .table th {
            white-space: nowrap;
        }

        /* ================= MOBILE RESPONSIVE UPGRADE ================= */

        @media (max-width: 768px) {

            /* FILTER STACK */
            #reportFilterForm .col-md-3 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            /* SUMMARY STACK */
            .row.text-center>.col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
            }

            /* TABLE TO CARD */
            .table-responsive {
                overflow: visible;
            }

            .table-responsive table thead {
                display: none;
            }

            .table-responsive table tbody tr {
                display: block;
                background: #ffffff;
                border-radius: 14px;
                padding: 14px;
                margin-bottom: 14px;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            }

            .table-responsive table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none !important;
                padding: 6px 0;
                font-size: 13px;
                white-space: normal !important;
            }

            .table-responsive table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 11px;
                text-transform: uppercase;
                color: #6c757d;
                margin-right: 10px;
            }

            /* BUTTON FULL WIDTH */
            .btn {
                width: 100%;
            }

            /* MODAL RESPONSIVE */
            .modal-dialog {
                margin: 10px;
            }

            .modal-content {
                border-radius: 16px;
            }

        }
    </style>

    @php
        $me = $me ?? auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug', 'admin') || $roles->contains('slug', 'superadmin');

        $canSeeMargin = !($isSales && !$isAdminLike && !$isWarehouse);

        $statusLabels = $statusOptions ?? [];

        $isSalesMenu = request()->routeIs('daily.sales.report');
        $listRouteName = $isSalesMenu ? 'daily.sales.report' : 'sales.report';
        $detailRoute = $isSalesMenu ? 'daily.report.detail' : 'sales.report.detail';

        $pageTitle =
            $isSales && !$isAdminLike && !$isWarehouse
                ? 'Daily Report Handover Saya'
                : ($isWarehouse
                    ? 'Sales Reports (Admin Warehouse)'
                    : 'Sales Reports');

        $search = $search ?? '';
        $canOpenApproval = $isWarehouse || $isAdminLike;

        $view = $view ?? 'handover';

        $summary = $summary ?? [
            'total_hdo_text' => '0 HDO',
            'total_sold_formatted' => 'Rp 0',
            'total_diff_formatted' => 'Rp 0',
            'period_text' => ($dateFrom ?? '') . ' s/d ' . ($dateTo ?? ''),
            'view' => $view,
        ];
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

        {{-- FILTER + REKAP --}}
        <div class="card mb-3">
            <div class="card-body">
                <form id="reportFilterForm" method="GET" action="{{ route($listRouteName) }}" class="row g-3 align-items-end">

                    <input type="hidden" id="searchBox" name="q" value="{{ $search }}">

                    {{-- VIEW --}}
                    <div class="col-md-3">
                        <label class="form-label">View</label>
                        <select name="view" id="viewFilter" class="form-select">
                            <option value="handover" @selected($view === 'handover')>Handover Detail</option>
                            <option value="sales" @selected($view === 'sales')>Recap per Sales</option>
                            <option value="daily" @selected($view === 'daily')>Recap per Day</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
                    </div>

                    {{-- FILTER WAREHOUSE --}}
                    <div class="col-md-3">
                        <label class="form-label">Warehouse</label>
                        @php
                            $lockWarehouseForSales = $isSales && !$isAdminLike && !$isWarehouse && $me?->warehouse_id;
                        @endphp

                        @if (($isWarehouse && $me?->warehouse_id && !$isAdminLike) || $lockWarehouseForSales)
                            @php
                                $lockedWarehouseId = $lockWarehouseForSales ? $me->warehouse_id : $warehouseId;
                                $lockedWh = $warehouses->firstWhere('id', $lockedWarehouseId);
                                $lockedName =
                                    $lockedWh->warehouse_name ??
                                    ($lockedWh->name ??
                                        ($lockedWh->warehouse_code ?? 'Warehouse #' . $lockedWarehouseId));
                            @endphp

                            <input type="hidden" name="warehouse_id" value="{{ $lockedWarehouseId }}">
                            <input type="text" class="form-control" value="{{ $lockedName }}" readonly>
                            <div class="form-text">
                                {{ $lockWarehouseForSales ? 'This report is only for your warehouse.' : 'This user is locked to this warehouse.' }}
                            </div>
                        @else
                            <select name="warehouse_id" class="form-select">
                                <option value="">All Warehouses</option>
                                @foreach ($warehouses as $w)
                                    @php
                                        $whLabel =
                                            $w->warehouse_name ??
                                            ($w->name ?? ($w->warehouse_code ?? 'Warehouse #' . $w->id));
                                    @endphp
                                    <option value="{{ $w->id }}" @selected($warehouseId == $w->id)>{{ $whLabel }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- FILTER SALES --}}
                    <div class="col-md-3">
                        <label class="form-label">Sales</label>

                        @if ($isSales && !$isAdminLike && !$isWarehouse)
                            <input type="hidden" name="sales_id" value="{{ $me->id }}">
                            <input type="text" class="form-control" value="{{ $me->name }}" readonly>
                            <div class="form-text">This report is only for your handover.</div>
                        @else
                            <select name="sales_id" class="form-select">
                                <option value="">All Sales</option>
                                @foreach ($salesList as $s)
                                    <option value="{{ $s->id }}" @selected($salesId == $s->id)>{{ $s->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach ($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($status === (string) $key)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="button" id="btnResetFilters" class="btn btn-outline-secondary w-100">Reset
                            Filters</button>
                        <button type="button" id="btnExportSales" class="btn btn-success w-100">Export Excel</button>
                    </div>
                </form>


                <hr>

                {{-- SUMMARY TOP --}}
                <div class="row text-center mb-3">

                    {{-- KIRI --}}
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-semibold text-muted small">Total Handover</div>
                            <div class="fs-5 fw-bold" id="sumHdo">{{ $summary['total_hdo_text'] }}</div>
                        </div>

                        @if ($canSeeMargin)
                            <div>
                                <div class="fw-semibold text-muted small">Total Discount</div>
                                <div class="fs-5 fw-bold text-danger" id="sumDiscount">
                                    -{{ $summary['total_discount'] ?? 'Rp 0' }}
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- KANAN --}}
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-semibold text-muted small">Total Sales Value (Closed)</div>
                            <div class="fs-5 fw-bold" id="sumSold">{{ $summary['total_sold_formatted'] }}</div>
                        </div>

                        {{-- Nilai sisa stok tepat di bawah penjualan --}}
                        <div class="pt-1">
                            <div class="fw-semibold text-muted small">Estimated Remaining Stock Value</div>
                            <div class="fs-5 fw-bold" id="sumDiff">{{ $summary['total_diff_formatted'] }}</div>
                        </div>
                    </div>

                </div>

                <div class="mt-2 text-muted small text-center">
                    Period:
                    <span class="fw-semibold"
                        id="periodText">{{ str_replace(' s/d ', ' to ', $summary['period_text']) }}</span>
                </div>

            </div>
        </div>

        {{-- TABLE --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">{{ $pageTitle }}</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead id="reportHead">
                            @if ($view === 'sales')
                                <tr>
                                    <th style="width:4%">#</th>
                                    <th style="width:22%">Sales</th>
                                    <th style="width:20%">Warehouse</th>
                                    <th class="text-end" style="width:10%">HDO</th>
                                    <th class="text-end" style="width:13%">Total Carried</th>
                                    <th class="text-end" style="width:13%">Total Sold</th>
                                    <th class="text-end" style="width:13%">Total Deposit</th>
                                    <th style="width:5%"></th>
                                </tr>
                            @elseif($view === 'daily')
                                <tr>
                                    <th style="width:4%">#</th>
                                    <th style="width:16%">Date</th>
                                    <th class="text-end" style="width:12%">HDO</th>
                                    <th class="text-end" style="width:20%">Total Carried</th>
                                    <th class="text-end" style="width:20%">Total Sold</th>
                                    <th class="text-end" style="width:20%">Total Deposit</th>
                                    <th style="width:8%"></th>
                                </tr>
                            @else
                                <tr>
                                    <th style="width:2%">#</th>
                                    <th style="width:8%">Date</th>
                                    <th style="width:11%">Code</th>
                                    <th style="width:14%">Warehouse</th>
                                    <th style="width:13%">Sales</th>
                                    <th style="width:7%">Status</th>
                                    <th class="text-end" style="width:8%">Carried Val</th>
                                    <th class="text-end" style="width:8%">Sold Val</th>

                                    @if ($canSeeMargin)
                                        <th class="text-end" style="width:8%">Ori Price</th>
                                        <th class="text-end" style="width:8%">Disc</th>
                                    @endif

                                    <th class="text-end" style="width:8%">Diff</th>
                                    <th style="width:5%"></th>
                                </tr>
                            @endif
                        </thead>

                        <tbody id="handoverRows">
                            @forelse(($rows ?? []) as $r)
                                @if ($view === 'sales')
                                    <tr>
                                        <td>{{ $r['no'] }}</td>
                                        <td class="fw-semibold text-wrap-name">{{ $r['sales'] }}</td>
                                        <td class="text-wrap-name">{{ $r['warehouse'] }}</td>
                                        <td class="text-end">{{ $r['handover_count'] }}</td>
                                        <td class="text-end">{{ $r['amount_dispatched'] }}</td>
                                        <td class="text-end">{{ $r['amount_sold'] }}</td>
                                        <td class="text-end">{{ $r['amount_setor'] }}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-drill-sales"
                                                data-sales-id="{{ $r['sales_id'] }}">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @elseif($view === 'daily')
                                    <tr>
                                        <td>{{ $r['no'] }}</td>
                                        <td class="fw-semibold">{{ $r['date'] }}</td>
                                        <td class="text-end">{{ $r['handover_count'] }}</td>
                                        <td class="text-end">{{ $r['amount_dispatched'] }}</td>
                                        <td class="text-end">{{ $r['amount_sold'] }}</td>
                                        <td class="text-end">{{ $r['amount_setor'] }}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-drill-day"
                                                data-date="{{ $r['date'] }}">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>{{ $r['no'] }}</td>
                                        <td data-label="Date">{{ $r['date'] }}</td>
                                        <td data-label="Code" class="fw-semibold">{{ $r['code'] }}</td>
                                        <td data-label="Warehouse" class="text-wrap-name">{{ $r['warehouse'] }}</td>
                                        <td data-label="Sales" class="text-wrap-name">{{ $r['sales'] }}</td>
                                        <td data-label="Status">
                                            <span
                                                class="badge {{ $r['status_badge_class'] }}">{{ $r['status_label'] }}</span>
                                        </td>
                                        <td data-label="Carried Value" class="text-end">{{ $r['amount_dispatched'] }}
                                        </td>
                                        <td data-label="Sold" class="text-end fw-bold">{{ $r['amount_sold'] }}</td>
                                        @if ($canSeeMargin)
                                            <td data-label="Original Price" class="text-end text-muted">
                                                {{ $r['amount_original'] }}</td>
                                            <td data-label="Discount" class="text-end text-danger">
                                                -{{ $r['amount_discount'] }}</td>
                                        @endif
                                        <td data-label="Difference" class="text-end">{{ $r['amount_diff'] }}</td>

                                        <td data-label="Action" class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-detail"
                                                data-id="{{ $r['id'] }}">
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="{{ $view === 'handover' ? ($canSeeMargin ? 11 : 9) : ($view === 'sales' ? 8 : 7) }}"
                                        class="text-center text-muted">No data available for this period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

            </div>
        </div>

    </div>

    {{-- MODAL DETAIL --}}
    <div class="modal fade" id="handoverDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title mb-0">Detail Handover</h5>
                    <div class="d-flex align-items-center gap-2">
                        @if ($canOpenApproval)
                            <button type="button" id="approvalButton" class="btn btn-sm btn-primary d-none">
                                Handover Evening &amp; Approval
                            </button>
                        @endif
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="modal-body">
                    <div id="detailHeader" class="mb-3 small"></div>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle" id="detailItemsTable">
                            <thead>
                                <tr>
                                    <th style="width:30%">Produk</th>
                                    <th class="text-end" style="width:10%">Dibawa</th>
                                    <th class="text-end" style="width:10%">Kembali</th>
                                    <th class="text-end" style="width:10%">Terjual</th>
                                    <th class="text-end" style="width:13%">Harga</th>
                                    <th class="text-end" style="width:12%">Diskon</th>
                                    <th class="text-end" style="width:14%">Harga Setelah Diskon</th>
                                    <th class="text-end" style="width:14%">Nilai Terjual</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div id="detailSummary" class="small"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function() {
            // 1. DOM ELEMENTS
            const filterForm = document.getElementById('reportFilterForm');
            const rowsTbody = document.getElementById('handoverRows');
            const headEl = document.getElementById('reportHead');
            const viewEl = document.getElementById('viewFilter');

            const sumHdoEl = document.getElementById('sumHdo');
            const sumSoldEl = document.getElementById('sumSold');
            const sumDiffEl = document.getElementById('sumDiff');
            const periodTextEl = document.getElementById('periodText');
            const sumDiscountEl = document.getElementById('sumDiscount');

            const modalEl = document.getElementById('handoverDetailModal');
            const detailHeader = document.getElementById('detailHeader');
            const detailSummary = document.getElementById('detailSummary');
            const detailTbody = document.querySelector('#detailItemsTable tbody');
            const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

            const approvalButton = document.getElementById('approvalButton');

            // 2. CONFIG & ROUTES
            const listUrl = @json(route($listRouteName));
            const detailUrlTemplate = @json(route($detailRoute, 0));
            const canSeeMargin = @json($canSeeMargin);
            const canOpenApproval = @json($canOpenApproval);
            const approvalUrlTemplate = canOpenApproval ? @json(route('warehouse.handovers.payments.form', 0)) : null;

            let currentHandoverId = null;

            // 3. HELPERS
            function formatRp(num) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(num || 0);
            }

            // 4. RENDERING FUNCTIONS
            function renderHead(view) {
                if (view === 'sales') {
                    headEl.innerHTML =
                        `<tr><th style="width:4%">#</th><th style="width:22%">Sales</th><th style="width:20%">Warehouse</th><th class="text-end" style="width:10%">HDO</th><th class="text-end" style="width:13%">Total Carried</th><th class="text-end" style="width:13%">Total Sold</th><th class="text-end" style="width:13%">Total Deposit</th><th style="width:5%"></th></tr>`;
                    return;
                }
                if (view === 'daily') {
                    headEl.innerHTML =
                        `<tr><th style="width:4%">#</th><th style="width:16%">Date</th><th class="text-end" style="width:12%">HDO</th><th class="text-end" style="width:20%">Total Carried</th><th class="text-end" style="width:20%">Total Sold</th><th class="text-end" style="width:20%">Total Deposit</th><th style="width:8%"></th></tr>`;
                    return;
                }
                let extra = canSeeMargin ?
                    `<th class="text-end" style="width:8%">Ori Price</th><th class="text-end" style="width:8%">Disc</th>` :
                    '';
                headEl.innerHTML =
                    `<tr><th style="width:2%">#</th><th style="width:8%">Date</th><th style="width:11%">Code</th><th style="width:14%">Warehouse</th><th style="width:13%">Sales</th><th style="width:7%">Status</th><th class="text-end" style="width:8%">Carried Val</th><th class="text-end" style="width:8%">Sold Val</th>${extra}<th class="text-end" style="width:8%">Diff</th><th style="width:5%"></th></tr>`;
            }

            function renderRows(view, rows) {
                if (!rows || !rows.length) {
                    rowsTbody.innerHTML =
                        `<tr><td colspan="12" class="text-center text-muted">No data available for this period.</td></tr>`;
                    return;
                }

                if (view === 'sales') {
                    rowsTbody.innerHTML = rows.map(r => `
                        <tr>
                            <td>${r.no}</td>
                            <td data-label="Sales" class="fw-semibold text-wrap-name">${r.sales}</td>
                            <td data-label="Warehouse" class="text-wrap-name">${r.warehouse}</td>
                            <td data-label="HDO" class="text-end">${r.handover_count}</td>
                            <td data-label="Total Carried" class="text-end">${r.amount_dispatched}</td>
                            <td data-label="Total Sold" class="text-end">${r.amount_sold}</td>
                            <td data-label="Total Deposit" class="text-end">${r.amount_setor}</td>
                            <td data-label="Action" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-drill-sales" data-sales-id="${r.sales_id}">View</button>
                            </td>
                        </tr>
                    `).join('');
                    return;
                }

                if (view === 'daily') {
                    rowsTbody.innerHTML = rows.map(r => `
                        <tr>
                            <td>${r.no}</td>
                            <td data-label="Date" class="fw-semibold">${r.date}</td>
                            <td data-label="HDO" class="text-end">${r.handover_count}</td>
                            <td data-label="Total Carried" class="text-end">${r.amount_dispatched}</td>
                            <td data-label="Total Sold" class="text-end">${r.amount_sold}</td>
                            <td data-label="Total Deposit" class="text-end">${r.amount_setor}</td>
                            <td data-label="Action" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-drill-day" data-date="${r.date}">View</button>
                            </td>
                        </tr>
                    `).join('');
                    return;
                }

                rowsTbody.innerHTML = rows.map(r => {
                    let mCols = '';
                    if (canSeeMargin) {
                        mCols = `
                            <td data-label="Original Price" class="text-end text-muted">${r.amount_original ?? '-'}</td>
                            <td data-label="Discount" class="text-end text-danger">${r.amount_discount != null ? '-' + r.amount_discount : '-'}</td>
                        `;
                    }
                    return `
                        <tr>
                            <td>${r.no}</td>
                            <td data-label="Date">${r.date || '-'}</td>
                            <td data-label="Code" class="fw-semibold">${r.code}</td>
                            <td data-label="Warehouse" class="text-wrap-name">${r.warehouse}</td>
                            <td data-label="Sales" class="text-wrap-name">${r.sales}</td>
                            <td data-label="Status">
                                <span class="badge ${r.status_badge_class}">${r.status_label}</span>
                            </td>
                            <td data-label="Carried Value" class="text-end">${r.amount_dispatched}</td>
                            <td data-label="Sold" class="text-end fw-bold">${r.amount_sold || '-'}</td>
                            ${mCols}
                            <td data-label="Difference" class="text-end">${r.amount_diff || '-'}</td>
                            <td data-label="Action" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-detail" data-id="${r.id}">Detail</button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // 5. CORE AJAX LOGIC
            async function reloadList() {
                const params = new URLSearchParams(new FormData(filterForm));
                try {
                    const res = await fetch(`${filterForm.action}?${params.toString()}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const json = await res.json();
                    if (!json.success) throw new Error('Invalid response');

                    renderHead(json.view || viewEl.value);
                    renderRows(json.view || viewEl.value, json.rows || []);

                    if (json.summary) {
                        if (sumHdoEl) sumHdoEl.textContent = json.summary.total_hdo_text;
                        if (sumSoldEl) sumSoldEl.textContent = json.summary.total_sold_formatted;
                        if (sumDiffEl) sumDiffEl.textContent = json.summary.total_diff_formatted;
                        if (periodTextEl) periodTextEl.textContent = json.summary.period_text;
                        if (sumDiscountEl) {
                            let dv = json.summary.total_discount || 'Rp 0';
                            sumDiscountEl.textContent = (dv !== 'Rp 0') ? '-' + dv : dv;
                        }
                    }
                } catch (err) {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load data.'
                    });
                }
            }

            // 6. EVENT LISTENERS
            const autoSelectors = [
                '#viewFilter',
                'input[name="date_from"]',
                'input[name="date_to"]',
                'select[name="status"]',
                'select[name="warehouse_id"]',
                'select[name="sales_id"]',
            ];
            filterForm.querySelectorAll(autoSelectors.join(',')).forEach(el => el.addEventListener('change', () =>
                reloadList()));
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                reloadList();
            });
            document.getElementById('btnResetFilters')?.addEventListener('click', () => {
                filterForm.reset();
                reloadList();
            });

            rowsTbody.addEventListener('click', async (e) => {
                // A. Click Detail
                const btnDetail = e.target.closest('.btn-detail');
                if (btnDetail) {
                    const id = btnDetail.dataset.id;
                    if (!id) return;

                    currentHandoverId = null;
                    if (approvalButton) approvalButton.classList.add('d-none');

                    detailHeader.innerHTML = 'Loading...';
                    detailTbody.innerHTML =
                        '<tr><td colspan="8" class="text-center text-muted">Loading…</td></tr>';
                    detailSummary.innerHTML = '';

                    const url = detailUrlTemplate.replace('/0', '/' + id);

                    try {
                        const res = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (!res.ok) throw new Error('HTTP ' + res.status);

                        const json = await res.json();
                        if (!json.success) throw new Error('Gagal load detail');

                        const h = json.handover;
                        const it = json.items || [];
                        currentHandoverId = h.id;

                        const statusLabelMap = {
                            draft: 'Draft',
                            waiting_morning_otp: 'Menunggu OTP Pagi',
                            on_sales: 'On Sales',
                            waiting_evening_otp: 'Menunggu Closing (Legacy)',
                            closed: 'Closed',
                            cancelled: 'Cancelled',
                        };
                        const badgeClassMap = {
                            closed: 'bg-label-success',
                            on_sales: 'bg-label-info',
                            waiting_morning_otp: 'bg-label-warning',
                            waiting_evening_otp: 'bg-label-warning',
                            cancelled: 'bg-label-danger',
                            default: 'bg-label-secondary',
                        };

                        const stLabel = statusLabelMap[h.status] || h.status;
                        const badgeClass = badgeClassMap[h.status] || badgeClassMap.default;

                        detailHeader.innerHTML = `
                            <div class="d-flex flex-column flex-md-row justify-content-between">
                                <div class="mb-2 mb-md-0">
                                    <div><span class="fw-semibold">Kode:</span> ${h.code}</div>
                                    <div><span class="fw-semibold">Tanggal:</span> ${h.handover_date || '-'}</div>
                                    <div><span class="fw-semibold">Warehouse:</span> ${h.warehouse_name || '-'}</div>
                                    <div><span class="fw-semibold">Sales:</span> ${h.sales_name || '-'}</div>
                                    <div>
                                        <span class="fw-semibold">Status:</span>
                                        <span class="badge ${badgeClass}">${stLabel}</span>
                                    </div>
                                </div>
                                <div class="text-md-end small">
                                    <div><span class="fw-semibold">OTP Pagi dikirim:</span> ${h.morning_otp_sent_at || '-'}</div>
                                    <div><span class="fw-semibold">OTP Pagi verif:</span> ${h.morning_otp_verified_at || '-'}</div>
                                    <div><span class="fw-semibold">OTP Sore dikirim:</span> ${h.evening_otp_sent_at || '-'}</div>
                                    <div><span class="fw-semibold">OTP Sore verif:</span> ${h.evening_otp_verified_at || '-'}</div>
                                </div>
                            </div>
                        `;

                        if (canOpenApproval && approvalButton && approvalUrlTemplate && h
                            .can_open_approval) {
                            approvalButton.classList.remove('d-none');
                        }

                        let htmlItems = '';
                        it.forEach(row => {
                            const hasDiscount = (row.discount_per_unit || 0) > 0;
                            htmlItems += `
                                <tr>
                                    <td>
                                        <div class="fw-semibold">${row.product_name}</div>
                                        ${row.product_code ? `<div class="small text-muted">${row.product_code}</div>` : ''}
                                    </td>
                                    <td class="text-end">${row.qty_start ?? 0}</td>
                                    <td class="text-end">${row.qty_returned ?? 0}</td>
                                    <td class="text-end">${row.qty_sold ?? 0}</td>
                                    <td class="text-end">${formatRp(row.unit_price || 0)}</td>
                                    <td class="text-end">
                                        ${hasDiscount ? formatRp(row.discount_per_unit) : '-'}
                                    </td>
                                    <td class="text-end">
                                        ${hasDiscount
                                        ? formatRp(row.unit_price_after_discount || (row.unit_price - row.discount_per_unit))
                                        : formatRp(row.unit_price || 0)}
                                    </td>
                                    <td class="text-end fw-semibold">
                                        ${(row.qty_sold ?? 0) > 0 ? formatRp(row.line_total_sold || 0) : formatRp(0)}
                                    </td>
                                </tr>`;
                        });
                        if (!htmlItems) htmlItems =
                            '<tr><td colspan="8" class="text-center text-muted">Tidak ada item.</td></tr>';
                        detailTbody.innerHTML = htmlItems;

                        let proofHtml = '-';
                        if (h.transfer_proof_url) {
                            proofHtml = `
                                <div class="mt-1">
                                    <img src="${h.transfer_proof_url}" class="img-thumbnail proof-thumb" style="max-width:140px;cursor:pointer;">
                                    <div class="small text-muted mt-1">Klik gambar untuk memperbesar.</div>
                                </div>`;
                        }

                        detailSummary.innerHTML = `
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Nilai Dibawa</div>
                                    <div>${formatRp(h.total_dispatched || 0)}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Nilai Terjual</div>
                                    <div>${h.status === 'closed' ? formatRp(h.total_sold) : '<span class="text-muted small">— belum closed —</span>'}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Nilai Sisa Stok (estimasi)</div>
                                    <div>${h.status === 'closed' ? formatRp(h.selisih_stock_value || 0) : '<span class="text-muted small">— belum closed —</span>'}</div>
                                </div>
                            </div>
                            <hr>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Setor Tunai</div>
                                    <div>${formatRp(h.cash_amount || 0)}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Setor Transfer</div>
                                    <div>${formatRp(h.transfer_amount || 0)}</div>
                                    ${proofHtml}
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Total Setoran</div>
                                    <div>${formatRp(h.setor_total || 0)}</div>
                                    <div class="small text-muted">Selisih jual vs setor: ${formatRp(h.selisih_jual_vs_setor || 0)}</div>
                                </div>
                            </div>`;

                        bsModal.show();
                    } catch (err) {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: 'Gagal memuat detail handover.'
                        });
                    }
                    return;
                }

                // B. Drill down Sales
                const btnSales = e.target.closest('.btn-drill-sales');
                if (btnSales) {
                    const sid = btnSales.dataset.salesId;
                    viewEl.value = 'handover';
                    const sSelect = filterForm.querySelector('select[name="sales_id"]');
                    if (sSelect) sSelect.value = sid;
                    reloadList();
                    return;
                }

                // C. Drill down Day
                const btnDay = e.target.closest('.btn-drill-day');
                if (btnDay) {
                    const dt = btnDay.dataset.date;
                    viewEl.value = 'handover';
                    filterForm.querySelector('input[name="date_from"]').value = dt;
                    filterForm.querySelector('input[name="date_to"]').value = dt;
                    reloadList();
                }
            });

            // Zoom Proof via Event Delegation on modal
            modalEl?.addEventListener('click', (e) => {
                const img = e.target.closest('.proof-thumb');
                if (img) Swal.fire({
                    imageUrl: img.src,
                    showConfirmButton: false,
                    width: 'auto'
                });
            });

            approvalButton?.addEventListener('click', () => {
                if (currentHandoverId) window.location.href = approvalUrlTemplate.replace('/0', '/' +
                    currentHandoverId);
            });

            // Navbar Search
            const navbarSearch = document.querySelector('.layout-navbar input[placeholder*="Search"]');
            if (navbarSearch) {
                let timer = null;
                navbarSearch.addEventListener('keyup', () => {
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        const searchBox = document.getElementById('searchBox');
                        if (searchBox) {
                            searchBox.value = navbarSearch.value;
                            reloadList();
                        }
                    }, 500);
                });
            }
        })();
    </script>
@endpush
