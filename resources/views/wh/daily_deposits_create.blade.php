@extends('layouts.home')

@section('title', 'Create New Deposit')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold py-1 mb-0">Daily Deposits / <span class="text-primary text-opacity-75">New Deposit</span></h4>
            <span class="text-muted">Process pending handovers into a bank deposit settlement.</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('warehouse.settlements.export.pending', request()->all()) }}" id="btnExportPending"
               style="background-color: #28a745; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bx bx-file"></i> Export Pending
            </a>
            <a href="{{ route('warehouse.settlements.index') }}" class="btn-back-custom">
                <i class="bx bx-arrow-back"></i> Back to History
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters Section -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form id="filterForm" action="{{ route('warehouse.settlements.create') }}" method="GET" class="row g-3 align-items-end">
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

    @php
        // Variables are now passed from controller
    @endphp

    @if($unsettled->count() > 0)
    <div class="card mb-4 bg-primary text-white shadow-none">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="text-white mb-1"><i class="bx bx-layer me-2"></i> Grand Total Unsettled (Filtered)</h5>
                    <p class="mb-0 opacity-75">There are <span id="grandCount">{{ $grandCount }}</span> transactions matching your filters.</p>
                    <div class="d-flex mt-3">
                        <div class="me-4 border-end pe-4">
                            <small class="d-block opacity-75 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Total Cash</small>
                             <h4 class="text-white mb-0" id="grandCashDisplay">Rp {{ number_format($grandCash, 0, ',', '.') }}</h4>
                        </div>
                        <div>
                            <small class="d-block opacity-75 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Total Transfer</small>
                            <h4 class="text-white mb-0" id="grandTFDisplay">Rp {{ number_format($grandTf, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-4 mt-md-0">
                    <button type="button" class="btn btn-lg btn-light text-primary shadow-sm btn-settle" 
                        data-date="" 
                        data-wh-id="{{ request('warehouse_id') }}"
                        data-sales-id="{{ request('sales_id') }}"
                        data-formatted="ALL FILTERED (BULK)"
                        data-cash="{{ number_format($grandCash, 0, ',', '.') }}"
                        data-tf="{{ number_format($grandTf, 0, ',', '.') }}">
                        <i class="bx bx-check-double me-1"></i> Deposit All (Bulk)
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Pending Transactions List</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="py-3">Date</th>
                        <th class="py-3">Warehouse</th>
                        <th class="py-3">Sales Person</th>
                        <th class="py-3 text-center">HDO Count</th>
                        <th class="py-3 text-end">Cash</th>
                        <th class="py-3 text-end">Transfer</th>
                        <th class="py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="unsettledTableBody">
                    @forelse($unsettled as $row)
                    <tr>
                        <td class="py-3">
                            <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($row->handover_date)->format('d M Y') }}</div>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($row->handover_date)->diffForHumans() }}</small>
                        </td>
                        <td class="py-3">
                            <span class="badge bg-label-info">{{ $row->warehouse->warehouse_name ?? $row->warehouse->name }}</span>
                        </td>
                        <td class="py-3">
                            <div class="fw-semibold">{{ $row->sales->name }}</div>
                            <small class="text-muted text-wrap d-block" style="max-width: 250px;">
                                @php
                                    $codes = \App\Models\SalesHandover::where('handover_date', $row->handover_date)
                                        ->where('warehouse_id', $row->warehouse_id)
                                        ->where('sales_id', $row->sales_id)
                                        ->whereNull('settlement_id')
                                        ->where('status', 'closed')
                                        ->pluck('code');
                                @endphp
                                <i class="bx bx-purchase-tag-alt small"></i> 
                                @php
                                    $codes = explode(', ', $row->hdo_codes);
                                    $displayCodes = implode(', ', array_slice($codes, 0, 3));
                                    $more = count($codes) - 3;
                                @endphp
                                {{ $displayCodes }}
                                @if($more > 0)
                                    <span class="badge bg-label-secondary small" style="font-size: 0.65rem;">+{{ $more }} more</span>
                                @endif
                            </small>
                        </td>
                        <td class="py-3 text-center">
                            <span class="badge bg-label-secondary rounded-pill">{{ $row->total_handovers }}</span>
                        </td>
                        <td class="py-3 text-end text-success fw-bold">
                            Rp {{ number_format($row->total_cash, 0, ',', '.') }}
                        </td>
                        <td class="py-3 text-end text-info fw-bold">
                            Rp {{ number_format($row->total_transfer, 0, ',', '.') }}
                        </td>
                        <td class="py-3 text-center">
                            <button type="button" class="btn btn-sm btn-primary shadow-sm btn-settle"
                                data-date="{{ $row->handover_date }}"
                                data-wh-id="{{ $row->warehouse_id }}"
                                data-sales-id="{{ $row->sales_id }}"
                                data-formatted="{{ \Carbon\Carbon::parse($row->handover_date)->format('d M Y') }} - {{ $row->sales->name }}"
                                data-cash="{{ number_format($row->total_cash, 0, ',', '.') }}"
                                data-tf="{{ number_format($row->total_transfer, 0, ',', '.') }}">
                                <i class="bx bx-send me-1"></i> Settle
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="py-4">
                                <i class="bx bx-check-circle display-1 text-light"></i>
                                <h5 class="mt-3 text-muted">All caught up! No pending transactions.</h5>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="paginationContainer" class="card-footer d-flex justify-content-center">
            {{ $unsettled->links() }}
        </div>
    </div>
