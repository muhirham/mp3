@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- HEADER --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Stock Product (All Item)</h4>
    <div class="text-muted small">
      <span class="badge bg-danger me-1">&nbsp;</span> = stok ≤ minimal
    </div>
  </div>

  {{-- FILTER BAR --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">

        {{-- SHOW --}}
        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Show</label>
          <select id="pageLength" class="form-select">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
        </div>

        {{-- SEARCH BOX --}}
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Pencarian</label>
          <input id="searchBox"
                 type="text"
                 class="form-control"
                 placeholder="Cari kode/nama product, kategori, supplier...">
        </div>

      </div>
    </div>
  </div>

  {{-- TABEL STOCK PRODUCT --}}
  <div class="card">
    <div class="table-responsive">
      <table id="tblProducts" class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:60px">NO</th>
            <th>CODE</th>
            <th>PRODUCT</th>
            <th>CATEGORY</th>
            <th>SUPPLIER</th>
            <th class="text-end">STOCK</th>
            <th class="text-end">MIN STOCK</th>
            <th>STATUS</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>
@endsection

@push('styles')
<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
  #tblProducts td,
  #tblProducts th {
    white-space: nowrap;
  }
  @media (max-width: 992px) {
    #tblProducts td,
    #tblProducts th {
      white-space: normal;
    }
  }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
  $(function () {
    const dtUrl = @json(route('stockproducts.datatable'));

    const table = $('#tblProducts').DataTable({
      processing: true,
      serverSide: true,
      lengthChange: false,
      ajax: {
        url: dtUrl,
        type: 'GET',
        data: function (d) {
          // kalau nanti mau tambah filter lain (kategori/supplier), tinggal set di sini
        }
      },
      order: [[1, 'asc']], // sort by CODE
      columns: [
        { data: 'rownum',        orderable: false, searchable: false },
        { data: 'product_code' },
        { data: 'product_name' },
        { data: 'category_name' },
        { data: 'supplier_name' },
        { data: 'stock',         className: 'text-end' },
        { data: 'min_stock',     className: 'text-end' },
        { data: 'status',        orderable: false, searchable: false },
      ]
    });

    // custom search box
    $('#searchBox').on('keyup change', function () {
      table.search(this.value).draw();
    });
    // page length
    $('#pageLength').on('change', function () {
      table.page.len(parseInt(this.value || 10, 10)).draw();
    });
  });
</script>
@endpush
