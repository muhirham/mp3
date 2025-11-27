    @extends('layouts.home')

    @section('title','Categories')

    @section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
        <h4 class="mb-1 fw-bold">Categories</h4>
        <small class="text-muted">Manage your product categories here.</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
        <i class="bx bx-plus"></i> Add Category
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body pb-0">
        <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
            <input id="dt-search" class="form-control" placeholder="Type to search..." style="max-width:260px">
            <select id="dt-length" class="form-select" style="width:120px">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            </select>
        </div>
        </div>
        <div class="table-responsive">
        <table id="dtCategories" class="table table-sm align-middle mb-0">
            <thead>
            <tr>
            <th style="width:80px">ID</th>
            <th>Code Category</th>
            <th>Category</th>
            <th>Description</th>
            <th style="width:160px">Updated</th>
            <th style="width:120px" class="text-end">Actions</th>
            </tr>
            </thead>
        </table>
        </div>
    </div>
    </div>

    {{-- =============== CREATE MODAL =============== --}}
    <div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formCreate" class="modal-content" method="POST" action="{{ route('categories.store') }}">
        @csrf
        <div class="modal-header">
            <h5 class="modal-title">Add Category</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body row g-3">
            <div class="col-6">
            <label class="form-label">Category Code</label>
            <input name="category_code" class="form-control"
                    value="{{ $nextCode }}" {{-- auto-suggest --}}
                    placeholder="Empty = auto (e.g. CAT-001)">
            <small class="text-muted">Bisa diedit. Kosongkan kalau mau auto generate.</small>
            </div>
            <div class="col-6">
            <label class="form-label">Category Name <span class="text-danger">*</span></label>
            <input name="category_name" class="form-control" required placeholder="e.g. Face Care">
            </div>
            <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control" placeholder="(optional)"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
            <button class="btn btn-primary" type="submit"><i class="bx bx-save"></i> Save</button>
        </div>
        </form>
    </div>
    </div>

    {{-- =============== EDIT MODAL =============== --}}
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formEdit" class="modal-content" method="POST" action="#">
        @csrf @method('PUT')
        <input type="hidden" id="edit_id">
        <div class="modal-header">
            <h5 class="modal-title">Edit Category</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body row g-3">
            <div class="col-6">
            <label class="form-label">Category Code <span class="text-danger">*</span></label>
            <input id="edit_category_code" name="category_code" class="form-control" required>
            </div>
            <div class="col-6">
            <label class="form-label">Category Name <span class="text-danger">*</span></label>
            <input id="edit_category_name" name="category_name" class="form-control" required>
            </div>
            <div class="col-12">
            <label class="form-label">Description</label>
            <textarea id="edit_description" name="description" rows="3" class="form-control"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
            <button class="btn btn-primary" type="submit"><i class="bx bx-save"></i> Save</button>
        </div>
        </form>
    </div>
    </div>
    @endsection

    @push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.1.5/datatables.min.css">
    <style>
    .swal2-container { z-index: 20000 !important; }
    html[data-color-scheme="dark"] .dataTable,
    html[data-color-scheme="dark"] .dataTables_wrapper .dataTables_info,
    html[data-color-scheme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: var(--text, #e5e7eb) !important;
    }
    /* Biar description nggak kelihatan ke-hidden */
    #dtCategories td:nth-child(4){
        white-space: normal;
    }
    </style>
    @endpush

    @push('scripts')
    <script src="https://cdn.datatables.net/v/bs5/dt-2.1.5/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    const DT = $('#dtCategories').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: "{{ route('categories.datatable') }}", type: 'GET' },
        order: [[0,'asc']],
        columns: [
        { data: 'id',            name: 'id',            width: 80,  className:'align-middle' },
        { data: 'category_code', name: 'category_code',           className:'align-middle fw-semibold' },
        { data: 'category_name', name: 'category_name',           className:'align-middle' },
        { data: 'description',   name: 'description',             className:'align-middle' },
        { data: 'updated_at',    name: 'updated_at',   width: 160, className:'align-middle' },
        { data: 'actions',       orderable:false, searchable:false, width:120, className:'text-end align-middle' },
        ],
        responsive: true,
        stateSave: true,
        searchDelay: 250,
        lengthMenu: [[10,25,50],[10,25,50]],
        dom: 't<"d-flex justify-content-between align-items-center p-2"ip>',
    });

    // external search & length
    document.getElementById('dt-search').addEventListener('input', e => DT.search(e.target.value).draw());
    document.getElementById('dt-length').addEventListener('change', e => DT.page.len(+e.target.value).draw());

    const toast = (msg, icon='success') =>
        Swal.fire({icon, title: msg, timer: 1400, showConfirmButton:false});

    // CREATE
    const modalCreate = document.getElementById('modalCreate');
    const formCreate  = document.getElementById('formCreate');

    formCreate.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formCreate.querySelector('[type="submit"]') || formCreate.querySelector('button');
        const codeInput = formCreate.querySelector('input[name="category_code"]');
        const oldCode   = codeInput ? (codeInput.value || '').trim() : '';

        if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        }
        try {
        const res = await fetch(formCreate.action, {
            method:'POST',
            headers:{'X-CSRF-TOKEN': CSRF},
            body:new FormData(formCreate)
        });
        if (res.status === 422) {
            const j = await res.json();
            throw new Error(Object.values(j.errors||{}).flat().join('<br>'));
        }
        await res.json().catch(()=> ({}));

        // tutup modal
        (bootstrap.Modal.getInstance(modalCreate) || new bootstrap.Modal(modalCreate)).hide();

        // hitung suggestion code berikutnya, based on kode terakhir yg dipakai user
        if (codeInput) {
            let next = oldCode;
            const m = oldCode.match(/^(.*?)(\d+)$/);
            if (m) {
            const prefix = m[1];
            const numStr = m[2];
            const nextNum = parseInt(numStr,10) + 1;
            next = prefix + String(nextNum).padStart(numStr.length,'0');
            }
            // kalau oldCode kosong, biarin kosong -> backend generate lagi
            codeInput.value = next;
            codeInput.setAttribute('value', next);
        }

        // kosongkan field lain
        const nameInput = formCreate.querySelector('input[name="category_name"]');
        const descInput = formCreate.querySelector('textarea[name="description"]');
        if (nameInput) nameInput.value = '';
        if (descInput) descInput.value = '';

        DT.ajax.reload(null,false);
        toast('Category created');
        } catch (err) {
        Swal.fire({icon:'error', title:'Error', html: err.message || 'Failed'});
        } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-save"></i> Save';
        }
        }
    });

    // EDIT
    window.openEdit = function(id, code, name, desc){
        const m = new bootstrap.Modal(document.getElementById('modalEdit'));
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_category_code').value = code;
        document.getElementById('edit_category_name').value = name;
        document.getElementById('edit_description').value  = desc || '';
        document.getElementById('formEdit').action =
        "{{ route('categories.update', ':id') }}".replace(':id', id);
        m.show();
    };

    const modalEdit = document.getElementById('modalEdit');
    const formEdit  = document.getElementById('formEdit');

    formEdit.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formEdit.querySelector('[type="submit"]') || formEdit.querySelector('button');
        if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        }
        try {
        const res = await fetch(formEdit.action, {
            method:'POST',
            headers:{
            'X-CSRF-TOKEN': CSRF,
            'X-HTTP-Method-Override':'PUT'
            },
            body: new FormData(formEdit)
        });
        if (res.status === 422) {
            const j = await res.json();
            throw new Error(Object.values(j.errors||{}).flat().join('<br>'));
        }
        await res.json().catch(()=> ({}));
        (bootstrap.Modal.getInstance(modalEdit) || new bootstrap.Modal(modalEdit)).hide();
        DT.ajax.reload(null,false);
        toast('Category updated');
        } catch (err) {
        Swal.fire({icon:'error', title:'Error', html: err.message || 'Failed'});
        } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-save"></i> Save';
        }
        }
    });

    // DELETE
    window.delCategory = async function(id){
        const ask = await Swal.fire({
        icon:'warning',
        title:'Yakin hapus?',
        text:'Tindakan ini tidak bisa dibatalkan.',
        showCancelButton:true,
        confirmButtonText:'Ya, hapus',
        cancelButtonText:'Batal'
        });
        if (!ask.isConfirmed) return;

        await fetch("{{ route('categories.destroy', ':id') }}".replace(':id', id), {
        method:'POST',
        headers:{
            'X-CSRF-TOKEN': CSRF,
            'X-HTTP-Method-Override':'DELETE'
        }
        });
        DT.ajax.reload(null,false);
        toast('Category deleted');
    };
    })();
    </script>
    @endpush
