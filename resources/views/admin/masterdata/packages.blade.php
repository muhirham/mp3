@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
        .swal2-container { z-index: 20000 !important; }
</style>
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="card mb-3">
    <div class="card-body d-flex gap-2 align-items-center">
      <div class="d-flex align-items-center gap-2">
        <label class="text-muted">Show</label>
        <select id="pageLength" class="form-select" style="width:90px">
          <option value="10" selected>10</option><option value="25">25</option><option value="50">50</option>
        </select>
      </div>
      <div class="ms-auto d-flex gap-2">
        <input id="searchBox" class="form-control" placeholder="Cari satuan..." style="max-width:260px">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlPkg" id="btnShowAdd">
          <i class="bx bx-plus"></i> Tambah Satuan
        </button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table id="tblPackages" class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>NO</th>
            <th>SATUAN</th>
            <th style="width:120px">ACTIONS</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="mdlPkg" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="modalTitle">Tambah Satuan</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form id="formPkg" class="modal-body">
      @csrf
      <input type="hidden" id="method" value="POST">
      <div class="mb-2">
        <label class="form-label">Nama Satuan <span class="text-danger">*</span></label>
        <input class="form-control" name="package_name" id="package_name" required>
      </div>
      <div class="d-flex gap-2 justify-content-end mt-3">
        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary" type="submit" id="btnSubmit">Simpan</button>
      </div>
    </form>
  </div></div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
  const baseUrl = @json(url('packages'));
  const dtUrl   = @json(route('packages.datatable'));
  const csrf    = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  $.ajaxSetup({ headers: {'X-CSRF-TOKEN': csrf} });

  const table = $('#tblPackages').DataTable({
    processing:true, serverSide:true, lengthChange:false,
    dom:'rt<"d-flex justify-content-between align-items-center p-2"ip>',
    ajax:{url:dtUrl,type:'GET'},
    order:[[1,'asc']],
    columns:[
      {data:'rownum', orderable:false, searchable:false},
      {data:'name'},
      {data:'actions', orderable:false, searchable:false},
    ]
  });

  $('#searchBox').on('keyup change', function(){ table.search(this.value).draw(); });
  $('#pageLength').on('change', function(){ table.page.len(parseInt(this.value||10,10)).draw(); });

  $('#btnShowAdd').on('click', function(){
    $('#modalTitle').text('Tambah Satuan');
    $('#formPkg').attr('action', baseUrl);
    $('#method').val('POST'); $('#btnSubmit').text('Simpan');
    $('#formPkg').trigger('reset');
  });

  $('#formPkg').on('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this); fd.set('_method', $('#method').val());
    $.ajax({
      url: $(this).attr('action') || baseUrl, method:'POST',
      data: fd, processData:false, contentType:false,
      success: res => { $('#mdlPkg').modal('hide'); table.ajax.reload(null,false); Swal.fire({title:res.success,icon:'success',timer:1200,showConfirmButton:false}); },
      error: xhr => {
        let msg = 'Error';
        if (xhr.status===422 && xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
        else if (xhr.responseJSON?.error) msg = xhr.responseJSON.error;
        Swal.fire({title:'Error', html:msg, icon:'error'});
      }
    });
  });

  $(document).on('click','.js-edit', function(){
    const d = $(this).data();
    $('#modalTitle').text('Edit Satuan');
    $('#formPkg').attr('action', baseUrl+'/'+d.id);
    $('#method').val('PUT'); $('#btnSubmit').text('Update');
    $('#package_name').val(d.name);
    $('#mdlPkg').modal('show');
  });

  $(document).on('click','.js-del', function(){
    const id = $(this).data('id');
    Swal.fire({title:'Hapus satuan?',icon:'warning',showCancelButton:true}).then(r=>{
      if(!r.isConfirmed) return;
      $.post(baseUrl+'/'+id, {_method:'DELETE'}, function(res){
        table.ajax.reload(null,false);
        Swal.fire('Deleted!', res.success, 'success');
      }).fail(()=> Swal.fire('Error','Cannot delete','error'));
    });
  });
});
</script>
@endpush
