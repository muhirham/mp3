@extends('layouts.home')
@section('title', 'Sales Dashboard')

@section('content')
@php
    $me = $me ?? auth()->user();
    $wh = $me?->warehouse;
    $warehouseLabel = $wh->warehouse_name
        ?? $wh->warehouse_code
        ?? null;

    $statusLabelMap = [
        'draft'               => 'Draft',
        'waiting_morning_otp' => 'Menunggu OTP Pagi',
        'on_sales'            => 'On Sales',
        'waiting_evening_otp' => 'Menunggu OTP Sore',
        'closed'              => 'Closed',
        'cancelled'           => 'Cancelled',
    ];

    $badgeClassMap = [
        'closed'              => 'bg-label-success',
        'on_sales'            => 'bg-label-info',
        'waiting_morning_otp' => 'bg-label-warning',
        'waiting_evening_otp' => 'bg-label-warning',
        'cancelled'           => 'bg-label-danger',
        'draft'               => 'bg-label-secondary',
    ];
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- HEADER --}}
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h4 class="fw-bold mb-1">Sales Dashboard</h4>
      <div class="text-muted small">
        Hai {{ $me->name }}{{ $warehouseLabel ? ' – Sales ' . $warehouseLabel : '' }}.
        Ini ringkasan penjualan kamu pada periode
        <span class="fw-semibold period-text">{{ $dateFrom }} s/d {{ $dateTo }}</span>.
      </div>
    </div>
  </div>

  {{-- FILTER PERIODE (AUTO AJAX) --}}
  <div class="card mb-3">
    <div class="card-body">
      <form id="salesFilterForm" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Dari Tanggal</label>
          <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Sampai Tanggal</label>
          <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
        </div>
      </form>
      <div class="mt-2 text-muted small">
        Periode: <span class="fw-semibold period-text">{{ $dateFrom }} s/d {{ $dateTo }}</span>
      </div>
    </div>
  </div>

  {{-- KPI CARDS --}}
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Total Penjualan (Closed)</div>
          <div class="fs-5 fw-bold" id="kpiTotalSold">
            {{ 'Rp '.number_format($totalSold ?? 0, 0, ',', '.') }}
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Total Nilai Barang Dibawa</div>
          <div class="fs-5 fw-bold" id="kpiTotalDispatched">
            {{ 'Rp '.number_format($totalDispatched ?? 0, 0, ',', '.') }}
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Perkiraan Nilai Sisa Stok</div>
          <div class="fs-5 fw-bold" id="kpiEstStock">
            {{ 'Rp '.number_format($estStockValue ?? 0, 0, ',', '.') }}
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Total Qty Terjual</div>
          <div class="fs-5 fw-bold" id="kpiTotalQty">
            {{ number_format($totalQtySold ?? 0, 0, ',', '.') }} pcs
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- CHARTS --}}
  <div class="row g-3 mb-3">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0 fw-bold">Penjualan per Tanggal</h5>
        </div>
        <div class="card-body" style="height: 260px;">
          <canvas id="salesByDateChart"></canvas>
          <div class="text-muted small mt-2" id="salesNoDataNote"
               @if(collect($chartData ?? [])->sum() > 0) style="display:none" @endif>
            Belum ada data penjualan closed pada periode ini.
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0 fw-bold">Komposisi Metode Pembayaran</h5>
        </div>
        <div class="card-body" style="height: 260px;">
          <canvas id="paymentMethodChart"></canvas>
          <div class="mt-3 small text-muted">
            Total Setoran:
            <span class="fw-semibold" id="totalSetoranText">
              {{ 'Rp '.number_format(($cashTotal ?? 0) + ($transferTotal ?? 0), 0, ',', '.') }}
            </span>
          </div>
          <div class="text-muted small mt-1" id="paymentNoDataNote"
               @if(($cashTotal ?? 0) + ($transferTotal ?? 0) > 0) style="display:none" @endif>
            Belum ada data setoran pada periode ini.
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- RECENT HANDOVERS & TOP PRODUCTS --}}
  <div class="row g-3 mb-3">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0 fw-bold">Handover Terbaru</h5>
          <a href="{{ route('daily.sales.report') }}" class="btn btn-sm btn-outline-primary">
            Lihat Daily Report
          </a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th style="width: 12%;">Tanggal</th>
                  <th style="width: 18%;">Kode</th>
                  <th style="width: 15%;">Status</th>
                  <th class="text-end" style="width: 18%;">Nilai Dibawa</th>
                  <th class="text-end" style="width: 18%;">Nilai Terjual</th>
                  <th class="text-end" style="width: 19%;">Selisih (stok)</th>
                  <th class="text-end" style="width: 10%;"></th>
                </tr>
              </thead>
              <tbody id="recentHandoversBody">
                @forelse($recentHandovers as $h)
                  @php
                      $dispatched = (int) $h->total_dispatched_amount;
                      $sold       = (int) $h->total_sold_amount;
                      $diff       = max(0, $dispatched - $sold);

                      $statusLabel = $statusLabelMap[$h->status] ?? $h->status;
                      $badgeClass  = $badgeClassMap[$h->status] ?? 'bg-label-secondary';
                  @endphp
                  <tr>
                    <td>{{ optional($h->handover_date)->format('Y-m-d') ?? $h->handover_date }}</td>
                    <td class="fw-semibold">{{ $h->code }}</td>
                    <td>
                      <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
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
                      {{-- BUTTON DETAIL: PAKAI AJAX + MODAL --}}
                      <button type="button"
                              class="btn btn-sm btn-outline-primary btn-handover-detail"
                              data-detail-url="{{ route('daily.report.detail', $h->id) }}">
                        Detail
                      </button>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted small">
                      Belum ada handover pada periode ini.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0 fw-bold">Top Produk Terjual</h5>
          <a href="{{ route('sales.otp.items') }}" class="btn btn-sm btn-outline-secondary">
            Input Penjualan
          </a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="text-end" style="width: 20%;">Qty</th>
                  <th class="text-end" style="width: 30%;">Nilai Terjual</th>
                </tr>
              </thead>
              <tbody id="topProductsBody">
                @forelse($topProducts as $p)
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $p->product_name }}</div>
                      <div class="small text-muted">
                        {{ $p->product_code ? 'Kode: '.$p->product_code : '' }}
                      </div>
                    </td>
                    <td class="text-end">
                      {{ number_format($p->total_qty_sold, 0, ',', '.') }}
                    </td>
                    <td class="text-end">
                      {{ 'Rp '.number_format($p->total_sales_amount, 0, ',', '.') }}
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted small">
                      Belum ada produk terjual pada periode ini.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- QUICK ACTIONS --}}
  <div class="row g-3">
    <div class="col-md-4">
      <a href="{{ route('sales.otp.items') }}" class="card h-100 text-decoration-none text-reset">
        <div class="card-body">
          <div class="fw-semibold mb-1">OTP Pagi &amp; Barang Dibawa</div>
          <div class="small text-muted">
            Verifikasi barang dan OTP sebelum mulai berjualan.
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="{{ route('daily.sales.report') }}" class="card h-100 text-decoration-none text-reset">
        <div class="card-body">
          <div class="fw-semibold mb-1">Daily Report Penjualan</div>
          <div class="small text-muted">
            Lihat rekap detail penjualan dan stok per handover.
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="#" class="card h-100 text-decoration-none text-reset">
        <div class="card-body">
          <div class="fw-semibold mb-1">Retur Barang</div>
          <div class="small text-muted">
            Catat barang retur dari pelanggan atau stok rusak.
          </div>
        </div>
      </a>
    </div>
  </div>

  {{-- MODAL DETAIL HANDOVER --}}
  <div class="modal fade" id="handoverDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title mb-0">Detail Handover</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="detailHeader" class="mb-3 small"></div>

          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="text-end">Dibawa</th>
                  <th class="text-end">Kembali</th>
                  <th class="text-end">Terjual</th>
                  <th class="text-end">Harga</th>
                  <th class="text-end">Nilai Dibawa</th>
                  <th class="text-end">Nilai Terjual</th>
                </tr>
              </thead>
              <tbody id="detailItemsBody"></tbody>
            </table>
          </div>

          <div id="detailSummary" class="small"></div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialLabels = @json($chartLabels ?? []);
    const initialData   = @json($chartData ?? []);
    const initialCash   = Number({{ (int) ($cashTotal ?? 0) }});
    const initialTrf    = Number({{ (int) ($transferTotal ?? 0) }});

    const dashboardUrl  = @json(route('sales.dashboard'));

    const dateFromInput = document.querySelector('input[name="date_from"]');
    const dateToInput   = document.querySelector('input[name="date_to"]');

    const kpiTotalSoldEl       = document.getElementById('kpiTotalSold');
    const kpiTotalDispatchedEl = document.getElementById('kpiTotalDispatched');
    const kpiEstStockEl        = document.getElementById('kpiEstStock');
    const kpiTotalQtyEl        = document.getElementById('kpiTotalQty');
    const periodTextEls        = document.querySelectorAll('.period-text');

    const recentBody     = document.getElementById('recentHandoversBody');
    const topBody        = document.getElementById('topProductsBody');
    const totalSetoranEl = document.getElementById('totalSetoranText');
    const salesNoData    = document.getElementById('salesNoDataNote');
    const paymentNoData  = document.getElementById('paymentNoDataNote');

    // MODAL DETAIL
    const modalEl        = document.getElementById('handoverDetailModal');
    const detailHeaderEl = document.getElementById('detailHeader');
    const detailItemsEl  = document.getElementById('detailItemsBody');
    const detailSummaryEl= document.getElementById('detailSummary');
    let detailModal      = modalEl ? new bootstrap.Modal(modalEl) : null;

    let salesChart   = null;
    let paymentChart = null;

    function formatRp(num) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(num || 0);
    }
    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num || 0);
    }

    // === INIT CHARTS ===
    const ctx1 = document.getElementById('salesByDateChart');
    if (ctx1) {
        salesChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: initialLabels,
                datasets: [{
                    label: 'Total Penjualan',
                    data: initialData,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                try {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                } catch (e) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    const ctx2 = document.getElementById('paymentMethodChart');
    if (ctx2) {
        paymentChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'Transfer'],
                datasets: [{
                    data: [initialCash, initialTrf],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)'
                    ],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    function updateCharts(labels, data, cashTotal, transferTotal) {
        if (salesChart) {
            salesChart.data.labels = labels;
            salesChart.data.datasets[0].data = data;
            salesChart.update();
        }
        if (paymentChart) {
            paymentChart.data.datasets[0].data = [cashTotal, transferTotal];
            paymentChart.update();
        }

        if (salesNoData) {
            const sum = (data || []).reduce((a, b) => a + Number(b || 0), 0);
            salesNoData.style.display = sum > 0 ? 'none' : '';
        }
        if (paymentNoData) {
            const tot = (cashTotal || 0) + (transferTotal || 0);
            paymentNoData.style.display = tot > 0 ? 'none' : '';
        }
    }

    // ====== DETAIL HANDOVER (AJAX + MODAL) ======
    function attachDetailHandlers() {
        if (!modalEl || !detailModal) return;

        const buttons = document.querySelectorAll('.btn-handover-detail');
        buttons.forEach(btn => {
            // biar nggak double listener
            btn.removeEventListener('click', btn._handoverClick || (()=>{}));

            btn._handoverClick = async function (e) {
                e.preventDefault();
                const url = btn.getAttribute('data-detail-url');
                if (!url) return;

                if (detailHeaderEl) {
                    detailHeaderEl.innerHTML = 'Loading...';
                }
                if (detailItemsEl) {
                    detailItemsEl.innerHTML =
                        '<tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>';
                }
                if (detailSummaryEl) {
                    detailSummaryEl.innerHTML = '';
                }

                try {
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    const json = await res.json();
                    if (!json.success) throw new Error('Response tidak valid');

                    const h  = json.handover || {};
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

                    const stLabel    = statusLabelMap[h.status] || h.status || '-';
                    const badgeClass = badgeClassMap[h.status] || badgeClassMap.default;

                    if (detailHeaderEl) {
                        detailHeaderEl.innerHTML = `
                            <div class="d-flex flex-column flex-md-row justify-content-between">
                              <div class="mb-2 mb-md-0">
                                <div><span class="fw-semibold">Kode:</span> ${h.code || '-'}</div>
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
                    }

                    let rowsHtml = '';
                    it.forEach(row => {
                        rowsHtml += `
                          <tr>
                            <td>
                              <div class="fw-semibold">${row.product_name || '-'}</div>
                              <div class="small text-muted">
                                ${row.product_code ? 'Kode: ' + row.product_code : ''}
                              </div>
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

                    if (!rowsHtml) {
                        rowsHtml =
                            '<tr><td colspan="7" class="text-center text-muted">Tidak ada item.</td></tr>';
                    }
                    if (detailItemsEl) {
                        detailItemsEl.innerHTML = rowsHtml;
                    }

                    const setorTotalText       = formatRp(h.setor_total || 0);
                    const selisihJualSetorText = formatRp(h.selisih_jual_vs_setor || 0);
                    const selisihStockText     = formatRp(h.selisih_stock_value || 0);

                    if (detailSummaryEl) {
                        detailSummaryEl.innerHTML = `
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
                            </div>
                            <div class="col-md-4">
                              <div class="fw-semibold text-muted small">Total Setoran</div>
                              <div>${setorTotalText}</div>
                              <div class="small text-muted">
                                Selisih jual vs setor: ${selisihJualSetorText}
                              </div>
                            </div>
                          </div>
                        `;
                    }

                    detailModal.show();
                } catch (err) {
                    console.error(err);
                    alert('Gagal memuat detail handover.');
                }
            };

            btn.addEventListener('click', btn._handoverClick);
        });
    }

    attachDetailHandlers();

    // === AJAX RELOAD ===
    let reloadTimer = null;
    function scheduleReload() {
        clearTimeout(reloadTimer);
        reloadTimer = setTimeout(reloadDashboard, 400);
    }

    async function reloadDashboard() {
        if (!dateFromInput || !dateToInput) return;

        const params = new URLSearchParams({
            date_from: dateFromInput.value || '',
            date_to:   dateToInput.value   || '',
        });

        try {
            const res = await fetch(dashboardUrl + '?' + params.toString(), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const json = await res.json();
            if (!json.success) throw new Error('Response tidak valid');

            const s      = json.summary || {};
            const chart  = json.chart   || {};
            const recent = json.recent_handovers || [];
            const tops   = json.top_products     || [];

            // KPI
            if (kpiTotalSoldEl)       kpiTotalSoldEl.textContent       = formatRp(s.total_sold);
            if (kpiTotalDispatchedEl) kpiTotalDispatchedEl.textContent = formatRp(s.total_dispatched);
            if (kpiEstStockEl)        kpiEstStockEl.textContent        = formatRp(s.est_stock_value);
            if (kpiTotalQtyEl)        kpiTotalQtyEl.textContent        = formatNumber(s.total_qty_sold) + ' pcs';

            // Periode text
            periodTextEls.forEach(el => {
                el.textContent = (json.date_from || '') + ' s/d ' + (json.date_to || '');
            });

            // Total setoran
            const totalSetor = (s.cash_total || 0) + (s.transfer_total || 0);
            if (totalSetoranEl) {
                totalSetoranEl.textContent = formatRp(totalSetor);
            }

            // Charts
            updateCharts(chart.labels || [], chart.data || [], s.cash_total || 0, s.transfer_total || 0);

            // Recent handovers table
            if (recentBody) {
                if (!recent.length) {
                    recentBody.innerHTML = `
                        <tr>
                          <td colspan="7" class="text-center text-muted small">
                            Belum ada handover pada periode ini.
                          </td>
                        </tr>`;
                } else {
                    let html = '';
                    recent.forEach(r => {
                        html += `
                          <tr>
                            <td>${r.date || '-'}</td>
                            <td class="fw-semibold">${r.code}</td>
                            <td><span class="badge ${r.status_badge_class}">${r.status_label}</span></td>
                            <td class="text-end">${formatRp(r.amount_dispatched)}</td>
                            <td class="text-end">${formatRp(r.amount_sold)}</td>
                            <td class="text-end">${formatRp(r.amount_diff)}</td>
                            <td class="text-end">
                              <button type="button"
                                      class="btn btn-sm btn-outline-primary btn-handover-detail"
                                      data-detail-url="${r.detail_url}">
                                Detail
                              </button>
                            </td>
                          </tr>`;
                    });
                    recentBody.innerHTML = html;
                }

                // setelah isi ulang tabel, pasang lagi handler tombol detail
                attachDetailHandlers();
            }

            // Top products table
            if (topBody) {
                if (!tops.length) {
                    topBody.innerHTML = `
                        <tr>
                          <td colspan="3" class="text-center text-muted small">
                            Belum ada produk terjual pada periode ini.
                          </td>
                        </tr>`;
                } else {
                    let html = '';
                    tops.forEach(p => {
                        html += `
                          <tr>
                            <td>
                              <div class="fw-semibold">${p.product_name}</div>
                              <div class="small text-muted">${p.product_code ? 'Kode: ' + p.product_code : ''}</div>
                            </td>
                            <td class="text-end">${formatNumber(p.total_qty_sold)}</td>
                            <td class="text-end">${formatRp(p.total_sales_amount)}</td>
                          </tr>`;
                    });
                    topBody.innerHTML = html;
                }
            }

        } catch (err) {
            console.error(err);
        }
    }

    if (dateFromInput) dateFromInput.addEventListener('change', scheduleReload);
    if (dateToInput)   dateToInput.addEventListener('change', scheduleReload);
});
</script>
@endpush
