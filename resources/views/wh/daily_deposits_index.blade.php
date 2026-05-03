@extends('layouts.home')

@section('title', 'Daily Deposits History')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold py-1 mb-0">Daily Deposits</h4>
            <span class="text-muted">Manage your daily cash and transfer deposits to the central company account.</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('warehouse.settlements.export.history', request()->all()) }}" id="btnExportHistory"
               style="background-color: #28a745; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bx bx-file"></i> Export Excel
            </a>
            <a href="{{ route('warehouse.settlements.create') }}" class="btn btn-primary shadow-sm">
                <i class="bx bx-plus"></i> New Deposit
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters Section -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form id="filterForm" action="{{ route('warehouse.settlements.index') }}" method="GET" class="row g-3 align-items-end">
                @if(auth()->user()->hasRole(['superadmin', 'admin']))
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
                @endif
                <div class="col-md-{{ auth()->user()->hasRole(['superadmin', 'admin']) ? '3' : '5' }}">
                    <label class="form-label text-muted small fw-bold text-uppercase">Date Start</label>
                    <input type="date" name="date_start" class="form-control border-light bg-light" value="{{ request('date_start') }}">
                </div>
                <div class="col-md-{{ auth()->user()->hasRole(['superadmin', 'admin']) ? '3' : '5' }}">
                    <label class="form-label text-muted small fw-bold text-uppercase">Date End</label>
                    <input type="date" name="date_end" class="form-control border-light bg-light" value="{{ request('date_end') }}">
                </div>
                <div class="col-md-{{ auth()->user()->hasRole(['superadmin', 'admin']) ? '3' : '2' }}">
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

    <div class="card shadow-sm border-0">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Date</th>
                        <th>Deposited By</th>
                        <th class="text-end">Total Cash</th>
                        <th class="text-end">Total Transfer</th>
                        <th class="text-center">Bank Proof</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="settlementsTableBody">
                    @forelse($settlements as $set)
                    <tr>
                        <td>
                            <span class="badge bg-label-secondary fw-bold">SET-{{ str_pad($set->id, 5, '0', STR_PAD_LEFT) }}</span>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $set->settlement_date->format('d M Y') }}</div>
                            <small class="text-muted" style="font-size: 0.75rem;">
                                {{ $set->created_at->diffForHumans() }}
                            </small>
                        </td>
                        <td>{{ $set->admin->name ?? '-' }}</td>
                        <td class="text-end text-success fw-bold">Rp {{ number_format($set->total_cash_amount, 0, ',', '.') }}</td>
                        <td class="text-end text-info fw-bold">Rp {{ number_format($set->total_transfer_amount, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($set->proof_path)
                                <button type="button" class="btn btn-sm btn-outline-primary btn-view-proof" data-url="{{ asset('storage/' . $set->proof_path) }}">
                                    <i class="bx bx-image"></i> View
                                </button>
                            @else
                                <span class="badge bg-label-warning">None</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-info btn-detail text-white" data-id="{{ $set->id }}">
                                <i class="bx bx-list-ul"></i> Detail
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No settlements found for this filter.</td>
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
        <h5 class="modal-title text-white">Handover Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" id="detailTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>HDO Code</th>
                        <th>Sales</th>
                        <th>Items (Sold Products)</th>
                        <th class="text-end">Cash</th>
                        <th class="text-end">Transfer</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer bg-light py-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
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
            <div class="modal-body p-0 text-center bg-dark overflow-hidden position-relative" style="height: 75vh;">
                <div id="zoomContainer" class="w-100 h-100 d-flex align-items-center justify-content-center" style="cursor: zoom-in;">
                    <img id="proofImage" src="" alt="Proof" class="img-fluid zoomable-img">
                </div>
                <div class="position-absolute bottom-0 start-0 w-100 py-2 bg-dark bg-opacity-75 text-white text-center pointer-events-none">
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
        pointer-events: none;
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
    const btnExportHistory = document.getElementById('btnExportHistory');

    const globalSearch = document.getElementById('globalSearch');
    const paginationContainer = document.getElementById('paginationContainer');

    function applyFilters(page = 1) {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        
        if (globalSearch && globalSearch.value) {
            params.append('search', globalSearch.value);
        }
        
        params.append('page', page);
        
        const url = `${filterForm.action}?${params.toString()}`;

        const exportBaseUrl = "{{ route('warehouse.settlements.export.history') }}";
        btnExportHistory.href = `${exportBaseUrl}?${params.toString()}`;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                tableBody.innerHTML = '';
                if(data.settlements.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-5">No records found</td></tr>';
                } else {
                    data.settlements.forEach(row => {
                        const date = new Date(row.settlement_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                        const cash = new Intl.NumberFormat('id-ID').format(row.total_cash_amount);
                        const tf = new Intl.NumberFormat('id-ID').format(row.total_transfer_amount);
                        const proofUrl = `/storage/${row.proof_path}`;
                        const code = 'SET-' + row.id.toString().padStart(5, '0');
                        
                        tableBody.innerHTML += `
                            <tr>
                                <td><span class="badge bg-label-secondary fw-bold">${code}</span></td>
                                <td><div class="fw-bold">${date}</div></td>
                                <td>${row.admin ? row.admin.name : '-'}</td>
                                <td class="text-end text-success fw-bold">Rp ${cash}</td>
                                <td class="text-end text-info fw-bold">Rp ${tf}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-view-proof" data-url="${proofUrl}">
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
                    initProofButtons();
                    initDetailButtons();
                }
                // Update Pagination
                if (paginationContainer) {
                    paginationContainer.innerHTML = data.pagination || '';
                }
            }
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

    // Intercept Pagination Links
    if (paginationContainer) {
        paginationContainer.addEventListener('click', function(e) {
            const link = e.target.closest('.page-link');
            if (link && link.href && !link.href.includes('javascript:void(0)')) {
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.searchParams.get('page');
                applyFilters(page);
            }
        });
    }

    const proofModal = new bootstrap.Modal(document.getElementById('proofModal'));
    const proofImg = document.getElementById('proofImage');
    const zoomContainer = document.getElementById('zoomContainer');
    const zoomHint = document.getElementById('zoomHint');

    function initProofButtons() {
        document.querySelectorAll('.btn-view-proof').forEach(btn => {
            btn.onclick = function() {
                proofImg.src = this.dataset.url;
                zoomContainer.classList.remove('is-zoomed');
                zoomContainer.style.cursor = 'zoom-in';
                zoomHint.innerHTML = '<i class="bx bx-pointer me-1"></i> Click to zoom & explore';
                proofModal.show();
            };
        });
    }

    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    const tbodyDetail = document.querySelector('#detailTable tbody');

    function initDetailButtons() {
        document.querySelectorAll('.btn-detail').forEach(btn => {
            btn.onclick = function() {
                const id = this.dataset.id;
                tbodyDetail.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading...</td></tr>';
                detailModal.show();
                
                fetch(`/warehouse/settlements/${id}/detail`)
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            tbodyDetail.innerHTML = '';
                            data.handovers.forEach(h => {
                                let itemsHtml = '<ul class="list-unstyled mb-0 small">';
                                if(h.items && h.items.length > 0) {
                                    h.items.forEach(it => {
                                        itemsHtml += `<li>• ${it.product ? it.product.name : 'Unknown'} (${it.qty_sold} pcs)</li>`;
                                    });
                                } else {
                                    itemsHtml += '<li>No items recorded</li>';
                                }
                                itemsHtml += '</ul>';

                                const hDate = h.handover_date ? new Date(h.handover_date).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';

                                tbodyDetail.innerHTML += `
                                    <tr>
                                        <td>${hDate}</td>
                                        <td>${h.code || '-'}</td>
                                        <td>${h.sales ? h.sales.name : '-'}</td>
                                        <td>${itemsHtml}</td>
                                        <td class="text-end fw-bold text-success">Rp ${new Intl.NumberFormat('id-ID').format(h.cash_amount)}</td>
                                        <td class="text-end fw-bold text-info">Rp ${new Intl.NumberFormat('id-ID').format(h.transfer_amount)}</td>
                                    </tr>
                                `;
                            });
                        }
                    });
            };
        });
    }

    zoomContainer.addEventListener('click', function() {
        const isZoomed = this.classList.toggle('is-zoomed');
        if (isZoomed) {
            this.style.cursor = 'zoom-out';
            zoomHint.innerHTML = '<i class="bx bx-move me-1"></i> Move to explore | Click to zoom out';
        } else {
            this.style.cursor = 'zoom-in';
            zoomHint.innerHTML = '<i class="bx bx-pointer me-1"></i> Click to zoom & explore';
        }
    });

    zoomContainer.addEventListener('mousemove', function(e) {
        if (!this.classList.contains('is-zoomed')) return;
        const rect = this.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        proofImg.style.transformOrigin = `${x}% ${y}%`;
    });

    initProofButtons();
    initDetailButtons();
});
</script>
@endpush
