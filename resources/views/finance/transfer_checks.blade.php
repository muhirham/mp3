@extends('layouts.home')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Finance /</span> Transfer Verifications
    </h4>

    <div id="financeAjaxContainer">
        {{-- FILTER FORM --}}
        <form method="GET" action="{{ route('finance.transfers') }}" class="mb-4" id="filterForm">
            @if (request('q'))
                <input type="hidden" name="q" value="{{ request('q') }}">
            @endif

            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between">
                                <label class="form-label text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Warehouse</label>
                                @if(request('date_from') != \Carbon\Carbon::now()->startOfMonth()->toDateString() || request('date_to') != \Carbon\Carbon::now()->toDateString() || request('warehouse_id'))
                                <a href="{{ route('finance.transfers') }}" id="btnReset" class="text-danger small" style="text-decoration: none;"><i class="bx bx-refresh"></i> Clear Filters</a>
                                @endif
                            </div>
                            <select name="warehouse_id" class="form-select">
                                <option value="">All Warehouses</option>
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->warehouse_name ?? ($wh->name ?? $wh->warehouse_code) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if (request('q'))
                        <div class="mt-3">
                            <span class="badge bg-label-info">
                                Search: <strong>{{ request('q') }}</strong>
                                <a href="{{ route('finance.transfers', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'warehouse_id' => $warehouseId]) }}"
                                    class="ms-1 text-info reset-search"><i class="bx bx-x"></i></a>
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </form>

        {{-- SUMMARY CARDS --}}
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <span class="d-block mb-1 text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">Total HDO with Transfers</span>
                        <h4 class="mb-0 fw-bold">{{ $totalHdoCount }} <small class="text-muted fw-normal fs-6">HDO</small>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <span class="d-block mb-1 text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">Total Verified Transfer Nominal</span>
                        <h4 class="mb-0 fw-bold text-success">Rp {{ number_format($totalTransferNominal, 0, ',', '.') }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- MAIN TABLE --}}
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">Transfer Verification List</h5>
            </div>
            <div class="table-responsive text-nowrap" style="min-height: 250px;">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>DATE</th>
                            <th>HDO CODE</th>
                            <th>WAREHOUSE</th>
                            <th>SALES</th>
                            <th>STATUS</th>
                            <th class="text-end">TOTAL TRANSFER</th>
                            <th class="text-center">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($rows as $i => $row)
                            @php
                                $stLabel = $row['status'];
                                $badgeClass = 'bg-label-secondary';
                                if ($stLabel === 'closed') {
                                    $badgeClass = 'bg-label-success';
                                } elseif ($stLabel === 'on_sales') {
                                    $badgeClass = 'bg-label-info';
                                } elseif ($stLabel === 'waiting_morning_otp' || $stLabel === 'waiting_evening_otp') {
                                    $badgeClass = 'bg-label-warning';
                                } elseif ($stLabel === 'cancelled') {
                                    $badgeClass = 'bg-label-danger';
                                }
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td class="fw-medium">{{ $row['code'] }}</td>
                                <td>{{ $row['warehouse'] }}</td>
                                <td>{{ $row['sales'] }}</td>
                                <td><span class="badge {{ $badgeClass }}">{{ strtoupper(str_replace('_', ' ', $stLabel)) }}</span></td>
                                <td class="text-end fw-bold">Rp {{ number_format($row['transfer_nominal'], 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProof{{ $row['id'] }}">
                                        View Proofs
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No transfer payments found for the selected criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- MODALS FOR TRANSFER PROOFS --}}
        @foreach ($rows as $row)
            <div class="modal fade" id="modalProof{{ $row['id'] }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header border-bottom pb-3">
                            <h5 class="modal-title">Transfer Proofs - {{ $row['code'] }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Sales:</strong> {{ $row['sales'] }}<br>
                                    <strong>Warehouse:</strong> {{ $row['warehouse'] }}
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <strong>Total Transfer (This HDO):</strong><br>
                                    <span class="fs-4 fw-bold text-success">Rp {{ number_format($row['transfer_nominal'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>PRODUCT</th>
                                            <th class="text-end">SOLD QTY</th>
                                            <th class="text-end">TRANSFER NOMINAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($row['items'] as $item)
                                            <tr>
                                                <td>{{ $item->product->name ?? 'Unknown Product' }}</td>
                                                <td class="text-end">{{ $item->qty_sold }}</td>
                                                <td class="text-end fw-medium">Rp {{ number_format($item->payment_amount, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <h6 class="fw-bold mb-3">Proof Attachments:</h6>
                            <div class="row g-3">
                                @foreach ($row['items'] as $item)
                                    @if ($item->payment_transfer_proof_path)
                                        <div class="col-md-6">
                                            <div class="card border shadow-none">
                                                <div class="card-header py-2 bg-light border-bottom">
                                                    <small class="fw-bold">{{ $item->product->name ?? 'Product' }}</small>
                                                </div>
                                                <div class="card-body p-2 text-center">
                                                    <a href="{{ Storage::url($item->payment_transfer_proof_path) }}" target="_blank">
                                                        <img src="{{ Storage::url($item->payment_transfer_proof_path) }}" alt="Transfer Proof" class="img-fluid rounded" style="max-height: 250px; object-fit: contain;">
                                                    </a>
                                                    <div class="mt-2 text-muted small">Click image to enlarge</div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="col-md-6">
                                            <div class="card border shadow-none h-100 bg-lighter">
                                                <div class="card-header py-2 bg-light border-bottom">
                                                    <small class="fw-bold">{{ $item->product->name ?? 'Product' }}</small>
                                                </div>
                                                <div class="card-body p-4 text-center d-flex flex-column justify-content-center align-items-center">
                                                    <i class="bx bx-image-alt fs-1 text-muted mb-2"></i>
                                                    <span class="text-muted">No proof uploaded</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer border-top pt-3">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<style>
    .finance-loader-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255, 255, 255, 0.6);
        z-index: 1050;
        display: flex;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(1px);
        border-radius: 0.5rem;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    let debounceTimer;

    function loadAjaxData(url) {
        const container = document.getElementById('financeAjaxContainer');
        
        // Show Spinner overlay instead of opacity
        container.style.position = 'relative';
        const loader = document.createElement('div');
        loader.className = 'finance-loader-overlay';
        loader.innerHTML = '<div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"><span class="visually-hidden">Loading...</span></div>';
        container.appendChild(loader);
        
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newContent = doc.getElementById('financeAjaxContainer');
                if (newContent) {
                    container.innerHTML = newContent.innerHTML;
                    bindLocalEvents();
                }
            })
            .catch(error => {
                console.error("AJAX Error:", error);
                window.location.href = url;
            });
    }

    function triggerFormSubmit() {
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);
            const url = filterForm.action + '?' + params.toString();
            window.history.pushState({}, '', url);
            loadAjaxData(url);
        }
    }

    function bindLocalEvents() {
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            // Prevent standard submit
            filterForm.addEventListener('submit', function(e) { e.preventDefault(); });

            // On input change, trigger ajax filter
            const inputs = filterForm.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('change', triggerFormSubmit);
            });
        }

        const resetBtn = document.getElementById('btnReset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.history.pushState({}, '', resetBtn.href);
                loadAjaxData(resetBtn.href);
            });
        }
        
        const resetSearchBtn = document.querySelector('.reset-search');
        if (resetSearchBtn) {
            resetSearchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.history.pushState({}, '', resetSearchBtn.href);
                loadAjaxData(resetSearchBtn.href);
            });
        }
    }

    bindLocalEvents();

    const globalSearchInput = document.querySelector('input[name="q"]:not(input[type="hidden"])');
    if (globalSearchInput) {
        const globalSearchForm = globalSearchInput.closest('form');
        if (globalSearchForm) {
            globalSearchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const searchValue = globalSearchInput.value;
                globalSearchInput.blur();
                
                const urlObj = new URL(window.location.href);
                urlObj.searchParams.set('q', searchValue);
                
                window.history.pushState({}, '', urlObj.toString());
                loadAjaxData(urlObj.toString());
            });
        }
    }

    window.addEventListener('popstate', function() {
        loadAjaxData(window.location.href);
    });
});
</script>
@endpush
@endsection
