@extends('layouts.home')

@section('title', 'Verifikasi Setoran')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold py-1 mb-0">Verifikasi Setoran (Pusat)</h4>
            <span class="text-muted">Verify daily warehouse deposits against company bank statements.</span>
        </div>
        <a href="{{ route('finance.settlements.export', request()->all()) }}" id="btnExportFinance"
           style="background-color: #28a745; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <i class="bx bx-file"></i> Export Laporan
        </a>
    </div>

    <!-- Filter -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form id="filterForm" action="{{ route('finance.settlements.index') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Warehouse</label>
                    <select name="warehouse_id" class="form-select border-light bg-light">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->warehouse_name ?? $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Date Start</label>
                    <input type="date" name="date_start" class="form-control border-light bg-light" value="{{ request('date_start') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Date End</label>
                    <input type="date" name="date_end" class="form-control border-light bg-light" value="{{ request('date_end') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Show</label>
                    <select name="per_page" class="form-select border-light bg-light">
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th>Admin Depositor</th>
                        <th class="text-end">Total Cash</th>
                        <th class="text-end">Total Transfer</th>
                        <th class="text-center">Bank Proof</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="settlementsTableBody">
                    @forelse($settlements as $set)
                    <tr>
                        <td><span class="badge bg-label-secondary fw-bold">SET-{{ str_pad($set->id, 5, '0', STR_PAD_LEFT) }}</span></td>
                        <td>{{ $set->settlement_date->format('d M Y') }}</td>
                        <td><strong>{{ $set->warehouse->warehouse_name ?? $set->warehouse->name ?? '-' }}</strong></td>
                        <td>{{ $set->admin->name ?? '-' }}</td>
                        <td class="text-end text-success fw-bold">Rp {{ number_format($set->total_cash_amount, 0, ',', '.') }}</td>
                        <td class="text-end text-info fw-bold">Rp {{ number_format($set->total_transfer_amount, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($set->proof_path)
                                <button type="button" class="btn btn-sm btn-outline-primary btn-view-proof" data-url="{{ asset('storage/' . $set->proof_path) }}">
                                    <i class="bx bx-image"></i> View Struk
                                </button>
                            @else
                                <span class="badge bg-label-warning">No Proof</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-info btn-detail text-white" data-id="{{ $set->id }}">
                                <i class="bx bx-list-ul"></i> Detail HDO
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No settlements found for this filter.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="paginationContainer" class="card-footer d-flex justify-content-center">
            {{ $settlements->links() }}
        </div>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-info border-bottom">
        <h5 class="modal-title text-white" id="detailModalTitle">Settlement Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" id="detailTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 120px">Date</th>
                        <th>HDO Code</th>
                        <th>Sales</th>
                        <th>Items (Sold Products)</th>
                        <th class="text-end" style="width: 140px">Cash</th>
                        <th class="text-end" style="width: 140px">Transfer</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Injected via JS -->
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <th colspan="4" class="text-end">TOTAL SETTLEMENT</th>
                        <th id="totCash" class="text-end text-success">0</th>
                        <th id="totTf" class="text-end text-info">0</th>
                    </tr>
                </tfoot>
            </table>
        </div>
      </div>
      <div class="modal-footer bg-light py-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Proof Image -->
<div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom bg-primary py-2">
                <h5 class="modal-title text-white">Bank Proof / Deposit Struk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 text-center bg-dark overflow-hidden position-relative" style="height: 75vh;">
                <div id="zoomContainer" class="w-100 h-100 d-flex align-items-center justify-content-center" style="cursor: zoom-in;">
                    <img id="proofImage" src="" alt="Proof" class="img-fluid zoomable-img">
                </div>
                <div class="position-absolute bottom-0 start-0 w-100 py-2 bg-dark bg-opacity-75 text-white text-center pointer-events-none shadow-lg">
                    <small id="zoomHint"><i class="bx bx-pointer me-1"></i> Click to zoom & explore</small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .zoomable-img {
        transition: transform 0.25s ease-out;
        transform-origin: center center;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        pointer-events: none; /* Let container handle clicks */
    }
    #zoomContainer.is-zoomed .zoomable-img {
        transform: scale(3.5);
    }
    .btn-success, .btn-success:hover, .btn-success:active, .btn-success:focus {
        background-color: #28a745 !important;
        color: #ffffff !important;
        border-color: #28a745 !important;
        box-shadow: none !important;
        opacity: 1 !important;
    }
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const tableBody = document.getElementById('settlementsTableBody');
    const btnExportFinance = document.getElementById('btnExportFinance');
    const globalSearch = document.getElementById('globalSearch'); // Match navbar ID
    const paginationContainer = document.getElementById('paginationContainer');
    
    const proofModal = new bootstrap.Modal(document.getElementById('proofModal'));
    const proofImg = document.getElementById('proofImage');
    const zoomContainer = document.getElementById('zoomContainer');
    const zoomHint = document.getElementById('zoomHint');
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    const tbodyDetail = document.querySelector('#detailTable tbody');

    function applyFilters(page = 1) {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        
        if (globalSearch && globalSearch.value) {
            params.append('search', globalSearch.value);
        }
        params.append('page', page);

        const url = `${filterForm.action}?${params.toString()}`;
        btnExportFinance.href = `{{ route('finance.settlements.export') }}?${params.toString()}`;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                tableBody.innerHTML = '';
                if(data.settlements.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-5">No records found</td></tr>';
                } else {
                    data.settlements.forEach(row => {
                        const date = new Date(row.settlement_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                        const code = 'SET-' + row.id.toString().padStart(5, '0');
                        tableBody.innerHTML += `
                            <tr>
                                <td><span class="badge bg-label-secondary fw-bold">${code}</span></td>
                                <td>${date}</td>
                                <td><strong>${row.warehouse?.warehouse_name || row.warehouse?.name || '-'}</strong></td>
                                <td>${row.admin?.name || '-'}</td>
                                <td class="text-end text-success fw-bold">Rp ${new Intl.NumberFormat('id-ID').format(row.total_cash_amount)}</td>
                                <td class="text-end text-info fw-bold">Rp ${new Intl.NumberFormat('id-ID').format(row.total_transfer_amount)}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-view-proof" data-url="/storage/${row.proof_path}">
                                        <i class="bx bx-image"></i> View
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-info btn-detail text-white" data-id="${row.id}">
                                        <i class="bx bx-list-ul"></i> Detail
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    initButtons();
                }
                if (paginationContainer) {
                    paginationContainer.innerHTML = data.pagination || '';
                }
            }
        });
    }

    function initButtons() {
        document.querySelectorAll('.btn-view-proof').forEach(btn => {
            btn.onclick = function() {
                proofImg.src = this.dataset.url;
                zoomContainer.classList.remove('is-zoomed');
                zoomContainer.style.cursor = 'zoom-in';
                zoomHint.innerHTML = '<i class="bx bx-pointer me-1"></i> Click to zoom & explore';
                proofModal.show();
            };
        });

        document.querySelectorAll('.btn-detail').forEach(btn => {
            btn.onclick = function() {
                const id = this.dataset.id;
                tbodyDetail.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading...</td></tr>';
                detailModal.show();
                fetch(`/finance/settlements/${id}/detail`)
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            tbodyDetail.innerHTML = '';
                            let c = 0, t = 0;
                            data.handovers.forEach(h => {
                                c += parseInt(h.cash_amount || 0);
                                t += parseInt(h.transfer_amount || 0);
                                let items = (h.items || []).map(it => `<li>• ${it.product?.name || 'Item'} (${it.qty_sold || 0} pcs)</li>`).join('');
                                tbodyDetail.innerHTML += `
                                    <tr>
                                        <td>${new Date(h.handover_date).toLocaleDateString('id-ID')}</td>
                                        <td>${h.code}</td>
                                        <td>${h.sales?.name || '-'}</td>
                                        <td><ul class="list-unstyled mb-0">${items || 'No items'}</ul></td>
                                        <td class="text-end text-success">Rp ${new Intl.NumberFormat('id-ID').format(h.cash_amount)}</td>
                                        <td class="text-end text-info">Rp ${new Intl.NumberFormat('id-ID').format(h.transfer_amount)}</td>
                                    </tr>
                                `;
                            });
                            document.getElementById('totCash').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(c);
                            document.getElementById('totTf').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(t);
                        }
                    });
            };
        });
    }

    if (filterForm) {
        filterForm.querySelectorAll('select, input').forEach(input => {
            input.addEventListener('change', () => applyFilters(1));
        });
    }

    if (globalSearch) {
        let timeout = null;
        globalSearch.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => applyFilters(1), 500);
        });
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', function(e) {
            const link = e.target.closest('.page-link');
            if (link && link.href && !link.href.includes('javascript:void(0)')) {
                e.preventDefault();
                const url = new URL(link.href);
                applyFilters(url.searchParams.get('page'));
            }
        });
    }

    zoomContainer.addEventListener('click', function() {
        const isZoomed = this.classList.toggle('is-zoomed');
        this.style.cursor = isZoomed ? 'zoom-out' : 'zoom-in';
        zoomHint.innerHTML = isZoomed ? '<i class="bx bx-move me-1"></i> Move mouse to explore | Click to zoom out' : '<i class="bx bx-pointer me-1"></i> Click to zoom & explore';
        if (!isZoomed) proofImg.style.transformOrigin = 'center center';
    });

    zoomContainer.addEventListener('mousemove', function(e) {
        if (!this.classList.contains('is-zoomed')) return;
        const rect = this.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        proofImg.style.transformOrigin = `${x}% ${y}%`;
    });

    initButtons();
});
</script>
@endpush
