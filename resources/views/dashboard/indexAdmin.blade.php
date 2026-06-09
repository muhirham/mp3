@extends('layouts.home')

@section('title', 'Admin Dashboard')

@section('content')

    @php
        $stats = $stats ?? [];
        $charts = $charts ?? [];
        $access = $access ?? [];

        $txCount = (int) ($stats['closed_count_month'] ?? 0);
        $txAmount = (int) ($stats['closed_amount_month'] ?? 0);

        $poPendingProc = (int) ($stats['po_pending_proc_count'] ?? 0);
        $poPendingCeo = (int) ($stats['po_pending_ceo_count'] ?? 0);

        $poApprovedCnt = (int) ($stats['po_approved_count'] ?? 0);
        $poApprovedTot = (int) ($stats['po_approved_total'] ?? 0);

        $poRestockCnt = (int) ($stats['po_restock_count'] ?? 0);
        $poRestockTot = (int) ($stats['po_restock_total'] ?? 0);

        $canOpenProc = (bool) ($access['can_open_pending_proc'] ?? false);
        $canOpenCeo = (bool) ($access['can_open_pending_ceo'] ?? false);

        // Link: aman (kalau filter belum dipakai, tetap kebuka listnya)
        $urlPoPendingProc = route('po.index', ['approval' => 'waiting_procurement']);
        $urlPoPendingCeo = route('po.index', ['approval' => 'waiting_ceo']);
        $urlPoApproved = route('po.index', ['approval' => 'approved']);
        $urlPoRestock = route('po.index', ['from_restock' => 1]);
    @endphp

    <style>
        .card-link-wrap {
            position: relative;
        }

        .card-link-wrap .stretched-link {
            z-index: 3;
        }

        .card-disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .admin-dashboard {
            background-color: #f0f2f5 !important;
        }

        .layout-page .content-wrapper {
            width: 100% !important;
        }

        .container-xxl,
        .container-fluid {
            max-width: 100% !important;
        }

        /* Kartu-kartu di dashboard */
        .admin-dashboard .card {
            border-radius: 12px !important;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Baris "Main Metrics" biar rapi */
        .admin-dashboard .metrics-row {
            margin-bottom: 24px;
        }

        /* Card kecil yang menampilkan jumlah/total */
        .admin-dashboard .stat-card {
            height: 100%;
            display: flex;
            align-items: center;
        }

        /* Header kartu (judul) */
        .admin-dashboard .stat-card .card-header {
            border-bottom: none;
            background: transparent;
            font-size: 16px;
            font-weight: 600;
        }

        /* Body kartu (isi) */
        .admin-dashboard .stat-card .card-body {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        /* Baris "Procurement Pipeline" */
        .admin-dashboard .pipeline-section {
            margin-bottom: 24px;
        }

        .admin-dashboard .pipeline-section .card-title {
            font-size: 18px;
            font-weight: 600;
        }

        /* Kartu-kartu di pipeline (Pending Proc, Pending CEO, dll) */
        .admin-dashboard .pipeline-card {
            height: 100%;
            padding: 12px;
        }

        /* Bagian bawah kartu pipeline (tombol & info) */
        .admin-dashboard .pipeline-card-footer {
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        /* Status badge di pipeline */
        .admin-dashboard .status-badge {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Visual untuk setiap status pipeline */
        .admin-dashboard .status-pending-proc {
            background-color: #fff7ed;
            color: #ea580c;
        }

        .admin-dashboard .status-pending-ceo {
            background-color: #fef3c7;
            color: #f59e0b;
        }

        .admin-dashboard .status-approved {
            background-color: #d1fae5;
            color: #059669;
        }

        .admin-dashboard .status-restock {
            background-color: #ede9fe;
            color: #6366f1;
        }

        /* Visual untuk angka di pipeline */
        .admin-dashboard .pipeline-count {
            font-size: 24px;
            font-weight: 700;
        }

        /* Line chart */
        .admin-dashboard .chart-card {
            border-radius: 12px !important;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        /* Responsive adjustment */
        @media (max-width: 768px) {
            .admin-dashboard .stat-card {
                min-height: 100px;
            }

            .admin-dashboard .pipeline-card-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .admin-dashboard .status-badge {
                align-self: flex-start;
            }
        }
    </style>

    <div class="container-fluid flex-grow-1 container-p-y admin-dashboard px-3 px-lg-4">

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
                    <option value="day" {{ request('period') == 'day' ? 'selected' : '' }}>Hari ini</option>
                    <option value="week" {{ request('period') == 'week' ? 'selected' : '' }}>Mingguan</option>
                    <option value="month" {{ request('period', 'month') == 'month' ? 'selected' : '' }}>Bulanan</option>
                </select>

                {{-- MONTH (ONLY IF MONTH) --}}
                @if (request('period', 'month') === 'month')
                    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}"
                                {{ request('month', now()->month) == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                            </option>
                        @endforeach
                    </select>

                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach (range(now()->year - 3, now()->year + 1) as $y)
                            <option value="{{ $y }}" {{ request('year', now()->year) == $y ? 'selected' : '' }}>
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
                                @if ($period === 'day')
                                    Hari ini
                                @elseif($period === 'week')
                                    Minggu ini
                                @else
                                    Bulan ini
                                @endif
                                )
                            </small>
                            <div class="small mt-1 text-muted">
                                Total nilai: <span class="fw-semibold">Rp
                                    {{ number_format($txAmount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-primary">S</span>
                        </div>
                        <div>
                            <h5 class="mb-0">
                                Rp {{ number_format((int) ($stats['stock_value'] ?? 0), 0, ',', '.') }}
                            </h5>

                            <small class="text-muted d-block">
                                ≈ {{ number_format(($stats['stock_value'] ?? 0) / 1000000000, 2) }}
                                Miliar Rupiah
                            </small>

                            <small class="text-muted d-block">
                                Inventory Value (WH + Sales)
                            </small>
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
                            <h5 class="mb-0">{{ number_format((int) ($stats['products_total'] ?? 0), 0, ',', '.') }}</h5>
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
                            <h5 class="mb-0">{{ number_format((int) ($stats['warehouses_total'] ?? 0), 0, ',', '.') }}
                            </h5>
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
                    @if ($canOpenProc)
                        <a href="{{ $urlPoPendingProc }}" class="stretched-link"
                            aria-label="Buka PO Pending Procurement"></a>
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
                    @if ($canOpenCeo)
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
                            @if ($period === 'day')
                                Hari ini
                            @elseif($period === 'week')
                                Minggu ini
                            @else
                                Bulan ini
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
                            @if ($period === 'day')
                                Hari ini
                            @elseif($period === 'week')
                                Minggu ini
                            @else
                                Bulan ini
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
                                @if ($period === 'day')
                                    Hari ini
                                @elseif($period === 'week')
                                    Minggu ini
                                @else
                                    Bulan ini
                                @endif
                                )
                            </div>

                        </div>
                        <div class="text-end">
                            <div class="text-muted small">This month</div>
                            <div class="fw-bold">Rp
                                {{ number_format((int) ($stats['closed_amount_month'] ?? 0), 0, ',', '.') }}</div>
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

                    <div class="card-header pb-2">

                        <div class="fw-bold">
                            Top 10 Best Selling Product
                        </div>

                        <div class="text-muted small mb-2">
                            By Revenue
                        </div>

                        <form id="top-selling-form">
                            <select name="warehouse_id" id="top-selling-warehouse" class="form-select form-select-sm">

                                <option value="">
                                    All WH
                                </option>

                                @foreach ($warehouseOptions as $wh)
                                    <option value="{{ $wh->id }}"
                                        {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->warehouse_name }}
                                    </option>
                                @endforeach

                            </select>
                        </form>

                    </div>

                    <div class="card-body pt-2" id="top-selling-container">
                        <div class="text-center py-4 text-muted small">
                            Loading...
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        // Fetch Top Selling Data
        function fetchTopSelling() {
            const warehouseId = document.getElementById('top-selling-warehouse').value;
            const container = document.getElementById('top-selling-container');

            const params = new URLSearchParams(window.location.search);
            params.set('warehouse_id', warehouseId);

            container.innerHTML = '<div class="text-center py-4 text-muted small">Loading...</div>';

            fetch(`{{ route('admin.dashboard.top_selling') }}?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching top selling products:', error);
                    container.innerHTML = '<div class="text-danger small py-2">Failed to load data.</div>';
                });
        }

        document.addEventListener('DOMContentLoaded', function() {

            // Initial load for Top Selling
            fetchTopSelling();

            // Listen for dropdown changes
            document.getElementById('top-selling-warehouse').addEventListener('change', function() {
                fetchTopSelling();
            });

            const yearlyLabels = @json($charts['yearly']['labels'] ?? []);
            const yearlySeries = @json($charts['yearly']['series'] ?? []);

            const statusLabels = @json($charts['status']['labels'] ?? []);
            const statusSeries = @json($charts['status']['series'] ?? []);

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
                            toolbar: {
                                show: false
                            },
                            animations: {
                                enabled: true,
                                easing: 'easeinout',
                                speed: 900,
                                animateGradually: {
                                    enabled: true,
                                    delay: 120
                                },
                                dynamicAnimation: {
                                    enabled: true,
                                    speed: 500
                                }
                            }
                        },
                        series: [{
                            name: 'Closed Sales',
                            data: yearlySeries
                        }],
                        xaxis: {
                            categories: yearlyLabels
                        },
                        stroke: {
                            curve: 'smooth',
                            width: 3
                        },
                        dataLabels: {
                            enabled: false
                        },
                        markers: {
                            size: 0,
                            hover: {
                                size: 5
                            }
                        },
                        yaxis: {
                            labels: {
                                formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math
                                    .round(v || 0))
                            }
                        },
                        tooltip: {
                            shared: true,
                            intersect: false,
                            y: {
                                formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math
                                    .round(v || 0))
                            }
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
                                animateGradually: {
                                    enabled: true,
                                    delay: 80
                                },
                                dynamicAnimation: {
                                    enabled: true,
                                    speed: 500
                                }
                            }
                        },
                        labels: statusLabels,
                        series: statusSeries,
                        legend: {
                            position: 'bottom'
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '70%',
                                    labels: {
                                        show: false
                                    }
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: v => new Intl.NumberFormat('id-ID').format(Math.round(
                                    v || 0)) + ' handover'
                            }
                        }
                    }).render();
                }
            });

        });
    </script>
@endpush
