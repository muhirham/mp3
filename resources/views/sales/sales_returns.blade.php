@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="container py-4">

        {{-- HEADER --}}
        <div class="mb-4">
            <h3 class="fw-bold mb-1">Sales Return Management</h3>
            <p class="text-muted mb-0">
                Submit product returns and track their approval status.
            </p>
        </div>


        {{-- CREATE RETURN --}}
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body">
                <form id="filterForm" class="row g-3 mb-3">

                    <div class="col-md-3">
                        <label>Dari Tanggal</label>
                        <input type="date" name="from" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="to" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    @if ($isAdmin)
                        <div class="col-md-3">
                            <label>Warehouse</label>
                            <select name="warehouse_id" id="warehouseSelect" class="form-select">
                                <option value="">Semua Warehouse</option>
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}">
                                        {{ $wh->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label>Sales</label>
                            <select name="sales_id" id="salesSelect" class="form-select">
                                <option value="">Semua Sales</option>
                            </select>
                        </div>
                    @endif
                </form>
                <h5 class="fw-semibold mb-3">Create New Return</h5>

                <form method="POST" action="{{ route('sales.returns.store') }}">
                    @csrf
                    <div class="row g-3 align-items-end">

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Select HDO</label>
                            <select name="handover_id" id="handoverSelect" class="form-select">
                                <option value="">-- Select HDO --</option>
                                @foreach ($handovers as $h)
                                    <option value="{{ $h->id }}">
                                        {{ $h->code }} - {{ \Carbon\Carbon::parse($h->handover_date)->format('d M Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- RETURN HISTORY --}}
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">

                <h5 class="fw-semibold mb-3">Return History</h5>

                {{-- DESKTOP TABLE --}}
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>HDO</th>
                                    <th>Total Items</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th width="120">Action</th>
                                </tr>
                            </thead>
                            <tbody id="returnTableBody">
                                @forelse($groupedReturns as $handoverId => $items)
                                    @php
                                        $itemsCollection = $items['items'];
                                        $status = $items['status'];
                                        $date = $items['date'];
                                    @endphp

                                    <tr>
                                        <td>
                                            <strong>
                                                {{ $itemsCollection->first()->handover->code ?? '-' }}
                                            </strong>
                                        </td>
                                        <td>{{ $itemsCollection->count() }} items</td>
                                        <td>
                                            @if ($status == 'pending')
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @elseif($status == 'approved')
                                                <span class="badge bg-success">Approved</span>
                                            @elseif($status == 'rejected')
                                                <span class="badge bg-danger">Rejected</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary w-100"
                                                onclick="viewHdoDetails({{ $handoverId }})">
                                                View
                                            </button>

                                            @if ($status == 'rejected')
                                                <button class="btn btn-sm btn-outline-danger w-100 mt-1"
                                                    onclick="viewHdoDetails({{ $handoverId }}, true)">
                                                    Resubmit
                                                </button>
                                            @endif
                                        </td>
                                    </tr>

                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            No return history found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>


                {{-- MOBILE CARD VERSION --}}
                <div class="d-block d-md-none">

                    @forelse($groupedReturns as $handoverId => $items)
                        @php
                            $itemsCollection = $items['items'];
                            $status = $items['status'];
                            $date = $items['date'];
                        @endphp
                        <div class="border rounded-3 p-3 mb-3 bg-light">

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>{{ $itemsCollection->first()->handover->code ?? '-' }}</strong>

                                @if ($status == 'pending')
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @elseif($status == 'approved')
                                    <span class="badge bg-success">Approved</span>
                                @elseif($status == 'rejected')
                                    <span class="badge bg-danger">Rejected</span>
                                @endif
                            </div>

                            <div class="small text-muted mb-2">
                                {{ $itemsCollection->count() }}items •
                                {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                            </div>

                            <button class="btn btn-primary btn-sm w-100 mb-2"
                                onclick="viewHdoDetails({{ $handoverId }})">
                                View Details
                            </button>

                            @if ($status == 'rejected')
                                <button class="btn btn-danger btn-sm w-100"
                                    onclick="viewHdoDetails({{ $handoverId }}, true)">
                                    Resubmit
                                </button>
                            @endif

                        </div>

                    @empty
                        <div class="text-center text-muted py-3">
                            No return history found.
                        </div>
                    @endforelse

                </div>

            </div>
        </div>

    </div>


    {{-- DETAIL MODAL --}}
    <div class="modal fade" id="hdoDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">HDO Return Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="hdoDetailBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>
@endsection


@push('scripts')
    <script>
        document.getElementById('handoverSelect').addEventListener('change', function() {

            const handoverId = this.value;
            if (!handoverId) return;

            fetch(`/sales/returns/load/${handoverId}`)
                .then(res => res.json())
                .then(data => {

                    if (!data.length) {
                        alert('Tidak ada item sisa untuk direturn.');
                        return;
                    }

                    const body = document.getElementById('hdoDetailBody');

                    // BUILD HTML DULU (JANGAN innerHTML += BERULANG)
                    let html = `
                <form id="modalReturnForm">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="handover_id" value="${handoverId}">
            `;

                    data.forEach((item, index) => {

                        html += `
                    <div class="border rounded p-3 mb-3">
                        <strong>${item.product}</strong>
                        <div class="text-muted small">
                            Remaining: <span class="remaining-val">${item.remaining}</span>
                        </div>

                        <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                        <input type="hidden" name="items[${index}][remaining]" value="${item.remaining}">

                        <div class="row mt-2">
                            <div class="col-6">
                                <label>Damaged</label>
                                <input type="number"
                                    name="items[${index}][damaged]"
                                    class="form-control damaged-input"
                                    min="0"
                                    max="${item.remaining}"
                                    value="0">
                            </div>
                            <div class="col-6">
                                <label>Expired</label>
                                <input type="number"
                                    name="items[${index}][expired]"
                                    class="form-control expired-input"
                                    min="0"
                                    max="${item.remaining}"
                                    value="0">
                            </div>
                        </div>
                    </div>
                `;
                    });

                    html += `
                <div class="mb-3">
                    <label>Notes</label>
                    <textarea name="note" class="form-control"></textarea>
                </div>

                <button type="button"
                    class="btn btn-primary w-100"
                    onclick="submitReturn()">
                    Submit Return
                </button>
                </form>
            `;

                    // INJECT SEKALI
                    body.innerHTML = html;

                    // PASANG VALIDASI INPUT
                    document.querySelectorAll('#hdoDetailBody .border').forEach(card => {

                        const remainingSpan = card.querySelector('.remaining-val');
                        const damagedInput = card.querySelector('.damaged-input');
                        const expiredInput = card.querySelector('.expired-input');

                        if (!remainingSpan) return;

                        const initialRemaining = parseInt(remainingSpan.innerText);

                        function recalc() {

                            let damaged = parseInt(damagedInput.value) || 0;
                            let expired = parseInt(expiredInput.value) || 0;

                            if (damaged < 0) damaged = 0;
                            if (expired < 0) expired = 0;

                            if (damaged + expired > initialRemaining) {
                                expired = initialRemaining - damaged;
                                if (expired < 0) expired = 0;
                                expiredInput.value = expired;
                            }

                            remainingSpan.innerText = initialRemaining - damaged - expired;
                        }

                        damagedInput.addEventListener('input', recalc);
                        expiredInput.addEventListener('input', recalc);
                    });

                    new bootstrap.Modal(document.getElementById('hdoDetailModal')).show();
                });
        });

        function viewHdoDetails(handoverId, isEdit = false) {

            fetch(`/sales/returns/hdo/${handoverId}`)
                .then(res => res.json())
                .then(data => {

                    const body = document.getElementById('hdoDetailBody');

                    if (!data.length) {
                        body.innerHTML = '<div class="text-muted">No return data found.</div>';
                        return;
                    }

                    let html = `
                        <div class="mb-3">
                            <strong>HDO Code:</strong> ${data[0].handover?.code ?? '-'}
                        </div>
                    `;

                    if (isEdit) {
                        html += `<form id="fixForm">`;
                    }

                    data.forEach((r, index) => {

                        html += `
                                    <div class="border rounded-3 p-3 mb-3">
                                        <strong>${r.product?.name ?? '-'}</strong>
                                `;

                        if (!isEdit) {

                            html += `
                                        <div class="mt-2">
                                            <div>Condition: ${r.condition}</div>
                                            <div>Qty: ${r.quantity}</div>
                                        </div>
                                    `;

                        } else {

                            html += `
                                        <div class="mt-2">
                                            <div class="row g-2">

                                                <div class="col-4">
                                                    <label>Good</label>
                                                    <input type="number"
                                                        name="items[${r.product_id}][good]"
                                                        class="form-control"
                                                        value="${r.condition === 'good' ? r.quantity : 0}"
                                                        min="0">
                                                </div>

                                                <div class="col-4">
                                                    <label>Damaged</label>
                                                    <input type="number"
                                                        name="items[${r.product_id}][damaged]"
                                                        class="form-control"
                                                        value="${r.condition === 'damaged' ? r.quantity : 0}"
                                                        min="0">
                                                </div>

                                                <div class="col-4">
                                                    <label>Expired</label>
                                                    <input type="number"
                                                        name="items[${r.product_id}][expired]"
                                                        class="form-control"
                                                        value="${r.condition === 'expired' ? r.quantity : 0}"
                                                        min="0">
                                                </div>

                                            </div>
                                        </div>
                                    `;
                        }

                        if (r.status === 'rejected' && r.reason) {
                            html += `
                                        <div class="text-danger mt-2">
                                            <strong>Reject Reason:</strong>
                                            <div>${r.reason}</div>
                                        </div>
                                    `;
                        }

                        html += `</div>`;
                    });

                    if (isEdit) {
                        html += `
                            <div class="mb-3">
                                <label>Reason</label>
                                <textarea name="note" class="form-control"></textarea>
                            </div>

                            <button type="button"
                                class="btn btn-primary w-100"
                                onclick="submitFix(${handoverId})">
                                Resubmit Return
                            </button>
                        </form>
                        `;
                    }

                    body.innerHTML = html;

                    new bootstrap.Modal(document.getElementById('hdoDetailModal')).show();
                });
        }

        function submitFix(handoverId) {

            const form = document.getElementById('fixForm');
            const formData = new FormData(form);

            fetch(`/sales/returns/${handoverId}/update-rejected`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(text => {
                            console.error(text);
                            throw new Error('Request failed');
                        });
                    }
                    return res.text(); // ⬅ jangan json
                })
                .then(() => {
                    location.reload();
                })
                .catch(err => console.error(err));
        }

        function submitReturn() {

            let form = document.getElementById('modalReturnForm');
            let formData = new FormData(form);

            fetch("{{ route('sales.returns.store') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(text => {
                            console.error(text);
                            alert("Terjadi error saat submit.");
                            throw new Error('Request failed');
                        });
                    }
                    return res.text();
                })
                .then(() => {
                    location.reload();
                })
                .catch(err => console.error(err));
        }

        const filterForm = document.getElementById('filterForm');
        const inputs = filterForm.querySelectorAll('input, select');

        inputs.forEach(input => {
            input.addEventListener('change', loadFilteredData);
        });

        function loadFilteredData() {

            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData).toString();

            fetch(`/sales/returns/filter?${params}`)
                .then(res => res.json())
                .then(data => {

                    const tbody = document.getElementById('returnTableBody');
                    tbody.innerHTML = '';

                    if (!data.length) {
                        tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            No return history found.
                        </td>
                    </tr>
                `;
                        return;
                    }

                    data.forEach(item => {

                        let badge = '';

                        if (item.status === 'pending') {
                            badge = '<span class="badge bg-warning text-dark">Pending</span>';
                        } else if (item.status === 'rejected') {
                            badge = '<span class="badge bg-danger">Rejected</span>';
                        } else {
                            badge = '<span class="badge bg-success">Approved</span>';
                        }

                        let actionButtons = `
                            <button class="btn btn-sm btn-outline-primary w-100"
                                onclick="viewHdoDetails(${item.handover_id})">
                                View
                            </button>
                        `;

                        if (item.status === 'rejected') {
                            actionButtons += `
                                <button class="btn btn-sm btn-outline-danger w-100 mt-1"
                                    onclick="viewHdoDetails(${item.handover_id}, true)">
                                    Resubmit
                                </button>
                            `;
                        }

                        tbody.innerHTML += `
                            <tr>
                                <td><strong>${item.handover_code}</strong></td>
                                <td>${item.total_items} items</td>
                                <td>${badge}</td>
                                <td>${item.date}</td>
                                <td>${actionButtons}</td>
                            </tr>
                        `;
                    });

                });
        }
    </script>
    <script>
        const warehouseSelect = document.getElementById('warehouseSelect');
        const salesSelect = document.getElementById('salesSelect');

        if (warehouseSelect) {

            warehouseSelect.addEventListener('change', function() {

                const warehouseId = this.value;

                salesSelect.innerHTML = '<option value="">Loading...</option>';

                if (!warehouseId) {
                    salesSelect.innerHTML = '<option value="">Semua Sales</option>';
                    return;
                }

                fetch(`/sales/by-warehouse/${warehouseId}`)
                    .then(res => res.json())
                    .then(data => {

                        salesSelect.innerHTML = '<option value="">Semua Sales</option>';

                        data.forEach(user => {
                            salesSelect.innerHTML += `
                        <option value="${user.id}">
                            ${user.name}
                        </option>
                    `;
                        });
                    });
            });
        }
    </script>
@if(session('success'))
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: '{{ session('success') }}',
        timer: 1500,
        showConfirmButton: false
    });
</script>
@endif
@endpush
