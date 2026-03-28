@extends('layouts.home')

@section('content')
    <style>
        @media (max-width:768px) {

            .table-responsive {
                overflow: visible;
            }

            table.table thead {
                display: none;
            }

            table.table tbody tr {
                display: block;
                background: #fff;
                border-radius: 12px;
                padding: 12px;
                margin-bottom: 15px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            }

            table.table tbody td {
                display: flex;
                justify-content: space-between;
                border: none !important;
                padding: 6px 0;
                font-size: 14px;
            }

            table.table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #696cff;
                margin-right: 10px;
            }
        }
    </style>

    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="fw-bold py-2 mb-3">
            <span class="text-muted fw-light">Sales /</span> Stock Request
        </h4>

        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Stock Request List</h5>

                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestModal">
                    + Request Stock
                </button>
            </div>

            <div class="card-body">

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" id="dateFrom" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>

                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" id="dateTo" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Warehouse</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody id="requestTable">

                            @foreach ($requests as $date => $group)
                                <tr>
                                    <td data-label="Date">{{ $date }}</td>

                                    <td data-label="Warehouse">
                                        {{ $group->first()->warehouse->warehouse_name }}
                                    </td>

                                    <td data-label="Items">
                                        {{ $group->count() }} item
                                    </td>

                                    <td data-label="Status">
                                        <span
                                            class="badge {{ $group->final_status == 'pending' ? 'bg-warning' : 'bg-success' }}">
                                            {{ strtoupper($group->final_status) }}
                                        </span>
                                    </td>

                                    <td>
                                        <button class="btn btn-sm btn-outline-primary detailBtn"
                                            data-items='@json($group->values())'>
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>

                    </table>
                </div>

            </div>
        </div>
    </div>

    {{-- MODAL REQUEST --}}
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <form method="POST" action="{{ route('sales-requests.store') }}">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title">Ajukan Stock Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body" style="max-height:70vh;overflow-y:auto;">

                        <div class="mb-3">
                            <label>Warehouse</label>

                            <select name="warehouse_id" class="form-control" {{ !$canSwitchWarehouse ? 'disabled' : '' }}>
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
                                        <label>Product</label>
                                        <select name="product_id[]" class="form-control">
                                            @foreach ($products as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label>Qty</label>
                                        <input type="number" name="quantity[]" class="form-control">
                                    </div>

                                    <div class="col-md-4">
                                        <label>Note</label>
                                        <input type="text" name="note[]" class="form-control">
                                    </div>

                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm removeRow w-100">×</button>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm" id="addRow">
                            + Tambah Produk
                        </button>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-primary w-100">Submit Request</button>
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
                    <h5 class="modal-title">Detail Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="detailList">
                        <p><strong>Date:</strong> <span id="dDate"></span></p>
                        <p><strong>Product:</strong> <span id="dProduct"></span></p>
                        <p><strong>Status:</strong> <span id="dStatus"></span></p>
                        <p><strong>Note:</strong></p>
                        <div class="border rounded p-2 bg-light" id="dNote"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('addRow').addEventListener('click', function() {

            const container = document.getElementById('requestRows');
            const row = container.children[0].cloneNode(true);

            row.querySelectorAll('input').forEach(el => el.value = '');

            container.appendChild(row);

            bindRemove();
        });

        function bindRemove() {
            document.querySelectorAll('.removeRow').forEach(btn => {
                btn.onclick = function() {
                    if (document.querySelectorAll('.request-row').length > 1) {
                        this.closest('.request-row').remove();
                    }
                }
            });
        }

        bindRemove();

        document.addEventListener('click', function(e) {

            if (!e.target.classList.contains('detailBtn')) return;

            let items = [];

            try {
                items = JSON.parse(e.target.dataset.items);
            } catch (err) {
                console.error('JSON parse error:', err);
                return;
            }

            let html = '';

            items.forEach(item => {

                let badge = '';

                if (item.status === 'pending') {
                    badge = '<span class="badge bg-warning">PENDING</span>';
                }

                if (item.status === 'approved') {
                    badge = '<span class="badge bg-success">APPROVED</span>';
                }

                if (item.status === 'rejected') {
                    badge = '<span class="badge bg-danger">REJECTED</span>';
                }

                html += `
            <div class="border rounded p-2 mb-2">
                <strong>Product:</strong> ${item.product?.name ?? '-'}<br>
                <strong>Qty:</strong> ${item.quantity_requested}<br>
                <strong>Status:</strong> <span class="ms-1">${badge}</span><br>
                <strong>Note:</strong> ${item.note ?? '-'}
            </div>
        `;
            });

            document.getElementById('detailList').innerHTML = html;

            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
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
    </script>
    <script>
        function loadRequests() {

            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const search = document.getElementById('globalSearch').value;

            fetch(`?date_from=${dateFrom}&date_to=${dateTo}&search=${search}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {

                let html = '';

                data.forEach(group => {

                    let badge = '';

                    if (group.final_status === 'pending') badge = 'bg-warning';
                    if (group.final_status === 'completed') badge = 'bg-success';
                    if (group.final_status === 'rejected') badge = 'bg-danger';

                    html += `
                        <tr>
                            <td>${group.date}</td>
                            <td>${group.warehouse}</td>
                            <td>${group.count} item</td>
                            <td>
                                <span class="badge ${badge}">
                                    ${group.final_status.toUpperCase()}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary detailBtn"
                                    data-items='${JSON.stringify(group.items)}'>
                                    Detail
                                </button>
                            </td>
                        </tr>
                    `;
                });

                document.getElementById('requestTable').innerHTML = html;
            });
        }

        document.getElementById('dateFrom').addEventListener('change', loadRequests);
        document.getElementById('dateTo').addEventListener('change', loadRequests);
        document.getElementById('globalSearch').addEventListener('keyup', loadRequests);
    </script>
@endpush
