@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="container-xxl flex-grow-1 container-p-y">

        <div class="mb-3">
            <h4 class="mb-0">Approval Sales Return</h4>
            <small class="text-muted">List of returns based on HDO</small>
        </div>

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body">
                <form id="filterForm" class="row g-3">

                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    @if(!empty($canSwitchWarehouse) && $canSwitchWarehouse)
                        <div class="col-md-3">
                            <label>Warehouse</label>
                            <select name="warehouse_id" class="form-select">
                                <option value="">All Warehouses</option>
                                    @foreach ($warehouses as $wh)
                                        <option value="{{ $wh->id }}">
                                            {{ $wh->warehouse_name }}
                                        </option>
                                    @endforeach
                            </select>
                        </div>
                    @endif

                </form>
            </div>
        </div>
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <div class="table-responsive" style="overflow-x: auto;">
                    <table id="tblApprovalReturns" class="table table-hover align-middle mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width:140px;">HDO</th>
                                <th>Sales</th>
                                <th style="width:120px;">Total Items</th>
                                <th style="width:120px;">Status</th>
                                <th style="width:180px;">Created</th>
                                <th style="width:110px;" class="text-end">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= MODAL DETAIL ================= --}}
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">HDO Return Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailBody"></div>
            </div>
        </div>
    </div>

    {{-- ================= MODAL REJECT ================= --}}
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="rejectForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Rejection Reason</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="reject_reason" class="form-control" required placeholder="Enter rejection reason"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">
                            Confirm Reject
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        function openDetailModal(handoverId) {


            fetch("{{ route('warehouse.returns.hdo.details', ':id') }}"
                    .replace(':id', handoverId))
                .then(res => res.json())
                .then(data => {

                    let body = document.getElementById('detailBody');
                    body.innerHTML = '';

                    if (data.length === 0) {
                        body.innerHTML = '<div class="text-center text-muted py-4">Tidak ada detail</div>';
                        new bootstrap.Modal(document.getElementById('detailModal')).show();
                        return;
                    }

                    let html = `
                    <button class="btn btn-success mb-3 me-2"
                        onclick="approveAll(${handoverId})">
                        Approve All
                    </button>
                    <button class="btn btn-danger mb-3"
                        onclick="rejectAll(${handoverId})">
                        Reject All
                    </button>
                    <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Condition</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Date</th>
                                <th width="130">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                    `;

                    data.forEach(item => {

                        let badge = '';

                        if (item.status === 'pending') {
                            badge = '<span class="badge bg-warning">Pending</span>';
                        } else if (item.status === 'rejected') {
                            badge = '<span class="badge bg-danger">Rejected</span>';
                        } else {
                            badge = '<span class="badge bg-success">Received</span>';
                        }

                        html += `
            <tr>
                <td>${item.product.name}</td>
                <td>${item.quantity}</td>
                <td>${item.condition}</td>
                <td>${item.reason ?? '-'}</td>
                <td>${badge}</td>
                <td>${item.approved_by_user?.name ?? '-'}</td>
                <td>${new Date(item.created_at).toLocaleString()}</td>
                <td>
                    ${item.status==='pending' ? `
                                                            <button class="btn btn-danger btn-sm"
                                                                onclick="openRejectModal(${item.id})">
                                                                Reject
                                                            </button>
                                                        ` : '-'}
                </td>
            </tr>
            `;
                    });

                    html += `</tbody></table></div>`;

                    body.innerHTML = html;

                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                });
        }

        function openRejectModal(id) {
            let form = document.getElementById('rejectForm');
            form.action = '/warehouse/returns/' + id + '/reject';
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        function approveAll(handoverId) {

            // 🔥 tutup modal dulu
            const modalEl = document.getElementById('detailModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            modalInstance.hide();

            Swal.fire({
                title: 'Approve all items?',
                text: "All pending items will be approved.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, approve!',
                cancelButtonText: 'Cancel'
            }).then((result) => {

                if (result.isConfirmed) {

                    Swal.fire({
                        title: 'Processing...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(`/warehouse/returns/${handoverId}/approve-all`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        })
                        .then(res => {
                            if (!res.ok) throw new Error();
                            return res.text();
                        })
                        .then(() => {

                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: 'All items successfully approved.',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });

                        })
                        .catch(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred.'
                            });
                        });
                }
            });
        }

        function rejectAll(handoverId) {
            // Hide detail modal to prevent backdrop issues with Swal
            const modalEl = document.getElementById('detailModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            Swal.fire({
                title: 'Reject all items?',
                text: "Please provide a reason for rejecting all items:",
                input: 'textarea',
                inputPlaceholder: 'Type your reason here...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject all!',
                inputValidator: (value) => {
                    if (!value) return 'You need to provide a reason!'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Rejecting...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch(`/warehouse/returns/${handoverId}/reject-all`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ reject_reason: result.value })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Rejected',
                                text: 'All items successfully rejected.',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            throw new Error();
                        }
                    })
                    .catch(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to reject items.' });
                    });
                }
            });
        }
    </script>

    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '{{ session('success') }}',
                timer: 2000,
                showConfirmButton: false
            });
        </script>
    @endif
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function() {
            const table = $('#tblApprovalReturns').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                ordering: false,
                dom: '<"d-flex justify-content-between align-items-center mx-3 mt-3"<"me-auto"l><"d-none"f>>rt<"d-flex justify-content-between align-items-center mx-3 mb-3"ip>',
                ajax: {
                    url: "{{ route('warehouse.returns.filter') }}",
                    data: d => {
                        d.from = $('input[name="from"]').val();
                        d.to = $('input[name="to"]').val();
                        d.status = $('select[name="status"]').val();
                        @if(!empty($canSwitchWarehouse) && $canSwitchWarehouse)
                        d.warehouse_id = $('select[name="warehouse_id"]').val();
                        @endif
                    }
                },
                columns: [
                    { data: 'handover_code', className: 'fw-bold text-nowrap' },
                    { data: 'sales_name' },
                    { data: 'total_items', className: 'text-nowrap' },
                    { data: 'status_badge' },
                    { data: 'date' },
                    { 
                        data: null, 
                        className: 'text-end',
                        render: data => `<button class="btn btn-sm btn-primary" onclick="openDetailModal(${data.handover_id})">Detail</button>`
                    }
                ]
            });

            // Filter triggers
            $('input[name="from"], input[name="to"], select[name="status"], select[name="warehouse_id"]').on('change', () => table.ajax.reload());

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
@endpush
