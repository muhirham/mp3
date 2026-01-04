@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    .swal2-container{ z-index:20000 !important; }

    /* ====== NO SCROLL DESKTOP (wrap) ====== */
    #tblSuppliers{
        table-layout: fixed;
        width: 100% !important;
    }
    #tblSuppliers thead th{
        font-size: .70rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        white-space: normal;
    }
    #tblSuppliers tbody td{
        font-size: .82rem;
        white-space: normal !important;
        word-break: break-word;
        overflow-wrap: anywhere;
        vertical-align: middle;
    }
    #tblSuppliers td, #tblSuppliers th{
        padding: .55rem .75rem;
    }

    /* kecilin info + pagination */
    .dataTables_info, .dataTables_paginate{
        font-size: .82rem;
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Toolbar --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <label class="text-muted small mb-0">Show</label>
                    <select id="pageLength" class="form-select form-select-sm" style="width:90px">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                    <div class="small text-muted d-none d-md-block">Search pakai input navbar atas</div>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#glassSupplier" id="btnShowAdd">
                        <i class="bx bx-plus"></i> Add Supplier
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Add/Edit --}}
    <div class="modal fade" id="glassSupplier" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 bg-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalTitle">Add Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formSupplier" class="modal-body" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="method" value="POST">
                    <div class="row g-2">
                        <div class="col-md-12">
                            <label class="form-label">Supplier Code</label>
                            {{-- Editable & auto-generated silently on open --}}
                            <input name="supplier_code" id="supplier_code"
                                   class="form-control"
                                   value="{{ $nextSupplierCode }}" required
                                   data-default="{{ $nextSupplierCode }}" placeholder="SUP-001">
                            <small class="-50">Bisa diganti manual. Duplikat akan ditolak.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class=r">*</span></label>
                            <input name="name" id="name" class="form-control bg-transparent border-secondary" required placeholder="Enter company name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input name="address" id="address" class="form-control bg-transparent border-secondary" required placeholder="Enter address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input name="phone" id="phone" class="form-control bg-transparent border-secondary" required placeholder="Enter phone number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Note</label>
                            <input name="note" id="note" class="form-control bg-transparent border-secondary" placeholder="Enter notes">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input name="bank_name" id="bank_name" class="form-control bg-transparent border-secondary" required placeholder="Enter bank name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Account</label>
                            <input name="bank_account" id="bank_account" class="form-control bg-transparent border-secondary" required placeholder="Enter bank account">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-white" id="btnSubmit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Table (Desktop NO scroll, Mobile boleh scroll) --}}
    <div class="card">
        <div class="table-responsive-md">
            <table id="tblSuppliers" class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">NO</th>
                        <th style="width:130px">SUPPLIER CODE</th>
                        <th style="width:170px">COMPANY NAME</th>
                        <th>ADDRESS</th>
                        <th style="width:130px">PHONE</th>
                        <th>NOTE</th>
                        <th style="width:140px">BANK NAME</th>
                        <th style="width:150px">BANK ACCOUNT</th>
                        <th style="width:120px" class="text-end">ACTIONS</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {
    const baseUrl     = @json(url('suppliers'));
    const nextCodeUrl = @json(route('suppliers.next_code'));

    $.ajaxSetup({
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}
    });

    $.fn.dataTable.ext.errMode = 'none';

    const table = $('#tblSuppliers').DataTable({
        processing: true,
        serverSide: true,
        lengthChange: false,
        dom: 'rt<"d-flex justify-content-between align-items-center p-2"ip>', // tanpa search bawaan DT
        ajax: { url: baseUrl + '/datatable', type: 'GET' },
        order: [[1, 'asc']],
        columns: [
            { data: 'rownum', orderable: false, searchable: false },
            { data: 'supplier_code' },
            { data: 'name' },
            { data: 'address' },
            { data: 'phone' },
            { data: 'note' },
            { data: 'bank_name' },
            { data: 'bank_account' },
            { data: 'actions', orderable: false, searchable: false, className:'text-end' }
        ]
    });

    $('#tblSuppliers').on('error.dt', function(){
        Swal.fire({ icon:'error', title:'Server error', text:'Cek storage/logs/laravel.log' });
    });

    // Page length
    $('#pageLength').on('change', function(){
        table.page.len(parseInt(this.value||10,10)).draw();
    });

    // ✅ Global search navbar (CUMA 1)
    $('#globalSearch')
        .off('keyup.suppliers change.suppliers')
        .on('keyup.suppliers change.suppliers', function(){
            table.search(this.value).draw();
        });

    // Uppercase kode
    $('#supplier_code').on('input', function(){ this.value = this.value.toUpperCase(); });

    // ADD -> auto generate code silent
    $('#btnShowAdd').on('click', function () {
        $('#modalTitle').text('Add Supplier');
        $('#formSupplier').attr('action', baseUrl);
        $('#method').val('POST');
        $('#btnSubmit').text('Submit');
        $('#name,#address,#phone,#note,#bank_name,#bank_account').val('');

        $.get(nextCodeUrl, function(res){
            $('#supplier_code').val(res?.next_code || $('#supplier_code').data('default'));
        });
    });

    // Submit (Add/Edit)
    $('#formSupplier').on('submit', function (e) {
        if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        Swal.fire({
            icon: 'warning',
            title: 'Form belum lengkap',
            text: 'Mohon lengkapi semua field wajib'
        });
        return;
    }
        e.preventDefault();
        const form = this;
        const method = $('#method').val();
        const fd = new FormData(form);
        fd.set('_method', method);

        $.ajax({
            url: $(form).attr('action') || baseUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (res) {
                $('#glassSupplier').modal('hide');
                table.ajax.reload(null, false);
                Swal.fire({ title: res.success, icon: 'success', timer: 1200, showConfirmButton: false });
            },
            error: function (xhr) {
                let msg = 'Something went wrong!';
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat()[0] || msg;
                }
                Swal.fire({ title: 'Error!', text: msg, icon: 'error' });
            }
        });
    });

    // Edit
    $(document).on('click', '.js-edit', function () {
        const d = $(this).data();
        $('#modalTitle').text('Edit Supplier');
        $('#formSupplier').attr('action', baseUrl + '/' + d.id);
        $('#method').val('PUT');
        $('#btnSubmit').text('Update');

        $('#supplier_code').val(d.supplier_code);
        $('#name').val(d.name);
        $('#address').val(d.address);
        $('#phone').val(d.phone);
        $('#note').val(d.note);
        $('#bank_name').val(d.bank_name);
        $('#bank_account').val(d.bank_account);

        $('#glassSupplier').modal('show');
    });

    // Delete
    $(document).on('click', '.js-del', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((res) => {
            if (!res.isConfirmed) return;

            $.ajax({
                url: baseUrl + '/' + id,
                method: 'POST',
                data: { _method: 'DELETE' },
                success: function (r) {
                    table.ajax.reload(null, false);
                    Swal.fire('Deleted!', r.success, 'success');
                },
                error: function () {
                    Swal.fire('Error!', 'Could not delete supplier!', 'error');
                }
            });
        });
    });
});
</script>
@endpush
