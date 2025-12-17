@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
  .swal2-container { z-index: 20000 !important; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- FILTER BAR (mirip PO) --}}
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

        <div class="col-12 col-md-auto ms-md-auto d-flex gap-2 justify-content-md-end">
          <div class="small text-muted d-none d-md-flex align-items-center">
            Gunakan search di navbar atas
          </div>

          <button class="btn btn-sm btn-primary"
                  data-bs-toggle="modal" data-bs-target="#mdlPkg"
                  id="btnShowAdd">
            <i class="bx bx-plus"></i> Tambah Satuan
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- TABLE --}}
  <div class="card">
    <div class="table-responsive">
      <table id="tblPackages" class="table table-sm table-hover align-middle mb-0 pkg-table">
        <thead class="table-light">
          <tr>
            <th style="width:70px" class="text-center">No</th>
            <th>Satuan</th>
            <th style="width:120px" class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>

{{-- MODAL --}}
<div class="modal fade" id="mdlPkg" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold mb-0" id="modalTitle">Tambah Satuan</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formPkg" class="modal-body">
        @csrf
        <input type="hidden" id="method" value="POST">

        <div class="mb-2">
          <label class="form-label small text-uppercase fw-semibold text-muted">
            Nama Satuan <span class="text-danger">*</span>
          </label>
          <input class="form-control form-control-sm" name="package_name" id="package_name" required>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-sm btn-primary" type="submit" id="btnSubmit">Simpan</button>
        </div>
      </form>

    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
  .pkg-table thead th{
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    white-space:nowrap;
  }
  .pkg-table tbody td{ font-size:.82rem; }
  .pkg-table td, .pkg-table th{ padding:.55rem .75rem; }
  .badge{ font-size:.70rem; }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function(){
  const baseUrl = @json(url('packages'));
  const dtUrl   = @json(route('packages.datatable'));

  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  $.ajaxSetup({ headers: {'X-CSRF-TOKEN': csrf} });

  // biar ga muncul alert DataTables default
  $.fn.dataTable.ext.errMode = 'none';

  const table = $('#tblPackages').DataTable({
    processing:true,
    serverSide:true,
    lengthChange:false,

    // ilangin search bawaan DT (pake navbar)
    dom:'rt<"row mt-2"<"col-12 col-md-5"i><"col-12 col-md-7"p>>',

    ajax:{ url:dtUrl, type:'GET' },

    order:[[1,'asc']],
    columns:[
      { data:'rownum',  orderable:false, searchable:false, className:'text-center' },
      { data:'name' },
      { data:'actions', orderable:false, searchable:false, className:'text-center' },
    ],
  });

  $('#tblPackages').on('error.dt', function(e, settings, techNote, message){
    console.error(message);
    Swal.fire({ icon:'error', title:'Server error', text:'Cek storage/logs/laravel.log' });
  });

  // page length
  $('#pageLength').on('change', function(){
    table.page.len(parseInt(this.value || 10, 10)).draw();
  });

  // ✅ SEARCH PAKAI NAVBAR (pastikan id input navbar = globalSearch)
  $('#globalSearch')
    .off('keyup.packages change.packages')
    .on('keyup.packages change.packages', function(){
      table.search(this.value).draw();
    });

  // ===== modal helper
  const mdlEl = document.getElementById('mdlPkg');
  const mdl   = new bootstrap.Modal(mdlEl);

  // ADD
  $('#btnShowAdd').on('click', function(){
    $('#modalTitle').text('Tambah Satuan');
    $('#formPkg').attr('action', baseUrl);
    $('#method').val('POST');
    $('#btnSubmit').text('Simpan');
    $('#formPkg').trigger('reset');
  });

  // SUBMIT (ADD/EDIT)
  $('#formPkg').on('submit', function(e){
    e.preventDefault();

    const fd = new FormData(this);
    fd.set('_method', $('#method').val());

    $.ajax({
      url: $(this).attr('action') || baseUrl,
      method:'POST',
      data: fd,
      processData:false,
      contentType:false,
      success: (res) => {
        bootstrap.Modal.getInstance(mdlEl)?.hide();
        table.ajax.reload(null,false);
        Swal.fire({ title: res.success || 'Berhasil', icon:'success', timer:1200, showConfirmButton:false });
      },
      error: (xhr) => {
        let msg = 'Error';
        if (xhr.status===422 && xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
        else if (xhr.responseJSON?.error) msg = xhr.responseJSON.error;
        Swal.fire({ title:'Error', html: msg, icon:'error' });
      }
    });
  });

  // EDIT
  $(document).on('click', '.js-edit', function(){
    const d = $(this).data();
    $('#modalTitle').text('Edit Satuan');
    $('#formPkg').attr('action', baseUrl + '/' + d.id);
    $('#method').val('PUT');
    $('#btnSubmit').text('Update');
    $('#package_name').val(d.name);
    mdl.show();
  });

  // DELETE
  $(document).on('click', '.js-del', function(){
    const id = $(this).data('id');

    Swal.fire({
      title:'Hapus satuan?',
      text:'Data akan dihapus permanen.',
      icon:'warning',
      showCancelButton:true,
      confirmButtonText:'Ya, hapus',
      cancelButtonText:'Batal'
    }).then(r=>{
      if(!r.isConfirmed) return;

      $.post(baseUrl + '/' + id, { _method:'DELETE' }, function(res){
        table.ajax.reload(null,false);
        Swal.fire({ title:'Deleted', text: res.success || 'Satuan dihapus.', icon:'success', timer:1200, showConfirmButton:false });
      }).fail((xhr)=>{
        const msg = xhr.responseJSON?.error || 'Cannot delete';
        Swal.fire('Error', msg, 'error');
      });
    });
  });

});
</script>
@endpush
