@extends('layouts.home')

@section('content')

    @php
        $me = auth()->user();
        $roles = $me?->roles ?? collect();

        $isSuperadmin = $roles->contains('slug', 'superadmin');
        $isProcurement = $roles->contains('slug', 'procurement');
        $isCeo = $roles->contains('slug', 'ceo');
        $isWarehouse = $roles->contains('slug', 'warehouse');

        $statusOptions = $statusOptions ?? [
            '' => 'All Status',
            'draft' => 'Draft',
            'ordered' => 'Ordered',
            'partially_received' => 'Partially Received',
            'received' => 'Received',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        $approvalOptions = $approvalOptions ?? [
            '' => 'All Approval',
            'draft' => 'Draft',
            'waiting_procurement' => 'Waiting Procurement',
            'waiting_ceo' => 'Waiting CEO',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];

        $warehouses =
            $warehouses ??
            \App\Models\Warehouse::query()->select('id', 'warehouse_name')->orderBy('warehouse_name')->get();

        $perPage = (int) request('per_page', 10);
        $myWhId = $me->warehouse_id ?? null;
    @endphp

    <style>
        .po-card-title {
            font-size: 1rem;
        }

        .po-filters .form-label {
            font-size: .75rem;
            color: #6c757d;
            margin-bottom: .25rem;
        }

        .po-filters .form-control,
        .po-filters .form-select,
        .po-filters .input-group-text {
            font-size: .8125rem;
        }

        .po-filters .input-group-text {
            padding: .25rem .5rem;
        }

        .po-filters .form-control-sm,
        .po-filters .form-select-sm {
            padding-top: .35rem;
            padding-bottom: .35rem;
        }

        .po-table thead th {
            font-size: .7rem;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: #6c757d;
            padding: 8px 4px !important;
        }

        .po-table tbody td {
            font-size: .725rem;
            padding: 4px 4px !important;
        }

        .table-tiny {
            width: 100% !important;
        }

        .po-muted {
            font-size: .625rem;
            line-height: 1.1;
        }

        @media (max-width: 576px) {
            .po-actions {
                width: 100%;
                justify-content: flex-start !important;
            }

            .po-actions form {
                width: 100%;
            }

            .po-actions .btn {
                width: 100%;
            }
        }

        .swal2-container {
            z-index: 99999 !important;
        }
    </style>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" />

    <div class="container-xxl flex-grow-1 container-p-y">

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <div class="d-flex flex-column">
                    <h5 class="mb-0 fw-bold po-card-title">Purchase Orders</h5>
                </div>

                <div class="d-flex flex-wrap gap-2 ms-auto po-actions justify-content-end">
                    <form id="po-export-form" method="GET" action="{{ route('po.export.index') }}"
                        class="d-flex align-items-center">

                        <input type="hidden" name="q" value="{{ request('q') }}">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <input type="hidden" name="approval_status" value="{{ request('approval_status') }}">
                        <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}">
                        <input type="hidden" name="from" value="{{ request('from') }}">
                        <input type="hidden" name="to" value="{{ request('to') }}">

                        <button class="btn btn-outline-success btn-sm" type="submit">
                            <i class="bx bx-download"></i> Excel
                        </button>
                    </form>

                    @if ($isSuperadmin)
                        <form action="{{ route('po.store') }}" method="POST" class="d-flex align-items-center">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bx bx-plus"></i> New PO
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card-body pt-3 pb-2">
                <form id="po-filter-form" method="get" action="{{ route('po.index') }}" class="po-filters">

                    <input type="hidden" name="q" value="{{ request('q') }}">

                    <div class="row g-2 align-items-end">

                        <div class="col-6 col-sm-3 col-lg-2">
                            <label class="form-label">From</label>
                            <input type="date" class="form-control form-control-sm" name="from"
                                value="{{ request('from') }}">
                        </div>

                        <div class="col-6 col-sm-3 col-lg-2">
                            <label class="form-label">To</label>
                            <input type="date" class="form-control form-control-sm" name="to"
                                value="{{ request('to') }}">
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                @foreach ($statusOptions as $k => $v)
                                    <option value="{{ $k }}" @selected((string) request('status', '') === (string) $k)>{{ $v }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label">Approval</label>
                            <select name="approval_status" class="form-select form-select-sm">
                                @foreach ($approvalOptions as $k => $v)
                                    <option value="{{ $k }}" @selected((string) request('approval_status', '') === (string) $k)>{{ $v }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label">Warehouse</label>
                            <select name="warehouse_id" class="form-select form-select-sm">
                                <option value="" @selected(request('warehouse_id', '') === '')>All Warehouse</option>
                                <option value="central" @selected(request('warehouse_id', '') === 'central')>Central Stock</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" @selected((string) request('warehouse_id', '') === (string) $w->id)>
                                        {{ $w->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-sm-3 col-lg-2">
                            <label class="form-label">Per Page</label>
                            <select name="per_page" class="form-select form-select-sm">
                                @foreach ([10, 25, 50, 100] as $n)
                                    <option value="{{ $n }}" @selected($perPage === $n)>
                                        {{ $n }}/page</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </form>

                <div class="alert alert-info small py-2 mt-3 mb-0">
                    <strong>Approval Rules:</strong>
                    <ul class="mb-0 ps-3">
                        <li>Grand total &le; Rp 1,000,000 → approved by <strong>Procurement</strong>.</li>
                        <li>Grand total &gt; Rp 1,000,000 → requires 2-level approval: <strong>Procurement → CEO</strong>.
                        </li>
                    </ul>
                </div>
            </div>

            <div id="po-table-wrapper">
                <div class="table-responsive">
                    <table class="table table-hover table-tiny align-middle mb-0 po-table" id="tblPOs">
                        <thead>
                            <tr>
                                <th style="width: 11%;">PO CODE</th>
                                <th style="width: 14%;">Supplier</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 15%;">Approval</th>
                                <th class="text-end" style="width: 8%;">Subtotal</th>
                                <th class="text-end" style="width: 8%;">Discount</th>
                                <th class="text-end" style="width: 8%;">Grand</th>
                                <th style="width: 5%;">Lines</th>
                                <th style="width: 11%;">Warehouse</th>
                                <th class="text-end" style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- SINGLE DYNAMIC MODAL FOR GOODS RECEIVED --}}
    <div class="modal fade mdl-gr-po" id="modalDynamicGR" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" id="modalDynamicGRContent">
                <div class="modal-body text-center p-5">
                    <span class="spinner-border text-primary" role="status"></span>
                    <p class="mt-2">Loading Good Received form...</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- DataTables Core --}}
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    @if(session('gr_success') || session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: "{{ session('gr_success') ?: session('success') }}",
                    timer: 2500,
                    showConfirmButton: false
                });
            });
        </script>
    @endif

    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: "{{ session('error') }}",
                });
            });
        </script>
    @endif

    <script>
        $(function() {
            // Sync navbar search with URL parameter 'q'
            const urlParams = new URLSearchParams(window.location.search);
            const qParam = urlParams.get('q');
            if (qParam) {
                $('#globalSearch').val(qParam);
            }

            const table = $('#tblPOs').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('po.datatable') }}",
                    data: function(d) {
                        d.status = $('select[name="status"]').val();
                        d.approval_status = $('select[name="approval_status"]').val();
                        d.warehouse_id = $('select[name="warehouse_id"]').val();
                        d.from = $('input[name="from"]').val();
                        d.to = $('input[name="to"]').val();
                        d.q = $('#globalSearch').val();
                    }
                },
                columns: [
                    { data: 'po_code', name: 'po_code', className: 'fw-semibold' },
                    { data: 'supplier', name: 'supplier' },
                    { data: 'status', name: 'status' },
                    { data: 'approval', name: 'approval' },
                    { data: 'subtotal', name: 'subtotal', className: 'text-end' },
                    { data: 'discount', name: 'discount', className: 'text-end' },
                    { data: 'grand_total', name: 'grand_total', className: 'text-end fw-bold' },
                    { data: 'lines', name: 'lines', className: 'text-center' },
                    { data: 'warehouse', name: 'warehouse' },
                    { data: 'actions', name: 'actions', className: 'text-end', orderable: false, searchable: false }
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    processing: '<div class="spinner-border text-primary" role="status"></div>',
                    search: "_INPUT_",
                    searchPlaceholder: "Search PO..."
                },
                dom: '<"d-flex justify-content-between align-items-center mx-0 row py-3 px-1"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6">>t<"d-flex justify-content-between mx-0 row py-3 px-1"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                drawCallback: function() {
                    $('.po-table [data-bs-toggle="tooltip"]').tooltip();
                }
            });

            // Auto-filter
            $('#po-filter-form select, #po-filter-form input[type="date"]').on('change', function() {
                table.ajax.reload();
            });

            // Global search
            const debounce = (fn, ms = 450) => {
                let t;
                return (...args) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn(...args), ms);
                };
            };

            $('#globalSearch').on('input', debounce(function() {
                table.ajax.reload();
            }, 450));

            // Export logic stays same but uses current filters
            $('#po-export-form').on('submit', function() {
                const form = $(this);
                form.find('input[name="q"]').val($('#globalSearch').val());
                form.find('input[name="status"]').val($('select[name="status"]').val());
                form.find('input[name="approval_status"]').val($('select[name="approval_status"]').val());
                form.find('input[name="warehouse_id"]').val($('select[name="warehouse_id"]').val());
                form.find('input[name="from"]').val($('input[name="from"]').val());
                form.find('input[name="to"]').val($('input[name="to"]').val());
            });

            // Dynamic Modal Loading
            const modalGR = new bootstrap.Modal(document.getElementById('modalDynamicGR'));
            const modalBody = document.getElementById('modalDynamicGRContent');

            $(document).on('click', '.js-btn-receive', function() {
                const url = $(this).data('url');
                modalBody.innerHTML = `
                    <div class="modal-body text-center p-5">
                        <span class="spinner-border text-primary" role="status"></span>
                        <p class="mt-2">Loading Good Received form...</p>
                    </div>`;
                modalGR.show();

                fetch(url)
                    .then(res => res.text())
                    .then(html => {
                        modalBody.innerHTML = html;
                    })
                    .catch(err => {
                        console.error(err);
                        modalBody.innerHTML = `<div class="modal-body"><div class="alert alert-danger">Failed to load data.</div></div>`;
                    });
            });

            // Auto-sync Good/Damaged (Delegated)
            const clampInt = (v, min, max) => {
                v = parseInt(v ?? 0, 10);
                if (isNaN(v)) v = 0;
                return Math.min(max, Math.max(min, v));
            };

            function syncRow(tr, changed) {
                const remEl = tr.querySelector('.js-remaining');
                const goodEl = tr.querySelector('.js-qty-good');
                const badEl = tr.querySelector('.js-qty-damaged');
                if (!remEl || !goodEl || !badEl) return;

                const remaining = parseInt(remEl.dataset.remaining ?? '0', 10) || 0;
                goodEl.max = remaining;
                badEl.max = remaining;

                let good = clampInt(goodEl.value, 0, remaining);
                let bad = clampInt(badEl.value, 0, remaining);

                if (changed === 'good') {
                    bad = remaining - good;
                } else if (changed === 'bad') {
                    good = remaining - bad;
                } else if (changed === 'init') {
                    if (good + bad !== remaining) {
                        good = remaining; bad = 0;
                    }
                }
                goodEl.value = good;
                badEl.value = bad;
            }

            $(document).on('input', '.mdl-gr-po .js-qty-good', function() { syncRow(this.closest('tr'), 'good'); });
            $(document).on('input', '.mdl-gr-po .js-qty-damaged', function() { syncRow(this.closest('tr'), 'bad'); });

            // Confirm gr_blocked
            $(document).on('click', '.js-gr-blocked', function() {
                const poCode = $(this).data('po');
                Swal.fire({
                    icon: 'info',
                    title: 'Restricted Action',
                    text: `PO ${poCode} berasal dari Request Stock. Sesuai prosedur, penerimaan barang HARUS dilakukan oleh akun Warehouse didepo tujuan, bukan Superadmin.`,
                    confirmButtonText: 'I Understand'
                });
            });
        });
    </script>
@endpush
