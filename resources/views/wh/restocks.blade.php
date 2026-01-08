@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
  $me = $me ?? auth()->user();
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- FILTER BAR --}}
  <div class="card mb-3">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">

        {{-- SHOW --}}
        <div class="col-6 col-md-2">
          <label class="form-label mb-1 small text-uppercase fw-semibold text-muted">Show</label>
          <select id="pageLength" class="form-select form-select-sm">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
        </div>

        {{-- DATE RANGE --}}
        <div class="col-12 col-md-4">
          <label class="form-label mb-1 small text-uppercase fw-semibold text-muted">Tanggal</label>
          <div class="d-flex gap-2">
            <input id="fromDate" type="date" class="form-control form-control-sm" />
            <input id="toDate" type="date" class="form-control form-control-sm" />
          </div>
        </div>

        {{-- STATUS --}}
        <div class="col-6 col-md-2">
          <label class="form-label mb-1 small text-uppercase fw-semibold text-muted">Status</label>
          <select id="filterStatus" class="form-select form-select-sm">
            <option value="">— Semua —</option>
            <option value="pending">PENDING</option>
            <option value="approved">REVIEW</option>
            <option value="ordered">ORDERED</option>
            <option value="received">RECEIVED</option>
            <option value="cancelled">CANCELLED</option>
          </select>
        </div>

        {{-- WAREHOUSE --}}
        <div class="col-12 col-md-3 ms-md-auto">
          <label class="form-label mb-1 small text-uppercase fw-semibold text-muted">Warehouse</label>

          @if($canSwitchWarehouse)
            <select id="filterWarehouse" class="form-select form-select-sm">
              <option value="">— Semua —</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}" @selected(($selectedWarehouseId ?? null) == $w->id)>
                  {{ $w->warehouse_name }}
                </option>
              @endforeach
            </select>
          @elseif($isWarehouseUser)
            <input class="form-control form-control-sm"
                   value="{{ $me->warehouse?->warehouse_name ?? '-' }}" disabled>
            <input type="hidden" id="filterWarehouse" value="{{ $me->warehouse_id }}">
          @else
            <input class="form-control form-control-sm" value="-" disabled>
            <input type="hidden" id="filterWarehouse" value="">
          @endif
        </div>

        {{-- BUTTONS --}}
        <div class="col-12 col-md-auto mt-2 mt-md-0 text-md-end d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-success" id="btnExportExcel">
            <i class="bx bx-export"></i> Export Excel
          </button>

          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mdlAdd">
            + Buat Request
          </button>
        </div>

      </div>
    </div>
  </div>

  {{-- TABLE --}}
  <div class="card">
    <div class="table-responsive">
      <table id="tblRestocks" class="table table-sm table-hover align-middle mb-0 restock-table">
        <thead class="table-light">
          <tr>
            <th style="width:60px" class="text-center">No</th>
            <th>Code</th>
            <th>Product</th>
            <th>Supplier</th>
            <th class="text-end">Req</th>
            <th class="text-end">Rcv</th>
            <th class="text-center">Status</th>
            <th class="text-center">Date</th>
            <th>Description</th>
            <th style="width:110px" class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>

{{-- ========== MODAL ADD REQUEST ========== --}}
<div class="modal fade" id="mdlAdd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold mb-0">Buat Request Restock</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formAdd" method="POST" action="{{ route('restocks.store') }}">
        @csrf
        <div class="modal-body">

          @if($canSwitchWarehouse)
            <div class="mb-3">
              <label class="form-label small text-uppercase fw-semibold text-muted">Warehouse</label>
              <select name="warehouse_id" class="form-select form-select-sm" required>
                <option value="">— Pilih —</option>
                @foreach($warehouses as $w)
                  <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="table-responsive mb-2">
            <table class="table table-sm align-middle" id="tblRfItems">
              <thead class="table-light">
                <tr>
                  <th style="width:40px" class="text-center">#</th>
                  <th>Product</th>
                  <th style="width:130px" class="text-end">Qty Request</th>
                  <th>Catatan</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody>
                <tr data-index="0">
                  <td class="row-num text-center">1</td>
                  <td>
                    <select name="items[0][product_id]" class="form-select form-select-sm" required>
                      <option value="">— Pilih —</option>
                      @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->product_code }} — {{ $p->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" min="1" name="items[0][quantity_requested]"
                           class="form-control form-control-sm text-end" value="1" required>
                  </td>
                  <td>
                    <input type="text" name="items[0][note]"
                           class="form-control form-control-sm" placeholder="Catatan (opsional)">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-remove" title="Hapus">&times;</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRfRow">
            <i class="bx bx-plus"></i> Tambah Item
          </button>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ========== MODAL RECEIVE (GR) ========== --}}
