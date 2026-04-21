@extends('layouts.home')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <style>
        .swal-front {
            z-index: 20000 !important;
        }

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
    <div class="container-xxl container-p-y">

        <h4 class="fw-bold py-2 mb-4">Sales Stock Request Approval</h4>

        <div class="card">
            <div class="card-body">

                <div class="row mb-3 g-2">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" id="dateFrom" class="form-control" value="{{ $dateFrom }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" id="dateTo" class="form-control" value="{{ $dateTo }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Warehouse</label>

                        @if ($me->hasRole(['admin', 'superadmin']))
                            <select id="warehouseFilter" class="form-select">
                                <option value="">All Warehouse</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}">
                                        {{ $w->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $me->warehouse->warehouse_name }}"
                                disabled>
                            <input type="hidden" id="warehouseFilter" value="{{ $me->warehouse_id }}">
                        @endif
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="requestsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Sales</th>
                                <th>Warehouse</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </div>

        <div class="modal fade" id="detailModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Request Detail</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div id="detailBody"></div>
                        <hr>
                        <div class="text-end mt-3" id="approveWrapper" style="display:none;">
                            <button class="btn btn-success" id="approveAllBtn">
                                Approve All Remaining
                            </button>
                        </div>
                    </div>

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
            let selectedItems = [];
            const detailModalEl = document.getElementById('detailModal');
            const detailModal = new bootstrap.Modal(detailModalEl);
            const csrfToken = '{{ csrf_token() }}';

            const table = $('#requestsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('warehouse.stock-requests.filter') }}',
                    data: function(d) {
                        d.date_from = $('#dateFrom').val();
                        d.date_to = $('#dateTo').val();
                        d.warehouse_id = $('#warehouseFilter').val();
                    }
                },
                columns: [{
                        data: 'date',
                        name: 'date',
                        createdCell: function(td) {
                            $(td).attr('data-label', 'Date');
                        }
                    },
                    {
                        data: 'sales',
                        name: 'sales',
                        createdCell: function(td) {
                            $(td).attr('data-label', 'Sales');
                        }
                    },
                    {
                        data: 'warehouse',
                        name: 'warehouse',
                        createdCell: function(td) {
                            $(td).attr('data-label', 'Warehouse');
                        }
                    },
                    {
                        data: 'count',
                        name: 'count',
                        createdCell: function(td) {
                            $(td).attr('data-label', 'Items');
                        }
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        render: function(data) {
                            let badge = 'bg-warning';
                            if (data === 'approved') badge = 'bg-success';
                            if (data === 'rejected') badge = 'bg-danger';
                            return `<span class="badge ${badge}">${data.toUpperCase()}</span>`;
                        },
                        createdCell: function(td) {
                            $(td).attr('data-label', 'Status');
                        }
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false
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

            // Refresh on filter change
            $('#dateFrom, #dateTo, #warehouseFilter').on('change', function() {
                table.draw();
            });

            // Detail button handling (Event Delegation for DataTables)
            $('#requestsTable').on('click', '.detailBtn', function() {
                const group = $(this).data('group');
                $(this).prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm"></span>');

                fetch(`/warehouse/stock-requests/detail?group=${encodeURIComponent(group)}`)
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) throw data;
                        return data;
                    })
                    .then(data => {
                        selectedItems = data;
                        renderItems();
                        detailModal.show();
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to open detail',
                            text: err.message || 'An error occurred'
                        });
                    })
                    .finally(() => {
                        $(this).prop('disabled', false).text('Detail');
                    });
            });

            function renderItems() {
                let html = '';
                selectedItems.forEach(item => {
                    const rejected = item.status === 'rejected';
                    const approved = item.status === 'approved';

                    html += `
                        <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${item.product.name}</strong><br>
                                Qty: ${item.quantity_requested}<br>
                                Note: ${item.note ?? '-'}
                                ${approved ? `<br><small class="text-success">Approved by: ${item.user_approved?.name || 'Admin'}</small>` : ''}
                            </div>
                            <div>
                                ${approved 
                                    ? `<span class="badge bg-success">Approved</span>` 
                                    : rejected 
                                    ? `<span class="badge bg-danger">Rejected</span>` 
                                    : `<button class="btn btn-danger btn-sm rejectItemBtn" data-id="${item.id}">Reject</button>`
                                }
                            </div>
                        </div>
                    `;
                });

                $('#detailBody').html(html);
                const hasPending = selectedItems.some(item => item.status === 'pending');
                $('#approveWrapper').toggle(hasPending);
                bindRejectButtons();
            }

            function bindRejectButtons() {
                $('.rejectItemBtn').off('click').on('click', function() {
                    const id = $(this).data('id');
                    detailModal.hide();

                    setTimeout(() => {
                        Swal.fire({
                            title: 'Rejection reason',
                            input: 'text',
                            inputPlaceholder: 'Enter rejection reason',
                            allowOutsideClick: false,
                            showCancelButton: true,
                            customClass: {
                                popup: 'swal-front'
                            },
                            didOpen: () => {
                                $('.swal2-container').css('z-index', '30000');
                            },
                            inputValidator: (value) => {
                                if (!value) return 'Note is required';
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch(`/warehouse/stock-requests/${id}/reject`, {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': csrfToken,
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            approval_note: result.value
                                        })
                                    })
                                    .then(async res => {
                                        const data = await res.json();
                                        if (!res.ok) throw data;
                                        return data;
                                    })
                                    .then(() => {
                                        const target = selectedItems.find(x => x.id ==
                                            id);
                                        if (target) target.status = 'rejected';
                                        renderItems();
                                        table.draw(false);

                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Rejected',
                                            timer: 1200,
                                            showConfirmButton: false
                                        }).then(() => {
                                            const anyPending = selectedItems
                                                .some(i => i.status ===
                                                    'pending');
                                            if (anyPending) {
                                                detailModal.show();
                                            }
                                        });
                                    });
                            } else {
                                const anyPending = selectedItems.some(i => i.status ===
                                    'pending');
                                if (anyPending) detailModal.show();
                            }
                        });
                    }, 200);
                });
            }

            $('#approveAllBtn').on('click', async function() {
                const remain = selectedItems.filter(x => x.status === 'pending');
                if (remain.length === 0) return;

                detailModal.hide();

                setTimeout(async () => {
                    const confirm = await Swal.fire({
                        title: 'Approve all items?',
                        text: "This will create a new Handover Order (HDO).",
                        icon: 'question',
                        showCancelButton: true,
                        customClass: {
                            popup: 'swal-front'
                        },
                        didOpen: () => {
                            $('.swal2-container').css('z-index', '30000');
                        }
                    });

                    if (!confirm.isConfirmed) {
                        const anyPending = selectedItems.some(i => i.status === 'pending');
                        if (anyPending) detailModal.show();
                        return;
                    }

                    Swal.fire({
                        title: 'Processing...',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    let handoverId = null;
                    try {
                        for (const item of remain) {
                            const response = await fetch(
                                `/warehouse/stock-requests/${item.id}/approve`, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken,
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        handover_id: handoverId
                                    })
                                });

                            const data = await response.json();
                            if (!response.ok) throw data;
                            if (!handoverId) handoverId = data.handover_id;
                            const target = selectedItems.find(x => x.id == item.id);
                            if (target) target.status = 'approved';
                        }

                        renderItems();
                        table.draw(false);

                        if (handoverId) {
                            window.open(`/sales/handover/morning?handover_id=${handoverId}`,
                                '_blank');
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Approval completed',
                            timer: 1200,
                            showConfirmButton: false
                        }).then(() => {
                            const anyPending = selectedItems.some(i => i.status ===
                                'pending');
                            if (anyPending) {
                                detailModal.show();
                            }
                        });

                    } catch (err) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cannot approve',
                            text: err.message || 'An error occurred'
                        });
                    }
                }, 200);
            });

            // LISTENER REAL-TIME (via Global Event Bus)
            window.addEventListener('reverb:stock-request-updated', (e) => {
                console.log('Real-time Update Received: WH Approval (via Event Bus)');
                if (typeof table !== 'undefined') table.draw(false);
            });
        });
    </script>
@endpush
