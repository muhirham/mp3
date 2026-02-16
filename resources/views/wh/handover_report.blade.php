@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        .swal2-container {
            z-index: 2005 !important;
        }

        .table {
            font-size: 12px;
        }

        .table thead th {
            font-size: 11px;
            white-space: nowrap;
        }

        .table tbody td {
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .badge {
            font-size: 10px;
            padding: 3px 6px;
        }

        .table td,
        .table th {
            white-space: nowrap;
        }
    </style>

    @php
        $me = $me ?? auth()->user();
        $roles = $me?->roles ?? collect();

        $isWarehouse = $roles->contains('slug', 'warehouse');
        $isSales = $roles->contains('slug', 'sales');
        $isAdminLike = $roles->contains('slug', 'admin') || $roles->contains('slug', 'superadmin');

        $canSeeMargin =
            $roles->contains('slug', 'superadmin') ||
            $roles->contains('slug', 'admin') ||
            $roles->contains('slug', 'warehouse') ||
            $roles->contains('slug', 'procurement') ||
            $roles->contains('slug', 'ceo');

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
                        <label class="form-label">Tampilan</label>
                        <select name="view" id="viewFilter" class="form-select">
                            <option value="handover" @selected($view === 'handover')>Detail Handover</option>
                            <option value="sales" @selected($view === 'sales')>Rekap per Sales</option>
                            <option value="daily" @selected($view === 'daily')>Rekap per Hari</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Sampai Tanggal</label>
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
                                {{ $lockWarehouseForSales ? 'Report ini hanya untuk warehouse kamu.' : 'User ini terkunci ke warehouse tersebut.' }}
                            </div>
                        @else
                            <select name="warehouse_id" class="form-select">
                                <option value="">Semua Gudang</option>
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
                            <div class="form-text">Report ini hanya untuk handover milik kamu.</div>
                        @else
                            <select name="sales_id" class="form-select">
                                <option value="">Semua Sales</option>
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

                    <div class="col-md-3 d-flex gap-2">
                        <a href="{{ route($listRouteName) }}" class="btn btn-outline-secondary flex-fill mt-3 mt-md-0">
                            Reset
                        </a>
                    </div>


                    <div class="col-md-3 d-flex gap-2">
                        <a href="#" id="btnExportSales" class="btn btn-success">
                            Export Excel
                        </a>
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
                                <div class="fw-semibold text-muted small">Total Diskon</div>
                                <div class="fs-5 fw-bold text-danger" id="sumDiscount">
                                    -{{ $summary['total_discount'] ?? 'Rp 0' }}
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- KANAN --}}
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-semibold text-muted small">Total Nilai Penjualan (Closed)</div>
                            <div class="fs-5 fw-bold" id="sumSold">{{ $summary['total_sold_formatted'] }}</div>
                        </div>

                        {{-- Nilai sisa stok tepat di bawah penjualan --}}
                        <div class="pt-1">
                            <div class="fw-semibold text-muted small">Perkiraan Nilai Sisa Stok</div>
                            <div class="fs-5 fw-bold" id="sumDiff">{{ $summary['total_diff_formatted'] }}</div>
                        </div>
                    </div>

                </div>

                <div class="mt-2 text-muted small text-center">
                    Periode:
                    <span class="fw-semibold" id="periodText">{{ $summary['period_text'] }}</span>
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
                                    <th style="width:18%">Sales</th>
                                    <th style="width:18%">Warehouse</th>
                                    <th class="text-end" style="width:10%">HDO</th>
                                    <th class="text-end" style="width:16%">Total Dibawa</th>
                                    <th class="text-end" style="width:16%">Total Terjual (Closed)</th>
                                    <th class="text-end" style="width:16%">Total Setor</th>
                                    <th style="width:8%"></th>
                                </tr>
                            @elseif($view === 'daily')
                                <tr>
                                    <th style="width:4%">#</th>
                                    <th style="width:14%">Tanggal</th>
                                    <th class="text-end" style="width:10%">HDO</th>
                                    <th class="text-end" style="width:18%">Total Dibawa</th>
                                    <th class="text-end" style="width:18%">Total Terjual (Closed)</th>
                                    <th class="text-end" style="width:18%">Total Setor</th>
                                    <th style="width:8%"></th>
                                </tr>
                            @else
                                <tr>
                                    <th style="width:4%">#</th>
                                    <th style="width:10%">Tanggal</th>
                                    <th style="width:13%">Kode</th>
                                    <th style="width:18%">Warehouse</th>
                                    <th style="width:16%">Sales</th>
                                    <th style="width:11%">Status</th>
                                    <th class="text-end" style="width:12%">Nilai Dibawa</th>
                                    <th class="text-end" style="width:12%">Terjual (After Disc)</th>

                                    @if ($canSeeMargin)
                                        <th class="text-end" style="width:12%">Harga Asli</th>
                                        <th class="text-end" style="width:12%">Diskon</th>
                                    @endif

                                    <th class="text-end" style="width:12%">Selisih (stok)</th>
                                    <th style="width:8%"></th>
                                </tr>
                            @endif
                        </thead>

                        <tbody id="handoverRows">
                            @forelse(($rows ?? []) as $r)
                                @if ($view === 'sales')
                                    <tr>
                                        <td>{{ $r['no'] }}</td>
                                        <td class="fw-semibold">{{ $r['sales'] }}</td>
                                        <td>{{ $r['warehouse'] }}</td>
                                        <td class="text-end">{{ $r['handover_count'] }}</td>
                                        <td class="text-end">{{ $r['amount_dispatched'] }}</td>
                                        <td class="text-end">{{ $r['amount_sold'] }}</td>
                                        <td class="text-end">{{ $r['amount_setor'] }}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-drill-sales"
                                                data-sales-id="{{ $r['sales_id'] }}">
                                                Lihat
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
                                                Lihat
                                            </button>
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>{{ $r['no'] }}</td>
                                        <td>{{ $r['date'] }}</td>
                                        <td class="fw-semibold">{{ $r['code'] }}</td>
                                        <td>{{ $r['warehouse'] }}</td>
                                        <td>{{ $r['sales'] }}</td>
                                        <td><span
                                                class="badge {{ $r['status_badge_class'] }}">{{ $r['status_label'] }}</span>
                                        </td>
                                        <td class="text-end">{{ $r['amount_dispatched'] }}</td>
                                        <td class="text-end fw-bold">{{ $r['amount_sold'] }}</td>

                                        @if ($canSeeMargin)
                                            <td class="text-end text-muted">{{ $r['amount_original'] }}</td>
                                            <td class="text-end text-danger">-{{ $r['amount_discount'] }}</td>
                                        @endif

                                        <td class="text-end">{{ $r['amount_diff'] }}</td>

                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-detail"
                                                data-id="{{ $r['id'] }}">
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">Belum ada data pada periode ini.
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
            const filterForm = document.getElementById('reportFilterForm');
            const rowsTbody = document.getElementById('handoverRows');
            const headEl = document.getElementById('reportHead');
            const viewEl = document.getElementById('viewFilter');

            const sumHdoEl = document.getElementById('sumHdo');
            const sumSoldEl = document.getElementById('sumSold');
            const sumDiffEl = document.getElementById('sumDiff');
            const periodTextEl = document.getElementById('periodText');

            const listUrl = @json(route($listRouteName));
            const detailUrlTemplate = @json(route($detailRoute, 0));

            const modalEl = document.getElementById('handoverDetailModal');
            const detailHeader = document.getElementById('detailHeader');
            const detailSummary = document.getElementById('detailSummary');
            const detailTbody = document.querySelector('#detailItemsTable tbody');
            const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

            const canOpenApproval = @json($canOpenApproval);
            const approvalButton = document.getElementById('approvalButton');
            const approvalUrlTemplate = canOpenApproval ? @json(route('warehouse.handovers.payments.form', 0)) : null;
            let currentHandoverId = null;

            function formatRp(num) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(num || 0);
            }

            const canSeeMargin = @json($canSeeMargin);

            document.getElementById('btnExportSales').addEventListener('click', function () {

                const params = new URLSearchParams(window.location.search);

                const view = document.querySelector('[name="view"]').value;
                const dateFrom = document.querySelector('[name="date_from"]').value;
                const dateTo = document.querySelector('[name="date_to"]').value;
                const warehouse = document.querySelector('[name="warehouse_id"]').value;
                const sales = document.querySelector('[name="sales_id"]').value;
                const status = document.querySelector('[name="status"]').value;

                params.set('view', view);
                params.set('date_from', dateFrom);
                params.set('date_to', dateTo);
                params.set('warehouse_id', warehouse);
                params.set('sales_id', sales);
                params.set('status', status);

                window.location.href = '/reports/sales/export?' + params.toString();
            });

            function renderHead(view) {
                if (view === 'sales') {
                    headEl.innerHTML = `
        <tr>
          <th style="width:4%">#</th>
          <th style="width:18%">Sales</th>
          <th style="width:18%">Warehouse</th>
          <th class="text-end" style="width:10%">HDO</th>
          <th class="text-end" style="width:16%">Total Dibawa</th>
          <th class="text-end" style="width:16%">Total Terjual (Closed)</th>
          <th class="text-end" style="width:16%">Total Setor</th>
          <th style="width:8%"></th>
        </tr>
      `;
                    return;
                }

                if (view === 'daily') {
                    headEl.innerHTML = `
        <tr>
          <th style="width:4%">#</th>
          <th style="width:14%">Tanggal</th>
          <th class="text-end" style="width:10%">HDO</th>
          <th class="text-end" style="width:18%">Total Dibawa</th>
          <th class="text-end" style="width:18%">Total Terjual (Closed)</th>
          <th class="text-end" style="width:18%">Total Setor</th>
          <th style="width:8%"></th>
        </tr>
      `;
                    return;
                }

                let extra = '';
                if (canSeeMargin) {
                    extra = `
          <th class="text-end" style="width:12%">Harga Asli</th>
          <th class="text-end" style="width:12%">Diskon</th>
        `;
                }

                headEl.innerHTML = `
        <tr>
          <th style="width:4%">#</th>
          <th style="width:10%">Tanggal</th>
          <th style="width:13%">Kode</th>
          <th style="width:18%">Warehouse</th>
          <th style="width:16%">Sales</th>
          <th style="width:11%">Status</th>
          <th class="text-end" style="width:12%">Nilai Dibawa</th>
          <th class="text-end" style="width:12%">Terjual (After Disc)</th>
          ${extra}
          <th class="text-end" style="width:12%">Selisih (stok)</th>
          <th style="width:8%"></th>
        </tr>
      `;
            }

            function renderRows(view, rows) {
                if (!rows || !rows.length) {
                    rowsTbody.innerHTML =
                        `<tr><td colspan="10" class="text-center text-muted">Belum ada data pada periode ini.</td></tr>`;
                    return;
                }

                if (view === 'sales') {
                    rowsTbody.innerHTML = rows.map(r => `
        <tr>
          <td>${r.no}</td>
          <td class="fw-semibold">${r.sales}</td>
          <td>${r.warehouse}</td>
          <td class="text-end">${r.handover_count}</td>
          <td class="text-end">${r.amount_dispatched}</td>
          <td class="text-end">${r.amount_sold}</td>
          <td class="text-end">${r.amount_setor}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-primary btn-drill-sales" data-sales-id="${r.sales_id}">Lihat</button>
          </td>
        </tr>
      `).join('');
                    return;
                }

                if (view === 'daily') {
                    rowsTbody.innerHTML = rows.map(r => `
        <tr>
          <td>${r.no}</td>
          <td class="fw-semibold">${r.date}</td>
          <td class="text-end">${r.handover_count}</td>
          <td class="text-end">${r.amount_dispatched}</td>
          <td class="text-end">${r.amount_sold}</td>
          <td class="text-end">${r.amount_setor}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-primary btn-drill-day" data-date="${r.date}">Lihat</button>
          </td>
        </tr>
      `).join('');
                    return;
                }

                rowsTbody.innerHTML = rows.map(r => {
                    let marginCols = '';

                    if (canSeeMargin) {
                        marginCols = `
      <td class="text-end text-muted">${r.amount_original}</td>
      <td class="text-end text-danger">-${r.amount_discount}</td>
    `;
                    }

                    return `
    <tr>
      <td>${r.no}</td>
      <td>${r.date || '-'}</td>
      <td class="fw-semibold">${r.code}</td>
      <td>${r.warehouse}</td>
      <td>${r.sales}</td>
      <td><span class="badge ${r.status_badge_class}">${r.status_label}</span></td>
      <td class="text-end">${r.amount_dispatched}</td>
      <td class="text-end fw-bold">${r.amount_sold}</td>
      ${marginCols}
      <td class="text-end">${r.amount_diff}</td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-primary btn-detail" data-id="${r.id}">
          Detail
        </button>
      </td>
    </tr>
  `;
                }).join('');

            }

            async function reloadList() {
                const fd = new FormData(filterForm);
                const params = new URLSearchParams(fd);

                try {
                    const res = await fetch(listUrl + '?' + params.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    const json = await res.json();
                    if (!json.success) throw new Error('Response tidak valid');

                    const view = json.view || viewEl.value || 'handover';
                    renderHead(view);
                    renderRows(view, json.rows || []);

                    if (json.summary) {
                        if (sumHdoEl) sumHdoEl.textContent = json.summary.total_hdo_text || '0 HDO';
                        if (sumSoldEl) sumSoldEl.textContent = json.summary.total_sold_formatted || 'Rp 0';
                        if (sumDiffEl) sumDiffEl.textContent = json.summary.total_diff_formatted || 'Rp 0';
                        if (periodTextEl) periodTextEl.textContent = json.summary.period_text || '-';
                        const sumDiscountEl = document.querySelector('#sumDiscount');

                        if (sumDiscountEl) {
                            sumDiscountEl.textContent = json.summary.total_discount || 'Rp 0';
                        }

                    }
                } catch (err) {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Gagal memuat data report.'
                    });
                }
            }

            // auto reload ketika filter berubah
            const autoSelectors = [
                '#viewFilter',
                'input[name="date_from"]',
                'input[name="date_to"]',
                'select[name="status"]',
                'select[name="warehouse_id"]',
                'select[name="sales_id"]',
            ];
            filterForm.querySelectorAll(autoSelectors.join(',')).forEach(el => el.addEventListener('change',
                reloadList));
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                reloadList();
            });

            // search navbar
            const hiddenSearch = document.getElementById('searchBox');
            const navbarSearch = document.querySelector('.layout-navbar input[placeholder*="Search"]');
            if (navbarSearch && hiddenSearch) {
                navbarSearch.value = hiddenSearch.value || '';
                let timer = null;
                navbarSearch.addEventListener('keyup', function() {
                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        hiddenSearch.value = navbarSearch.value;
                        reloadList();
                    }, 400);
                });
            }

            rowsTbody.addEventListener('click', async (e) => {
                const btnDetail = e.target.closest('.btn-detail');
                if (btnDetail) {
                    const id = btnDetail.dataset.id;
                    if (!id) return;

                    currentHandoverId = null;
                    if (approvalButton) approvalButton.classList.add('d-none');

                    detailHeader.innerHTML = 'Loading...';
                    detailTbody.innerHTML =
                        '<tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>';
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

                        if (canOpenApproval && approvalButton && approvalUrlTemplate) {
                            approvalButton.classList.remove('d-none');
                        }

                        let htmlItems = '';
                        it.forEach(row => {
                            const hasDiscount = (row.discount_per_unit ?? 0) > 0;

                            htmlItems += `
            <tr>
              <td>
                <div class="fw-semibold">${row.product_name}</div>
                ${row.product_code ? `<div class="small text-muted">${row.product_code}</div>` : ''}
              </td>

              <td class="text-end">${row.qty_start ?? 0}</td>
              <td class="text-end">${row.qty_returned ?? 0}</td>
              <td class="text-end">${row.qty_sold ?? 0}</td>

              <!-- HARGA ASLI -->
              <td class="text-end">${formatRp(row.unit_price || 0)}</td>

              <!-- DISKON -->
                  <td class="text-end">
                    ${hasDiscount ? formatRp(row.discount_per_unit) : '-'}
                  </td>
              <!-- HARGA SETELAH DISKON -->
              <td class="text-end">
                ${hasDiscount
                  ? formatRp(row.unit_price_after_discount)
                  : formatRp(row.line_total_sold)}
              </td>

              <!-- NILAI TERJUAL -->
              <td class="text-end fw-semibold">
                ${formatRp(row.line_total_sold || 0)}
              </td>
            </tr>
            `;
                        });
                        if (!htmlItems) htmlItems =
                            '<tr><td colspan="7" class="text-center text-muted">Tidak ada item.</td></tr>';
                        detailTbody.innerHTML = htmlItems;

                        // BUKTI TF (BALIKIN)
                        let proofHtml = '-';
                        if (h.transfer_proof_url) {
                            proofHtml = `
            <div class="mt-1">
              <img src="${h.transfer_proof_url}" class="img-thumbnail proof-thumb" style="max-width:140px;cursor:pointer;">
              <div class="small text-muted mt-1">Klik gambar untuk memperbesar.</div>
            </div>
          `;
                        }

                        detailSummary.innerHTML = `
          <div class="row g-2">
            <div class="col-md-4">
              <div class="fw-semibold text-muted small">Nilai Dibawa</div>
              <div>${formatRp(h.total_dispatched || 0)}</div>
            </div>
            <div class="col-md-4">
              <div class="fw-semibold text-muted small">Nilai Terjual</div>
              <div>${formatRp(h.total_sold)}</div>
            </div>
            <div class="col-md-4">
              <div class="fw-semibold text-muted small">Nilai Sisa Stok (estimasi)</div>
              <div>${formatRp(h.selisih_stock_value || 0)}</div>
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
          </div>
        `;

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

                const btnSales = e.target.closest('.btn-drill-sales');
                if (btnSales) {
                    const salesId = btnSales.dataset.salesId;
                    if (!salesId) return;

                    viewEl.value = 'handover';

                    const salesSelect = filterForm.querySelector('select[name="sales_id"]');
                    const salesHidden = filterForm.querySelector('input[name="sales_id"]');
                    if (salesSelect) salesSelect.value = salesId;
                    if (salesHidden) salesHidden.value = salesId;

                    reloadList();
                    return;
                }

                const btnDay = e.target.closest('.btn-drill-day');
                if (btnDay) {
                    const date = btnDay.dataset.date;
                    if (!date) return;

                    viewEl.value = 'handover';
                    const df = filterForm.querySelector('input[name="date_from"]');
                    const dt = filterForm.querySelector('input[name="date_to"]');
                    if (df) df.value = date;
                    if (dt) dt.value = date;

                    reloadList();
                    return;
                }
            });

            // zoom bukti transfer
            if (modalEl) {
                modalEl.addEventListener('click', (e) => {
                    const img = e.target.closest('.proof-thumb');
                    if (!img) return;

                    Swal.fire({
                        imageUrl: img.src,
                        showConfirmButton: false,
                        showCloseButton: true,
                        width: 'auto',
                        background: '#000',
                    });
                });
            }

            // approval button
            if (approvalButton && canOpenApproval && approvalUrlTemplate) {
                approvalButton.addEventListener('click', function() {
                    if (!currentHandoverId) return;
                    const url = approvalUrlTemplate.replace('/0', '/' + currentHandoverId);
                    window.location.href = url;
                });
            }
        })();
    </script>
@endpush
