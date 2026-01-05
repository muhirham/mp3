@extends('layouts.home')

@section('title', 'Admin Dashboard')

@section('content')

@php
  $stats   = $stats ?? [];
  $charts  = $charts ?? [];
  $access  = $access ?? [];
  

  $txCount  = (int)($stats['closed_count_month'] ?? 0);
  $txAmount = (int)($stats['closed_amount_month'] ?? 0);

  $poPendingProc = (int)($stats['po_pending_proc_count'] ?? 0);
  $poPendingCeo  = (int)($stats['po_pending_ceo_count'] ?? 0);

  $poApprovedCnt = (int)($stats['po_approved_count'] ?? 0);
  $poApprovedTot = (int)($stats['po_approved_total'] ?? 0);

  $poRestockCnt  = (int)($stats['po_restock_count'] ?? 0);
  $poRestockTot  = (int)($stats['po_restock_total'] ?? 0);

  $canOpenProc = (bool)($access['can_open_pending_proc'] ?? false);
  $canOpenCeo  = (bool)($access['can_open_pending_ceo']  ?? false);

  // Link: aman (kalau filter belum dipakai, tetap kebuka listnya)
  $urlPoPendingProc = route('po.index', ['approval' => 'waiting_procurement']);
  $urlPoPendingCeo  = route('po.index', ['approval' => 'waiting_ceo']);
  $urlPoApproved    = route('po.index', ['approval' => 'approved']);
  $urlPoRestock     = route('po.index', ['from_restock' => 1]);
@endphp

