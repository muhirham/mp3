@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    .swal2-container {
        z-index: 2005 !important;
    }
</style>

@php
    $me     = $me ?? auth()->user();
    $roles  = $me?->roles ?? collect();

    $isWarehouse = $roles->contains('slug', 'warehouse');
    $isSales     = $roles->contains('slug', 'sales');
    $isAdminLike = $roles->contains('slug', 'admin')
                || $roles->contains('slug', 'superadmin');

    $statusLabels = $statusOptions ?? [];

    $isSalesMenu   = request()->routeIs('daily.sales.report');
    $listRouteName = $isSalesMenu ? 'daily.sales.report' : 'sales.report';
    $detailRoute   = $isSalesMenu ? 'daily.report.detail' : 'sales.report.detail';

    if ($isSales && ! $isAdminLike && ! $isWarehouse) {
        $pageTitle = 'Daily Report Handover Saya';
    } elseif ($isWarehouse) {
        $pageTitle = 'Sales Reports (Admin Warehouse)';
    } else {
        $pageTitle = 'Sales Reports';
    }

    $search = $search ?? '';
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- FILTER + REKAP --}}
  <div class="card mb-3">
    <div class="card-body">
      <form id="reportFilterForm"
            method="GET"
            action="{{ route($listRouteName) }}"
            class="row g-3 align-items-end">

        {{-- hidden q untuk search global navbar --}}
        <input type="hidden" id="searchBox" name="q" value="{{ $search }}">

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
              $lockWarehouseForSales = $isSales && ! $isAdminLike && ! $isWarehouse && $me?->warehouse_id;
          @endphp

          @if(($isWarehouse && $me?->warehouse_id && ! $isAdminLike) || $lockWarehouseForSales)
            @php
                $lockedWarehouseId = $lockWarehouseForSales
                    ? $me->warehouse_id
                    : $warehouseId;

                $lockedWh = $warehouses->firstWhere('id', $lockedWarehouseId);
                $lockedName = $lockedWh->warehouse_name
                              ?? $lockedWh->warehouse_code
                              ?? ('Warehouse #' . $lockedWarehouseId);
            @endphp

            <input type="hidden" name="warehouse_id" value="{{ $lockedWarehouseId }}">
            <input type="text" class="form-control" value="{{ $lockedName }}" readonly>
            <div class="form-text">
                {{ $lockWarehouseForSales ? 'Report ini hanya untuk warehouse kamu.' : 'User ini terkunci ke warehouse tersebut.' }}
            </div>
          @else
            <select name="warehouse_id" class="form-select">
              <option value="">Semua Gudang</option>
              @foreach($warehouses as $w)
                @php
                    $whLabel = $w->warehouse_name
                              ?? $w->warehouse_code
                              ?? ('Warehouse #'.$w->id);
                @endphp
                <option value="{{ $w->id }}" @selected($warehouseId == $w->id)>
                  {{ $whLabel }}
                </option>
              @endforeach
            </select>
          @endif
        </div>

        {{-- FILTER SALES --}}
        <div class="col-md-3">
          <label class="form-label">Sales</label>

          @if($isSales && ! $isAdminLike && ! $isWarehouse)
            {{-- sales murni: hanya dirinya sendiri --}}
            <input type="hidden" name="sales_id" value="{{ $me->id }}">
            <input type="text" class="form-control" value="{{ $me->name }}" readonly>
            <div class="form-text">Report ini hanya untuk handover milik kamu.</div>
          @else
            <select name="sales_id" class="form-select">
              <option value="">Semua Sales</option>
              @foreach($salesList as $s)
                <option value="{{ $s->id }}" @selected($salesId == $s->id)>
                  {{ $s->name }}
                </option>
              @endforeach
            </select>
          @endif
        </div>

        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            @foreach($statusLabels as $key => $label)
              <option value="{{ $key }}" @selected($status === (string) $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        {{-- Hanya tombol Reset, tanpa "Terapkan Filter" --}}
        <div class="col-md-3 d-flex gap-2">
          <a href="{{ route($listRouteName) }}"
             class="btn btn-outline-secondary flex-fill mt-3 mt-md-0">
            Reset
          </a>
        </div>
      </form>

      <hr>

      <div class="row text-center">
        <div class="col-md-4 mb-2">
          <div class="fw-semibold text-muted small">Total Nilai Barang Dibawa</div>
          <div class="fs-5 fw-bold" id="sumDispatched">
            {{ 'Rp '.number_format($totalDispatched, 0, ',', '.') }}
          </div>
        </div>
        <div class="col-md-4 mb-2">
          <div class="fw-semibold text-muted small">Total Nilai Penjualan (Closed)</div>
          <div class="fs-5 fw-bold" id="sumSold">
            {{ 'Rp '.number_format($totalSold, 0, ',', '.') }}
          </div>
        </div>
        <div class="col-md-4 mb-2">
          <div class="fw-semibold text-muted small">Perkiraan Nilai Sisa Stok</div>
          <div class="fs-5 fw-bold" id="sumDiff">
            {{ 'Rp '.number_format($totalDiff, 0, ',', '.') }}
          </div>
        </div>
      </div>

      <div class="mt-2 text-muted small">
        Periode: <span class="fw-semibold" id="periodText">{{ $dateFrom }} s/d {{ $dateTo }}</span>
      </div>
    </div>
  </div>

  {{-- TABEL HANDOVER --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-bold">{{ $pageTitle }}</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead>
          <tr>
            <th style="width:4%">#</th>
            <th style="width:10%">Tanggal</th>
            <th style="width:13%">Kode</th>
            <th style="width:18%">Warehouse</th>
            <th style="width:16%">Sales</th>
            <th style="width:11%">Status</th>
            <th class="text-end" style="width:12%">Nilai Dibawa</th>
            <th class="text-end" style="width:12%">Nilai Terjual</th>
            <th class="text-end" style="width:12%">Selisih (stok)</th>
            <th style="width:8%"></th>
          </tr>
          </thead>
          <tbody id="handoverRows">
            @php
                $statusLabels = $statusLabels ?? [];
            @endphp

            @forelse($handovers as $idx => $h)
                @php
                    $whName = optional($h->warehouse)->warehouse_name
                             ?? optional($h->warehouse)->name
                             ?? '-';

                    $salesName = optional($h->sales)->name ?? ('Sales #'.$h->sales_id);

                    $dispatched = (int) $h->total_dispatched_amount;
                    $sold       = (int) $h->total_sold_amount;
                    $diff       = max(0, $dispatched - $sold);

                    $stLabel    = $statusLabels[$h->status] ?? $h->status;

                    $badgeClass = 'bg-label-secondary';
                    if ($h->status === 'closed') {
                        $badgeClass = 'bg-label-success';
                    } elseif ($h->status === 'on_sales') {
                        $badgeClass = 'bg-label-info';
                    } elseif ($h->status === 'waiting_morning_otp' || $h->status === 'waiting_evening_otp') {
                        $badgeClass = 'bg-label-warning';
                    } elseif ($h->status === 'cancelled') {
                        $badgeClass = 'bg-label-danger';
                    }
                @endphp

                <tr>
                  <td>{{ $idx + 1 }}</td>
                  <td>{{ optional($h->handover_date)->format('Y-m-d') }}</td>
                  <td class="fw-semibold">{{ $h->code }}</td>
                  <td>{{ $whName }}</td>
                  <td>{{ $salesName }}</td>
                  <td>
                    <span class="badge {{ $badgeClass }}">{{ $stLabel }}</span>
                  </td>
                  <td class="text-end">
                    {{ 'Rp '.number_format($dispatched, 0, ',', '.') }}
                  </td>
                  <td class="text-end">
                    {{ 'Rp '.number_format($sold, 0, ',', '.') }}
                  </td>
                  <td class="text-end">
                    {{ 'Rp '.number_format($diff, 0, ',', '.') }}
                  </td>
                  <td class="text-end">
                    <button type="button"
                            class="btn btn-sm btn-outline-primary btn-detail"
                            data-id="{{ $h->id }}">
                      Detail
                    </button>
                  </td>
                </tr>
            @empty
                <tr>
                  <td colspan="10" class="text-center text-muted">
                    Belum ada data handover pada periode ini.
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
      <div class="modal-header">
        <h5 class="modal-title">Detail Handover</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
              <th class="text-end" style="width:15%">Harga</th>
              <th class="text-end" style="width:12%">Nilai Dibawa</th>
              <th class="text-end" style="width:13%">Nilai Terjual</th>
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
(function () {
    const modalEl       = document.getElementById('handoverDetailModal');
    const detailHeader  = document.getElementById('detailHeader');
    const detailSummary = document.getElementById('detailSummary');
    const detailTbody   = document.querySelector('#detailItemsTable tbody');

    const filterForm    = document.getElementById('reportFilterForm');
    const hiddenSearch  = document.getElementById('searchBox');
    const rowsTbody     = document.getElementById('handoverRows');

    const sumDispatchedEl = document.getElementById('sumDispatched');
    const sumSoldEl       = document.getElementById('sumSold');
    const sumDiffEl       = document.getElementById('sumDiff');
    const periodTextEl    = document.getElementById('periodText');

    if (!modalEl) return;

    const bsModal = new bootstrap.Modal(modalEl);

    const listUrl           = @json(route($listRouteName));
    const detailUrlTemplate = @json(route($detailRoute, 0));

    function formatRp(num) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(num || 0);
    }

    // === DETAIL HANDOVER ===
    function attachDetailButtons() {
        const buttons = document.querySelectorAll('.btn-detail');

        buttons.forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                if (!id) return;

                detailHeader.innerHTML  = 'Loading...';
                detailTbody.innerHTML   = '<tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>';
                detailSummary.innerHTML = '';

                const url = detailUrlTemplate.replace('/0', '/' + id);

                try {
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    const json = await res.json();
                    if (!json.success) throw new Error('Gagal load data');

                    const h  = json.handover;
                    const it = json.items || [];

                    const statusLabelMap = {
                        draft: 'Draft',
                        waiting_morning_otp: 'Menunggu OTP Pagi',
                        on_sales: 'On Sales',
                        waiting_evening_otp: 'Menunggu OTP Sore',
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

                    const stLabel    = statusLabelMap[h.status] || h.status;
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
                                <div><span class="fw-semibold">OTP Pagi verifikasi:</span> ${h.morning_otp_verified_at || '-'}</div>
                                <div><span class="fw-semibold">OTP Sore dikirim:</span> ${h.evening_otp_sent_at || '-'}</div>
                                <div><span class="fw-semibold">OTP Sore verifikasi:</span> ${h.evening_otp_verified_at || '-'}</div>
                            </div>
                        </div>
                    `;

                    let htmlItems = '';
                    it.forEach(row => {
                        htmlItems += `
                            <tr>
                                <td>
                                    <div class="fw-semibold">${row.product_name}</div>
                                    <div class="small text-muted">${row.product_code ? 'Kode: ' + row.product_code : ''}</div>
                                </td>
                                <td class="text-end">${row.qty_start ?? 0}</td>
                                <td class="text-end">${row.qty_returned ?? 0}</td>
                                <td class="text-end">${row.qty_sold ?? 0}</td>
                                <td class="text-end">${formatRp(row.unit_price || 0)}</td>
                                <td class="text-end">${formatRp(row.line_start || 0)}</td>
                                <td class="text-end">${formatRp(row.line_sold || 0)}</td>
                            </tr>
                        `;
                    });
                    if (!htmlItems) {
                        htmlItems = '<tr><td colspan="7" class="text-center text-muted">Tidak ada item.</td></tr>';
                    }
                    detailTbody.innerHTML = htmlItems;

                    const setorTotalText       = formatRp(h.setor_total || 0);
                    const selisihJualSetorText = formatRp(h.selisih_jual_vs_setor || 0);
                    const selisihStockText     = formatRp(h.selisih_stock_value || 0);

                    let proofHtml = '-';
                    if (h.transfer_proof_url) {
                        proofHtml = `
                            <div class="mt-1">
                                <img src="${h.transfer_proof_url}"
                                     alt="Bukti transfer"
                                     class="img-thumbnail proof-thumb"
                                     style="max-width:140px;cursor:pointer;">
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
                                <div>${formatRp(h.total_sold || 0)}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="fw-semibold text-muted small">Perkiraan Sisa Stok (nilai)</div>
                                <div>${selisihStockText}</div>
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
                                <div>${setorTotalText}</div>
                                <div class="small text-muted">Selisih jual vs setor: ${selisihJualSetorText}</div>
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
            });
        });
    }

    // Zoom bukti transfer
    modalEl.addEventListener('click', (e) => {
        const img = e.target.closest('.proof-thumb');
        if (!img) return;

        Swal.fire({
            imageUrl: img.src,
            imageAlt: 'Bukti transfer',
            showConfirmButton: false,
            showCloseButton: true,
            width: 'auto',
            background: '#000',
        });
    });

    // === AJAX reload list ===
    async function reloadList() {
        if (!filterForm || !rowsTbody) return;

        const fd     = new FormData(filterForm);
        const params = new URLSearchParams(fd);

        try {
            const res = await fetch(listUrl + '?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const json = await res.json();
            if (!json.success) throw new Error('Response tidak valid');

            const rows = json.rows || [];
            if (!rows.length) {
                rowsTbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-muted">
                            Belum ada data handover pada periode ini.
                        </td>
                    </tr>
                `;
            } else {
                let html = '';
                rows.forEach(r => {
                    html += `
                        <tr>
                            <td>${r.no}</td>
                            <td>${r.date || '-'}</td>
                            <td class="fw-semibold">${r.code}</td>
                            <td>${r.warehouse}</td>
                            <td>${r.sales}</td>
                            <td>
                                <span class="badge ${r.status_badge_class}">${r.status_label}</span>
                            </td>
                            <td class="text-end">${r.amount_dispatched}</td>
                            <td class="text-end">${r.amount_sold}</td>
                            <td class="text-end">${r.amount_diff}</td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-detail"
                                        data-id="${r.id}">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    `;
                });
                rowsTbody.innerHTML = html;
            }

            if (json.summary) {
                if (sumDispatchedEl) sumDispatchedEl.textContent = json.summary.total_dispatched_formatted;
                if (sumSoldEl)       sumSoldEl.textContent       = json.summary.total_sold_formatted;
                if (sumDiffEl)       sumDiffEl.textContent       = json.summary.total_diff_formatted;
                if (periodTextEl)    periodTextEl.textContent    = json.summary.period_text;
            }

            attachDetailButtons();
        } catch (err) {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Gagal memuat data report.'
            });
        }
    }

    // Auto-filter: setiap perubahan input / select
    if (filterForm) {
        const autoSelectors = [
            'input[name="date_from"]',
            'input[name="date_to"]',
            'select[name="status"]',
            'select[name="warehouse_id"]',
            'select[name="sales_id"]',
        ];
        const autoInputs = filterForm.querySelectorAll(autoSelectors.join(','));
        autoInputs.forEach(el => {
            el.addEventListener('change', reloadList);
        });

        // Kalau form ke-submit (misal tekan Enter), tetap pakai AJAX
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            reloadList();
        });
    }

    // Search global di navbar (input "Search..." atas)
    const navbarSearch = document.querySelector('.layout-navbar input[placeholder*="Search"]');
    if (navbarSearch && hiddenSearch) {
        // sync nilai awal
        navbarSearch.value = hiddenSearch.value || '';

        let timer = null;
        navbarSearch.addEventListener('keyup', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                hiddenSearch.value = navbarSearch.value;
                reloadList();
            }, 400);
        });
    }

    // init
    attachDetailButtons();
})();
</script>
@endpush
