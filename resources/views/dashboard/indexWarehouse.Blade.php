    @extends('layouts.home')

    @section('content')
    @php
    // fallback aman kalau controller belum kirim data tertentu
    $me        = $me        ?? auth()->user();
    $greeting  = $greeting  ?? ('Selamat datang, Wh ' . ($me->name ?? 'User'));
    $whName    = $me?->warehouse?->warehouse_name ?? 'Warehouse';
    $stats     = $stats     ?? ['products_total'=>0,'low_stock_count'=>0,'restock_pending'=>0,'today_in'=>0,'today_out'=>0];
    $inout     = $inout     ?? ['labels'=>[],'in'=>[],'out'=>[]];
    $lowStocks = $lowStocks ?? [];   // item: ['code','name','package','min','current']
    $restocks  = $restocks  ?? [];   // item: ['code','product','qty','status','requested_at']
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
        <h4 class="mb-1 fw-bold">Warehouse Dashboard</h4>
        <div class="text-muted">{{ $greeting }}</div>
        <div class="small text-muted">Lokasi: <span class="fw-semibold">{{ $whName }}</span></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
        <a href="{{ url('products') }}" class="btn btn-sm btn-primary">
            <i class="bx bx-package me-1"></i> Kelola Produk
        </a>
        <a href="{{ url('restocks') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bx bx-refresh me-1"></i> Restock
        </a>
        <a href="{{ url('suppliers') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bx bx-group me-1"></i> Supplier
        </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
            <div class="flex-grow-1">
                <div class="text-muted mb-1">Total Produk</div>
                <div class="h4 mb-0">{{ number_format($stats['products_total']) }}</div>
            </div>
            <div class="ms-3 fs-3 text-primary"><i class="bx bx-box"></i></div>
            </div>
        </div>
        </div>
        <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
            <div class="flex-grow-1">
                <div class="text-muted mb-1">Low Stock</div>
                <div class="h4 mb-0">{{ number_format($stats['low_stock_count']) }}</div>
            </div>
            <div class="ms-3 fs-3 text-warning"><i class="bx bx-error-circle"></i></div>
            </div>
        </div>
        </div>
        <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
            <div class="flex-grow-1">
                <div class="text-muted mb-1">Restock Pending</div>
                <div class="h4 mb-0">{{ number_format($stats['restock_pending']) }}</div>
            </div>
            <div class="ms-3 fs-3 text-info"><i class="bx bx-time"></i></div>
            </div>
        </div>
        </div>
        <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body">
            <div class="text-muted mb-1">Transaksi Hari Ini</div>
            <div class="d-flex align-items-end justify-content-between">
                <div>
                <div class="small text-success">Inbound</div>
                <div class="h5 mb-0">{{ number_format($stats['today_in']) }}</div>
                </div>
                <div class="text-end">
                <div class="small text-danger">Outbound</div>
                <div class="h5 mb-0">{{ number_format($stats['today_out']) }}</div>
                </div>
            </div>
            </div>
        </div>
        </div>
    </div>

    {{-- Chart + Quick menu --}}
    <div class="row g-3">
        <div class="col-12 col-xl-8">
        <div class="card h-100">
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
        <div class="card h-100">
            <div class="card-header">
            <h6 class="mb-0 fw-semibold">Aksi Cepat</h6>
            </div>
            <div class="card-body">
            <div class="list-group">
                <a class="list-group-item list-group-item-action d-flex align-items-center" href="{{ url('products/create') }}">
                <i class="bx bx-plus-circle me-2"></i> Tambah Produk
                </a>
                <a class="list-group-item list-group-item-action d-flex align-items-center" href="{{ url('restocks/create') }}">
                <i class="bx bx-refresh me-2"></i> Buat Request Restock
                </a>
                <a class="list-group-item list-group-item-action d-flex align-items-center" href="{{ url('suppliers/create') }}">
                <i class="bx bx-user-plus me-2"></i> Tambah Supplier
                </a>
                <a class="list-group-item list-group-item-action d-flex align-items-center" href="{{ url('warehouses') }}">
                <i class="bx bx-building-house me-2"></i> Data Warehouse
                </a>
            </div>
            </div>
        </div>
        </div>
    </div>

    {{-- Tables --}}
    <div class="row g-3 mt-1">
        {{-- Low stocks --}}
        <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Produk Low Stock</h6>
            <a href="{{ url('products') }}" class="small">Lihat semua</a>
            </div>
            <div class="card-body p-0">
            <div class="table-responsive">
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

        {{-- Restock pending --}}
        <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Restock Pending</h6>
            <a href="{{ url('restocks') }}" class="small">Lihat semua</a>
            </div>
            <div class="card-body p-0">
            <div class="table-responsive">
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

    // fallback kalau kosong
    function dstr(d){ return d.toISOString().slice(0,10); }
    if (!labels.length) {
        const days = [];
        for (let i=6;i>=0;i--) { const d=new Date(); d.setDate(d.getDate()-i); days.push(dstr(d)); }
        while (inbound.length < 7) inbound.unshift(0);
        while (outbound.length < 7) outbound.unshift(0);
        labels.push(...days);
    }

    const el = document.getElementById('inoutChart');
    if (!el) return;

    new Chart(el, {
        type: 'bar',
        data: {
        labels: labels,
        datasets: [
            { label: 'Inbound',  data: inbound },
            { label: 'Outbound', data: outbound }
        ]
        },
        options: {
        maintainAspectRatio: false,
        responsive: true,
        scales: {
            x: { grid: { display:false } },
            y: { beginAtZero:true, ticks:{ precision:0 } }
        },
        plugins: {
            legend: { display:true, position:'top' },
            tooltip: { mode:'index', intersect:false }
        }
        }
    });
    })();
    </script>
    @endpush
