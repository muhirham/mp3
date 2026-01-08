@extends('layouts.home')

@push('styles')
<style>
  :root { --ease-out: cubic-bezier(.16, 1, .3, 1); }
  [data-reveal]{opacity:0;transform:translateY(10px);transition:opacity .65s var(--ease-out),transform .65s var(--ease-out);}
  [data-reveal].is-visible{opacity:1;transform:none;}
  .card-hover{transition:transform .25s var(--ease-out),box-shadow .25s var(--ease-out);}
  .card-hover:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(15,23,42,.10);}
  .kpi-card{position:relative;overflow:hidden;}
  .kpi-card::after{content:"";position:absolute;left:-40%;right:-40%;top:0;height:2px;transform:scaleX(0);transform-origin:left;transition:transform .9s var(--ease-out);
    background:linear-gradient(90deg,rgba(var(--bs-primary-rgb),0),rgba(var(--bs-primary-rgb),.9),rgba(var(--bs-primary-rgb),0));}
  .kpi-card.is-visible::after{transform:scaleX(1);}
  [data-stagger] > *{opacity:0;transform:translateY(6px);transition:opacity .5s var(--ease-out),transform .5s var(--ease-out);}
  [data-stagger].is-visible > *{opacity:1;transform:none;}
  @media (prefers-reduced-motion: reduce){[data-reveal],.card-hover,[data-stagger]>*{transition:none!important;transform:none!important;}}
</style>
@endpush

@section('content')
@php
  $me        = $me ?? auth()->user();
  $greeting  = $greeting ?? ('Selamat datang, Wh ' . ($me->name ?? 'User'));
  $whName    = $whName ?? ($me?->warehouse?->warehouse_name ?? 'Warehouse');

  $stats     = $stats ?? [
    'products_total'=>0,'low_stock_count'=>0,'closed_sales'=>0,'closed_is_money'=>0,
    'restock_pending'=>0,'today_in'=>0,'today_out'=>0
  ];
  $inout     = $inout ?? ['labels'=>[],'in'=>[],'out'=>[]];

  $lowStocks = $lowStocks ?? [];
  $restocks  = $restocks ?? [];

  // ✅ ROUTE sesuai routes lo
  $links = $links ?? [
    'stock_level'     => route('stocklevel.index'),
    'stock_level_low' => route('stocklevel.index', ['filter'=>'low']),
    'issue_morning'   => route('sales.handover.morning'),
    'reconcile_otp'   => route('sales.handover.evening'),
    'sales_reports'   => route('sales.report'),
    'restocks'        => route('restocks.index'),
  ];

  $salesStats = $salesStats ?? ['sales'=>0,'admin_sales'=>0,'total'=>0];
  $closedPrefix = ((int)($stats['closed_is_money'] ?? 0) === 1) ? 'Rp ' : '';
@endphp