</div>

<!-- Modal Settle -->
<div class="modal fade" id="settleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('warehouse.settlements.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="handover_date" id="modal_date">
            <input type="hidden" name="warehouse_id" id="modal_wh">
            <input type="hidden" name="sales_id" id="modal_sales">
            
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom bg-primary">
                    <h5 class="modal-title text-white" id="modalTitle">Settle: <span></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="row mb-4 text-center">
                        <div class="col-6 border-end">
                            <small class="text-muted d-block mb-1 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Total Cash</small>
                            <h4 class="text-success mb-0 fw-bold" id="modal_cash">Rp 0</h4>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block mb-1 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Total Transfer</small>
                            <h4 class="text-info mb-0 fw-bold" id="modal_tf">Rp 0</h4>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Upload Deposit Receipt</label>
                        <input type="file" name="proof_path" class="form-control form-control-lg" accept="image/jpeg,image/png,image/jpg" required>
                        <div class="form-text">Photo/Screenshot of your bank transfer or cash deposit slip.</div>
                    </div>
                    
                    <div class="alert alert-info py-2 mb-0 d-flex align-items-center" style="font-size: 0.85rem">
                        <i class="bx bx-info-circle me-2 fs-4"></i> 
                        <span>Data will be locked and marked as officially deposited.</span>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bx bx-check me-1"></i> Confirm & Submit
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<style>
    .btn-back-custom {
        background-color: #f8f9fa;
        color: #566a7f;
        border: 1px solid #d9dee3;
        border-radius: 6px;
        padding: 8px 16px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background-color 0.2s;
    }
    .btn-back-custom:hover,
    .btn-back-custom:focus,
    .btn-back-custom:active {
        background-color: #e9ecef;
        color: #566a7f !important;
        border-color: #c8cdd2;
        text-decoration: none;
    }
    .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const tableBody = document.getElementById('unsettledTableBody');
    const grandCashDisplay = document.getElementById('grandCashDisplay');
    const grandTFDisplay = document.getElementById('grandTFDisplay');
    const grandCount = document.getElementById('grandCount');
    const btnExportPending = document.getElementById('btnExportPending');
    const paginationContainer = document.getElementById('paginationContainer');
    const globalSearch = document.getElementById('globalSearch');
    
    const modalElement = document.getElementById('settleModal');
    const modal = new bootstrap.Modal(modalElement);

    function initSettleButtons() {
        const settleBtns = document.querySelectorAll('.btn-settle');
        settleBtns.forEach(btn => {
            btn.onclick = function() {
                const date = this.dataset.date;
                const wh = this.dataset.whId;
                const sales = this.dataset.salesId;
                const formatted = this.dataset.formatted;
                const cash = this.dataset.cash;
                const tf = this.dataset.tf;
                
                document.getElementById('modal_date').value = date;
                document.getElementById('modal_wh').value = wh || '';
                document.getElementById('modal_sales').value = sales || '';
                
                document.querySelector('#modalTitle span').innerText = formatted;
                document.getElementById('modal_cash').innerText = 'Rp ' + cash;
                document.getElementById('modal_tf').innerText = 'Rp ' + tf;
                
                const header = document.querySelector('.modal-header');
                if (date === "") {
                    header.classList.add('bg-dark');
                    header.classList.remove('bg-primary');
                } else {
                    header.classList.add('bg-primary');
                    header.classList.remove('bg-dark');
                }
                
                modal.show();
            };
        });
    }

    function applyFilters(page = 1) {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        
        if (globalSearch && globalSearch.value) {
            params.append('search', globalSearch.value);
        }
        params.append('page', page);

        const url = `${filterForm.action}?${params.toString()}`;

        // Update Export URL
        const exportBaseUrl = "{{ route('warehouse.settlements.export.pending') }}";
        btnExportPending.href = `${exportBaseUrl}?${params.toString()}`;

        // Fetch Data
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                // Update Totals
                grandCashDisplay.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.grand_cash);
                grandTFDisplay.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.grand_tf);
                grandCount.innerText = data.grand_count;

                // Update Table
                tableBody.innerHTML = '';
                if(data.unsettled.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="py-4"><i class="bx bx-check-circle display-1 text-light"></i><h5 class="mt-3 text-muted">All caught up! No pending transactions.</h5></div></td></tr>';
                } else {
                    data.unsettled.forEach(row => {
                        const dateRaw = new Date(row.handover_date);
                        const date = dateRaw.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                        const cash = new Intl.NumberFormat('id-ID').format(row.total_cash);
                        const tf = new Intl.NumberFormat('id-ID').format(row.total_transfer);
                        const whName = row.warehouse ? (row.warehouse.warehouse_name || row.warehouse.name) : '-';
                        const salesName = row.sales ? row.sales.name : '-';
                        const allCodes = (row.hdo_codes || '').split(', ');
                        const displayCodes = allCodes.slice(0, 3).join(', ');
                        const moreCount = allCodes.length > 3 ? allCodes.length - 3 : 0;
                        const moreBadge = moreCount > 0 ? `<span class="badge bg-label-secondary small" style="font-size: 0.65rem;">+${moreCount} more</span>` : '';
                        
                        tableBody.innerHTML += `
                            <tr>
                                <td class="py-3">
                                    <div class="fw-bold text-dark">${date}</div>
                                    <small class="text-muted">Just updated</small>
                                </td>
                                <td class="py-3"><span class="badge bg-label-info">${whName}</span></td>
                                <td class="py-3">
                                    <div class="fw-semibold">${salesName}</div>
                                    <small class="text-muted text-wrap d-block" style="max-width: 250px;">
                                        <i class="bx bx-purchase-tag-alt small"></i> ${displayCodes} ${moreBadge}
                                    </small>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge bg-label-secondary rounded-pill">${row.total_handovers}</span>
                                </td>
                                <td class="py-3 text-end text-success fw-bold">Rp ${cash}</td>
                                <td class="py-3 text-end text-info fw-bold">Rp ${tf}</td>
                                <td class="py-3 text-center">
                                    <button type="button" class="btn btn-sm btn-primary shadow-sm btn-settle" 
                                        data-date="${row.handover_date}"
                                        data-wh-id="${row.warehouse_id}"
                                        data-sales-id="${row.sales_id}"
                                        data-formatted="${date} - ${salesName}"
                                        data-cash="${cash}"
                                        data-tf="${tf}">
                                        <i class="bx bx-send me-1"></i> Settle
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    initSettleButtons();
                }
                
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

    initSettleButtons();
});
</script>
@endpush
