@extends('layouts.home')

@section('content')
    @php
        /** @var \App\Models\User $me */
        $isWarehouseUser = $me?->hasRole('warehouse');
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

        {{-- Toolbar & Filters --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1">Role</label>
                        <select id="f_role" class="form-select">
                            <option value="">Select Role</option>
                            @foreach ($allRoles as $r)
                                <option value="{{ $r->name }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1">Status</label>
                        <select id="f_status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-muted">Show</label>
                        <select id="pageLength" class="form-select" style="width:90px">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>

                    <div class="ms-auto d-flex flex-wrap align-items-center gap-2">

                        @if(auth()->user()->hasPermission('users.export'))
                            <div class="btn-group">
                                <button class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bx bx-export me-1"></i> Export
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" id="btnExportExcel">Excel</a></li>
                                    <li><a class="dropdown-item" href="#" id="btnExportPrint">Print</a></li>
                                </ul>
                            </div>
                        @endif

                        @if(auth()->user()->hasPermission('users.bulk_delete'))
                            <button id="btnBulkDelete" class="btn btn-outline-danger">
                                <i class="bx bx-trash me-1"></i> Delete Selected
                            </button>
                        @endif

                        @if(auth()->user()->hasPermission('users.create'))
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#glassAddUser">
                                <i class="bx bx-plus"></i> Add User
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel Users --}}
        <div class="card">
            <div class="table-responsive">
                <table id="tblUsers" class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:36px">
                                <input class="form-check-input" type="checkbox" id="checkAll">
                            </th>
                            <th>ID</th>
                            <th>NAME</th>
                            <th>USERNAME</th>
                            <th>EMAIL</th>
                            <th>PHONE</th>
                            <th>POSITION</th>
                            <th>SIGNATURE</th>
                            <th>ROLES</th>
                            <th>WAREHOUSE</th>
                            <th>STATUS</th>
                            <th>CREATED</th>
                            <th>UPDATED</th>
                            <th style="width:120px">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                            @php
                                $roleSlugs = $u->roles->pluck('slug')->all();
                                $roleNames = $u->roles->pluck('name')->all();
                                $roleText = implode(', ', $roleNames);
                                $rolesAttr = implode(',', $roleSlugs);
                                $signatureUrl = $u->signature_path ? asset('storage/' . $u->signature_path) : null;

                                // Admin WH hanya boleh manage SALES di warehouse dia, atau dirinya sendiri
                                $canManageRow =
                                    !$isWarehouseUser ||
                                    ($u->id === $me->id) ||
                                    (in_array('sales', $roleSlugs, true) && $u->warehouse_id === $me->warehouse_id);
                            @endphp
                            <tr>
                                <td>
                                    @if(auth()->user()->hasPermission('users.bulk_delete'))
                                        <input class="form-check-input row-check" type="checkbox"
                                            @if(!$canManageRow) 
                                                disabled 
                                            @endif>
                                    @endif
                                        @if (!$canManageRow) disabled @endif
                                </td>
                                <td>{{ $u->id }}</td>
                                <td>{{ $u->name }}</td>
                                <td>{{ $u->username }}</td>
                                <td>{{ $u->email }}</td>
                                <td>{{ $u->phone ?? '-' }}</td>
                                <td>{{ $u->position ?? '-' }}</td>
                                <td class="text-center">
                                    @php
                                        $signature = $u->signature_path;
                                    @endphp

                                    @if ($signature)
                                        @if (file_exists(storage_path('app/public/' . $signature)))
                                            <a href="#" class="js-signature"
                                                data-img="{{ asset('storage/' . $signature) }}">
                                                <img src="{{ asset('storage/' . $signature) }}" alt="signature"
                                                    style="height:24px;">
                                            </a>
                                        @elseif (file_exists(public_path($signature)))
                                            <a href="#" class="js-signature" data-img="{{ asset($signature) }}">
                                                <img src="{{ asset($signature) }}" alt="signature" style="height:24px;">
                                            </a>
                                        @else
                                            -
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>

                                <td>{{ $roleText ?: '-' }}</td>
                                <td>{{ $u->warehouse?->warehouse_name ?? '-' }}</td>
                                <td class="txt-nowrap">
                                    @if ($u->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="txt-nowrap">{{ $u->created_at?->format('Y-m-d') }}</td>
                                <td class="txt-nowrap">{{ $u->updated_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        @if(auth()->user()->hasPermission('users.update'))
                                            <a href="#" class="btn btn-sm btn-icon btn-outline-secondary js-edit"
                                                title="Edit"
                                                data-id="{{ $u->id }}"
                                                data-name="{{ $u->name }}"
                                                data-username="{{ $u->username }}"
                                                data-email="{{ $u->email }}"
                                                data-phone="{{ $u->phone }}"
                                                data-position="{{ $u->position }}"
                                                data-roles="{{ $rolesAttr }}"
                                                data-role_names="{{ $roleText }}"
                                                data-status="{{ $u->status }}"
                                                data-warehouse_id="{{ $u->warehouse_id ?? '' }}">
                                                <i class="bx bx-edit-alt"></i>
                                            </a>
                                        @endif

                                        @if(auth()->user()->hasPermission('users.delete'))
                                            <a href="#" class="btn btn-sm btn-icon btn-outline-danger js-del"
                                                title="Delete"
                                                data-id="{{ $u->id }}"
                                                data-name="{{ $u->name }}">
                                                <i class="bx bx-trash"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted">No data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ADD USER – Glass modal --}}
    <div class="modal fade" id="glassAddUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="background:#fff;">

                <div class="modal-header border-0">
                    <h5 class="modal-title">Add User</h5>
                    <button type="button" class="btn-close btn-" data-bs-dismiss="modal"></button>
                </div>

                @if ($errors->any() && !session('edit_open_id'))
                    <div class="px-4">
                        <div class="alert alert-danger mb-0">
                            <div class="fw-semibold mb-1">Failed to save:</div>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form id="formAddUser" method="POST" action="{{ route('users.store') }}" class="modal-body "
                    enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input name="name" value="{{ old('name') }}"
                            class="form-control bg-transparent border-secondary" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input name="username" value="{{ old('username') }}"
                            class="form-control bg-transparent border-secondary" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input name="email" type="email" value="{{ old('email') }}"
                            class="form-control bg-transparent border-secondary" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone (optional)</label>
                        <input name="phone" value="{{ old('phone') }}"
                            class="form-control bg-transparent border-secondary">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Position (optional)</label>
                        <input name="position" value="{{ old('position') }}"
                            class="form-control bg-transparent border-secondary">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Signature (optional)</label>
                        <input type="file" name="signature" class="form-control bg-transparent border-secondary">
                        <small class=-50">Format: jpg, jpeg, png, webp — max 2MB.</small>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Roles</label>
                            @if ($isWarehouseUser)
                                {{-- Admin WH: selalu Sales --}}
                                <input type="hidden" name="roles[]" value="sales">
                                <input class="form-control bg-transparent border-secondary" value="Sales" disabled>
                            @else
                                <select name="roles[]" id="add_roles" class="form-select bg-transparent border-secondary"
                                    required>
                                    <option value="">— Choose role —</option>
                                    @foreach ($allRoles as $r)
                                        <option value="{{ $r->slug }}"
                                            {{ old('roles.0') == $r->slug ? 'selected' : '' }}>
                                            {{ $r->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select bg-transparent border-secondary" required>
                                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive"{{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3" id="wrap_add_wh" style="{{ $isWarehouseUser ? '' : 'display:none' }}">
                        <label class="form-label">Warehouse</label>
                        @if ($isWarehouseUser)
                            <input type="hidden" name="warehouse_id" value="{{ $me->warehouse_id }}">
                            <input class="form-control bg-transparent border-secondary"
                                value="{{ $me->warehouse?->warehouse_name ?? 'My Warehouse' }}" disabled>
                        @else
                            <select name="warehouse_id" class="form-select bg-transparent border-secondary">
                                <option value="">— Choose warehouse —</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}"
                                        {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>
                                        {{ $w->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="row g-2 mt-3">
                        <div class="col">
                            <input name="password" type="password" placeholder="Password"
                                class="form-control bg-transparent border-secondary" required>
                        </div>
                        <div class="col">
                            <input name="password_confirmation" type="password" placeholder="Confirm"
                                class="form-control bg-transparent border-secondary" required>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary text-white">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- EDIT USER – Glass modal --}}
    <div class="modal fade" id="glassEditUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="background:rgb(255, 255, 255);backdrop-filter:blur(14px)">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close btn-" data-bs-dismiss="modal"></button>
                </div>

                <form id="formEditUser" method="POST" class="modal-body" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_id">

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input id="edit_name" name="name" class="form-control bg-transparent border-secondary"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input id="edit_username" name="username" class="form-control bg-transparent border-secondary"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input id="edit_email" name="email" type="email"
                            class="form-control bg-transparent border-secondary" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone (optional)</label>
                        <input id="edit_phone" name="phone" class="form-control bg-transparent border-secondary">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Position (optional)</label>
                        <input id="edit_position" name="position" class="form-control bg-transparent border-secondary">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Signature (optional)</label>
                        <input type="file" name="signature" class="form-control bg-transparent border-secondary">
                        <small class="text-muted">Leave empty to keep current signature.</small>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Roles</label>
                            @if ($isWarehouseUser)
                                {{-- Admin WH: dinamis via JS (Sales/Warehouse) --}}
                                <input type="hidden" name="roles[]" id="edit_roles_input" value="sales">
                                <input class="form-control bg-transparent border-secondary" id="edit_roles_display" value="Sales" disabled>
                            @else
                                <select id="edit_roles" name="roles[]"
                                    class="form-select bg-transparent border-secondary" required>
                                    @foreach ($allRoles as $r)
                                        <option value="{{ $r->slug }}">{{ $r->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select id="edit_status" name="status" class="form-select bg-transparent border-secondary"
                                required>
                                @foreach (['active', 'inactive'] as $s)
                                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-3" id="wrap_edit_wh" style="display:none">
                        <label class="form-label">Warehouse</label>
                        @if ($isWarehouseUser)
                            <input type="hidden" name="warehouse_id" id="edit_warehouse_id"
                                value="{{ $me->warehouse_id }}">
                            <input class="form-control bg-transparent border-secondary"
                                value="{{ $me->warehouse?->warehouse_name ?? 'My Warehouse' }}" disabled>
                        @else
                            <select id="edit_warehouse_id" name="warehouse_id"
                                class="form-select bg-transparent border-secondary">
                                <option value="">— Choose warehouse —</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="row g-2 mt-3">
                        <div class="col">
                            <input name="password" type="password" placeholder="New password (optional)"
                                class="form-control bg-transparent border-secondary">
                        </div>
                        <div class="col">
                            <input name="password_confirmation" type="password" placeholder="Confirm"
                                class="form-control bg-transparent border-secondary">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary text-white" type="submit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .swal2-container {
            z-index: 20000 !important;
        }

        /* 📱 RESPONSIVE & STATIC TABLE FIX */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            width: 100%;
        }

        #tblUsers {
            font-size: 0.8rem;
            width: 100% !important;
            margin: 0 !important;
            border-collapse: collapse;
        }

        #tblUsers thead th {
            background-color: var(--bg-body);
            white-space: nowrap;
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 12px 10px;
            border-bottom: 2px solid var(--border);
        }

        #tblUsers tbody td {
            padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            /* Izinkan wrap di kolom teks panjang biar tabel gak "melar" ke samping */
            white-space: normal; 
        }

        /* Kolom yang wajib 1 baris (nanti kita kasih class text-nowrap di HTML) */
        .txt-nowrap {
            white-space: nowrap !important;
        }

        /* Batasi lebar kolom ID & Status & Dates */
        #tblUsers th:nth-child(2), #tblUsers td:nth-child(2) { width: 50px; text-align: center; }
        #tblUsers th:nth-child(11), #tblUsers td:nth-child(11) { width: 80px; text-align: center; }
        #tblUsers th:nth-child(12), #tblUsers td:nth-child(12),
        #tblUsers th:nth-child(13), #tblUsers td:nth-child(13) { width: 100px; white-space: nowrap; }

        /* 🔥 FIX ACTIONS COLUMN (Statik di ujung kanan) */
        #tblUsers th:last-child, 
        #tblUsers td:last-child {
            width: 90px !important;
            min-width: 90px !important;
            text-align: center;
            white-space: nowrap !important;
        }
        
        .btn-icon {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* 🖨️ PRINT OPTIMIZATION (Biar Bersih) */
        @media print {
            /* Sembunyikan semua elemen UI */
            .layout-menu, 
            .layout-navbar, 
            .footer,
            .card.mb-3, /* Filter & Toolbar */
            .btn,
            .btn-group,
            .dropdown-menu,
            .dataTables_length,
            .dataTables_filter,
            .dataTables_info,
            .dataTables_paginate,
            .modal {
                display: none !important;
            }

            /* Atur Body & Container biar Full Width */
            body, .container-xxl, .content-wrapper, .layout-page, .layout-container {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            /* Tampilkan Tabel Secara Penuh */
            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            #tblUsers {
                width: 100% !important;
                border: 1px solid #000 !important;
                font-size: 10pt !important;
            }

            #tblUsers th, #tblUsers td {
                border: 1px solid #ddd !important;
                padding: 6px !important;
                color: #000 !important;
                white-space: normal !important; /* Biar kalau teks kepanjangan dia turun pas di print */
            }

            /* Sembunyikan kolom checkbox & Actions pas print */
            #tblUsers th:first-child, #tblUsers td:first-child,
            #tblUsers th:last-child, #tblUsers td:last-child {
                display: none !important;
            }
        }

        #pageLength,
        #f_role,
        #f_status {
            font-size: 0.75rem;
            height: calc(1.5em + .5rem + 2px);
            padding: .25rem .5rem;
        }

        .dataTables_info,
        .dataTables_paginate {
            font-size: 0.75rem;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(function() {
            const table = $('#tblUsers').DataTable({
                order: [
                    [1, 'asc']
                ],
                columnDefs: [{
                        targets: [0, 13],
                        orderable: false
                    },
                    {
                        targets: 1,
                        type: 'num'
                    }
                ],
                pageLength: 10,
                lengthChange: false,
                searching: true,
                info: true,
                dom: 't<"d-flex justify-content-between align-items-center p-3 pt-2"ip>'
            });

            // Connect global navbar search
            $('#globalSearch').on('keyup', function() {
                table.search(this.value).draw();
            });
            $('#pageLength').on('change', function() {
                table.page.len(parseInt(this.value, 10)).draw();
            });

            $('#f_role').on('change', function() {
                const v = this.value;
                table.column(8).search(v ? v : '', true, false).draw();
            });

            $('#f_status').on('change', function() {
                const v = (this.value || '').toLowerCase();

                if (!v) {
                    table.column(10).search('').draw();
                    return;
                }

                // Karena isi kolom adalah badge "Active/Inactive"
                const label = (v === 'active') ? 'Active' : 'Inactive';
                table.column(10).search(label, false, true).draw(); // contains, case-insensitive
            });


            $('#checkAll').on('change', function() {
                $('#tblUsers tbody .row-check:not(:disabled)').prop('checked', this.checked);
            });

            // Preview signature
            $(document).on('click', '.js-signature', function(e) {
                e.preventDefault();
                const img = this.dataset.img;
                if (!img) return;
                Swal.fire({
                    title: 'Signature',
                    imageUrl: img,
                    imageAlt: 'Signature',
                    imageWidth: 450,
                    showCloseButton: true,
                    showConfirmButton: false
                });
            });

            // Export Excel (Backend)
            $('#btnExportExcel').on('click', function(e) {
                e.preventDefault();
                const role   = $('#f_role').val();
                const status = $('#f_status').val();
                const search = $('#globalSearch').val();

                let url = "{{ route('users.exportExcel') }}";
                let params = new URLSearchParams();
                if(role)   params.append('role', role);
                if(status) params.append('status', status);
                if(search) params.append('search', search);

                window.location.href = url + '?' + params.toString();
            });


            $('#btnExportPrint').on('click', function(e) {
                e.preventDefault();
                // Tutup dropdown sebelum print agar tidak ikut kecetak
                $('.dropdown-toggle').dropdown('hide');
                
                // Small delay biar dropdown beneran ketutup dulu
                setTimeout(() => {
                    window.print();
                }, 200);
            });

            // toggle warehouse on add
            function toggleAddWarehouse() {
                @if ($isWarehouseUser)
                    $('#wrap_add_wh').show();
                    return;
                @endif
                const val = $('#add_roles').val();
                const vals = val ? [val] : [];
                const need = vals.includes('warehouse') || vals.includes('sales');
                $('#wrap_add_wh').toggle(need);
                if (!need) $('#wrap_add_wh select').val('');
            }
            $('#add_roles').on('change', toggleAddWarehouse);
            toggleAddWarehouse();

            // Edit modal
            const modalEl = document.getElementById('glassEditUser');
            const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
            const form = document.getElementById('formEditUser');
            const baseUrl = @json(url('users'));

            function toggleEditWarehouse() {
                @if ($isWarehouseUser)
                    $('#wrap_edit_wh').show();
                    return;
                @endif
                const val = $('#edit_roles').val();
                const vals = val ? [val] : [];
                const need = vals.includes('warehouse') || vals.includes('sales');
                $('#wrap_edit_wh').toggle(need);
                if (!need) $('#edit_warehouse_id').val('');
            }

            $(document).on('click', '.js-edit', function(e) {
                e.preventDefault();
                const d = this.dataset;
                form.setAttribute('action', baseUrl + '/' + d.id);
                $('#edit_id').val(d.id);
                $('#edit_name').val(d.name || '');
                $('#edit_username').val(d.username || '');
                $('#edit_email').val(d.email || '');
                $('#edit_phone').val(d.phone || '');
                $('#edit_position').val(d.position || '');

                @if ($isWarehouseUser)
                    const rolesArr = (d.roles || '').split(',').filter(Boolean);
                    const roleSlug = rolesArr[0] || 'sales';
                    const roleName = d.role_names || (roleSlug === 'sales' ? 'Sales' : 'Admin Warehouse');
                    $('#edit_roles_input').val(roleSlug);
                    $('#edit_roles_display').val(roleName);
                @else
                    const rolesArr = (d.roles || '').split(',').filter(Boolean);
                    $('#edit_roles').val(rolesArr[0] || '');
                @endif

                $('#edit_status').val((d.status || 'active').toLowerCase());

                $('#edit_warehouse_id').val(d.warehouse_id || '');
                toggleEditWarehouse();

                form.querySelector('input[name="password"]').value = '';
                form.querySelector('input[name="password_confirmation"]').value = '';
                modal?.show();
            });

            $('#edit_roles').on('change', toggleEditWarehouse);

            function getCheckedIds() {
                const ids = [];
                $('#tblUsers tbody tr').each(function() {
                    const cb = $(this).find('.row-check');
                    if (cb.is(':checked') && !cb.is(':disabled')) {
                        const id = $(this).find('td').eq(1).text().trim();
                        if (id) ids.push(Number(id));
                    }
                });
                return ids;
            }

            // Delete single
            $('#tblUsers').on('click', '.js-del', function(e) {
                e.preventDefault();
                const id = this.dataset.id;
                const name = this.dataset.name || 'user';
                Swal.fire({
                    title: 'Delete user?',
                    html: `<div class="text-muted">Data <b>${name}</b> will be permanently deleted.</div>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete!',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d33'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    fetch(baseUrl + '/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    }).then(async r => {
                        if (!r.ok) {
                            const tx = await r.text();
                            throw new Error(tx || 'Failed to delete.');
                        }
                        location.reload();
                    }).catch(err => Swal.fire('Error', err.message || 'Failed to delete.',
                        'error'));
                });
            });

            // Bulk delete
            $('#btnBulkDelete').on('click', function(e) {
                e.preventDefault();
                const ids = getCheckedIds();
                if (!ids.length) return Swal.fire('Info', 'Please select at least one row.', 'info');
                Swal.fire({
                    title: 'Delete selected data?',
                    html: `Total <b>${ids.length}</b> users`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete!',
                    confirmButtonColor: '#d33'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    fetch(@json(route('users.bulk-destroy')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            ids
                        })
                    }).then(async r => {
                        if (!r.ok) {
                            throw new Error(await r.text() || 'Failed to bulk delete.');
                        }
                        location.reload();
                    }).catch(err => Swal.fire('Error', err.message || 'Failed to bulk delete.',
                        'error'));
                });
            });

            // auto show add modal on validation error
            @if ($errors->any() && !session('edit_open_id'))
                new bootstrap.Modal(document.getElementById('glassAddUser')).show();
            @endif

            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('success')),
                    timer: 1800,
                    showConfirmButton: false
                });
            @endif
            @if (session('edit_success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('edit_success')),
                    timer: 1800,
                    showConfirmButton: false
                });
            @endif
        });
    </script>
@endpush
