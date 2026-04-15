@extends('layouts.home')

@section('content')
    <style>
        /* Table compact & Responsive */
        #tblStock {
            width: 100% !important;
            font-size: 0.75rem; 
        }

        #tblStock th,
        #tblStock td {
            white-space: nowrap !important;
            vertical-align: middle;
            padding: .45rem .55rem;
        }

        @media (max-width: 992px) {
            #tblStock th,
            #tblStock td {
                white-space: normal !important;
                word-break: break-word;
            }
        }

        /* wrapper UTAMA: aktifkan scroll horizontal */
        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 1px; /* Avoid scrollbar covering content */
        }
    </style>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $me = $me ?? auth()->user();
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

        {{-- Header & toolbar --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">

                    {{-- page length --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Show</label>
                        <select id="pageLength" class="form-select">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                        </select>
                    </div>

                    {{-- Warehouse filter --}}
                    <div class="col-12 col-md-4 ms-auto">
                        <label class="form-label mb-1">Warehouse</label>

                        <div class="d-flex gap-2">
                            @if (!empty($canSwitchWarehouse) && $canSwitchWarehouse)
                                {{-- Superadmin / Admin pusat: bebas pilih gudang --}}
                                <select id="filterWarehouse" class="form-select flex-grow-1">
                                    <option value="">— All —</option>
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}" @selected(($selectedWarehouseId ?? null) == $w->id)>
                                            {{ $w->warehouse_name }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                {{-- Admin WH / user lain: hanya lihat gudang miliknya --}}
                                <input class="form-control flex-grow-1" value="{{ $me->warehouse?->warehouse_name ?? '-' }}" disabled>
                            @endif
                        </div>
                    </div>

                    {{-- Export & Date Range Filter --}}
                    @if($me->hasPermission('products.export'))
                    <div class="col-12 mt-3 d-flex justify-content-end align-items-end gap-2">
                        <div>
                            <label class="form-label mb-1">Date Start</label>
                            <input type="date" id="exportStartDate" class="form-control">
                        </div>
                        <div>
                            <label class="form-label mb-1">Date End</label>
                            <input type="date" id="exportEndDate" class="form-control">
                        </div>
                        <button id="btnExportStock" class="btn btn-success d-flex align-items-center text-nowrap">
                            <i class="bx bx-export me-2"></i> Export
                        </button>
                    </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="table-responsive">
                <table id="tblStock" class="table table-striped table-hover align-middle mb-0 table-bordered w-100">
                    <thead class="table-light">
                        <tr>
                            <th>NO</th>
                            <th>CODE</th>
                            <th>PRODUCT</th>
                            <th>TYPE</th>
                            <th>UNIT</th>
                            <th>CATEGORY</th>
                            <th>SUPPLIER</th>
                            <th class="text-end">STOCK</th>
                            <th class="text-end">MIN STOCK</th>
                            <th>STATUS</th>
                            <th class="text-end">COGS</th>
                            <th class="text-end">SELLING PRICE</th>
                            <th>CREATED AT</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <style>
        /* Standadisasi Header & Footer DataTable */
        .dataTables_wrapper .dataTables_info {
            font-size: 12px;
            padding: 0.5rem 1rem;
        }
        .dataTables_wrapper .pagination {
            padding: 0.5rem 1rem;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(function() {
            const dtUrl = @json(route('stocklevel.datatable')); // ✅ FIX
            const CAN_SWITCH_WH = @json($canSwitchWarehouse ?? false);
            const USER_WAREHOUSE_ID = @json($me->warehouse_id ?? null);

            const table = $('#tblStock').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                autoWidth: true, // ✅ Biar ngatur sendiri lebarnya
                responsive: false, // ✅ scroll horizontal dikontrol di .table-responsive

                // ✅ Standarisasi layout dengan padding biar lega
                dom: "<'row px-3 mt-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row px-3 mb-2 mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                pageLength: 25,
                ajax: {
                    url: dtUrl,
                    type: 'GET',
                    data: function(d) {
                        if (CAN_SWITCH_WH) {
                            d.warehouse_id = $('#filterWarehouse').val() || '';
                        } else {
                            d.warehouse_id = USER_WAREHOUSE_ID || '';
                        }
                    }
                },
                order: [
                    [1, 'asc']
                ],
                columns: [{
                        data: 'rownum',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'product_code'
                    },
                    {
                        data: 'product_name'
                    },
                    {
                        data: 'product_type'
                    },
                    {
                        data: 'package_name'
                    },
                    {
                        data: 'category_name'
                    },
                    {
                        data: 'supplier_name'
                    },
                    {
                        data: 'quantity',
                        className: 'text-end'
                    },
                    {
                        data: 'stock_minimum',
                        className: 'text-end'
                    },
                    {
                        data: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'hpp',
                        className: 'text-end'
                    },
                    {
                        data: 'selling_price',
                        className: 'text-end'
                    },
                    {
                        data: 'created_at',
                        orderable: true
                    },
                ]
            });

            if (CAN_SWITCH_WH) {
                $('#filterWarehouse').on('change', function() {
                    table.ajax.reload();
                });
            }

            // Connect global navbar search
            $('#globalSearch').on('keyup', function() {
                table.search(this.value).draw();
            });

            $('#pageLength').on('change', function() {
                table.page.len(parseInt(this.value || 10, 10)).draw();
            });

            // Export Logic
            $('#btnExportStock').on('click', function() {
                let whId = CAN_SWITCH_WH ? ($('#filterWarehouse').val() || '') : (USER_WAREHOUSE_ID || '');
                let start = $('#exportStartDate').val();
                let end = $('#exportEndDate').val();
                let params = [];
                
                if (whId) params.push('warehouse_id=' + whId);
                if (start) params.push('start_date=' + start);
                if (end) params.push('end_date=' + end);

                let url = @json(route('stocklevel.exportExcel'));
                if (params.length > 0) {
                    url += '?' + params.join('&');
                }
                
                // Add loading spinner specifically to this button
                let originalHtml = $(this).html();
                $(this).html('<span class="spinner-border spinner-border-sm me-2"></span> Exporting...').prop('disabled', true);
                
                // Redirect to download
                window.location.href = url;
                
                // Restore button after short delay
                setTimeout(() => {
                    $(this).html(originalHtml).prop('disabled', false);
                }, 3000);
            });

        });
    </script>
@endpush
