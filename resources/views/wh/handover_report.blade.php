@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        /* ================= DESKTOP BASE ================= */

        .swal2-container {
            z-index: 2005 !important;
        }

        #proofModal {
            z-index: 1090 !important;
        }

        #proofModal + .modal-backdrop {
            z-index: 1085 !important;
        }

        .zoomable-img {
            transition: transform 0.25s ease-out;
            transform-origin: center center;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            pointer-events: none;
        }

        #zoomContainer.is-zoomed .zoomable-img {
            transform: scale(3.5);
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
                ? 'My Daily Handover Report'
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
            'period_text' => ($dateFrom ?? '') . ' to ' . ($dateTo ?? ''),
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

                    <div class="col-md-2">
                        <label class="form-label">Entries</label>
                        <select name="per_page" id="perPage" class="form-select">
                            <option value="10" @selected(($perPage ?? 10) == 10)>10</option>
                            <option value="30" @selected(($perPage ?? 10) == 30)>30</option>
                            <option value="50" @selected(($perPage ?? 10) == 50)>50</option>
                        </select>
                    </div>

                    <div class="col-md-1 d-flex align-items-end gap-2" style="flex: 1;">
                        <button type="button" id="btnExportSales" class="btn btn-success w-100 px-2" title="Export Excel">
                            <i class="bx bx-download"></i> Export
                        </button>
                        <button type="button" id="btnResetFilters" class="btn btn-outline-secondary w-100 px-2"
                            title="Reset Filters">
                            <i class="bx bx-refresh"></i>
                        </button>
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
            <div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <small id="pageInfo" class="text-muted">Showing 0 to 0 of 0 results</small>
                <nav>
                    <ul id="pgNav" class="pagination mb-0"></ul>
                </nav>
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
                        @if ($isAdminLike)
                            <button type="button" id="deleteHandoverButton" class="btn btn-sm btn-outline-danger d-none">
                                <i class="bx bx-trash"></i> Delete Transaction
                            </button>
                        @endif
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
                                    <th style="width:30%">Product</th>
                                    <th class="text-end" style="width:10%">Carried</th>
                                    <th class="text-end" style="width:10%">Returned</th>
                                    <th class="text-end" style="width:10%">Sold</th>
                                    <th class="text-end" style="width:13%">Price</th>
                                    <th class="text-end" style="width:12%">Discount</th>
                                    <th class="text-end" style="width:14%">Price After Discount</th>
                                    <th class="text-end" style="width:14%">Sold Value</th>
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

    <!-- Modal Proof -->
    <div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary py-2 border-bottom">
                    <h5 class="modal-title text-white">Deposit Proof</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 text-center bg-dark overflow-hidden position-relative d-flex flex-column" style="height: 75vh;">
                    <div id="zoomContainer" class="flex-grow-1 w-100 d-flex align-items-center justify-content-center" style="cursor: zoom-in; overflow: hidden; min-height: 0;">
                        <img id="proofImage" src="" alt="Proof" class="img-fluid zoomable-img" style="max-height: 100%; object-fit: contain;">
                    </div>
                    <!-- Thumbnail bar for multiple proofs -->
                    <div id="proofThumbsContainer" class="w-100 py-2 bg-black bg-opacity-75 d-none flex-wrap justify-content-center gap-2 border-top border-secondary" style="z-index: 10;">
                    </div>
                    <div class="py-2 bg-dark bg-opacity-75 text-white text-center pointer-events-none" style="z-index: 5;">
                        <small id="zoomHint"><i class="bx bx-pointer me-1"></i> Click to zoom & explore</small>
                    </div>
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
            const deleteButton = document.getElementById('deleteHandoverButton');

            // 2. CONFIG & ROUTES
            const listUrl = @json(route($listRouteName));
            const detailUrlTemplate = @json(route($detailRoute, 0));
            const canSeeMargin = @json($canSeeMargin);
            const canOpenApproval = @json($canOpenApproval);
            const approvalUrlTemplate = canOpenApproval ? @json(route('warehouse.handovers.payments.form', 0)) : null;
            const deleteUrlTemplate = @json(route('sales.report.destroy', 0));
            const isAdminLike = @json($isAdminLike);

            let currentHandoverId = null;
            let currentPage = 1;

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
            async function reloadList(page = 1) {
                currentPage = page;
                const formData = new FormData(filterForm);
                formData.append('page', currentPage);
                const params = new URLSearchParams(formData);

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

                    // Update Summary
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

                    // Update Pagination UI
                    renderPagination(json.pagination);

                } catch (err) {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load data.'
                    });
                }
            }

            function renderPagination(pg) {
                const nav = document.getElementById('pgNav');
                const info = document.getElementById('pageInfo');
                if (!nav || !info) return;

                if (!pg || pg.total === 0) {
                    nav.innerHTML = '';
                    info.textContent = 'Showing 0 to 0 of 0 results';
                    return;
                }

                info.textContent = `Showing ${pg.from} to ${pg.to} of ${pg.total} results`;

                let html = '';
                // Previous
                html += `<li class="page-item ${pg.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${pg.current_page - 1}">Prev</a>
                </li>`;

                // Calculate range
                let start = Math.max(1, pg.current_page - 2);
                let end = Math.min(pg.last_page, start + 4);
                if (end === pg.last_page) start = Math.max(1, end - 4);

                for (let i = start; i <= end; i++) {
                    html += `<li class="page-item ${i === pg.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>`;
                }

                // Next
                html += `<li class="page-item ${pg.current_page === pg.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${pg.current_page + 1}">Next</a>
                </li>`;

                nav.innerHTML = html;
            }

            // 6. EVENT LISTENERS
            document.getElementById('pgNav')?.addEventListener('click', (e) => {
                const link = e.target.closest('.page-link');
                if (link) {
                    e.preventDefault();
                    const page = parseInt(link.dataset.page);
                    if (page && page !== currentPage) reloadList(page);
                }
            });

            const autoSelectors = [
                '#viewFilter',
                '#perPage',
                'input[name="date_from"]',
                'input[name="date_to"]',
                'select[name="status"]',
                'select[name="warehouse_id"]',
                'select[name="sales_id"]',
            ];
            filterForm.querySelectorAll(autoSelectors.join(',')).forEach(el => el.addEventListener('change', () =>
                reloadList(1)));
            // Handle Excel Export
            document.getElementById('btnExportSales')?.addEventListener('click', () => {
                const params = new URLSearchParams(new FormData(filterForm));
                const exportUrl = "{{ route('sales.report.export') }}";
                window.location.href = `${exportUrl}?${params.toString()}`;
            });

            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                reloadList(1);
            });
            document.getElementById('btnResetFilters')?.addEventListener('click', () => {
                filterForm.reset();
                reloadList(1);
            });

            rowsTbody.addEventListener('click', async (e) => {
                // A. Click Detail
                const btnDetail = e.target.closest('.btn-detail');
                if (btnDetail) {
                    const id = btnDetail.dataset.id;
                    if (!id) return;

                    currentHandoverId = null;
                    if (approvalButton) approvalButton.classList.add('d-none');
                    if (deleteButton) deleteButton.classList.add('d-none');

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
                        if (!json.success) throw new Error('Failed to load details');

                        const h = json.handover;
                        const it = json.items || [];
                        currentHandoverId = h.id;

                        const statusLabelMap = {
                            draft: 'Draft',
                            waiting_morning_otp: 'Waiting for Morning OTP',
                            on_sales: 'On Sales',
                            waiting_evening_otp: 'Waiting for Evening OTP (Closing)',
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

                        // 🔥 Info Buyer untuk Penjualan Langsung (POS)
                        let buyerInfoHtml = '';
                        if (h.is_direct_sale) {
                            let bType = h.buyer_type ? h.buyer_type.charAt(0).toUpperCase() + h.buyer_type.slice(1) : 'Sales';
                            buyerInfoHtml = `<div class="mb-1"><span class="fw-semibold text-muted small">Buyer Type:</span> <span class="badge bg-label-info ms-1">${bType}</span></div>`;
                            if (h.customer_name) {
                                buyerInfoHtml += `<div class="mb-1"><span class="fw-semibold text-muted small">Customer Name:</span> <span class="text-primary fw-bold">${h.customer_name}</span></div>`;
                            }
                        }

                        detailHeader.innerHTML = `
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-1"><span class="fw-semibold text-muted small">Transaction Code:</span> <span class="fw-bold">${h.code}</span></div>
                                    <div class="mb-1"><span class="fw-semibold text-muted small">Transaction Date:</span> ${h.date || '-'}</div>
                                    <div class="mb-1"><span class="fw-semibold text-muted small">Warehouse:</span> ${h.warehouse || '-'}</div>
                                    <div class="mb-1"><span class="fw-semibold text-muted small">Sales Owner:</span> ${h.sales || '-'}</div>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    ${buyerInfoHtml}
                                    <div class="mt-2">
                                        <span class="fw-semibold text-muted small">Status:</span>
                                        <span class="badge ${badgeClass} ms-1">${stLabel}</span>
                                    </div>
                                </div>
                            </div>
                        `;

                        if (approvalButton) {
                            if (canOpenApproval && approvalUrlTemplate && h.can_open_approval) {
                                approvalButton.classList.remove('d-none');
                            } else {
                                approvalButton.classList.add('d-none');
                            }
                        }

                        if (deleteButton) {
                            // PM Request: Admin can delete even if settled. Backend handles settlement adjustment.
                            if (isAdminLike) {
                                deleteButton.classList.remove('d-none');
                                deleteButton.dataset.id = h.id;
                                deleteButton.dataset.code = h.code;
                                deleteButton.dataset.isSettled = h.settlement_id ? 'yes' : 'no'; // Track for UI warning
                            } else {
                                deleteButton.classList.add('d-none');
                            }
                        }

                        let htmlItems = '';
                        it.forEach(row => {
                            const discMode = row.discount_mode || 'unit';
                            const discUnit = row.discount_per_unit || 0;
                            const discFixed = row.discount_fixed_amount || 0;

                            let discLabel = '-';
                            if (discMode === 'fixed' && discFixed > 0) {
                                discLabel = `${formatRp(discFixed)} <br><small class="text-info fw-semibold">(Fixed)</small>`;
                            } else if (discUnit > 0) {
                                discLabel = `${formatRp(discUnit)} <br><small class="text-muted">(Unit)</small>`;
                            }

                            let priceAfterHtml = '';
                            if (discMode === 'fixed') {
                                const netTotal = row.line_net_dispatched || 0;
                                priceAfterHtml = `${formatRp(netTotal)}<br><small class="text-info">(Net)</small>`;
                            } else {
                                priceAfterHtml = formatRp(row.unit_price_after_discount ?? row.unit_price ?? 0);
                            }

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
                                    <td class="text-end">${discLabel}</td>
                                    <td class="text-end">${priceAfterHtml}</td>
                                    <td class="text-end fw-semibold">
                                        ${(row.qty_sold ?? 0) > 0 ? formatRp(row.line_total_sold || 0) : formatRp(0)}
                                    </td>
                                </tr>`;
                        });
                        if (!htmlItems) htmlItems = '<tr><td colspan="8" class="text-center text-muted">No items available.</td></tr>';
                        detailTbody.innerHTML = htmlItems;

                        const s = json.summary || {};
                        let proofHtml = '-';
                        if (s.proof_urls && s.proof_urls.length > 0) {
                            const totalProofs = s.proof_urls.length;
                            const firstUrl = s.proof_urls[0];
                            proofHtml = `
                                <div class="mt-2">
                                    <div class="position-relative d-inline-block shadow-sm rounded overflow-hidden" style="cursor:pointer;" id="btnOpenHandoverProof" data-urls='${JSON.stringify(s.proof_urls)}'>
                                        <img src="${firstUrl}" class="img-thumbnail" style="max-width:140px; height:120px; object-fit:cover; display:block;">
                                        ${totalProofs > 1 ? `
                                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 text-white fw-bold fs-6">
                                                <i class="bx bx-images me-1"></i> +${totalProofs - 1} More
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="small text-muted mt-1"><i class="bx bx-search-alt me-1"></i>Click to view proof</div>
                                </div>`;
                        } else if (s.proof_url) {
                            proofHtml = `
                                <div class="mt-2">
                                    <div class="position-relative d-inline-block shadow-sm rounded overflow-hidden" style="cursor:pointer;" id="btnOpenHandoverProof" data-urls='${JSON.stringify([s.proof_url])}'>
                                        <img src="${s.proof_url}" class="img-thumbnail" style="max-width:140px; height:120px; object-fit:cover; display:block;">
                                    </div>
                                    <div class="small text-muted mt-1"><i class="bx bx-search-alt me-1"></i>Click to view proof</div>
                                </div>`;
                        }

                        detailSummary.innerHTML = `
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Carried Value</div>
                                    <div>${formatRp(s.total_dispatched || 0)}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Sold Value</div>
                                    <div>${h.status === 'closed' ? formatRp(s.total_sold) : '<span class="text-muted small">— not closed yet —</span>'}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Remaining Stock Value</div>
                                    <div>${h.status === 'closed' ? formatRp(s.total_remaining || 0) : '<span class="text-muted small">— not closed yet —</span>'}</div>
                                </div>
                            </div>
                            <hr>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Cash Deposit</div>
                                    <div>${formatRp(s.cash_amount || 0)}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Transfer Deposit</div>
                                    <div>${formatRp(s.transfer_amount || 0)}</div>
                                    ${proofHtml}
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-semibold text-muted small">Total Deposit</div>
                                    <div class="fw-bold">${formatRp(s.total_deposit || 0)}</div>
                                    <div class="small text-muted">Diff Sold vs Deposit: ${formatRp(s.diff_sold_vs_deposit || 0)}</div>
                                </div>
                            </div>
                        `;              bsModal.show();
                    } catch (err) {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: 'Failed to load handover details.'
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

            // View Handover Proof Modal
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('#btnOpenHandoverProof');
                if (btn) {
                    let urls = [];
                    try {
                        urls = JSON.parse(btn.dataset.urls);
                    } catch (err) {
                        console.error('Failed to parse urls:', err);
                    }

                    const proofModalEl = document.getElementById('proofModal');
                    const proofImg = document.getElementById('proofImage');
                    const thumbsContainer = document.getElementById('proofThumbsContainer');
                    const zoomContainer = document.getElementById('zoomContainer');
                    const zoomHint = document.getElementById('zoomHint');

                    if (urls && urls.length > 0) {
                        proofImg.src = urls[0];
                        
                        // Render thumbnails if multiple
                        if (urls.length > 1) {
                            thumbsContainer.classList.remove('d-none');
                            thumbsContainer.classList.add('d-flex');
                            thumbsContainer.innerHTML = urls.map((url, idx) => `
                                <img src="${url}" class="img-thumbnail proof-thumb-item ${idx === 0 ? 'border-primary' : 'border-secondary'}" 
                                     style="width: 50px; height: 50px; object-fit: cover; cursor: pointer; opacity: ${idx === 0 ? '1' : '0.6'}; transition: all 0.2s;"
                                     onclick="changeHandoverProofActiveImage(this, '${url}')">
                            `).join('');
                        } else {
                            thumbsContainer.classList.add('d-none');
                            thumbsContainer.classList.remove('d-flex');
                            thumbsContainer.innerHTML = '';
                        }
                    } else {
                        proofImg.src = '';
                        thumbsContainer.classList.add('d-none');
                        thumbsContainer.classList.remove('d-flex');
                        thumbsContainer.innerHTML = '';
                    }

                    zoomContainer.classList.remove('is-zoomed');
                    zoomContainer.style.cursor = 'zoom-in';
                    zoomHint.innerHTML = '<i class="bx bx-pointer me-1"></i> Click to zoom & explore';

                    const bsProofModal = new bootstrap.Modal(proofModalEl);
                    bsProofModal.show();
                }
            });

            window.changeHandoverProofActiveImage = function(thumb, url) {
                const proofImg = document.getElementById('proofImage');
                const zoomContainer = document.getElementById('zoomContainer');
                const zoomHint = document.getElementById('zoomHint');

                proofImg.src = url;
                document.querySelectorAll('.proof-thumb-item').forEach(el => {
                    el.classList.remove('border-primary');
                    el.classList.add('border-secondary');
                    el.style.opacity = '0.6';
                });
                thumb.classList.remove('border-secondary');
                thumb.classList.add('border-primary');
                thumb.style.opacity = '1';
                
                // Reset zoom state
                zoomContainer.classList.remove('is-zoomed');
                zoomContainer.style.cursor = 'zoom-in';
                zoomHint.innerHTML = '<i class="bx bx-pointer me-1"></i> Click to zoom & explore';
            };

            // Zoom interactivity
            document.addEventListener('click', (e) => {
                const zoomContainer = e.target.closest('#zoomContainer');
                if (zoomContainer) {
                    const isZoomed = zoomContainer.classList.toggle('is-zoomed');
                    const zoomHint = document.getElementById('zoomHint');
                    if (isZoomed) {
                        zoomContainer.style.cursor = 'zoom-out';
                        zoomHint.innerHTML = '<i class="bx bx-move me-1"></i> Move to explore | Click to zoom out';
                    } else {
                        zoomContainer.style.cursor = 'zoom-in';
                        zoomHint.innerHTML = '<i class="bx bx-pointer me-1"></i> Click to zoom & explore';
                    }
                }
            });

            document.addEventListener('mousemove', (e) => {
                const zoomContainer = e.target.closest('#zoomContainer');
                if (zoomContainer && zoomContainer.classList.contains('is-zoomed')) {
                    const rect = zoomContainer.getBoundingClientRect();
                    const x = ((e.clientX - rect.left) / rect.width) * 100;
                    const y = ((e.clientY - rect.top) / rect.height) * 100;
                    const proofImg = document.getElementById('proofImage');
                    if (proofImg) {
                        proofImg.style.transformOrigin = `${x}% ${y}%`;
                    }
                }
            });

            if (deleteButton) {
                deleteButton.addEventListener('click', async function() {
                    this.blur(); // Fix aria-hidden focus warning
                    const id = deleteButton.dataset.id;
                    const code = deleteButton.dataset.code;
                    if (!id) return;

                    const isSettled = deleteButton.dataset.isSettled === 'yes';
                    let swalText = `You are about to delete transaction ${code}. This will REVERT all sold items back to the warehouse stock. This action cannot be undone!`;
                    
                    if (isSettled) {
                        swalText = `<strong>WARNING:</strong> Transaction ${code} is already <strong>SETTLED</strong>. Deleting this will automatically subtract its value from the financial settlement report. Are you absolutely sure?`;
                    }

                    const { isConfirmed } = await Swal.fire({
                        title: isSettled ? 'Delete Settled Transaction?' : 'Are you sure?',
                        html: swalText,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, Delete and Revert Stock!',
                        showLoaderOnConfirm: true,
                        preConfirm: async () => {
                            try {
                                const res = await fetch(deleteUrlTemplate.replace('/0', '/' + id), {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                });
                                const json = await res.json();
                                if (!res.ok) throw new Error(json.message || 'Failed to delete');
                                return json;
                            } catch (error) {
                                Swal.showValidationMessage(`Request failed: ${error}`);
                            }
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    });

                    if (isConfirmed) {
                        Swal.fire('Deleted!', `Transaction ${code} has been deleted and stock reverted.`, 'success');
                        bsModal.hide();
                        reloadList(currentPage);
                    }
                });
            }

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
