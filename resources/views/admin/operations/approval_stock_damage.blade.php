@extends('layouts.home')

@section('title', 'Damaged Stock Management')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
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
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
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
                                value="{{ request('start_date') }}" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">To Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"
                                onchange="this.form.submit()">
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
                                <th style="width: 40px" class="text-center">NO</th>
                                <th style="width: 150px">WAREHOUSE</th>
                                <th>PRODUCT</th>
                                <th style="width: 60px" class="text-center">QTY</th>
                                <th style="width: 130px">REPORTED</th>
                                <th style="width: 120px" class="text-center">STATUS</th>
                                <th style="width: 100px" class="text-center">RESOLVED</th>
                                <th style="width: 150px" class="text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $index => $item)
                                <tr>
                                    <td class="text-center small py-2">{{ $items->firstItem() + $index }}</td>
                                    <td class="py-2"><span
                                            class="badge bg-label-primary text-xs">{{ $item->warehouse->warehouse_name }}</span>
                                    </td>
                                    <td class="py-2">
                                        <div class="fw-bold text-dark small">{{ $item->product->name }}</div>
                                        <div class="text-muted text-xs">{{ $item->product->product_code }} |
                                            {{ ucfirst($item->condition) }}</div>
                                    </td>
                                    <td class="text-center fw-bold py-2 small">{{ $item->quantity }}</td>
                                    <td class="py-2">
                                        <div class="small fw-medium">{{ $item->created_at->format('d/m/Y') }}</div>
                                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">
                                            {{ str_replace('_', ' ', $item->source_type) }}
                                        </div>
                                    </td>
                                    <td class="text-center py-2">
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
                                        <span class="badge {{ $cls }} rounded-pill" style="font-size: 0.65rem;">
                                            {{ strtoupper(str_replace('_', ' ', $st)) }}
                                        </span>
                                    </td>
                                    <td class="text-center py-2">
                                        @if ($item->resolved_at)
                                            <div class="fw-medium text-success text-xs">
                                                {{ $item->resolved_at->format('d/m/Y') }}</div>
                                            <div class="text-muted" style="font-size: 0.6rem;">Appr: {{ $item->approver?->name ?? 'System' }}</div>
                                        @elseif($item->approved_at)
                                            <div class="fw-medium text-info text-xs">Approved</div>
                                            <div class="text-muted" style="font-size: 0.6rem;">By {{ $item->approver?->name ?? 'System' }}</div>
                                        @else
                                            <span class="text-muted text-xs italic">Waiting...</span>
                                        @endif
                                    </td>
                                    <td class="text-center py-2">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button class="btn btn-outline-info btn-xs px-2 btn-detail"
                                                data-item='@json($item)'
                                                data-photos='@json($item->photos)'>
                                                <i class="bx bx-show"></i>
                                            </button>

                                            @if ($item->status == 'pending_approval')
                                                <button class="btn btn-warning btn-xs px-2 btn-approve"
                                                    data-id="{{ $item->id }}" data-action="{{ $item->action }}">
                                                    <i class="bx bx-cog me-1"></i> Handle
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

            $('.btn-detail').on('click', function() {
                const item = $(this).data('item');
                const photos = $(this).data('photos');

                $('#detWarehouse').html(`${item.warehouse.warehouse_name} <span class="badge bg-label-secondary ms-2 text-xs">${item.source_type.toUpperCase().replace('_', ' ')}</span>`);
                $('#detRequester').html(`Reported by <b>${item.requester ? item.requester.name : 'System'}</b>`);
                $('#detProductName').text(item.product.name);
                $('#detProductCode').text(item.product.product_code + ' | ' + item.condition.toUpperCase());
                $('#detQty').text(item.quantity);
                $('#detCreated').text(new Date(item.created_at).toLocaleDateString('id-ID'));

                const st = item.status.toUpperCase().replace('_', ' ');
                $('#detStatusBadge').html(`<span class="badge bg-label-info text-xs">${st}</span>`);
                $('#detAction').text(item.action ? item.action.toUpperCase().replace('_', ' ') :
                    'NOT ANALYZED');
                $('#detNotes').html(item.notes ? item.notes :
                    '<span class="text-muted italic">No notes.</span>');

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
                    photoHtml =
                        '<div class="col-12 text-center text-muted small py-3 bg-light rounded border-dashed">No evidence photos.</div>';
                }
                $('#divPhotos').html(photoHtml);
                $('#mdlDetail').modal('show');
            });

            $('.btn-approve').on('click', function() {
                const id = $(this).data('id');
                const action = $(this).data('action').toUpperCase().replace('_', ' ');
                $('#frmApprove').attr('action',
                    `{{ url('admin/warehouse/damaged-stocks') }}/${id}/approve`);
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