<div class="modal fade" id="mdlReceive" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0 pb-1">
        <div>
          <h6 class="modal-title fw-bold mb-0">Tanda Terima Barang Restock (GR)</h6>
          <div class="small text-muted">
            Kode Restock: <span id="rcvCode">-</span> ·
            <span id="rcvWarehouse">-</span> ·
            <span id="rcvRequester">-</span>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formReceive" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-body pt-0">

          <div class="mb-3">
            <table class="table table-sm table-bordered mb-0 restock-subtable" id="tblReceiveItems">
              <thead class="table-light">
                <tr class="text-center">
                  <th style="width:40px;">#</th>
                  <th>Product</th>
                  <th>Supplier</th>
                  <th style="width:110px;">Req</th>
                  <th style="width:140px;">Rcv (prev)</th>
                  <th style="width:110px;">Remain</th>
                  <th style="width:110px;">Good</th>
                  <th style="width:120px;">Damaged</th>
                  <th style="width:160px;">Notes</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
            <small class="text-muted">
              Qty Good + Qty Damaged per baris tidak boleh lebih besar dari <strong>Qty Remaining</strong>.
            </small>
          </div>

          <div class="mb-3">
            <label class="form-label small text-uppercase fw-semibold text-muted">Upload Foto Barang Bagus (opsional)</label>
            <input type="file" name="photos_good[]" class="form-control form-control-sm" multiple accept="image/*">
            <div class="form-text">Maks 4MB per file.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small text-uppercase fw-semibold text-muted">Upload Foto Barang Rusak (opsional)</label>
            <input type="file" name="photos_damaged[]" class="form-control form-control-sm" multiple accept="image/*">
            <div class="form-text">Jika ada kerusakan, lampirkan foto detail.</div>
          </div>

        </div>

        <div class="modal-footer border-0">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary">Simpan Goods Received</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ========== MODAL DETAIL ========== --}}