<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Header (BUTTON KANAN DIHAPUS) --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" data-reveal>
    <div>
      <h4 class="mb-1 fw-bold">Warehouse Dashboard</h4>
      <div class="text-muted">{{ $greeting }}</div>
      <div class="small text-muted">Lokasi: <span class="fw-semibold">{{ $whName }}</span></div>
    </div>
  </div>

  {{-- KPI Cards --}}
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="card h-100 card-hover kpi-card" data-reveal>
        <div class="card-body d-flex align-items-center">
          <div class="flex-grow-1">
            <div class="text-muted mb-1">Total Produk</div>
            <div class="h4 mb-0">
              <span class="kpi-number" data-count="{{ (int)$stats['products_total'] }}">0</span>
            </div>
          </div>
          <div class="ms-3 fs-3 text-primary"><i class="bx bx-box"></i></div>
        </div>
      </div>
    </div>

    {{-- GANTI DARI LOW STOCK -> PENJUALAN CLOSED --}}
    <div class="col-6 col-md-3">
      <div class="card h-100 card-hover kpi-card" data-reveal>
        <div class="card-body d-flex align-items-center">
          <div class="flex-grow-1">
            <div class="text-muted mb-1">Penjualan Closed (Hari Ini)</div>
            <div class="h4 mb-0">
              <span class="kpi-number" data-count="{{ (int)$stats['closed_sales'] }}" data-prefix="{{ $closedPrefix }}">0</span>
            </div>
          </div>
          <div class="ms-3 fs-3 text-success"><i class="bx bx-check-double"></i></div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card h-100 card-hover kpi-card" data-reveal>
        <div class="card-body d-flex align-items-center">
          <div class="flex-grow-1">
            <div class="text-muted mb-1">Restock Pending</div>
            <div class="h4 mb-0">
              <span class="kpi-number" data-count="{{ (int)$stats['restock_pending'] }}">0</span>
            </div>
          </div>
          <div class="ms-3 fs-3 text-info"><i class="bx bx-time"></i></div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card h-100 card-hover kpi-card" data-reveal>
        <div class="card-body">
          <div class="text-muted mb-1">Transaksi Hari Ini</div>
          <div class="d-flex align-items-end justify-content-between">
            <div>
              <div class="small text-success">Inbound</div>
              <div class="h5 mb-0"><span class="kpi-number" data-count="{{ (int)$stats['today_in'] }}">0</span></div>
            </div>
            <div class="text-end">
              <div class="small text-danger">Outbound</div>
              <div class="h5 mb-0"><span class="kpi-number" data-count="{{ (int)$stats['today_out'] }}">0</span></div>
            </div>
          </div>
          <div class="small text-muted mt-2">Sumber: {{ $usedSource ?? '-' }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Chart + Quick menu --}}
  <div class="row g-3">
    <div class="col-12 col-xl-8">
      <div class="card h-100 card-hover" data-reveal data-chart="inout">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Inbound vs Outbound (7 hari)</h6>
          <small class="text-muted">Terupdate harian</small>
        </div>
        <div class="card-body">
          <div style="height:300px">
            <canvas id="inoutChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card h-100 card-hover" data-reveal>
        <div class="card-header">
          <h6 class="mb-0 fw-semibold">Short Cut</h6>
        </div>
        <div class="card-body">
          <div class="list-group" data-stagger data-step="70">

            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between"
               href="{{ $links['stock_level'] }}">
              <span><i class="bx bx-layer me-2"></i> Warehouse Stock Level</span>
              <span class="badge bg-label-primary">{{ number_format((int)$stats['products_total']) }}</span>
            </a>

            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between"
               href="{{ $links['stock_level_low'] }}">
              <span><i class="bx bx-error-circle me-2"></i> Low Stock</span>
              <span class="badge {{ ((int)$stats['low_stock_count']>0) ? 'bg-label-warning' : 'bg-label-success' }}">{{ number_format((int)$stats['low_stock_count']) }}</span>
            </a>

            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between"
                href="{{ $links['issue_morning'] }}">
              <span><i class="bx bx-transfer me-2"></i> Issue ke Sales (Pagi)</span>
              <span class="badge bg-label-secondary">GO</span>
            </a>

            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between"
               href="{{ $links['reconcile_otp'] }}">
              <span><i class="bx bx-check-shield me-2"></i> Reconcile + OTP (Sore)</span>
              <span class="badge bg-label-secondary">GO</span>
            </a>

            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between"
               href="{{ $links['sales_reports'] }}">
              <span><i class="bx bx-bar-chart-alt-2 me-2"></i> Sales Reports</span>
              <span class="badge bg-label-secondary">GO</span>
            </a>

            <div class="list-group-item d-flex align-items-center justify-content-between">
              <span><i class="bx bx-user me-2"></i> Akun Sales</span>
              <span class="badge bg-label-info">{{ number_format((int)$salesStats['sales']) }}</span>
            </div>

            <div class="list-group-item d-flex align-items-center justify-content-between">
              <span><i class="bx bx-user-check me-2"></i> Admin Sales</span>
              <span class="badge bg-label-info">{{ number_format((int)$salesStats['admin_sales']) }}</span>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Tables --}}
  <div class="row g-3 mt-1">
    <div class="col-12 col-xl-6">
      <div class="card h-100 card-hover" data-reveal>
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Produk Low Stock</h6>
          <a href="{{ $links['stock_level_low'] }}" class="small">Lihat semua</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" data-stagger data-step="35">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>CODE</th>
                  <th>PRODUCT</th>
                  <th>UNIT</th>
                  <th class="text-end">MIN</th>
                  <th class="text-end">CURRENT</th>
                </tr>
              </thead>
              <tbody>
                @forelse($lowStocks as $row)
                  <tr>
                    <td class="fw-semibold">{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['package'] ?? '-' }}</td>
                    <td class="text-end">{{ number_format($row['min'] ?? 0) }}</td>
                    <td class="text-end">{{ number_format($row['current'] ?? 0) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted p-3">Tidak ada data</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card h-100 card-hover" data-reveal>
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Restock Pending</h6>
          <a href="{{ $links['restocks'] }}" class="small">Lihat semua</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" data-stagger data-step="35">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>REQ#</th>
                  <th>PRODUCT</th>
                  <th class="text-end">QTY</th>
                  <th>STATUS</th>
                  <th>REQUESTED</th>
                </tr>
              </thead>
              <tbody>
                @forelse($restocks as $r)
                  <tr>
                    <td class="fw-semibold">{{ $r['code'] }}</td>
                    <td>{{ $r['product'] }}</td>
                    <td class="text-end">{{ number_format($r['qty'] ?? 0) }}</td>
                    <td>
                      @php $st = strtoupper($r['status'] ?? 'pending'); @endphp
                      <span class="badge
                        @if($st==='APPROVED') bg-label-success
                        @elseif($st==='REJECTED') bg-label-danger
                        @else bg-label-warning @endif">{{ $st }}</span>
                    </td>
                    <td>{{ $r['requested_at'] ?? '-' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted p-3">Tidak ada data</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
  const labels  = @json($inout['labels'] ?? []);
  const inbound = @json($inout['in'] ?? []);
  const outbound= @json($inout['out'] ?? []);

  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function animateCount(el, to, duration = 900){
    to = Number(to || 0);
    const prefix = el.dataset.prefix || '';
    if (prefersReduced) { el.textContent = prefix + to.toLocaleString('id-ID'); return; }
    const start = performance.now();
    function tick(now){
      const p = Math.min((now - start) / duration, 1);
      const val = Math.floor(to * p);
      el.textContent = prefix + val.toLocaleString('id-ID');
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  function applyStagger(container){
    const step = Number(container.dataset.step || 70);
    Array.from(container.children || []).forEach((ch, i) => ch.style.transitionDelay = (i * step) + 'ms');
  }

  let chartInited = false;
  function initChart(){
    if (chartInited) return;
    chartInited = true;
    const el = document.getElementById('inoutChart');
    if (!el) return;

    new Chart(el, {
      type: 'bar',
      data: { labels, datasets: [{ label:'Inbound', data: inbound }, { label:'Outbound', data: outbound }] },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        animation: prefersReduced ? false : { duration: 900, easing: 'easeOutQuart' },
        scales: { x: { grid:{ display:false } }, y: { beginAtZero:true, ticks:{ precision:0 } } },
        plugins: { legend: { display:true, position:'top' }, tooltip: { mode:'index', intersect:false } }
      }
    });
  }

  function onVisible(el){
    el.classList.add('is-visible');

    el.querySelectorAll('[data-stagger]').forEach(s => { applyStagger(s); s.classList.add('is-visible'); });

    el.querySelectorAll('.kpi-number').forEach(n => {
      if (n.dataset.animated === '1') return;
      n.dataset.animated = '1';
      animateCount(n, n.dataset.count, 900);
    });

    if (el.dataset.chart === 'inout') initChart();
  }

  const targets = document.querySelectorAll('[data-reveal]');
  if (!targets.length) return;

  if (prefersReduced || !('IntersectionObserver' in window)) {
    targets.forEach(onVisible);
    return;
  }

  const obs = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      onVisible(e.target);
      obs.unobserve(e.target);
    });
  }, { threshold: 0.12 });

  targets.forEach(t => obs.observe(t));
})();
//-- End IIFE
</script>
@endpush