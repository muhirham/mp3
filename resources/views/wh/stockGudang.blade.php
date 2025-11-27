@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
  $me = $me ?? auth()->user();
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Header & toolbar --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">

        {{-- Search global --}}
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Pencarian</label>
          <input id="searchBox" type="text" class="form-control"
                 placeholder="Cari produk/kode/kategori/supplier…">
        </div>

        {{-- page length --}}
        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Show</label>
          <select id="pageLength" class="form-select">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
        </div>

        {{-- Warehouse filter --}}
        <div class="col-12 col-md-4 ms-auto">
          <label class="form-label mb-1">Warehouse</label>

          @if(!empty($canSwitchWarehouse) && $canSwitchWarehouse)
            {{-- Superadmin / Admin pusat: bebas pilih gudang --}}
            <select id="filterWarehouse" class="form-select">
              <option value="">— Semua —</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}"
                  @selected(($selectedWarehouseId ?? null) == $w->id)>
                  {{ $w->warehouse_name }}
                </option>
              @endforeach
            </select>
          @else
            {{-- Admin WH / user lain: hanya lihat gudang miliknya --}}
            <input class="form-control"
                   value="{{ $me->warehouse?->warehouse_name ?? '-' }}"
                   disabled>
          @endif
        </div>

      </div>
    </div>
  </div>

  {{-- Table --}}
  <div class="card">
    <div class="table-responsive">
      <table id="tblStock" class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>NO</th>
            <th>CODE</th>
            <th>PRODUCT</th>
            <th>UNIT</th>
            <th>CATEGORY</th>
            <th>SUPPLIER</th>
            <th class="text-end">STOCK</th>
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
  /* Biar nggak perlu scroll horizontal */
  #tblStock td, #tblStock th { white-space: nowrap; }
  @media (max-width: 992px){
    #tblStock td, #tblStock th { white-space: normal; }
  }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function () {
  const dtUrl            = @json(route('stocklevel.datatable'));
  const CAN_SWITCH_WH    = @json($canSwitchWarehouse ?? false);
  const USER_WAREHOUSE_ID= @json($me->warehouse_id ?? null);

  const table = $('#tblStock').DataTable({
    processing: true,
    serverSide: true,
    lengthChange: false,
    ajax: {
      url: dtUrl,
      type: 'GET',
      data: function(d){
        if (CAN_SWITCH_WH) {
          // superadmin/admin pusat: ambil dari dropdown
          d.warehouse_id = $('#filterWarehouse').val() || '';
        } else {
          // admin WH: pakai warehouse dia sendiri
          d.warehouse_id = USER_WAREHOUSE_ID || '';
        }
      }
    },
    order: [[1, 'asc']], // by CODE
    columns: [
      { data: 'rownum', orderable:false, searchable:false },
      { data: 'product_code' },
      { data: 'product_name' },
      { data: 'package_name' },
      { data: 'category_name' },
      { data: 'supplier_name' },
      { data: 'quantity', className:'text-end' }
    ]
  });

  if (CAN_SWITCH_WH) {
    $('#filterWarehouse').on('change', function(){
      table.ajax.reload();
    });
  }

  $('#searchBox').on('keyup change', function(){
    table.search(this.value).draw();
  });

  $('#pageLength').on('change', function(){
    table.page.len(parseInt(this.value || 10,10)).draw();
  });
});
</script>
@endpush
