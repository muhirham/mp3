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
                @foreach($allRoles as $r)
                <option value="{{ $r->name }}">{{ $r->name }}</option>
                @endforeach
            </select>
            </div>
            <div class="col-12 col-md-4">
            <label class="form-label mb-1">Status</label>
            <select id="f_status" class="form-select">
                <option value="">Select Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
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
            <input id="searchUser" type="text" class="form-control" placeholder="Search user..." style="max-width:260px">

            <div class="btn-group">
                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bx bx-export me-1"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="" id="btnExportCSV">CSV</a></li>
                <li><a class="dropdown-item" href="" id="btnExportPrint">Print</a></li>
                </ul>
            </div>

            <button id="btnBulkDelete" class="btn btn-outline-danger">
                <i class="bx bx-trash me-1"></i> Delete Selected
            </button>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#glassAddUser">
                <i class="bx bx-plus"></i> Add User
            </button>
            </div>
        </div>
        </div>
    </div>

    {{-- Tabel Users --}}
    <div class="card">
        <div class="table-responsive">
        <table id="tblUsers" class="table table-hover align-middle mb-0">
            <thead>
            <tr>
            <th style="width:36px"><input class="form-check-input" type="checkbox" id="checkAll"></th>
            <th>ID</th>
            <th>NAME</th>
            <th>USERNAME</th>
            <th>EMAIL</th>
            <th>PHONE</th>
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
                $roleText  = implode(', ', $roleNames);
                $rolesAttr = implode(',', $roleSlugs);
            @endphp
            <tr>
                <td><input class="form-check-input row-check" type="checkbox"></td>
                <td>{{ $u->id }}</td>
                <td>{{ $u->name }}</td>
                <td>{{ $u->username }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->phone ?? '-' }}</td>
                <td>{{ $roleText ?: '-' }}</td>
                <td>{{ $u->warehouse?->warehouse_name ?? '-' }}</td>
                <td>{{ ucfirst($u->status) }}</td>
                <td>{{ $u->created_at?->format('Y-m-d') }}</td>
                <td>{{ $u->updated_at?->format('Y-m-d H:i') }}</td>
                <td>
                <div class="d-flex gap-1">
                    <a href="#" class="btn btn-sm btn-icon btn-outline-secondary js-edit"
                    title="Edit"
                    data-id="{{ $u->id }}"
                    data-name="{{ $u->name }}"
                    data-username="{{ $u->username }}"
                    data-email="{{ $u->email }}"
                    data-phone="{{ $u->phone }}"
                    data-roles="{{ $rolesAttr }}"
                    data-status="{{ $u->status }}"
                    data-warehouse_id="{{ $u->warehouse_id ?? '' }}">
                    <i class="bx bx-edit-alt"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-icon btn-outline-danger js-del"
                    title="Delete"
                    data-id="{{ $u->id }}"
                    data-name="{{ $u->name }}">
                    <i class="bx bx-trash"></i>
                    </a>
                </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="12" class="text-center text-muted">No data</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    </div>

    {{-- ADD USER – Glass modal --}}
    <div class="modal fade" id="glassAddUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="background:rgba(17,22,28,.6);backdrop-filter:blur(14px)">
        <div class="modal-header border-0">
            <h5 class="modal-title text-white">Add User</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        @if ($errors->any() && !session('edit_open_id'))
            <div class="px-4">
            <div class="alert alert-danger mb-0">
                <div class="fw-semibold mb-1">Gagal menyimpan:</div>
                <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
                </ul>
            </div>
            </div>
        @endif

        <form id="formAddUser" method="POST" action="{{ route('users.store') }}" class="modal-body text-white">
            @csrf

            <div class="mb-3">
            <label class="form-label text-white">Name</label>
            <input name="name" value="{{ old('name') }}" class="form-control bg-transparent text-white border-secondary" required>
            </div>

            <div class="mb-3">
            <label class="form-label text-white">Username</label>
            <input name="username" value="{{ old('username') }}" class="form-control bg-transparent text-white border-secondary" required>
            </div>

            <div class="mb-3">
            <label class="form-label text-white">Email</label>
            <input name="email" type="email" value="{{ old('email') }}" class="form-control bg-transparent text-white border-secondary" required>
            </div>

            <div class="mb-3">
            <label class="form-label text-white">Phone</label>
            <input name="phone" value="{{ old('phone') }}" class="form-control bg-transparent text-white border-secondary">
            </div>

            <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label text-white">Roles</label>
                @if($isWarehouseUser)
                <input type="hidden" name="roles[]" value="sales">
                <input class="form-control bg-transparent text-white border-secondary" value="Sales" disabled>
                @else
                <select name="roles[]" id="add_roles" class="form-select bg-transparent text-white border-secondary" required>
                    <option value="">— Choose role —</option>
                    @foreach ($allRoles as $r)
                    <option value="{{ $r->slug }}" {{ (old('roles.0') == $r->slug) ? 'selected' : '' }}>
                        {{ $r->name }}
                    </option>
                    @endforeach
                </select>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label text-white">Status</label>
                <select name="status" class="form-select bg-transparent text-white border-secondary" required>
                <option value="active"  {{ old('status')==='active' ? 'selected':'' }}>Active</option>
                <option value="inactive"{{ old('status')==='inactive' ? 'selected':'' }}>Inactive</option>
                </select>
            </div>
            </div>

            <div class="mt-3" id="wrap_add_wh" style="{{ $isWarehouseUser ? '' : 'display:none' }}">
            <label class="form-label text-white">Warehouse</label>
            @if($isWarehouseUser)
                <input type="hidden" name="warehouse_id" value="{{ $me->warehouse_id }}">
                <input class="form-control bg-transparent text-white border-secondary"
                    value="{{ $me->warehouse?->warehouse_name ?? 'My Warehouse' }}" disabled>
            @else
                <select name="warehouse_id" class="form-select bg-transparent text-white border-secondary">
                <option value="">— Choose warehouse —</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected':'' }}>
                    {{ $w->warehouse_name }}
                    </option>
                @endforeach
                </select>
            @endif
            </div>

            <div class="row g-2 mt-3">
            <div class="col">
                <input name="password" type="password" placeholder="Password" class="form-control bg-transparent text-white border-secondary" required>
            </div>
            <div class="col">
                <input name="password_confirmation" type="password" placeholder="Confirm" class="form-control bg-transparent text-white border-secondary" required>
            </div>
            </div>

            <div class="mt-4 d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-light text-dark">Submit</button>
            </div>
        </form>
        </div>
    </div>
    </div>

    {{-- EDIT USER – Glass modal --}}
    <div class="modal fade" id="glassEditUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="background:rgba(17,22,28,.6);backdrop-filter:blur(14px)">
        <div class="modal-header border-0">
            <h5 class="modal-title text-white">Edit User</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <form id="formEditUser" method="POST" class="modal-body text-white">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit_id">

            <div class="mb-3">
            <label class="form-label text-white">Name</label>
            <input id="edit_name" name="name" class="form-control bg-transparent text-white border-secondary" required>
            </div>

            <div class="mb-3">
            <label class="form-label text-white">Username</label>
            <input id="edit_username" name="username" class="form-control bg-transparent text-white border-secondary" required>
            </div>

            <div class="mb-3">
            <label class="form-label text-white">Email</label>
            <input id="edit_email" name="email" type="email" class="form-control bg-transparent text-white border-secondary" required>
            </div>

            <div class="mb-3">
            <label class="form-label text-white">Phone</label>
            <input id="edit_phone" name="phone" class="form-control bg-transparent text-white border-secondary">
            </div>

            <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label text-white">Roles</label>
                @if($isWarehouseUser)
                <input type="hidden" name="roles[]" id="edit_roles_force" value="sales">
                <input class="form-control bg-transparent text-white border-secondary" value="Sales" disabled>
                @else
                <select id="edit_roles" name="roles[]" class="form-select bg-transparent text-white border-secondary" required>
                    @foreach($allRoles as $r)
                    <option value="{{ $r->slug }}">{{ $r->name }}</option>
                    @endforeach
                </select>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label text-white">Status</label>
                <select id="edit_status" name="status" class="form-select bg-transparent text-white border-secondary" required>
                @foreach(['active','inactive'] as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
                </select>
            </div>
            </div>

            <div class="mt-3" id="wrap_edit_wh" style="display:none">
            <label class="form-label text-white">Warehouse</label>
            @if($isWarehouseUser)
                <input type="hidden" name="warehouse_id" id="edit_warehouse_id" value="{{ $me->warehouse_id }}">
                <input class="form-control bg-transparent text-white border-secondary"
                    value="{{ $me->warehouse?->warehouse_name ?? 'My Warehouse' }}" disabled>
            @else
                <select id="edit_warehouse_id" name="warehouse_id" class="form-select bg-transparent text-white border-secondary">
                <option value="">— Choose warehouse —</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                @endforeach
                </select>
            @endif
            </div>

            <div class="row g-2 mt-3">
            <div class="col">
                <input name="password" type="password" placeholder="New password (optional)" class="form-control bg-transparent text-white border-secondary">
            </div>
            <div class="col">
                <input name="password_confirmation" type="password" placeholder="Confirm" class="form-control bg-transparent text-white border-secondary">
            </div>
            </div>

            <div class="mt-4 d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-light text-dark" type="submit">Save</button>
            </div>
        </form>
        </div>
    </div>
    </div>
    @endsection

    @push('styles')
    <style>.swal2-container { z-index: 20000 !important; }</style>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    @endpush

    @push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(function () {
    const table = $('#tblUsers').DataTable({
        order: [[1,'asc']],
        columnDefs: [
        { targets: [0,11], orderable:false },
        { targets: 1, type: 'num' }
        ],
        pageLength: 10,
        lengthChange: false,
        searching: true,
        info: true,
        dom: 't<"d-flex justify-content-between align-items-center p-3 pt-2"ip>'
    });

    $('#searchUser').on('keyup', function(){ table.search(this.value).draw(); });
    $('#pageLength').on('change', function(){ table.page.len(parseInt(this.value,10)).draw(); });

    // filter by role (column index 6) – pakai nama role
    $('#f_role').on('change', function(){
        const v = this.value;
        table.column(6).search(v ? v : '', true, false).draw();
    });

    // filter by status (column index 8)
    $('#f_status').on('change', function(){
        const v = this.value;
        table.column(8).search(v ? '^'+v+'$' : '', true, false).draw();
    });

    $('#checkAll').on('change', function(){
        $('#tblUsers tbody .row-check').prop('checked', this.checked);
    });

    // Export CSV
    $('#btnExportCSV').on('click', function(e){
        e.preventDefault();
        const headers = ['ID','Name','Username','Email','Phone','Roles','Warehouse','Status','Created','Updated'];
        let csv = headers.join(',') + '\n';
        $('#tblUsers tbody tr:visible').each(function(){
        const t = $(this).find('td');
        csv += [
            t.eq(1).text().trim(),
            t.eq(2).text().trim(),
            t.eq(3).text().trim(),
            t.eq(4).text().trim(),
            t.eq(5).text().trim(),
            t.eq(6).text().trim(),
            t.eq(7).text().trim(),
            t.eq(8).text().trim(),
            t.eq(9).text().trim(),
            t.eq(10).text().trim()
        ].join(',') + '\n';
        });
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}), url = URL.createObjectURL(blob);
        $('<a>').attr({href:url, download:'users.csv'})[0].click();
        setTimeout(()=>URL.revokeObjectURL(url), 500);
    });

    $('#btnExportPrint').on('click', function(e){ e.preventDefault(); window.print(); });

    // ====== ADD: toggle warehouse (dropdown) ======
    function toggleAddWarehouse() {
        @if($isWarehouseUser)
        $('#wrap_add_wh').show(); return;
        @endif
        const val = $('#add_roles').val();
        const vals = val ? [val] : [];
        const need = vals.includes('warehouse') || vals.includes('sales');
        $('#wrap_add_wh').toggle(need);
        if (!need) $('#wrap_add_wh select').val('');
    }
    $('#add_roles').on('change', toggleAddWarehouse);
    toggleAddWarehouse();

    // ====== EDIT modal ======
    const modalEl = document.getElementById('glassEditUser');
    const modal   = modalEl ? new bootstrap.Modal(modalEl) : null;
    const form    = document.getElementById('formEditUser');
    const baseUrl = @json(url('admin/users'));

    function toggleEditWarehouse() {
        @if($isWarehouseUser)
        $('#wrap_edit_wh').show(); return;
        @endif
        const val = $('#edit_roles').val();
        const vals = val ? [val] : [];
        const need = vals.includes('warehouse') || vals.includes('sales');
        $('#wrap_edit_wh').toggle(need);
        if (!need) $('#edit_warehouse_id').val('');
    }

    $(document).on('click', '.js-edit', function(e){
        e.preventDefault();
        const d = this.dataset;
        form.setAttribute('action', baseUrl + '/' + d.id);
        $('#edit_id').val(d.id);
        $('#edit_name').val(d.name || '');
        $('#edit_username').val(d.username || '');
        $('#edit_email').val(d.email || '');
        $('#edit_phone').val(d.phone || '');

        @if(!$isWarehouseUser)
        const roles = (d.roles || '').split(',').filter(Boolean);
        $('#edit_roles').val(roles[0] || '');
        @endif

        $('#edit_status').val(d.status || 'active');
        $('#edit_warehouse_id').val(d.warehouse_id || '');
        toggleEditWarehouse();

        form.querySelector('input[name="password"]').value = '';
        form.querySelector('input[name="password_confirmation"]').value = '';
        modal?.show();
    });

    $('#edit_roles').on('change', toggleEditWarehouse);

    // ====== DELETE single ======
    $('#tblUsers').on('click', '.js-del', function(e){
        e.preventDefault();
        const id = this.dataset.id, name = this.dataset.name || 'user';
        Swal.fire({
        title: 'Hapus user?',
        html: `<div class="text-muted">Data <b>${name}</b> akan dihapus permanen.</div>`,
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus!', cancelButtonText: 'Batal',
        confirmButtonColor: '#d33'
        }).then(res => {
        if (!res.isConfirmed) return;
        fetch(baseUrl + '/' + id, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}
        }).then(async r => {
            if (!r.ok) { const tx = await r.text(); throw new Error(tx || 'Gagal menghapus.'); }
            location.reload();
        }).catch(err => Swal.fire('Error', err.message || 'Gagal menghapus.','error'));
        });
    });

    // ====== BULK DELETE ======
    function getCheckedIds(){
        const ids=[];
        $('#tblUsers tbody tr').each(function(){
        if ($(this).find('.row-check').is(':checked')) {
            const id = $(this).find('td').eq(1).text().trim();
            if (id) ids.push(Number(id));
        }
        });
        return ids;
    }

    $('#btnBulkDelete').on('click', function(e){
        e.preventDefault();
        const ids = getCheckedIds();
        if (!ids.length) return Swal.fire('Info','Pilih minimal satu baris.','info');
        Swal.fire({
        title:'Hapus data terpilih?', html:`Total <b>${ids.length}</b> user`,
        icon:'warning', showCancelButton:true, confirmButtonText:'Ya, hapus!', confirmButtonColor:'#d33'
        }).then(res=>{
        if (!res.isConfirmed) return;
        fetch(@json(route('users.bulk-destroy')), {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
            body: JSON.stringify({ids})
        }).then(async r=>{
            if (!r.ok) { throw new Error(await r.text() || 'Gagal bulk delete.'); }
            location.reload();
        }).catch(err=> Swal.fire('Error', err.message || 'Gagal bulk delete.','error'));
        });
    });

    // Auto show add modal on validation error (server)
    @if ($errors->any() && !session('edit_open_id'))
        new bootstrap.Modal(document.getElementById('glassAddUser')).show();
    @endif

    @if (session('success'))
        Swal.fire({icon:'success',title:'Success',text:@json(session('success')),timer:1800,showConfirmButton:false});
    @endif
    @if (session('edit_success'))
        Swal.fire({icon:'success',title:'Success',text:@json(session('edit_success')),timer:1800,showConfirmButton:false});
    @endif
    });
    </script>
    @endpush
