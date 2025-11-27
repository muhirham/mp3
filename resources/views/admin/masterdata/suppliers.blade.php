@extends('layouts.home')

@section('content')
<style>
        .swal2-container{ z-index:20000 !important; }
</style>
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Toolbar --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <label class="text-muted">Show</label>
                    <select id="pageLength" class="form-select" style="width:90px">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                    <input id="searchSupplier" type="text" class="form-control" placeholder="Search supplier..." style="max-width:260px">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#glassSupplier" id="btnShowAdd">
                        <i class="bx bx-plus"></i> Add Supplier
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Add/Edit --}}
    <div class="modal fade" id="glassSupplier" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="background:rgba(17,22,28,.6);backdrop-filter:blur(14px)">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="modalTitle">Add Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="formSupplier" class="modal-body text-white" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="method" value="POST">
                    <div class="row g-2">
                        <div class="col-md-12">
                            <label class="form-label text-white">Supplier Code</label>
                            {{-- Editable & auto-generated silently on open --}}
                            <input name="supplier_code" id="supplier_code"
                                   class="form-control bg-transparent text-white border-secondary"
                                   value="{{ $nextSupplierCode }}" required
                                   data-default="{{ $nextSupplierCode }}" placeholder="SUP-001">
                            <small class="text-white-50">Bisa diganti manual. Duplikat akan ditolak.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white">Company Name <span class="text-danger">*</span></label>
                            <input name="name" id="name" class="form-control bg-transparent text-white border-secondary" required placeholder="Enter company name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Address</label>
                            <input name="address" id="address" class="form-control bg-transparent text-white border-secondary" placeholder="Enter address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Phone Number</label>
                            <input name="phone" id="phone" class="form-control bg-transparent text-white border-secondary" placeholder="Enter phone number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Note</label>
                            <input name="note" id="note" class="form-control bg-transparent text-white border-secondary" placeholder="Enter notes">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Bank Name</label>
                            <input name="bank_name" id="bank_name" class="form-control bg-transparent text-white border-secondary" placeholder="Enter bank name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Bank Account</label>
                            <input name="bank_account" id="bank_account" class="form-control bg-transparent text-white border-secondary" placeholder="Enter bank account">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-light text-dark" id="btnSubmit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="table-responsive">
            <table id="tblSuppliers" class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>SUPPLIER CODE</th>
                        <th>COMPANY NAME</th>
                        <th>ADDRESS</th>
                        <th>PHONE</th>
                        <th>NOTE</th>
                        <th>BANK NAME</th>
                        <th>BANK ACCOUNT</th>
                        <th style="width:120px">ACTIONS</th>
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

    const table = $('#tblSuppliers').DataTable({
        processing: true,
        serverSide: true,
        lengthChange: false,         // hide built-in page length (kita pakai custom)
        dom: 'rt<"d-flex justify-content-between align-items-center p-2"ip>', // HILANGKAN filter 'f'
        ajax: { url: baseUrl + '/datatable', type: 'GET' },
        order: [[1, 'asc']], // by supplier_code
        columns: [
            { data: 'rownum', orderable: false, searchable: false }, // NO
            { data: 'supplier_code' },
            { data: 'name' },
            { data: 'address' },
            { data: 'phone' },
            { data: 'note' },
            { data: 'bank_name' },
            { data: 'bank_account' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });

    // Custom search & page length
    $('#searchSupplier').on('keyup change', function(){ table.search(this.value).draw(); });
    $('#pageLength').on('change', function(){ table.page.len(parseInt(this.value||10,10)).draw(); });

    // Uppercase otomatis saat ngetik kode
    $('#supplier_code').on('input', function(){ this.value = this.value.toUpperCase(); });

    // Mode ADD -> auto-generate code silent (tanpa tombol)
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
        e.preventDefault();
        const form = this;
        const method = $('#method').val(); // POST / PUT
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
                    const first = Object.values(xhr.responseJSON.errors)[0];
                    if (first && first.length) msg = first[0];
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
            if (res.isConfirmed) {
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
            }
        });
    });
});
</script>
@endpush