<style>
  .card-link-wrap { position: relative; }
  .card-link-wrap .stretched-link { z-index: 3; }
  .card-disabled { opacity: .55; cursor: not-allowed; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0 fw-bold">Admin Dashboard</h4>
      <div class="text-muted">
        Periode: <span class="fw-semibold">{{ $label }}</span>
      </div>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-center">

      {{-- PERIOD --}}
      <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="day"   {{ request('period')=='day' ? 'selected':'' }}>Hari ini</option>
        <option value="week"  {{ request('period')=='week' ? 'selected':'' }}>Mingguan</option>
        <option value="month" {{ request('period','month')=='month' ? 'selected':'' }}>Bulanan</option>
      </select>

      {{-- MONTH (ONLY IF MONTH) --}}
      @if(request('period','month') === 'month')
        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
          @foreach(range(1,12) as $m)
            <option value="{{ $m }}" {{ request('month', now()->month)==$m?'selected':'' }}>
              {{ \Carbon\Carbon::create()->month($m)->format('F') }}
            </option>
          @endforeach
        </select>

        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
          @foreach(range(now()->year-3, now()->year+1) as $y)
            <option value="{{ $y }}" {{ request('year', now()->year)==$y?'selected':'' }}>
              {{ $y }}
            </option>
          @endforeach
        </select>
      @endif

    </form>
  </div>

  {{-- TOP KPI --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-primary">TX</span>
          </div>
          <div class="flex-grow-1">
            <h5 class="mb-0">{{ number_format($txCount, 0, ',', '.') }}</h5>
            <small class="text-muted">
            Closed Handover (
            @if($period === 'day') Hari ini
            @elseif($period === 'week') Minggu ini
            @else Bulan ini
            @endif
            )
          </small>
            <div class="small mt-1 text-muted">
              Total nilai: <span class="fw-semibold">Rp {{ number_format($txAmount, 0, ',', '.') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-primary">U</span>
          </div>
          <div>
            <h5 class="mb-0">{{ number_format((int)($stats['users_total'] ?? 0), 0, ',', '.') }}</h5>
            <small class="text-muted">Total Users</small>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-primary">P</span>
          </div>
          <div>
            <h5 class="mb-0">{{ number_format((int)($stats['products_total'] ?? 0), 0, ',', '.') }}</h5>
            <small class="text-muted">Total Products</small>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-primary">W</span>
          </div>
          <div>
            <h5 class="mb-0">{{ number_format((int)($stats['warehouses_total'] ?? 0), 0, ',', '.') }}</h5>
            <small class="text-muted">Warehouses</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- PO CARDS --}}
  <div class="row g-3 mb-3">

    {{-- Pending Procurement (disable kecuali role procurement) --}}
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100 card-link-wrap {{ $canOpenProc ? '' : 'card-disabled' }}">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-warning">PO</span>
          </div>
          <div class="flex-grow-1">
            <h5 class="mb-0">{{ number_format($poPendingProc, 0, ',', '.') }}</h5>
            <small class="text-muted">PO Pending Procurement</small>
            <div class="small mt-1 text-muted">
              {{ $canOpenProc ? 'Klik untuk buka list PO' : 'Tidak ada akses' }}
            </div>
          </div>
        </div>
        @if($canOpenProc)
          <a href="{{ $urlPoPendingProc }}" class="stretched-link" aria-label="Buka PO Pending Procurement"></a>
        @endif
      </div>
    </div>

    {{-- Pending CEO (disable kecuali role ceo) --}}
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100 card-link-wrap {{ $canOpenCeo ? '' : 'card-disabled' }}">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-danger">PO</span>
          </div>
          <div class="flex-grow-1">
            <h5 class="mb-0">{{ number_format($poPendingCeo, 0, ',', '.') }}</h5>
            <small class="text-muted">PO Pending CEO</small>
            <div class="small mt-1 text-muted">
              {{ $canOpenCeo ? 'Klik untuk buka list PO' : 'Tidak ada akses' }}
            </div>
          </div>
        </div>
        @if($canOpenCeo)
          <a href="{{ $urlPoPendingCeo }}" class="stretched-link" aria-label="Buka PO Pending CEO"></a>
        @endif
      </div>
    </div>

    {{-- Approved --}}
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100 card-link-wrap">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-success">OK</span>
          </div>
          <div class="flex-grow-1">
            <h5 class="mb-0">{{ number_format($poApprovedCnt, 0, ',', '.') }}</h5>
            PO Approved (
            @if($period === 'day') Hari ini
            @elseif($period === 'week') Minggu ini
            @else Bulan ini
            @endif
            )
            <div class="small mt-1 text-muted">
              Total: <span class="fw-semibold">Rp {{ number_format($poApprovedTot, 0, ',', '.') }}</span>
            </div>
          </div>
        </div>
        <a href="{{ $urlPoApproved }}" class="stretched-link" aria-label="Buka PO Approved"></a>
      </div>
    </div>
    {{-- Restock --}}
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100 card-link-wrap">
        <div class="card-body d-flex align-items-center">
          <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-info">RR</span>
          </div>
          <div class="flex-grow-1">
            <h5 class="mb-0">{{ number_format($poRestockCnt, 0, ',', '.') }}</h5>
            PO dari Restock (
            @if($period === 'day') Hari ini
            @elseif($period === 'week') Minggu ini
            @else Bulan ini
            @endif
            )
            <div class="small mt-1 text-muted">
              Total: <span class="fw-semibold">Rp {{ number_format($poRestockTot, 0, ',', '.') }}</span>
            </div>
          </div>
        </div>
        <a href="{{ $urlPoRestock }}" class="stretched-link" aria-label="Buka PO Restock"></a>
      </div>
    </div>

  </div>

  {{-- CHART ROW --}}
  <div class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Total Income</div>
            <div class="fw-bold">
              Report overview (
              @if($period === 'day') Hari ini
              @elseif($period === 'week') Minggu ini
              @else Bulan ini
              @endif
              )
            </div>

          </div>
          <div class="text-end">
            <div class="text-muted small">This month</div>
            <div class="fw-bold">Rp {{ number_format((int)($stats['closed_amount_month'] ?? 0), 0, ',', '.') }}</div>
          </div>
        </div>
        <div class="card-body">
          <div id="yearlySalesChart" style="height:320px;"></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-3">
      <div class="card h-100">
        <div class="card-header">
          <div class="fw-bold">By status</div>
          <div class="text-muted small">Bulan ini</div>
        </div>
        <div class="card-body">
          <div id="handoverStatusChart" style="height:320px;"></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-3">
      <div class="card h-100">
        <div class="card-header">
          <div class="fw-bold">Transactions</div>
          <div class="text-muted small">Closed / hari (12 hari terakhir)</div>
        </div>
        <div class="card-body">
          <div class="fw-bold mb-2">{{ number_format((int)($stats['closed_count_month'] ?? 0), 0, ',', '.') }}</div>
          <div id="closedDailyChart" style="height:280px;"></div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const yearlyLabels = @json($charts['yearly']['labels'] ?? []);
  const yearlySeries = @json($charts['yearly']['series'] ?? []);

  const statusLabels = @json($charts['status']['labels'] ?? []);
  const statusSeries = @json($charts['status']['series'] ?? []);

  const dailyLabels  = @json($charts['daily']['labels'] ?? []);
  const dailySeries  = @json($charts['daily']['series'] ?? []);

  if (typeof ApexCharts === 'undefined') return;

  // ✅ Biar smooth: render setelah layout stabil
  requestAnimationFrame(() => {
    // === YEARLY LINE ===
    const yearlyEl = document.getElementById('yearlySalesChart');
    if (yearlyEl) {
      new ApexCharts(yearlyEl, {
        chart: {
          type: 'line',
          height: 320,
          toolbar: { show:false },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 900,
            animateGradually: { enabled: true, delay: 120 },
            dynamicAnimation: { enabled: true, speed: 500 }
          }
        },
        series: [{ name: 'Closed Sales', data: yearlySeries }],
        xaxis: { categories: yearlyLabels },
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        yaxis: {
          labels: { formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v||0)) }
        },
        tooltip: {
          shared: true,
          intersect: false,
          y: { formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v||0)) }
        }
      }).render();
    }

    // === STATUS DONUT ===
    const statusEl = document.getElementById('handoverStatusChart');
    if (statusEl) {
      new ApexCharts(statusEl, {
        chart: {
          type: 'donut',
          height: 320,
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 900,
            animateGradually: { enabled: true, delay: 80 },
            dynamicAnimation: { enabled: true, speed: 500 }
          }
        },
        labels: statusLabels,
        series: statusSeries,
        legend: { position: 'bottom' },
        plotOptions: {
          pie: {
            donut: {
              size: '70%',
              labels: { show: false }
            }
          }
        },
        tooltip: {
          y: { formatter: v => new Intl.NumberFormat('id-ID').format(Math.round(v||0)) + ' handover' }
        }
      }).render();
    }

    // === DAILY BAR ===
    const dailyEl = document.getElementById('closedDailyChart');
    if (dailyEl) {
      new ApexCharts(dailyEl, {
        chart: {
          type: 'bar',
          height: 280,
          toolbar: { show:false },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
            animateGradually: { enabled: true, delay: 60 },
            dynamicAnimation: { enabled: true, speed: 450 }
          }
        },
        series: [{ name: 'Closed', data: dailySeries }],
        xaxis: { categories: dailyLabels },
        plotOptions: {
          bar: {
            columnWidth: '55%',
            borderRadius: 6,
            // ✅ ini bikin transisi bar lebih halus
            endingShape: 'rounded'
          }
        },
        dataLabels: { enabled: false },
        tooltip: { shared: true, intersect: false },
        yaxis: { labels: { formatter: v => Math.round(v||0) } }
      }).render();
    }
  });

});


</script>
@endpush
