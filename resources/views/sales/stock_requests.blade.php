@extends('layouts.home')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <style>
        /* Responsive Card Layout for Mobile */
        @media (max-width: 767.98px) {
            #requestsTable thead {
                display: none;
            }

            #requestsTable,
            #requestsTable tbody,
            #requestsTable tr,
            #requestsTable td {
                display: block;
                width: 100%;
            }

            #requestsTable tr {
                margin-bottom: 1rem;
                border: 1px solid var(--border);
                border-radius: 0.5rem;
                padding: 0.5rem;
                background: var(--bg-card);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            #requestsTable td {
                text-align: right;
                padding: 0.5rem 0.5rem;
                border: none !important;
                position: relative;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            #requestsTable td::before {
                content: attr(data-label);
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.75rem;
                color: var(--muted);
            }

            #requestsTable td:last-child {
                border-top: 1px solid var(--border) !important;
                margin-top: 5px;
                padding-top: 10px;
                display: block;
                text-align: center;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="fw-bold py-2 mb-3">
            <span class="text-muted fw-light">Sales /</span> Stock Request
        </h4>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Stock Request History</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestModal">
                    + Request Stock
                </button>
            </div>

            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" id="dateFrom" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" id="dateTo" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="requestsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Warehouse</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL REQUEST --}}
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="requestForm" method="POST" action="{{ route('sales-requests.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">New Stock Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                        <div class="mb-3">
                            <label class="form-label">Target Warehouse</label>
                            <select name="warehouse_id" class="form-select" {{ !$canSwitchWarehouse ? 'disabled' : '' }}>
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}"
                                        {{ $selectedWarehouseId == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>

                            @if (!$canSwitchWarehouse)
                                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                            @endif
                        </div>

                        <div id="requestRows">
                            <div class="request-row border rounded p-3 mb-3">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label">Product</label>
                                        <select name="product_id[]" class="form-select">
                                            @foreach ($products as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Qty</label>
                                        <input type="number" name="quantity[]" class="form-control" required min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Note</label>
                                        <input type="text" name="note[]" class="form-control" placeholder="Optional">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm removeRow w-100">×</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm" id="addRow">
                            + Add Product
                        </button>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL DETAIL --}}
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Batch Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailList"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            const table = $('#requestsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('sales-requests.filter') }}',
                    data: function(d) {
                        d.date_from = $('#dateFrom').val();
                        d.date_to = $('#dateTo').val();
                    }
                },
                columns: [{
                        data: 'date',
                        name: 'date',
                        createdCell: function(td) { $(td).attr('data-label', 'Date'); }
                    },
                    {
                        data: 'warehouse',
                        name: 'warehouse',
                        createdCell: function(td) { $(td).attr('data-label', 'Warehouse'); }
                    },
                    {
                        data: 'count',
                        name: 'count',
                        createdCell: function(td) { $(td).attr('data-label', 'Items'); }
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        createdCell: function(td) { $(td).attr('data-label', 'Status'); }
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    }
                ],
                order: [
                    [0, 'desc']
                ],
                dom: 'lrtip', // Hide default search box
                language: {
                    search: ""
                }
            });

            // Global Search Integration
            $('#globalSearch').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Refresh on filters
            $('#dateFrom, #dateTo').on('change', function() {
                table.draw();
            });

            // Row dynamic handling
            $('#addRow').on('click', function() {
                const container = $('#requestRows');
                const row = container.children().first().clone();
                row.find('input').val('');
                container.append(row);
                bindRemove();
            });

            function bindRemove() {
                $('.removeRow').off('click').on('click', function() {
                    if ($('.request-row').length > 1) {
                        $(this).closest('.request-row').remove();
                    }
                });
            }
            bindRemove();

            // Detail Logic
            $('#requestsTable').on('click', '.detailBtn', function() {
                const group = $(this).data('group');
                $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

                fetch(`{{ route('warehouse.stock-requests.detail') }}?group=${encodeURIComponent(group)}`)
                    .then(res => res.json())
                    .then(items => {
                        let html = '';
                        items.forEach(item => {
                            let badgeClass = 'bg-warning';
                            if (item.status === 'approved') badgeClass = 'bg-success';
                            if (item.status === 'rejected') badgeClass = 'bg-danger';

                            html += `
                                <div class="border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <strong>${item.product?.name ?? '-'}</strong>
                                        <span class="badge ${badgeClass}">${item.status.toUpperCase()}</span>
                                    </div>
                                    <div class="mt-1 small">
                                        Qty: ${item.quantity_requested}<br>
                                        Note: ${item.note ?? '-'}
                                    </div>
                                </div>
                            `;
                        });
                        $('#detailList').html(html);
                        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
                        modal.show();
                    })
                    .finally(() => {
                        $(this).prop('disabled', false).text('Detail');
                    });
            });

            // AJAX SUBMIT (BIAR GA RELOAD)
            $('#requestForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const btn = form.find('button[type="submit"]');
                const modal = bootstrap.Modal.getInstance(document.getElementById('requestModal'));

                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

                fetch(form.attr('action'), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: form.serialize()
                })
                .then(async res => {
                    if (res.redirected) {
                        // Kalau sukses biasanya diredirect, tapi kita mau handle AJAX
                        return { success: true };
                    }
                    const data = await res.json();
                    if (!res.ok) throw data;
                    return data;
                })
                .then(data => {
                    modal.hide();
                    form[0].reset();
                    table.draw();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Request Sent',
                        text: 'Your stock request has been submitted successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: err.message || 'An error occurred while submitting.'
                    });
                })
                .finally(() => {
                    btn.prop('disabled', false).text('Submit Request');
                });
            });

            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('success')),
                    timer: 1800,
                    showConfirmButton: false
                });
            @endif

            // LISTENER REAL-TIME (PoC)
            if (window.Echo) {
                window.Echo.channel('sales-channel')
                    .listen('.stock-request-updated', (e) => {
                        console.log('Real-time Update Received');
                        // Refresh tabel tanpa ganggu input user (null = jangan reset paging)
                        table.draw();
                    });
            }
        });
    </script>
@endpush
