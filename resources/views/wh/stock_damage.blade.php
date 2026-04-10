@extends('layouts.home')

@section('title', 'Warehouse Stock Damage')

@section('content')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" />
    <div class="container-xxl flex-grow-1 container-p-y text-sm">
        {{-- Header Ala Modul Master --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Stock Damage & Expired</h4>
                <p class="mb-0 text-muted small">Manage items that are unsellable due to condition or expiration.</p>
            </div>
            @if ($isWarehouse && !$isSuperadmin)
                <button type="button" class="btn btn-primary shadow-none d-none" id="btnBulkRequest">
                    <i class="bx bx-list-check me-1"></i> Bulk Request Action (<span id="bulkCount">0</span>)
                </button>
            @endif
        </div>

        {{-- Filters Bar --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('damaged-stocks.index') }}" method="GET" id="filterForm">
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
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Status Filter</label>
                            <select name="status" class="form-select status-select">
                                <option value="">-- All Status --</option>
                                <option value="quarantine" {{ request('status') == 'quarantine' ? 'selected' : '' }}>
                                    Quarantine</option>
                                <option value="pending_approval"
                                    {{ request('status') == 'pending_approval' ? 'selected' : '' }}>Pending Approval
                                </option>
                                <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In
                                    Progress (Wait Replacement)</option>
                                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved
                                </option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Condition</label>
                            <select name="condition" class="form-select">
                                <option value="">-- All Conditions --</option>
                                <option value="damaged" {{ request('condition') == 'damaged' ? 'selected' : '' }}>Damaged
                                </option>
                                <option value="expired" {{ request('condition') == 'expired' ? 'selected' : '' }}>Expired
                                </option>
                            </select>
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
            .col-checkbox { width: 30px !important; padding-right: 0 !important; }
            .col-no { width: 35px !important; }
            .col-product { 
                max-width: 250px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .text-xs { font-size: 0.65rem !important; }
        </style>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 table-bordered w-100 table-tiny" id="mainTable">
                        <thead>
                            <tr>
                                <th class="text-center no-sort col-checkbox">
                                    <input type="checkbox" class="form-check-input" id="checkAll">
                                </th>
                                <th class="text-center col-no">NO</th>
                                <th class="col-product">PRODUCT</th>
                                <th>REPORTED</th>
                                <th class="text-center" style="width: 50px;">QTY</th>
                                <th>STATUS</th>
                                <th>RESOLVED</th>
                                <th class="text-center no-sort" style="width: 150px;">ACTIONS</th>
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

    <div class="offcanvas offcanvas-end" tabindex="-1" id="ocBulkRequest" aria-labelledby="ocBulkRequestLabel"
        style="width: 450px;">
        <div class="offcanvas-header bg-dark text-white p-3">
            <h5 id="ocBulkRequestLabel" class="offcanvas-title fw-bold">Bulk Action Request</h5>
            <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <form id="frmBulkRequest" action="{{ route('damaged-stocks.bulk-request') }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                <div class="bg-light p-3 border-bottom small text-muted">
                    The system will submit <span class="fw-bold text-dark" id="numSelected">0</span> items for Superadmin
                    approval.
                </div>

                <div id="bulkItemsContainer" class="p-3" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                    {{-- Bulk items injected via JS --}}
                </div>

                <div class="p-3 border-top position-absolute bottom-0 w-100 bg-white shadow-lg">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-none text-uppercase">Submit
                        All Requests</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL REQUEST ACTION (SINGLE) --}}
    <div class="modal fade" id="mdlRequest" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="frmRequest" action="" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold">Action Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Propose Action <span class="text-danger">*</span></label>
                            <select name="action" class="form-select" required>
                                <option value="return_to_supplier">Return to Supplier</option>
                                <option value="dispose">Disposal / Destruction (Stock Lost)</option>
                                <option value="repair">Repair / Repackage</option>
                                <option value="other">Other Action</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason / Analysis Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Explain why this action is needed..."></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Upload Proof (Images)</label>
                            <input type="file" name="photos[]" class="form-control" multiple accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
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
                    <h5 class="modal-title fw-semibold">Stock Lifecycle Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 border-end">
                            <label class="text-muted small text-uppercase mb-1">Product Details</label>
                            <div class="fw-bold h6" id="detProductName"></div>
                            <div class="small" id="detProductCode"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase mb-1">Current Lifecycle Status</label>
                            <div id="detStatusBadge"></div>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase mb-1">Proposed Action</label>
                            <div class="fw-bold text-primary" id="detAction"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase mb-1">Approval Context</label>
                            <div id="detApprover" class="small"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded mb-4 border border-dashed">
                        <label class="text-muted small text-uppercase mb-1 fw-bold">Audit Notes</label>
                        <div id="detNotes" class="small"></div>
                    </div>
                    <label class="text-muted small text-uppercase mb-2 fw-bold">Evidence Gallery</label>
                    <div class="row g-2" id="divPhotos"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL RESOLVE GR (PROFESSIONAL LOOK - MATCHING SCREENSHOT) --}}
    <div class="modal fade" id="mdlResolveGR" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form id="frmResolveGR" action="" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="grTitle">Goods Received – UNKNOWN</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <div class="mb-4 small">
                            <div id="grSupplier">Supplier: <strong>-</strong></div>
                            <div id="grWarehouse">Warehouse: <strong>-</strong></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle text-nowrap mb-4">
                                <thead style="background-color: #f8f9fa;" class="text-muted small fw-bold">
                                    <tr>
                                        <th class="ps-3 py-3 text-uppercase border-bottom-0">#</th>
                                        <th class="py-3 text-uppercase border-bottom-0">PRODUCT</th>
                                        <th class="py-3 text-uppercase text-center border-bottom-0">QTY ORDERED</th>
                                        <th class="py-3 text-uppercase text-center border-bottom-0">QTY RECEIVED</th>
                                        <th class="py-3 text-uppercase text-center border-bottom-0">QTY REMAINING</th>
                                        <th class="py-3 text-uppercase text-center border-bottom-0" style="width: 100px;">
                                            QTY GOOD</th>
                                        <th class="py-3 text-uppercase text-center border-bottom-0" style="width: 100px;">
                                            QTY DAMAGED</th>
                                        <th class="pe-3 py-3 text-uppercase border-bottom-0">NOTES</th>
                                    </tr>
                                </thead>
                                <tbody id="grTableBody">
                                    {{-- Injected via JS --}}
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-1">UPLOAD PHOTO OF GOOD
                                ITEMS (OPTIONAL)</label>
                            <input type="file" name="photos_good[]" class="form-control mb-3" multiple
                                accept="image/*">

                            <label class="form-label fw-bold small text-uppercase text-muted mb-1">UPLOAD PHOTO OF DAMAGED
                                ITEMS (OPTIONAL)</label>
                            <input type="file" name="photos_damaged[]" class="form-control" multiple
                                accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal"
                            style="border-color: #d9dee3; color: #8592a3;">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bx bx-save me-1"></i> Save Goods Received
                        </button>
                    </div>
                </div>
            </form>
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
            const table = $('#mainTable').DataTable({
                processing: true,
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(function() {
            const isWarehouse = @json($isWarehouse);
            const Alert = Swal.mixin({
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-secondary ms-2'
                },
                buttonsStyling: false
            });

            // DataTables AJAX init
            const table = $('#mainTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('damaged-stocks.data') }}",
                    type: "GET",
                    data: function(d) {
                        d.warehouse_id = $('select[name="warehouse_id"]').val();
                        d.status = $('select[name="status"]').val();
                        d.condition = $('select[name="condition"]').val();
                    }
                },
                columns: [
                    { data: null, orderable: false, className: 'text-center', render: function(data, type, row) {
                        if (['quarantine', 'rejected'].includes(row.status)) {
                            return `<input type="checkbox" class="form-check-input check-item" value="${row.id}" data-name="${row.product.name}" data-code="${row.product.product_code}">`;
                        }
                        return '';
                    }},
                    { data: null, orderable: false, className: 'text-center', render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                    { data: 'product.name', render: function(data, type, row) {
                        return `<div style="line-height: 1.1;">
                            <div class="fw-bold text-dark">${data}</div>
                            <div class="text-muted italic" style="font-size: 0.65rem;">${row.product.product_code} | ${row.condition.toUpperCase()}</div>
                        </div>`;
                    }},
                    { data: 'created_at', render: function(data, type, row) {
                        const date = new Date(data).toLocaleDateString('id-ID');
                        const source = (row.source_type || 'Manual').toUpperCase().replace(/_/g, ' ');
                        return `<div style="line-height: 1.1;">
                            <div class="fw-medium">${date}</div>
                            <div class="text-muted italic" style="font-size: 0.65rem;">${source}</div>
                        </div>`;
                    }},
                    { data: 'quantity', className: 'text-center fw-bold' },
                    { data: 'status', render: function(data) {
                        const colors = { quarantine: 'warning', pending_approval: 'info', in_progress: 'primary', resolved: 'success', rejected: 'danger' };
                        const label = data.toUpperCase().replace(/_/g, ' ');
                        return `<span class="badge bg-label-${colors[data] || 'secondary'}">${label}</span>`;
                    }},
                    { data: 'resolved_at', render: function(data, type, row) {
                        const approverName = row.approver ? row.approver.name : 'System';
                        if (data) {
                            const date = new Date(data).toLocaleDateString('id-ID');
                            return `<div style="line-height: 1.1;">
                                <div class="text-success fw-medium">${date}</div>
                                <div class="text-muted italic" style="font-size: 0.65rem;">By: ${approverName}</div>
                            </div>`;
                        } else if (row.approved_at) {
                            return `<div style="line-height: 1.1;">
                                <div class="text-info fw-medium">Approved</div>
                                <div class="text-muted italic" style="font-size: 0.65rem;">By: ${approverName}</div>
                            </div>`;
                        }
                        return `<span class="text-muted italic" style="font-size: 0.65rem;">Waiting...</span>`;
                    }},
                    { data: null, orderable: false, className: 'text-center', render: function(data, type, row) {
                        let btns = `<div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-outline-primary btn-sm px-2 btn-detail" data-id="${row.id}">
                                <i class="bx bx-show me-1"></i> Detail
                            </button>`;
                        
                        if (['quarantine', 'rejected'].includes(row.status)) {
                            btns += `<button class="btn btn-primary btn-sm px-2 btn-request" data-id="${row.id}" data-notes="${row.notes || ''}">
                                <i class="bx bx-paper-plane me-1"></i> Request
                            </button>`;
                        }

                        if (row.status === 'in_progress' && isWarehouse) {
                            btns += `<button class="btn btn-success btn-sm px-2 btn-resolve" data-id="${row.id}" data-action="${row.action}">
                                <i class="bx bx-check-square me-1"></i> ${row.action === 'dispose' ? 'Confirm' : 'Receive'}
                            </button>`;
                        }
                        btns += `</div>`;
                        return btns;
                    }}
                ],
                order: [[3, 'desc']],
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

            // Filters reload
            $('select[name="warehouse_id"], select[name="status"], select[name="condition"]').on('change', function() {
                table.ajax.reload();
            });

            // Update bulk when clicking checks
            $(document).on('change', '.check-item, #checkAll', function() {
                if ($(this).attr('id') === 'checkAll') {
                    $('.check-item').prop('checked', $(this).prop('checked'));
                }
                const checked = $('.check-item:checked');
                $('#bulkCount').text(checked.length);
                if (checked.length > 0) $('#btnBulkRequest').removeClass('d-none');
                else $('#btnBulkRequest').addClass('d-none');
            });

            // Detail Logic
            $(document).on('click', '.btn-detail', function() {
                const tr = $(this).closest('tr');
                const rowData = table.row(tr).data();
                const item = rowData;
                const photos = rowData.photos || [];

                $('#detProductName').text(item.product.name);
                $('#detProductCode').text(item.product.product_code);

                const st = item.status.toUpperCase().replace(/_/g, ' ');
                $('#detStatusBadge').html(`<span class="badge bg-label-info">${st}</span>`);
                $('#detAction').text(item.action ? item.action.toUpperCase().replace(/_/g, ' ') : 'PENDING ANALYSIS');
                
                let approverHtml = item.approver ?
                    `Approved by <b>${item.approver.name}</b> on ${new Date(item.approved_at).toLocaleDateString('id-ID')}` :
                    '<span class="text-danger">Not yet reviewed</span>';
                if (item.resolver) {
                    approverHtml += `<br><span class="text-success">Resolved by <b>${item.resolver.name}</b></span>`;
                }
                $('#detApprover').html(approverHtml);
                $('#detNotes').text(item.notes || 'No notes available.');

                let photoHtml = '';
                if (photos.length > 0) {
                    photos.forEach(p => {
                        photoHtml += `
                        <div class="col-md-3 col-6">
                            <img src="/storage/${p.path}" class="img-fluid rounded border shadow-sm" 
                                style="height: 100px; width: 100%; object-fit: cover; cursor: pointer;"
                                onclick="previewImage('/storage/${p.path}')">
                        </div>`;
                    });
                } else {
                    photoHtml = '<div class="col-12 py-3 text-center text-muted small border-dashed rounded">No proof photos.</div>';
                }
                $('#divPhotos').html(photoHtml);
                $('#mdlDetail').modal('show');
            });

            // Individual Request
            $(document).on('click', '.btn-request', function() {
                const id = $(this).data('id');
                const notes = $(this).data('notes');
                $('#frmRequest').attr('action', `{{ url('admin/warehouse/damaged-stocks') }}/${id}/request`);
                $('#frmRequest textarea[name="notes"]').val(notes || '');
                $('#mdlRequest').modal('show');
            });

            // Resolve Action
            $(document).on('click', '.btn-resolve', async function() {
                const tr = $(this).closest('tr');
                const id = $(this).data('id');
                const action = $(this).data('action');
                const rowData = table.row(tr).data();

                if (action !== 'dispose') {
                    let typeCode = action === 'repair' ? 'RP' : 'RT';
                    $('#grTitle').html(`Goods Received – ${typeCode}-${id}`);
                    $('#grSupplier').html(`Supplier: <strong>${rowData.product.supplier ? rowData.product.supplier.name : 'Unknown Supplier'}</strong>`);
                    $('#grWarehouse').html(`Warehouse: <strong>${rowData.warehouse ? rowData.warehouse.warehouse_name : 'Unknown Warehouse'}</strong>`);
                    $('#frmResolveGR').attr('action', `{{ url('admin/warehouse/damaged-stocks') }}/${id}/resolve`);

                    let html = `
                    <tr>
                        <td class="ps-3 fw-bold">1</td>
                        <td>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem;">${rowData.product.name}</div>
                            <div class="text-muted small" style="font-size: 0.75rem;">${rowData.product.product_code}</div>
                        </td>
                        <td class="text-center text-muted fw-bold">${rowData.quantity}</td>
                        <td class="text-center text-muted fw-bold">0</td>
                        <td class="text-center text-muted fw-bold">${rowData.quantity}</td>
                        <td>
                            <input type="number" name="qty_good" class="form-control form-control-sm text-center fw-bold border-primary js-sync-gr" 
                                value="${rowData.quantity}" min="0" max="${rowData.quantity}" data-target="bad">
                        </td>
                        <td>
                            <input type="number" name="qty_damaged" class="form-control form-control-sm text-center fw-bold js-sync-gr" 
                                value="0" min="0" max="${rowData.quantity}" data-target="good">
                        </td>
                        <td class="pe-3">
                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes (optional)">
                        </td>
                    </tr>`;
                    $('#grTableBody').html(html);
                    $('#mdlResolveGR').modal('show');
                } else {
                    const result = await Alert.fire({
                        icon: 'warning', title: 'Confirm Disposal?', text: 'Verify destruction. Stock will NOT increase.',
                        showCancelButton: true, confirmButtonText: 'Yes, Confirm'
                    });
                    if (result.isConfirmed) {
                        const form = $('<form>', { action: `{{ url('admin/warehouse/damaged-stocks') }}/${id}/resolve`, method: 'POST' })
                            .append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
                        $('body').append(form);
                        form.submit();
                    }
                }
            });

            // Single item Sync Logic (Repair/Return)
            $(document).on('input', '.js-sync-gr', function() {
                const tr = $(this).closest('tr');
                const maxText = tr.find('td:nth-child(3)').text();
                const max = parseInt(maxText);
                const targetType = $(this).data('target');
                const val = parseInt($(this).val()) || 0;
                let clamped = Math.min(max, Math.max(0, val));
                $(this).val(clamped);
                if (targetType === 'bad') tr.find('input[name="qty_damaged"]').val(max - clamped);
                else tr.find('input[name="qty_good"]').val(max - clamped);
            });

            // Bulk Request Logic
            $('#btnBulkRequest').on('click', function() {
                const checked = $('.check-item:checked');
                $('#numSelected').text(checked.length);
                let html = '';
                checked.each(function(index) {
                    const id = $(this).val();
                    const name = $(this).data('name');
                    const code = $(this).data('code');
                    html += `
                    <div class="card mb-3 border shadow-none bg-white">
                        <div class="card-header bg-light py-2 px-3 fw-bold small">
                            ${name} <br> <span class="text-muted text-xs">${code}</span>
                            <input type="hidden" name="items[${index}][id]" value="${id}">
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-2">
                                <label class="form-label fw-bold text-xs">PROPOSED ACTION</label>
                                <select name="items[${index}][action]" class="form-select form-select-sm" required>
                                    <option value="return_to_supplier">Return to Supplier</option>
                                    <option value="dispose">Disposal / Destruction</option>
                                    <option value="repair">Repair / Servicing</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fw-bold text-xs">SPECIFIC NOTES</label>
                                <textarea name="items[${index}][notes]" class="form-control form-control-sm" rows="2" placeholder="State reason or condition..."></textarea>
                            </div>
                            <div>
                                <label class="form-label fw-bold text-xs">EVIDENCE PHOTOS (Action Proof)</label>
                                <input type="file" name="items[${index}][photos][]" class="form-control form-control-sm" multiple accept="image/*">
                            </div>
                        </div>
                    </div>`;
                });
                $('#bulkItemsContainer').html(html);
                const offcanvas = new bootstrap.Offcanvas(document.getElementById('ocBulkRequest'));
                offcanvas.show();
            });

            @if (session('success')) Alert.fire({ icon: 'success', title: 'Success!', text: "{{ session('success') }}", timer: 3000, showConfirmButton: false }); @endif
            @if (session('error')) Alert.fire({ icon: 'error', title: 'Oops!', text: "{{ session('error') }}" }); @endif
        });

        function previewImage(src) {
            $('#imgFullPreview').attr('src', src);
            $('#mdlPreviewImage').modal('show');
        }
    </script>
@endpush
