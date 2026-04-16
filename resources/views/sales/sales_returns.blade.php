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
                                        {{ $h->code }} -
                                        {{ \Carbon\Carbon::parse($h->handover_date)->format('d M Y') }}
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

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-semibold mb-0">Return History</h5>
                </div>

                {{-- ADAPTIVE CONTAINER: Table on Desktop, Cards on Mobile --}}
                <div class="table-responsive d-none d-md-block" style="overflow-x: auto;">
                    <table id="tblSalesReturns" class="table align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>HDO</th>
                                <th>Sales</th>
                                <th>Total Items</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th width="100">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                {{-- MOBILE CONTAINER (Populated via DataTable drawCallback) --}}
                <div id="mobileReturnContainer" class="d-block d-md-none">
                    {{-- Cards injected here --}}
                </div>
                
                {{-- MOBILE PAGINATION PLACEHOLDER --}}
                <div id="mobilePagination" class="d-block d-md-none mt-3"></div>

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
                        <div class="mt-2">
                            <label class="small fw-semibold">Item Note (optional)</label>
                            <input type="text"
                                name="items[${index}][item_note]"
                                class="form-control form-control-sm"
                                placeholder="e.g. Broken seal, box crushed">
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
                    html += `<div class="border rounded-3 p-3 mb-3">`;
                    html += `<strong>${r.product?.name ?? '-'}</strong>`;

                    if (!isEdit) {
                        html += `
                            <div class="mt-2">
                                <div>Condition: <span class="badge bg-light text-dark">${r.condition}</span></div>
                                <div>Qty: <strong>${r.quantity}</strong></div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="mt-2 card-item" id="item-card-${r.id}" data-total="${r.quantity}">
                                <div class="row g-2">
                                    <div class="col-4">
                                        <label class="small fw-bold">Good</label>
                                        <input type="number" name="items[${r.id}][good]" class="form-control form-control-sm good-input" value="${r.condition === 'good' ? r.quantity : 0}" readonly>
                                    </div>
                                    <div class="col-4">
                                        <label class="small fw-bold text-danger">Damaged</label>
                                        <input type="number" name="items[${r.id}][damaged]" class="form-control form-control-sm damaged-input" value="${r.condition === 'damaged' ? r.quantity : 0}" min="0" max="${r.quantity}" oninput="recalcFix(${r.id})">
                                    </div>
                                    <div class="col-4">
                                        <label class="small fw-bold text-warning">Expired</label>
                                        <input type="number" name="items[${r.id}][expired]" class="form-control form-control-sm expired-input" value="${r.condition === 'expired' ? r.quantity : 0}" min="0" max="${r.quantity}" oninput="recalcFix(${r.id})">
                                    </div>
                                </div>
                                <div class="mt-2 text-start">
                                    <label class="small fw-bold">Item Note</label>
                                    <input type="text" name="items[${r.id}][note]" class="form-control form-control-sm" placeholder="Catatan per barang...">
                                </div>
                            </div>
                        `;
                    }

                    if (r.status === 'rejected' && r.reason) {
                        html += `
                            <div class="mt-2 small border-top pt-2">
                                <strong>Reject Reason:</strong> ${r.reason}
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

        function recalcFix(id) {
            const card = document.getElementById(`item-card-${id}`);
            const total = parseInt(card.dataset.total);
            const goodInput = card.querySelector('.good-input');
            const damagedInput = card.querySelector('.damaged-input');
            const expiredInput = card.querySelector('.expired-input');

            let d = parseInt(damagedInput.value) || 0;
            let e = parseInt(expiredInput.value) || 0;

            if (d + e > total) {
                if (d > total) d = total;
                e = total - d;
                damagedInput.value = d;
                expiredInput.value = e;
            }

            goodInput.value = total - d - e;
        }

        function submitFix(handoverId) {
            const form = document.getElementById('fixForm');
            const formData = new FormData(form);

            // Hide the modal first to prevent z-index overlap with Swal
            const modalEl = document.getElementById('hdoDetailModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch(`/sales/returns/${handoverId}/update-rejected`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                })
                .then(res => {
                    if (!res.ok) throw new Error('Request failed');
                    return res.text();
                })
                .then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Return resubmitted successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // 🔄 Reload DataTable, bukan halaman
                        if ($.fn.DataTable.isDataTable('#tblSalesReturns')) {
                            $('#tblSalesReturns').DataTable().ajax.reload(null, false);
                        }
                        // 🔥 Update badge sidebar
                        if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    });
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to resubmit return.' });
                });
        }

        function submitReturn() {
            let form = document.getElementById('modalReturnForm');
            let formData = new FormData(form);

            // Hide the modal first
            const modalEl = document.getElementById('hdoDetailModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch("{{ route('sales.returns.store') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(res => {
                    if (!res.ok) throw new Error('Request failed');
                    return res.text();
                })
                .then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Return submitted successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // 🔄 Reload DataTable, bukan halaman
                        if ($.fn.DataTable.isDataTable('#tblSalesReturns')) {
                            $('#tblSalesReturns').DataTable().ajax.reload(null, false);
                        }
                        // 🔥 Update badge sidebar
                        if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    });
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to submit return.' });
                });
        }
    </script>

    {{-- DataTables Core --}}
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function() {
            const table = $('#tblSalesReturns').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                ordering: false,
                dom: '<"d-flex justify-content-between align-items-center mx-3 mt-3"<"me-auto"l><"d-none"f>>rt<"d-flex justify-content-between align-items-center mx-3 mb-3"ip>',
                ajax: {
                    url: "{{ route('sales.returns.filter') }}",
                    data: d => {
                        d.from = $('input[name="from"]').val();
                        d.to = $('input[name="to"]').val();
                        d.status = $('select[name="status"]').val();
                        @if($isAdmin)
                        d.warehouse_id = $('#warehouseSelect').val();
                        d.sales_id = $('#salesSelect').val();
                        @endif
                    }
                },
                columns: [
                    { data: 'handover_code', className: 'fw-bold text-primary text-nowrap' },
                    { data: 'sales_name' },
                    { data: 'total_items', className: 'text-nowrap' },
                    { data: 'status_badge', className: 'text-center' },
                    { data: 'date' },
                    { 
                        data: null, 
                        orderable: false,
                        render: function(data) {
                            let btns = `<button class="btn btn-sm btn-outline-primary w-100" onclick="viewHdoDetails(${data.handover_id})">View</button>`;
                            if (data.status === 'rejected') {
                                btns += `<button class="btn btn-sm btn-outline-danger w-100 mt-1" onclick="viewHdoDetails(${data.handover_id}, true)">Resubmit</button>`;
                            }
                            return btns;
                        }
                    }
                ],
                drawCallback: function(settings) {
                    const api = this.api();
                    const data = api.rows({ page: 'current' }).data();
                    const container = $('#mobileReturnContainer');
                    const pagination = $('#mobilePagination');
                    
                    container.empty();
                    
                    if (data.length === 0) {
                        container.append('<div class="text-center text-muted py-3">No return history found.</div>');
                        pagination.hide();
                        return;
                    }

                    data.each(function(item) {
                        let resubmitBtn = item.status === 'rejected' ? 
                            `<button class="btn btn-danger btn-sm w-100" onclick="viewHdoDetails(${item.handover_id}, true)">Resubmit</button>` : '';
                        
                        let card = `
                            <div class="card mb-3 border shadow-none bg-light">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-primary">${item.handover_code}</div>
                                            <div class="small text-muted">${item.sales_name}</div>
                                        </div>
                                        ${item.status_badge}
                                    </div>
                                    <div class="small mb-3">
                                        <i class='bx bx-package me-1'></i> ${item.total_items} • 
                                        <i class='bx bx-calendar me-1'></i> ${item.date}
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary btn-sm" onclick="viewHdoDetails(${item.handover_id})">View Details</button>
                                        ${resubmitBtn}
                                    </div>
                                </div>
                            </div>
                        `;
                        container.append(card);
                    });

                    // Sync pagination for mobile
                    pagination.show().html($('.dataTables_paginate').html());
                    pagination.find('ul').addClass('pagination-sm justify-content-center');
                }
            });

            // Filter triggers
            $('input[name="from"], input[name="to"], select[name="status"], #warehouseSelect, #salesSelect').on('change', () => table.ajax.reload());

            // Global Search with Debounce
            let searchTimeout;
            $('#globalSearch').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    table.search(this.value).draw();
                }, 400);
            });
        });
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
    @if (session('success'))
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

    {{-- 🔥 REAL-TIME: Expose refresh function, dipanggil dari global listener di home.blade.php --}}
    <script>
        window.refreshReturnTable = function() {
            if ($.fn.DataTable.isDataTable('#tblSalesReturns')) {
                $('#tblSalesReturns').DataTable().ajax.reload(null, false);
                console.log('[SalesReturn] Table refreshed via global listener.');
            }
        };
    </script>

@endpush

@push('styles')
    <style>
        .swal2-container {
            z-index: 20000 !important;
        }
    </style>
@endpush
