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
                font-size: 0.8rem;
                width: 100% !important;
                border-collapse: collapse !important;
            }
            #tblTransfers thead th {
                background-color: #f8f9fa;
                color: #566a7f;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.7rem;
                letter-spacing: 0.5px;
                border-bottom: 2px solid #e6e8eb !important;
                padding: 10px 15px;
            }
            #tblTransfers tbody td {
                padding: 8px 15px;
                vertical-align: middle;
                border-bottom: 1px solid #f0f2f4;
            }
            .dataTables_processing {
                box-shadow: 0 0.25rem 1rem rgba(161, 172, 184, 0.45);
                border: none !important;
                background: rgba(255,255,255,0.9) !important;
                border-radius: 8px;
            }
            .badge {
                font-size: 0.7rem;
                padding: 0.4em 0.7em;
            }
            /* Menghilangkan scrollbar horizontal di desktop */
            .table-responsive {
                overflow-x: hidden;
            }
            @media (max-width: 991.98px) {
                .table-responsive {
                    overflow-x: auto;
                }
                #tblTransfers {
                    white-space: nowrap;
                }
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

                <div class="d-flex justify-content-end mt-3">
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
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function() {
            // Restore filters from localStorage if exist
            window.transferTable = $('#tblTransfers').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                ordering: false, // Default order logic di backend
                dom: '<"d-flex justify-content-between align-items-center mx-3 mt-3"l>rt<"d-flex justify-content-between align-items-center mx-3 mb-3"ip>',
                ajax: {
                    url: "{{ route('warehouse-transfers.data') }}",
                    data: d => {
                        d.status = $('#f_status').val();
                        d.from_warehouse = $('#f_from_wh').val();
                        d.to_warehouse = $('#f_to_wh').val();
                    }
                },
                columns: [{
                        data: 'code',
                        className: 'fw-bold text-primary',
                        width: '12%'
                    },
                    {
                        data: 'products',
                        width: '30%',
                        render: function(data) {
                            return `<div style="max-height: 80px; overflow-y: auto; line-height: 1.4;">${data}</div>`;
                        }
                    },
                    {
                        data: 'from_warehouse',
                        width: '12%'
                    },
                    {
                        data: 'to_warehouse',
                        width: '12%'
                    },
                    {
                        data: 'total_qty',
                        className: 'text-center fw-bold',
                        width: '7%'
                    },
                    {
                        data: 'total_cost',
                        className: 'text-end',
                        width: '10%'
                    },
                    {
                        data: 'status_badge',
                        className: 'text-center',
                        width: '8%'
                    },
                    {
                        data: 'created_at',
                        width: '10%'
                    },
                    {
                        data: 'action',
                        orderable: false,
                        className: 'text-center',
                        width: '5%'
                    }
                ],
                pageLength: 10,
                language: {
                    paginate: {
                        next: '<i class="bx bx-chevron-right"></i>',
                        previous: '<i class="bx bx-chevron-left"></i>'
                    }
                }
            });

            // Auto-reload on filter change
            $('#f_status, #f_from_wh, #f_to_wh').on('change', function() {
                if (window.transferTable) window.transferTable.ajax.reload();
            });

            // Connect global navbar search
            const $globalSearch = $('#globalSearch');
            if ($globalSearch.length) {
                // Initial sync if URL has 'q' parameter or navbar search already has value
                const urlParams = new URLSearchParams(window.location.search);
                const q = urlParams.get('q') || $globalSearch.val();
                if (q) {
                    $globalSearch.val(q);
                    window.transferTable.search(q).draw();
                }

                $globalSearch.on('keyup', function() {
                    window.transferTable.search(this.value).draw();
                });
            }

            $('#btnExport').on('click', function(e) {
                e.preventDefault();
                const params = new URLSearchParams({
                    status: $('#f_status').val(),
                    from_warehouse_id: $('#f_from_wh').val(),
                    to_warehouse_id: $('#f_to_wh').val(),
                });
                window.location.href = "{{ route('warehouse-transfer.export') }}?" + params.toString();
            });

            // ✅ LISTEN REVERB: Auto reload table when transfer updated
            window.addEventListener('reverb:warehouse-transfer-updated', function(e) {
                console.log('📡 [Signal] Index Table detected update:', e.detail);
                if (window.transferTable) {
                    console.log('🔄 Reloading Transfer Table...');
                    window.transferTable.ajax.reload(null, false);
                }
            });
        });
    </script>
@endpush
