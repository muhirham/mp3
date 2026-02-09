@extends('layouts.home')

@section('title', 'Warehouse Transfer')

@section('content')
    @php
        $isWarehouseUser = $me->hasRole('warehouse');
        $canSwitchWarehouse = !$isWarehouseUser;
    @endphp

    @push('styles')
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
        <style>
            #tblTransfers {
                font-size: .75rem;
                white-space: nowrap
            }

            #tblTransfers th,
            #tblTransfers td {
                padding: .35rem .45rem
            }

            .dataTables_info,
            .dataTables_paginate {
                font-size: .75rem
            }
        </style>
    @endpush

    <div class="container-xxl container-p-y">

        {{-- FILTER --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select id="f_status" class="form-select">
                            <option value="">All</option>
                            <option value="draft">Draft</option>
                            <option value="pending_destination">Pending Destination</option>
                            <option value="approved">Approved</option>
                            <option value="canceled">Canceled</option>
                            <option value="rejected">Rejected</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">From Warehouse</label>
                        @if ($canSwitchWarehouse)
                            <select id="f_from_wh" class="form-select">
                                <option value="">All</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" id="f_from_wh" value="">
                            <input type="text" class="form-control" value="{{ $me->warehouse->warehouse_name }}"
                                disabled>
                        @endif
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">To Warehouse</label>
                        <select id="f_to_wh" class="form-select">
                            <option value="">All</option>
                            @foreach ($toWarehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <input id="searchTransfer" class="form-control w-25" placeholder="Search...">
                    <div class="d-flex gap-2">
                        <a href="#" id="btnExport" class="btn btn-success">
                            <i class="bx bx-download"></i> Export Excel
                        </a>
                        <a href="{{ route('warehouse-transfer-forms.create') }}" class="btn btn-primary">
                            + Create Transfer
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLE --}}
        <div class="card">
            <div class="table-responsive">
                <table id="tblTransfers" class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>CODE</th>
                            <th>PRODUCTS</th>
                            <th>FROM</th>
                            <th>TO</th>
                            <th class="text-end">QTY</th>
                            <th class="text-end">TOTAL</th>
                            <th>STATUS</th>
                            <th>CREATED</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script>
        const table = $('#tblTransfers').DataTable({
            searching: false,
            ajax: {
                url: "{{ route('warehouse-transfers.data') }}",
                data: d => {
                    d.status = $('#f_status').val();
                    d.from_warehouse = $('#f_from_wh').val();
                    d.to_warehouse = $('#f_to_wh').val();
                }
            },
            columns: [{
                    data: 'code'
                },
                {
                    data: 'products'
                },
                {
                    data: 'from_warehouse'
                },
                {
                    data: 'to_warehouse'
                },
                {
                    data: 'total_qty',
                    className: 'text-end'
                },
                {
                    data: 'total_cost',
                    className: 'text-end'
                },
                {
                    data: 'status_badge'
                },
                {
                    data: 'created_at'
                },
                {
                    data: 'action',
                    orderable: false
                }
            ],
            pageLength: 10
        });

        $('#searchTransfer').keyup(e => table.search(e.target.value).draw());
        $('#f_status,#f_from_wh,#f_to_wh').change(() => table.ajax.reload());

        $('#btnExport').on('click', function(e) {
            e.preventDefault();

            const params = new URLSearchParams({
                status: $('#f_status').val(),
                from_warehouse_id: $('#f_from_wh').val(),
                to_warehouse_id: $('#f_to_wh').val(),
                q: $('#searchTransfer').val(),
            });

            window.location.href =
                "{{ route('warehouse-transfer.export') }}?" + params.toString();
        });
    </script>
@endpush
