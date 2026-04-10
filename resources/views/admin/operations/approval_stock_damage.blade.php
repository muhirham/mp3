@extends('layouts.home')

@section('title', 'Damaged Stock Management')

@section('content')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" />
    <div class="container-xxl flex-grow-1 container-p-y text-sm">
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Damaged Stock Management</h4>
            </div>
        </div>

        {{-- Filters Bar --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('damaged-stocks.approval') }}" method="GET" id="filterForm">
                    {{-- Hidden Keyword for Sync with Navbar --}}
                    <input type="hidden" name="keyword" id="hiddenKeyword" value="{{ request('keyword') }}">

                    <div class="row g-3 align-items-end">
                        <div class="col-md-1">
                            <label class="form-label small fw-bold">Show</label>
                            <select id="pageLength" class="form-select">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Warehouse</label>
                            @if ($isWarehouse && !$isSuperadmin)
                                <input type="text" class="form-control bg-light"
                                    value="{{ auth()->user()->warehouse?->warehouse_name ?? 'My Warehouse' }}" readonly>
                                <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                            @else
                                <select name="warehouse_id" class="form-select">
                                    <option value="">-- All Warehouses --</option>
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}"
                                            {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>
                                            {{ $w->warehouse_name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Status Filter</label>
                            <select name="status" class="form-select">
                                <option value="">-- All History --</option>
                                <option value="pending_approval"
                                    {{ request('status') == 'pending_approval' ? 'selected' : '' }}>Pending Decision
                                </option>
                                <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In
                                    Progress</option>
                                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved
                                </option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">From Date</label>
                            <input type="date" name="start_date" class="form-control"
                                value="{{ request('start_date') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">To Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table Card --}}
        <style>
            .table-tiny {
                font-size: 0.775rem !important; /* Slightly larger base */
            }
            .table-tiny th, .table-tiny td {
                padding: 4px 8px !important; /* Tighter padding */
            }
            .table-tiny th {
                font-weight: 700;
                text-transform: uppercase;
                background-color: #f8f9fa;
            }
            .table-tiny td {
                vertical-align: middle !important;
            }
            /* Specific column adjustments */
            .col-no { width: 35px !important; }
            .col-warehouse { width: 120px !important; }
            .col-product { 
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .text-xs { font-size: 0.65rem !important; }
        </style>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 table-bordered w-100 table-tiny" id="approvalTable">
                        <thead>
                            <tr>
                                <th class="text-center no-sort col-no">NO</th>
                                <th class="col-warehouse">WAREHOUSE</th>
                                <th class="col-product">PRODUCT</th>
                                <th style="width: 50px" class="text-center">QTY</th>
                                <th>REPORTED</th>
                                <th class="text-center" style="width: 100px;">STATUS</th>
                                <th class="text-center" style="width: 100px;">RESOLVED</th>
                                <th class="text-center no-sort" style="width: 120px;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Handled by DataTables AJAX --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL APPROVE/REJECT --}}
    <div class="modal fade" id="mdlApprove" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="frmApprove" action="" method="POST">
                @csrf
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold">Handle Approval: <span id="txtProposedAction"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <input class="btn-check" type="radio" name="status" value="in_progress"
                                    id="approveRadio" checked>
                                <label class="btn btn-outline-success w-100 d-flex flex-column align-items-center py-2"
                                    for="approveRadio">
                                    <i class="bx bx-check-double mb-1 h4"></i>
                                    <span class="small fw-bold">APPROVE</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input class="btn-check" type="radio" name="status" value="rejected"
                                    id="rejectRadio">
                                <label class="btn btn-outline-danger w-100 d-flex flex-column align-items-center py-2"
                                    for="rejectRadio">
                                    <i class="bx bx-x-circle mb-1 h4"></i>
                                    <span class="small fw-bold">REJECT</span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold small">Audit Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Notes for Warehouse Admin..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Decision</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL DETAIL --}}
    <div class="modal fade" id="mdlDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Audit Trail History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 border-end">
                            <label class="text-muted small text-uppercase fw-bold d-block mb-1">Origin & Reporter</label>
                            <div class="fw-bold text-primary h6" id="detWarehouse"></div>
                            <div class="small"><span id="detRequester"></span> on <span id="detCreated"></span></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase fw-bold d-block mb-1">Item Data</label>
                            <div class="fw-bold h6" id="detProductName"></div>
                            <div class="small" id="detProductCode"></div>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Status</label>
                            <div id="detStatusBadge"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Proposed Action</label>
                            <div class="text-info fw-bold" id="detAction"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Quantity</label>
                            <div class="h5 mb-0 fw-bold" id="detQty"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded mb-4 border-dashed">
                        <label class="text-muted small text-uppercase fw-bold d-block mb-1">Notes History</label>
                        <div id="detNotes" class="small"></div>
                    </div>
                    <label class="text-muted small text-uppercase fw-bold mb-2">Evidence Gallery</label>
                    <div class="row g-2" id="divPhotos"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL PREVIEW IMAGE --}}
    <div class="modal fade" id="mdlPreviewImage" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0 shadow-none">
                <div class="modal-body p-0 text-center position-relative">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2"
                        data-bs-dismiss="modal" style="z-index: 10;"></button>
                    <img src="" id="imgFullPreview" class="img-fluid rounded shadow-lg"
                        style="max-height: 90vh;">
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(function() {
            const isWarehouse = {{ $isWarehouse ? 'true' : 'false' }};
            const isSuperadmin = {{ $isSuperadmin ? 'true' : 'false' }};

            const Alert = Swal.mixin({
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary shadow-none',
                    cancelButton: 'btn btn-outline-secondary ms-2 shadow-none'
                }
            });

            // DataTables AJAX init
            const table = $('#approvalTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('damaged-stocks.approval-data') }}",
                    type: "GET",
                    data: function(d) {
                        d.warehouse_id = $('select[name="warehouse_id"]').val();
                        d.status = $('select[name="status"]').val();
                        d.start_date = $('input[name="start_date"]').val();
                        d.end_date = $('input[name="end_date"]').val();
                    }
                },
                columns: [
                    { data: null, orderable: false, className: 'text-center', render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                    { data: 'warehouse.warehouse_name', render: function(data) {
                        return `<span class="badge bg-label-primary text-xs">${data}</span>`;
                    }},
                    { data: 'product.name', render: function(data, type, row) {
                        return `<div>
                            <div class="fw-bold text-dark small">${data}</div>
                            <div class="text-muted text-xs">${row.product.product_code} | ${row.condition.charAt(0).toUpperCase() + row.condition.slice(1)}</div>
                        </div>`;
                    }},
                    { data: 'quantity', className: 'text-center fw-bold small' },
                    { data: 'created_at', render: function(data, type, row) {
                        const date = new Date(data).toLocaleDateString('id-ID');
                        const source = (row.source_type || 'Manual').toUpperCase().replace(/_/g, ' ');
                        return `<div style="line-height: 1.1;">
                            <div class="small fw-medium">${date}</div>
                            <div class="text-muted italic" style="font-size: 0.65rem;">${source}</div>
                        </div>`;
                    }},
                    { data: 'status', className: 'text-center', render: function(data) {
                        let cls = 'bg-label-secondary';
                        switch(data) {
                            case 'quarantine': cls = 'bg-label-secondary'; break;
                            case 'pending_approval': cls = 'bg-label-warning'; break;
                            case 'in_progress': cls = 'bg-label-info'; break;
                            case 'resolved': cls = 'bg-label-success'; break;
                            case 'rejected': cls = 'bg-label-danger'; break;
                        }
                        return `<span class="badge ${cls} rounded-pill" style="font-size: 0.65rem;">${data.toUpperCase().replace(/_/g, ' ')}</span>`;
                    }},
                    { data: 'resolved_at', className: 'text-center', render: function(data, type, row) {
                        const approverName = row.approver ? row.approver.name : 'System';
                        if (data) {
                            const date = new Date(data).toLocaleDateString('id-ID');
                            return `<div style="line-height: 1.1;">
                                <div class="fw-medium text-success text-xs">${date}</div>
                                <div class="text-muted italic" style="font-size: 0.65rem;">By: ${approverName}</div>
                            </div>`;
                        } else if (row.approved_at) {
                            return `<div style="line-height: 1.1;">
                                <div class="fw-medium text-info text-xs">Approved</div>
                                <div class="text-muted italic" style="font-size: 0.65rem;">By: ${approverName}</div>
                            </div>`;
                        }
                        return `<span class="text-muted text-xs italic" style="font-size: 0.65rem;">Waiting...</span>`;
                    }},
                    { data: null, orderable: false, className: 'text-center', render: function(data, type, row) {
                        let btns = `<div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-outline-info btn-xs px-2 btn-detail" data-id="${row.id}">
                                <i class="bx bx-show"></i>
                            </button>`;
                        
                        if (row.status === 'pending_approval') {
                            const action = row.action.toUpperCase().replace(/_/g, ' ');
                            btns += `<button class="btn btn-warning btn-xs px-2 btn-approve" data-id="${row.id}" data-action="${action}">
                                <i class="bx bx-cog me-1"></i> Handle
                            </button>`;
                        }
                        btns += `</div>`;
                        return btns;
                    }}
                ],
                order: [[4, 'desc']], 
                pageLength: 10,
                pagingType: "simple_numbers",
                dom: 'rt<"d-flex justify-content-between align-items-center p-2"ip>',
                language: {
                    paginate: {
                        next: '<i class="bx bx-chevron-right"></i>',
                        previous: '<i class="bx bx-chevron-left"></i>'
                    }
                }
            });

            // Sync Navbar Search
            const $globalSearch = $('#globalSearch');
            if ($globalSearch.length) {
                $globalSearch.off().on('keyup change', function() {
                    table.search(this.value).draw();
                });
            }

            // Sync Page Length
            $('#pageLength').on('change', function() {
                table.page.len(parseInt(this.value || 10, 10)).draw();
            });

            // Filter reloads
            $('select[name="warehouse_id"], select[name="status"], input[name="start_date"], input[name="end_date"]').on('change', function() {
                table.ajax.reload();
            });

            @if (session('success')) Alert.fire({ icon: 'success', title: 'Success!', text: "{{ session('success') }}", timer: 3000, showConfirmButton: false }); @endif
            @if (session('error')) Alert.fire({ icon: 'error', title: 'Oops!', text: "{{ session('error') }}" }); @endif

            $(document).on('click', '.btn-detail', function() {
                const tr = $(this).closest('tr');
                const rowData = table.row(tr).data();
                const item = rowData;
                const photos = rowData.photos || [];

                $('#detWarehouse').html(`${item.warehouse.warehouse_name} <span class="badge bg-label-secondary ms-2 text-xs">${item.source_type.toUpperCase().replace(/_/g, ' ')}</span>`);
                $('#detRequester').html(`Reported by <b>${item.requester ? item.requester.name : 'System'}</b>`);
                $('#detProductName').text(item.product.name);
                $('#detProductCode').text(item.product.product_code + ' | ' + item.condition.toUpperCase());
                $('#detQty').text(item.quantity);
                $('#detCreated').text(new Date(item.created_at).toLocaleDateString('id-ID'));

                const st = item.status.toUpperCase().replace(/_/g, ' ');
                $('#detStatusBadge').html(`<span class="badge bg-label-info text-xs">${st}</span>`);
                $('#detAction').text(item.action ? item.action.toUpperCase().replace(/_/g, ' ') : 'NOT ANALYZED');
                $('#detNotes').html(item.notes ? item.notes : '<span class="text-muted italic">No notes.</span>');

                let approverHtml = item.approver ? `<div class="mt-2 text-xs border-top pt-2">Approved by <b>${item.approver.name}</b></div>` : '';
                if (item.resolver) {
                    approverHtml += `<div class="text-xs text-success">Resolved by <b>${item.resolver.name}</b> on ${new Date(item.resolved_at).toLocaleDateString('id-ID')}</div>`;
                }
                $('#detNotes').append(approverHtml);

                let photoHtml = '';
                if (photos.length > 0) {
                    photos.forEach(p => {
                        photoHtml += `
                        <div class="col-3">
                            <img src="/storage/${p.path}" class="img-fluid rounded border shadow-sm" 
                                style="height: 100px; width: 100%; object-fit: cover; cursor: pointer;"
                                onclick="previewImage('/storage/${p.path}')">
                        </div>`;
                    });
                } else {
                    photoHtml = '<div class="col-12 text-center text-muted small py-3 bg-light rounded border-dashed">No evidence photos.</div>';
                }
                $('#divPhotos').html(photoHtml);
                $('#mdlDetail').modal('show');
            });

            $(document).on('click', '.btn-approve', function() {
                const id = $(this).data('id');
                const action = $(this).data('action');
                $('#frmApprove').attr('action', `{{ url('admin/warehouse/damaged-stocks') }}/${id}/approve`);
                $('#txtProposedAction').text(action);
                $('#mdlApprove').modal('show');
            });
        });

        function previewImage(src) {
            $('#imgFullPreview').attr('src', src);
            $('#mdlPreviewImage').modal('show');
        }
    </script>
@endpush
