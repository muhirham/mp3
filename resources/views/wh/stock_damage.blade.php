@extends('layouts.home')

@section('title', 'Warehouse Stock Damage')

@section('content')
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
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Warehouse</label>
                            @if ($isWarehouse && !$isSuperadmin)
                                <input type="text" class="form-control bg-light"
                                    value="{{ auth()->user()->warehouse?->warehouse_name ?? 'My Warehouse' }}" readonly>
                                <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                            @else
                                <select name="warehouse_id" class="form-select" onchange="this.form.submit()">
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
                            <select name="status" class="form-select status-select" onchange="this.form.submit()">
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
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Condition</label>
                            <select name="condition" class="form-select" onchange="this.form.submit()">
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
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 table-bordered w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="checkAll">
                                </th>
                                <th style="width: 50px" class="text-center">NO</th>
                                <th>PRODUCT</th>
                                <th>REPORTED</th>
                                <th class="text-center">QTY</th>
                                <th>STATUS</th>
                                <th>RESOLVED</th>
                                <th style="width: 200px" class="text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $index => $item)
                                <tr>
                                    <td class="text-center">
                                        @if (in_array($item->status, ['quarantine', 'rejected']))
                                            <input type="checkbox" class="form-check-input check-item"
                                                value="{{ $item->id }}" data-name="{{ $item->product->name }}"
                                                data-code="{{ $item->product->product_code }}">
                                        @else
                                            <i class="bx bx-lock-alt text-muted small"></i>
                                        @endif
                                    </td>
                                    <td class="text-center small">{{ $items->firstItem() + $index }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $item->product->name }}</div>
                                        <small class="text-muted">{{ $item->product->product_code }} |
                                            {{ strtoupper($item->condition) }}</small>
                                    </td>
                                    <td class="py-2">
                                        <div class="small fw-medium">{{ $item->created_at->format('d/m/Y') }}</div>
                                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">
                                            {{ str_replace('_', ' ', $item->source_type) }}</div>
                                    </td>
                                    <td class="text-center fw-bold">{{ $item->quantity }}</td>
                                    <td>
                                        @php
                                            $st = $item->status;
                                            $cls = match ($st) {
                                                'quarantine' => 'bg-label-secondary',
                                                'pending_approval' => 'bg-label-warning',
                                                'in_progress' => 'bg-label-info',
                                                'resolved' => 'bg-label-success',
                                                'rejected' => 'bg-label-danger',
                                                default => 'bg-label-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $cls }} rounded-pill small">
                                            {{ strtoupper(str_replace('_', ' ', $st)) }}
                                        </span>
                                        @if ($st == 'rejected' && $item->notes)
                                            <div class="text-danger mb-0 mt-1 fw-bold"
                                                style="font-size: 0.65rem; cursor: help;" title="{{ $item->notes }}">
                                                <i class="bx bx-info-circle me-1"></i> REASON ATTACHED
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($item->resolved_at)
                                            <div class="small fw-medium text-success">
                                                {{ $item->resolved_at->format('d/m/Y') }}</div>
                                            <small class="text-muted" style="font-size: 0.6rem;">Appr by:
                                                {{ $item->approver?->name ?? 'System' }}</small>
                                        @elseif($item->approved_at)
                                            <div class="small fw-medium text-info">Approved</div>
                                            <small class="text-muted" style="font-size: 0.6rem;">By
                                                {{ $item->approver?->name ?? 'System' }}</small>
                                        @else
                                            <span class="text-muted small italic">Waiting...</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button class="btn btn-outline-primary btn-sm px-2 btn-detail"
                                                data-item='@json($item)'
                                                data-photos='@json($item->photos)'>
                                                <i class="bx bx-show me-1"></i> Detail
                                            </button>

                                            @if (in_array($item->status, ['quarantine', 'rejected']))
                                                <button class="btn btn-primary btn-sm px-2 btn-request"
                                                    data-id="{{ $item->id }}" data-notes="{{ $item->notes }}">
                                                    <i class="bx bx-paper-plane me-1"></i> Request
                                                </button>
                                            @endif

                                            @if ($item->status == 'in_progress' && auth()->user()->hasRole('warehouse'))
                                                <button class="btn btn-success btn-sm px-2 btn-resolve"
                                                    data-id="{{ $item->id }}" data-action="{{ $item->action }}">
                                                    <i class="bx bx-check-square me-1"></i>
                                                    {{ $item->action == 'dispose' ? 'Confirm' : 'Receive' }}
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">No records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of
                        {{ $items->total() }} entries</small>
                    <div>{{ $items->appends(request()->query())->links() }}</div>
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
            const Alert = Swal.mixin({
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary shadow-none',
                    cancelButton: 'btn btn-outline-secondary ms-2 shadow-none'
                }
            });

            @if (session('success'))
                Alert.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: "{{ session('success') }}",
                    timer: 3000,
                    showConfirmButton: false
                });
            @endif

            @if (session('error'))
                Alert.fire({
                    icon: 'error',
                    title: 'Oops!',
                    text: "{{ session('error') }}"
                });
            @endif

            @if ($errors->any())
                let errList = '';
                @foreach ($errors->all() as $error)
                    errList += '<li>{{ $error }}</li>';
                @endforeach
                Alert.fire({
                    icon: 'error',
                    title: 'Input Error!',
                    html: '<ul class="text-start small mb-0">' + errList + '</ul>',
                });
            @endif

            // 1. Navbar Search Sync
            const globalSearch = $('#globalSearch');
            if (globalSearch.length) {
                const currentKeyword = $('#hiddenKeyword').val();
                if (currentKeyword) globalSearch.val(currentKeyword);

                globalSearch.on('keypress', function(e) {
                    if (e.which === 13) {
                        $('#hiddenKeyword').val($(this).val());
                        $('#filterForm').submit();
                    }
                });
            }

            // 2. Bulk Selection Logic
            const btnBulk = $('#btnBulkRequest');
            const bulkCount = $('#bulkCount');
            const checkAll = $('#checkAll');
            const checkItems = $('.check-item');

            function updateBulkUI() {
                const checked = $('.check-item:checked');
                bulkCount.text(checked.length);
                if (checked.length > 0) {
                    btnBulk.removeClass('d-none');
                } else {
                    btnBulk.addClass('d-none');
                }
            }

            checkAll.on('change', function() {
                checkItems.prop('checked', $(this).prop('checked'));
                updateBulkUI();
            });

            checkItems.on('change', function() {
                updateBulkUI();
            });

            // 3. Bulk Request Sidebar (Offcanvas)
            btnBulk.on('click', function() {
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

            $('.btn-detail').on('click', function() {
                const item = $(this).data('item');
                const photos = $(this).data('photos');
                $('#detProductName').text(item.product.name);
                $('#detProductCode').text(item.product.product_code);

                const st = item.status.toUpperCase().replace('_', ' ');
                $('#detStatusBadge').html(`<span class="badge bg-label-info">${st}</span>`);
                $('#detAction').text(item.action ? item.action.toUpperCase().replace('_', ' ') :
                    'PENDING ANALYSIS');
                let approverHtml = item.approver ?
                    `Approved by <b>${item.approver.name}</b> on ${new Date(item.approved_at).toLocaleDateString('id-ID')}` :
                    '<span class="text-danger">Not yet reviewed</span>';
                if (item.resolver) {
                    approverHtml +=
                        `<br><span class="text-success">Resolved by <b>${item.resolver.name}</b></span>`;
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
                    photoHtml =
                        '<div class="col-12 py-3 text-center text-muted small border-dashed rounded">No proof photos.</div>';
                }
                $('#divPhotos').html(photoHtml);
                $('#mdlDetail').modal('show');
            });

            // 3. Bulk Request Sidebar (Offcanvas)
            btnBulk.on('click', function() {
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
                bootstrap.Offcanvas.getOrCreateInstance('#ocBulkRequest').show();
            });

            $('#frmBulkRequest').on('submit', async function(e) {
                e.preventDefault();
                const form = this;
                const btn = $(form).find('button[type="submit"]');
                const oc = bootstrap.Offcanvas.getOrCreateInstance('#ocBulkRequest');

                // Hide Sidebar immediately for better UX
                oc.hide();

                const result = await Alert.fire({
                    icon: 'question',
                    title: 'Submit Requests?',
                    text: 'This will submit all selected items to the Superadmin for approval.',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Submit Bulk'
                });

                if (result.isConfirmed) {
                    btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Processing...');
                    form.submit();
                } else {
                    // Show sidebar again if cancelled
                    oc.show();
                }
            });

            // 3. Single Request Action (Modal)
            $('.btn-request').on('click', function() {
                const id = $(this).data('id');
                const notes = $(this).data('notes');
                $('#frmRequest').attr('action',
                    `{{ url('admin/warehouse/damaged-stocks') }}/${id}/request`);
                $('#frmRequest textarea[name="notes"]').val(notes || '');
                $('#mdlRequest').modal('show');
            });

            $('.btn-resolve').on('click', async function() {
                const id = $(this).data('id');
                const action = $(this).data('action');
                const item = $(this).closest('tr').find('.btn-detail').data('item');

                // NEW: Use Professional GR Modal for all resolvable actions (Supplier, Repair, Other)
                if (action !== 'dispose') {
                    let typeCode = action === 'repair' ? 'RP' : 'RT';
                    $('#grTitle').html(`Goods Received – ${typeCode}-${id}`);
                    $('#grSupplier').html(
                        `Supplier: <strong>${item.product.supplier ? item.product.supplier.name : 'Unknown Supplier'}</strong>`
                        );
                    $('#grWarehouse').html(
                        `Warehouse: <strong>${item.warehouse ? item.warehouse.warehouse_name : 'Unknown Warehouse'}</strong>`
                        );

                    $('#frmResolveGR').attr('action',
                        `{{ url('admin/warehouse/damaged-stocks') }}/${id}/resolve`);

                    let html = `
                    <tr>
                        <td class="ps-3 fw-bold">1</td>
                        <td>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem;">${item.product.name}</div>
                            <div class="text-muted small" style="font-size: 0.75rem;">${item.product.product_code}</div>
                        </td>
                        <td class="text-center text-muted fw-bold">${item.quantity}</td>
                        <td class="text-center text-muted fw-bold">0</td>
                        <td class="text-center text-muted fw-bold">${item.quantity}</td>
                        <td>
                            <input type="number" name="qty_good" class="form-control form-control-sm text-center fw-bold border-primary js-sync-gr" 
                                value="${item.quantity}" min="0" max="${item.quantity}" data-target="bad">
                        </td>
                        <td>
                            <input type="number" name="qty_damaged" class="form-control form-control-sm text-center fw-bold js-sync-gr" 
                                value="0" min="0" max="${item.quantity}" data-target="good">
                        </td>
                        <td class="pe-3">
                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes (optional)">
                        </td>
                    </tr>`;

                    $('#grTableBody').html(html);
                    $('#mdlResolveGR').modal('show');
                } else {
                    // ORIGINAL: SIMPLE CONFIRMATION FOR DISPOSALS (SAT-SET)
                    const result = await Alert.fire({
                        icon: 'warning',
                        title: 'Confirm Disposal?',
                        text: 'Verify that the item has been permanently destroyed. Stock levels will not increase.',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Confirm Destruction'
                    });

                    if (result.isConfirmed) {
                        const form = $('<form>', {
                            action: `{{ url('admin/warehouse/damaged-stocks') }}/${id}/resolve`,
                            method: 'POST'
                        }).append($('<input>', {
                            type: 'hidden',
                            name: '_token',
                            value: '{{ csrf_token() }}'
                        }));
                        $('body').append(form);
                        form.submit();
                    }
                }
            });

            // Sync Logic for the new GR Modal
            $(document).on('input', '.js-sync-gr', function() {
                const tr = $(this).closest('tr');
                const max = parseInt(tr.find('td:nth-child(3)').text());
                const targetType = $(this).data('target');
                const val = parseInt($(this).val()) || 0;

                let clamped = Math.min(max, Math.max(0, val));
                $(this).val(clamped);

                if (targetType === 'bad') {
                    tr.find('input[name="qty_damaged"]').val(max - clamped);
                } else {
                    tr.find('input[name="qty_good"]').val(max - clamped);
                }
            });
        });

        function previewImage(src) {
            $('#imgFullPreview').attr('src', src);
            $('#mdlPreviewImage').modal('show');
        }
    </script>
@endpush