<div class="modal fade" id="mdlDetail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0 pb-1">
        <div>
          <h6 class="modal-title fw-bold mb-0">Detail Request Restock</h6>
          <div class="small text-muted">No. Dokumen: <span id="detCode">-</span></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body pt-0">

        <div class="border rounded p-3 mb-3 small">
          <div class="row">
            <div class="col-md-6">
              <div><strong>Warehouse</strong> : <span id="detWarehouse">-</span></div>
              <div><strong>Tanggal</strong> : <span id="detDate">-</span></div>
            </div>
            <div class="col-md-6">
              <div><strong>Requester</strong> : <span id="detRequester">-</span></div>
              <div><strong>Status</strong> : <span id="detStatus">-</span></div>
              <div><strong>Jumlah Item</strong> : <span id="detTotalItems">0</span></div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle restock-subtable" id="tblDetailItems">
            <thead class="table-light">
              <tr class="text-center">
                <th style="width:40px;">No.</th>
                <th>Product</th>
                <th>Supplier</th>
                <th style="width:110px;">Req</th>
                <th style="width:110px;">Rcv</th>
                <th style="width:110px;">Remain</th>
                <th style="width:160px;">Note</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

      </div>

      <div class="modal-footer border-0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
  .restock-table thead th{
    font-size:.72rem; text-transform:uppercase; letter-spacing:.04em;
    white-space:nowrap;
  }
  .restock-table tbody td{ font-size:.82rem; }
  .restock-table td, .restock-table th{ padding:.55rem .75rem; }

  .restock-subtable thead th{ font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; }
  .restock-subtable tbody td{ font-size:.82rem; }

  .badge{ font-size:.70rem; }

  #tblRestocks td, #tblRestocks th { white-space: nowrap; }
  @media (max-width: 992px){
    #tblRestocks td, #tblRestocks th { white-space: normal; }
  }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {
  const dtUrl  = @json(route('restocks.datatable'));
  const baseUrl = @json(url('/restocks'));
  const exportUrl = @json(route('restocks.export.excel'));
  const canSwitchWarehouse = @json((bool) $canSwitchWarehouse);

  // biar ga muncul alert DataTables default
  $.fn.dataTable.ext.errMode = 'none';

  const table = $('#tblRestocks').DataTable({
    processing: true,
    serverSide: true,
    lengthChange: false,
    dom: 'rt<"row mt-2"<"col-12 col-md-5"i><"col-12 col-md-7"p>>',
    ajax: {
      url: dtUrl,
      type: 'GET',
      data: function (d) {
        d.warehouse_id = $('#filterWarehouse').val() || '';
        d.status       = $('#filterStatus').val() || '';
        d.from         = $('#fromDate').val() || '';
        d.to           = $('#toDate').val() || '';
      }
    },
    order: [[1, 'desc']],
    columns: [
      { data: 'rownum',     orderable: false, searchable: false, className: 'text-center' },
      { data: 'code' },
      { data: 'product' },
      { data: 'supplier' },
      { data: 'qty_req',    className: 'text-end' },
      { data: 'qty_rcv',    className: 'text-end' },
      { data: 'status',     orderable: false, searchable: false, className: 'text-center' },
      { data: 'created_at', className: 'text-center' },
      { data: 'warehouse' },
      { data: 'actions',    orderable: false, searchable: false, className: 'text-center' }
    ]
  });

  $('#tblRestocks').on('error.dt', function(e, settings, techNote, message){
    console.error(message);
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon:'error', title:'Server error', text:'Cek storage/logs/laravel.log buat detail.' });
    } else {
      alert('Server error. Cek laravel.log');
    }
  });

  $('#filterWarehouse, #filterStatus, #fromDate, #toDate').on('change', function () {
    table.ajax.reload();
  });

  $('#pageLength').on('change', function () {
    table.page.len(parseInt(this.value || 10, 10)).draw();
  });

  // global search (input di navbar layout lu)
  $('#globalSearch')
    .off('keyup.restocks change.restocks')
    .on('keyup.restocks change.restocks', function () {
      table.search(this.value).draw();
    });

  // EXPORT
  $('#btnExportExcel').on('click', function () {
    const q  = ($('#globalSearch').val() || '').trim();
    const st = $('#filterStatus').val() || '';
    const fr = $('#fromDate').val() || '';
    const to = $('#toDate').val() || '';
    const wh = $('#filterWarehouse').val() || '';

    const params = { q, status: st, from: fr, to, warehouse_id: wh };

    const clean = {};
    Object.keys(params).forEach(k => {
      if (params[k] !== '' && params[k] != null) clean[k] = params[k];
    });

    const hasFilter =
      (q !== '') ||
      (st !== '') ||
      (fr !== '' || to !== '') ||
      (canSwitchWarehouse && wh !== '');

    const qs = $.param(clean);
    const go = () => window.location = exportUrl + (qs ? ('?' + qs) : '');

    if (!hasFilter) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Export semua data?',
          text: 'Kamu belum set filter. Data bisa banyak & berat.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ya, Export',
          cancelButtonText: 'Batal'
        }).then((res) => { if (res.isConfirmed) go(); });
      } else {
        if (confirm('Kamu belum set filter. Export semua data?')) go();
      }
      return;
    }
    go();
  });

  // DETAIL
  $(document).on('click', '.js-detail', function () {
    const id  = $(this).data('id');
    const codeFromRow = $(this).data('code') || '-';

    $.getJSON(baseUrl + '/' + id + '/items', function (res) {
      if (res.status !== 'ok') return alert(res.message || 'Gagal memuat detail');

      const h = res.header || {};
      $('#detCode').text(h.code || codeFromRow);
      $('#detWarehouse').text(h.warehouse || '-');
      $('#detDate').text(h.request_date || '-');
      $('#detRequester').text(h.requester || '-');
      $('#detStatus').text(h.status || '-');
      $('#detTotalItems').text(h.total_items || 0);

      const tbody = $('#tblDetailItems tbody').empty();
      (res.items || []).forEach((item, idx) => {
        tbody.append(`
          <tr>
            <td class="text-center">${idx + 1}</td>
            <td>
              <div class="fw-semibold">${item.product ?? '-'}</div>
              <div class="small text-muted">${item.product_code ?? ''}</div>
            </td>
            <td>${item.supplier ?? '-'}</td>
            <td class="text-center">${item.qty_req}</td>
            <td class="text-center">${item.qty_rcv}</td>
            <td class="text-center">${item.qty_remaining}</td>
            <td>${item.note ?? '-'}</td>
          </tr>
        `);
      });

      new bootstrap.Modal(document.getElementById('mdlDetail')).show();
    }).fail(function () {
      alert('Gagal memuat detail');
    });
  });

  // RECEIVE
  $(document).on('click', '.js-receive', function () {
    const btn = $(this);
    const id  = btn.data('id');
    const codeFromRow = btn.data('code') || '-';

    $.getJSON(baseUrl + '/' + id + '/items', function (res) {
      if (res.status !== 'ok') return alert(res.message || 'Gagal memuat data penerimaan');

      const h = res.header || {};
      $('#rcvCode').text(h.code || codeFromRow);
      $('#rcvWarehouse').text(h.warehouse || '-');
      $('#rcvRequester').text(h.requester || '-');

      const tbody = $('#tblReceiveItems tbody').empty();
      (res.items || []).forEach((item, idx) => {
        const qtyReq = parseInt(item.qty_req) || 0;
        const qtyRcv = parseInt(item.qty_rcv) || 0;
        const qtyRem = parseInt(item.qty_remaining) || 0;
        const maxAttr = qtyRem > 0 ? qtyRem : 0;

        tbody.append(`
          <tr data-id="${item.id}">
            <td class="text-center">${idx + 1}</td>
            <td>
              <div class="fw-semibold">${item.product ?? '-'}</div>
              <div class="small text-muted">${item.product_code ?? ''}</div>
            </td>
            <td>${item.supplier ?? '-'}</td>
            <td class="text-center">${qtyReq}</td>
            <td class="text-center">${qtyRcv}</td>
            <td class="text-center">${qtyRem}</td>
            <td>
              <input type="number" min="0" max="${maxAttr}" value="${maxAttr}"
                     name="items[${item.id}][qty_good]"
                     class="form-control form-control-sm text-end">
            </td>
            <td>
              <input type="number" min="0" max="${maxAttr}" value="0"
                     name="items[${item.id}][qty_damaged]"
                     class="form-control form-control-sm text-end">
            </td>
            <td>
              <input type="text" name="items[${item.id}][notes]"
                     class="form-control form-control-sm"
                     placeholder="Catatan (opsional)">
            </td>
          </tr>
        `);
      });

      $('#formReceive').attr('action', btn.data('action'));
      $('#formReceive').find('input[type="file"]').val('');

      new bootstrap.Modal(document.getElementById('mdlReceive')).show();
    }).fail(function () {
      alert('Gagal memuat data penerimaan.');
    });
  });

  // ===== MULTI ITEM ADD ROW (RF) =====
  const $rfTbody = $('#tblRfItems tbody');

  function renumberRfRows(){
    $rfTbody.find('tr').each(function(i){
      $(this).attr('data-index', i);
      $(this).find('.row-num').text(i+1);

      $(this).find('select, input').each(function(){
        const name = $(this).attr('name');
        if(!name) return;
        $(this).attr('name', name.replace(/items\[\d+\]/, 'items['+i+']'));
      });
    });
  }

  $('#btnAddRfRow').on('click', function(){
    const $last = $rfTbody.find('tr:last');
    const $new  = $last.clone();

    $new.find('select').val('');
    $new.find('input[type="number"]').val(1);
    $new.find('input[type="text"]').val('');

    $rfTbody.append($new);
    renumberRfRows();
  });

  $(document).on('click', '#tblRfItems .btn-remove', function(){
    const total = $rfTbody.find('tr').length;
    if(total <= 1){
      const $tr = $(this).closest('tr');
      $tr.find('select').val('');
      $tr.find('input[type="number"]').val(1);
      $tr.find('input[type="text"]').val('');
      return;
    }
    $(this).closest('tr').remove();
    renumberRfRows();
  });

});
</script>
@endpush
