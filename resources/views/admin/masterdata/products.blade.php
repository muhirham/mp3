@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css"/>

<style>
  .swal2-container { z-index: 20000 !important; }

  #tblProducts{ width:100% !important; font-size:0.875rem; }
  #tblProducts th{ white-space:nowrap; }
  #tblProducts td{ white-space:normal; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- HEADER + BUTTON --}}
  <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
    <div>
      <h4 class="mb-1">Products</h4>
      <p class="mb-0 text-muted">Kelola daftar produk, stok pusat, kategori, dan supplier.</p>
    </div>
    <div class="ms-auto">
      <button class="btn btn-primary d-flex align-items-center gap-1"
              data-bs-toggle="modal"
              data-bs-target="#mdlProduct"
              id="btnShowAdd">
        <i class="bx bx-plus"></i>
        <span>Add Product</span>
      </button>
    </div>
  </div>

  {{-- FILTER BAR + SUMMARY --}}
  <div class="row g-3 mb-3">
    {{-- Filter utama --}}
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-body">
          <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
              <label class="form-label mb-1 text-muted small">Show</label>
              <select id="pageLength" class="form-select form-select-sm">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
            </div>

            <div class="col-6 col-md-3">
              <label class="form-label mb-1 text-muted small">Category</label>
              <select id="filterCategory" class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach($categories as $c)
                  <option value="{{ $c->category_name }}">{{ $c->category_name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-6 col-md-3">
              <label class="form-label mb-1 text-muted small">Supplier</label>
              <select id="filterSupplier" class="form-select form-select-sm">
                <option value="">All suppliers</option>
                @foreach($suppliers as $s)
                  <option value="{{ $s->name }}">{{ $s->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label mb-1 text-muted small">Search</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input id="searchProduct" type="text" class="form-control" placeholder="Search code / name...">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- SUMMARY KECIL DI KANAN --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h6 class="mb-3">Inventory summary</h6>

          <div class="d-flex align-items-center mb-2">
            <div class="avatar avatar-sm flex-shrink-0 me-2">
              <span class="avatar-initial rounded-circle bg-label-primary">
                <i class="bx bx-package"></i>
              </span>
            </div>
            <div>
              <div class="fw-semibold">{{ $productCount ?? 0 }}</div>
              <div class="text-muted small">Total products</div>
            </div>
          </div>

          <div class="d-flex align-items-center mb-2">
            <div class="avatar avatar-sm flex-shrink-0 me-2">
              <span class="avatar-initial rounded-circle bg-label-success">
                <i class="bx bx-category"></i>
              </span>
            </div>
            <div>
              <div class="fw-semibold">{{ $categoryCount ?? 0 }}</div>
              <div class="text-muted small">Product categories</div>
            </div>
          </div>

          <div class="d-flex align-items-center">
            <div class="avatar avatar-sm flex-shrink-0 me-2">
              <span class="avatar-initial rounded-circle bg-label-info">
                <i class="bx bx-user"></i>
              </span>
            </div>
            <div>
              <div class="fw-semibold">{{ $supplierCount ?? 0 }}</div>
              <div class="text-muted small">Active suppliers</div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  {{-- TABEL PRODUK --}}
  <div class="card">
    <div class="card-body p-0">
      <table id="tblProducts" class="table table-hover align-middle mb-0 table-bordered">
        <thead class="table-light">
        <tr>
          <th style="width: 60px">NO</th>
          <th>CODE</th>
          <th>PRODUCT NAME</th>
          <th>CATEGORY</th>
          <th>UOM</th>
          <th>SUPPLIER</th>
          <th>DESCRIPTION</th>
          <th class="text-end">STOCK</th>
          <th class="text-end">MIN STOCK</th>
          <th>STATUS</th>
          <th class="text-end">PURCHASING</th>
          <th class="text-end">SELLING</th>
          <th style="width: 110px">ACTIONS</th>
        </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>

{{-- MODAL ADD / EDIT PRODUCT --}}
<div class="modal fade" id="mdlProduct" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-semibold" id="modalTitle">Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formProduct" class="modal-body">
        @csrf
        <input type="hidden" name="_method" id="method" value="POST">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Product Code <span class="text-danger">*</span></label>
            <input type="text" name="product_code" id="product_code" class="form-control"
                   value="{{ $nextProductCode }}" data-default="{{ $nextProductCode }}" required>
            <small class="text-muted">Bisa diubah, tidak boleh duplikat.</small>
          </div>

          <div class="col-md-8">
            <label class="form-label">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Category <span class="text-danger">*</span></label>
            <select name="category_id" id="category_id" class="form-select" required>
              <option value="">— Choose —</option>
              @foreach($categories as $c)
                <option value="{{ $c->id }}">{{ $c->category_name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Package / Satuan</label>
            <select name="package_id" id="package_id" class="form-select">
              <option value="">— None —</option>
              @foreach($packages as $p)
                <option value="{{ $p->id }}">{{ $p->package_name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" id="supplier_id" class="form-select">
              <option value="">— None —</option>
              @foreach($suppliers as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-12">
            <label class="form-label">Description</label>
            <textarea name="description" id="description" rows="3" class="form-control"
                      placeholder="Optional"></textarea>
          </div>

          <div class="col-md-4">
            <label class="form-label">Purchasing Price</label>
            <input type="number" name="purchasing_price" id="purchasing_price"
                   class="form-control" value="0" min="0">
          </div>

          <div class="col-md-4">
            <label class="form-label">Selling Price</label>
            <input type="number" name="selling_price" id="selling_price"
                   class="form-control" value="0" min="0">
            <small class="text-muted small d-none" id="priceEditNote">
              Harga beli &amp; harga jual tidak bisa diubah di sini.
              Gunakan menu <strong>Adjustment</strong> untuk mengubah harga.
            </small>
          </div>

          <div class="col-md-4">
            <label class="form-label">Min Stock</label>
            <input type="number" name="stock_minimum" id="stock_minimum"
                   class="form-control" min="0">
          </div>
        </div>

        <div class="mt-4 d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmit">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {
  const baseUrl     = @json(url('products'));
  const dtUrl       = @json(route('products.datatable'));
  const nextCodeUrl = @json(route('products.next_code'));
  const csrf        = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  $.ajaxSetup({ headers: {'X-CSRF-TOKEN': csrf} });

  const table = $('#tblProducts').DataTable({
    processing: true,
    serverSide: true,
    lengthChange: false,
    dom: 'rt<"d-flex justify-content-between align-items-center p-2"ip>',
    ajax: { url: dtUrl, type: 'GET' },
    order: [[1, 'asc']],
    pagingType: "simple_numbers",
    responsive: {
      details: {
        type: 'inline'
      }
    },
    columnDefs: [
      { responsivePriority: 1, targets: [1,2] },
      { responsivePriority: 2, targets: -1 }
    ],
    columns: [
      { data: 'rownum',           orderable:false, searchable:false },
      { data: 'product_code' },
      { data: 'name' },
      { data: 'category' },
      { data: 'package' },
      { data: 'supplier' },
      { data: 'description' },
      { data: 'stock',            className:'text-end' },
      { data: 'min_stock',        className:'text-end' },
      { data: 'status',           orderable:false, searchable:false },
      { data: 'purchasing_price', className:'text-end' },
      { data: 'selling_price',    className:'text-end' },
      { data: 'actions',          orderable:false, searchable:false }
    ]
  });

  $('#searchProduct').on('keyup change', function () {
    table.search(this.value).draw();
  });

  $('#pageLength').on('change', function () {
    table.page.len(parseInt(this.value || 10, 10)).draw();
  });

  $('#filterCategory').on('change', function () {
    table.column(3).search(this.value).draw();
  });

  $('#filterSupplier').on('change', function () {
    table.column(5).search(this.value).draw();
  });

  $('#product_code').on('input', function () {
    this.value = this.value.toUpperCase();
  });

  // ==== MODE ADD PRODUCT ====
  $('#btnShowAdd').on('click', function () {
    $('#modalTitle').text('Add Product');
    $('#formProduct').attr('action', baseUrl);
    $('#method').val('POST');
    $('#btnSubmit').text('Submit');
    $('#formProduct').trigger('reset');
    $('#category_id, #package_id, #supplier_id').val('');

    $('#purchasing_price, #selling_price').prop('readonly', false);
    $('#priceEditNote').addClass('d-none');

    $.get(nextCodeUrl, function (res) {
      $('#product_code').val(res?.next_code || $('#product_code').data('default'));
    });
  });

  // SUBMIT FORM (add / update)
  $('#formProduct').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.set('_method', $('#method').val());

    $.ajax({
      url: $(this).attr('action') || baseUrl,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      success: function (res) {
        $('#mdlProduct').modal('hide');
        table.ajax.reload(null, false);
        Swal.fire({
          title: res.success || 'Saved',
          icon: 'success',
          timer: 1300,
          showConfirmButton: false
        });
      },
      error: function (xhr) {
        let msg = 'Something went wrong!';
        if (xhr.status === 422 && xhr.responseJSON?.errors) {
          msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
        } else if (xhr.responseJSON?.error) {
          msg = xhr.responseJSON.error;
        }
        Swal.fire({ title: 'Error!', html: msg, icon:'error' });
      }
    });
  });

  // ==== MODE EDIT PRODUCT ====
  $(document).on('click', '.js-edit', function () {
    const d = $(this).data();

    $('#modalTitle').text('Edit Product');
    $('#formProduct').attr('action', baseUrl + '/' + d.id);
    $('#method').val('PUT');
    $('#btnSubmit').text('Update');

    $('#product_code').val(d.product_code);
    $('#name').val(d.name);
    $('#category_id').val(d.category_id || '');
    $('#package_id').val(d.package_id || '');
    $('#supplier_id').val(d.supplier_id || '');
    $('#description').val(d.description || '');
    $('#purchasing_price').val(d.purchasing_price);
    $('#selling_price').val(d.selling_price);
    $('#stock_minimum').val(d.stock_minimum || '');

    $('#purchasing_price, #selling_price').prop('readonly', true);
    $('#priceEditNote').removeClass('d-none');

    Swal.fire({
      icon: 'info',
      title: 'Harga tidak bisa diubah',
      text: 'Harga beli dan harga jual tidak dapat di-edit dari sini. Silakan gunakan menu Adjustment.',
      timer: 2500,
      showConfirmButton: false
    });

    $('#mdlProduct').modal('show');
  });

  // DELETE PRODUCT
  $(document).on('click', '.js-del', function () {
    const id = $(this).data('id');

    Swal.fire({
      title: 'Delete product?',
      text: 'Tindakan ini tidak bisa dibatalkan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel'
    }).then((res) => {
      if (!res.isConfirmed) return;

      $.post(baseUrl + '/' + id, { _method:'DELETE' }, function (r) {
        table.ajax.reload(null, false);
        Swal.fire('Deleted!', r.success || 'Product deleted.', 'success');
      }).fail(function () {
        Swal.fire('Error!', 'Could not delete product!', 'error');
      });
    });
  });
});
</script>
@endpush
