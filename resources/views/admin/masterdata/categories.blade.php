@extends('layouts.home')

@section('title','Categories')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="mb-1 fw-bold">Categories</h5>
      <small class="text-muted">Manage your product categories here.</small>
    </div>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
      <i class="bx bx-plus"></i> Add Category
    </button>
  </div>

  {{-- FILTER BAR (show doang, search di navbar) --}}
  <div class="card mb-3">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label mb-1 small text-uppercase fw-semibold text-muted">Show</label>
          <select id="pageLength" class="form-select form-select-sm">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
        </div>

        <div class="col-12 col-md-auto ms-md-auto d-flex justify-content-md-end">
          <div class="small text-muted d-none d-md-flex align-items-center">
            Search pakai input navbar atas
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- TABLE (NO SCROLL DESKTOP, SCROLL CUMA < md) --}}
  <div class="card">
    <div class="table-responsive-md">
      <table id="dtCategories" class="table table-sm table-hover align-middle mb-0 cat-table w-100">
        <thead class="table-light">
          <tr>
            <th style="width:6%;">ID</th>
            <th style="width:16%;">Code Category</th>
            <th style="width:18%;">Category</th>
            <th style="width:36%;">Description</th>
            <th style="width:14%;">Updated</th>
            <th style="width:10%;" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>

{{-- =============== CREATE MODAL =============== --}}
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formCreate" class="modal-content" method="POST" action="{{ route('categories.store') }}">
      @csrf
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold mb-0">Add Category</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body row g-3">
        <div class="col-6">
          <label class="form-label small text-uppercase fw-semibold text-muted">Category Code</label>
          <input name="category_code" class="form-control form-control-sm"
                 value="{{ $nextCode }}"
                 placeholder="Empty = auto (e.g. CAT-001)">
          <small class="text-muted">Bisa diedit. Kosongkan kalau mau auto generate.</small>
        </div>

        <div class="col-6">
          <label class="form-label small text-uppercase fw-semibold text-muted">
            Category Name <span class="text-danger">*</span>
          </label>
          <input name="category_name" class="form-control form-control-sm" required placeholder="e.g. Face Care">
        </div>

        <div class="col-12">
          <label class="form-label small text-uppercase fw-semibold text-muted">Description</label>
          <textarea name="description" rows="3" class="form-control form-control-sm" placeholder="(optional)"></textarea>
        </div>
      </div>

      <div class="modal-footer py-2">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-save"></i> Save</button>
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

      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold mb-0">Edit Category</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body row g-3">
        <div class="col-6">
          <label class="form-label small text-uppercase fw-semibold text-muted">
            Category Code <span class="text-danger">*</span>
          </label>
          <input id="edit_category_code" name="category_code" class="form-control form-control-sm" required>
        </div>

        <div class="col-6">
          <label class="form-label small text-uppercase fw-semibold text-muted">
            Category Name <span class="text-danger">*</span>
          </label>
          <input id="edit_category_name" name="category_name" class="form-control form-control-sm" required>
        </div>

        <div class="col-12">
          <label class="form-label small text-uppercase fw-semibold text-muted">Description</label>
          <textarea id="edit_description" name="description" rows="3" class="form-control form-control-sm"></textarea>
        </div>
      </div>

      <div class="modal-footer py-2">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
  .swal2-container { z-index: 20000 !important; }

  /* ✅ NO HORIZONTAL SCROLL DESKTOP: table fixed + wrap */
  .cat-table{
    table-layout: fixed;
  }
  .cat-table thead th{
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    white-space: normal;
  }
  .cat-table tbody td{
    font-size:.82rem;
    white-space: normal;
    word-break: break-word;
    overflow-wrap: anywhere;
  }
  .cat-table td, .cat-table th{
    padding:.55rem .75rem;
    vertical-align: middle;
  }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(() => {
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;

  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF } });
  $.fn.dataTable.ext.errMode = 'none';

  const DT = $('#dtCategories').DataTable({
    processing: true,
    serverSide: true,
    lengthChange: false,

    // ✅ search bawaan DT hilang (search pake navbar)
    dom: 'rt<"row mt-2"<"col-12 col-md-5"i><"col-12 col-md-7"p>>',

    ajax: { url: "{{ route('categories.datatable') }}", type: 'GET' },
    order: [[0,'asc']],
    columns: [
      { data: 'id',            name: 'id',            className:'align-middle' },
      { data: 'category_code', name: 'category_code', className:'align-middle fw-semibold' },
      { data: 'category_name', name: 'category_name', className:'align-middle' },
      { data: 'description',   name: 'description',   className:'align-middle' },
      { data: 'updated_at',    name: 'updated_at',    className:'align-middle' },
      { data: 'actions',       orderable:false, searchable:false, className:'text-end align-middle' },
    ],
  });

  $('#dtCategories').on('error.dt', function(){
    Swal.fire({ icon:'error', title:'Server error', text:'Cek storage/logs/laravel.log' });
  });

  // ✅ page length
  document.getElementById('pageLength').addEventListener('change', (e) => {
    DT.page.len(+e.target.value).draw();
  });

  // ✅ global search navbar (CUMA 1)
  $('#globalSearch')
    .off('keyup.categories change.categories')
    .on('keyup.categories change.categories', function(){
      DT.search(this.value).draw();
    });

  const toast = (msg, icon='success') =>
    Swal.fire({icon, title: msg, timer: 1400, showConfirmButton:false});

  // CREATE
  const modalCreate = document.getElementById('modalCreate');
  const formCreate  = document.getElementById('formCreate');

  formCreate.addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = formCreate.querySelector('[type="submit"]');
    const codeInput = formCreate.querySelector('input[name="category_code"]');
    const oldCode   = codeInput ? (codeInput.value || '').trim() : '';

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    }

    try {
      const res = await fetch(formCreate.action, {
        method:'POST',
        body:new FormData(formCreate)
      });

      if (res.status === 422) {
        const j = await res.json();
        throw new Error(Object.values(j.errors||{}).flat().join('<br>'));
      }

      await res.json().catch(()=> ({}));
      (bootstrap.Modal.getInstance(modalCreate) || new bootstrap.Modal(modalCreate)).hide();

      // suggestion next code (kalau user isi manual)
      if (codeInput) {
        let next = oldCode;
        const m = oldCode.match(/^(.*?)(\d+)$/);
        if (m) {
          const prefix = m[1];
          const numStr = m[2];
          next = prefix + String(parseInt(numStr,10) + 1).padStart(numStr.length,'0');
        }
        codeInput.value = next;
        codeInput.setAttribute('value', next);
      }

      // reset field lain
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

  // EDIT (dipanggil dari tombol actions controller)
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

    const btn = formEdit.querySelector('[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    }

    try {
      const res = await fetch(formEdit.action, {
        method:'POST',
        headers:{ 'X-HTTP-Method-Override':'PUT' },
        body:new FormData(formEdit)
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
      headers:{ 'X-HTTP-Method-Override':'DELETE' }
    });

    DT.ajax.reload(null,false);
    toast('Category deleted');
  };
})();
</script>
@endpush
