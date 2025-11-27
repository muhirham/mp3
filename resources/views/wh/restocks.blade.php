@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $me = $me ?? auth()->user();
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

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

        {{-- SEARCH BOX (custom) --}}
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Pencarian</label>
          <input id="searchBox" type="text" class="form-control"
                 placeholder="Cari restock...">
        </div>

        {{-- WAREHOUSE FILTER --}}
        <div class="col-12 col-md-4 ms-md-auto">
          <label class="form-label mb-1">Warehouse</label>

          @if($canSwitchWarehouse)
            {{-- superadmin / admin pusat: bisa pilih gudang --}}
            <select id="filterWarehouse" class="form-select">
              <option value="">— Semua —</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}"
                    @selected(($selectedWarehouseId ?? null) == $w->id)>
                  {{ $w->warehouse_name }}
                </option>
              @endforeach
            </select>
          @elseif($isWarehouseUser)
            {{-- admin WH: terkunci ke gudangnya sendiri --}}
            <input class="form-control"
                   value="{{ $me->warehouse?->warehouse_name ?? '-' }}"
                   disabled>
            <input type="hidden" id="filterWarehouse"
                   value="{{ $me->warehouse_id }}">
          @else
            {{-- fallback kalau role lain --}}
            <input class="form-control" value="-" disabled>
            <input type="hidden" id="filterWarehouse" value="">
          @endif
        </div>

        {{-- BUTTON ADD --}}
        <div class="col-12 col-md-auto mt-2 mt-md-0 text-md-end">
          <button class="btn btn-primary" data-bs-toggle="modal"
                  data-bs-target="#mdlAdd">
            + Buat Request
          </button>
        </div>

      </div>
    </div>
  </div>

  {{-- TABEL RESTOCK --}}
  <div class="card">
    <div class="table-responsive">
      <table id="tblRestocks" class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:60px">NO</th>
            <th>CODE</th>
            <th>PRODUCT</th>
            <th>SUPPLIER</th>
            <th class="text-end">REQ</th>
            <th class="text-end">RCV</th>
            <th>STATUS</th>
            <th>DATE</th>
            <th>DESCRIPTION</th>
            <th style="width:90px">ACTIONS</th>
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
      <div class="modal-header">
        <h5 class="modal-title">Buat Request Restock</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formAdd" method="POST" action="{{ route('restocks.store') }}">
        @csrf
        <div class="modal-body">

          @if($canSwitchWarehouse)
            <div class="mb-3">
              <label class="form-label">Warehouse</label>
              <select name="warehouse_id" class="form-select" required>
                <option value="">— Pilih —</option>
                @foreach($warehouses as $w)
                  <option value="{{ $w->id }}">
                    {{ $w->warehouse_name }}
                  </option>
                @endforeach
              </select>
            </div>
          @endif

          {{-- Tabel item RF --}}
          <div class="table-responsive mb-2">
            <table class="table align-middle" id="tblRfItems">
              <thead>
                <tr>
                  <th style="width:40px">#</th>
                  <th>Product</th>
                  <th style="width:120px" class="text-end">Qty Request</th>
                  <th>Catatan</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody>
                {{-- baris pertama --}}
                <tr data-index="0">
                  <td class="row-num">1</td>
                  <td>
                    <select name="items[0][product_id]" class="form-select form-select-sm" required>
                      <option value="">— Pilih —</option>
                      @foreach($products as $p)
                        <option value="{{ $p->id }}">
                          {{ $p->product_code }} — {{ $p->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" min="1"
                           name="items[0][quantity_requested]"
                           class="form-control form-control-sm text-end" value="1" required>
                  </td>
                  <td>
                    <input type="text"
                           name="items[0][note]"
                           class="form-control form-control-sm"
                           placeholder="Catatan (opsional)">
                  </td>
                  <td class="text-center">
                    <button type="button"
                            class="btn btn-xs btn-link text-danger btn-remove">&times;</button>
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
          <button type="button" class="btn btn-outline-secondary"
                  data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>



{{-- ========== MODAL RECEIVE BARANG (MULTI ITEM) ========== --}}
  <div class="modal fade" id="mdlReceive" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header border-0 pb-1">
          <div>
            <h5 class="modal-title fw-bold mb-0">
              Tanda Terima Barang Restock (GR)
            </h5>
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

            {{-- TABEL INPUT GR MULTI ITEM --}}
            <div class="mb-3">
              <table class="table table-sm table-bordered mb-0" id="tblReceiveItems">
                <thead class="table-light">
                  <tr class="text-center">
                    <th style="width:40px;">#</th>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th style="width:110px;">Qty Requested</th>
                    <th style="width:130px;">Qty Received (sebelumnya)</th>
                    <th style="width:110px;">Qty Remaining</th>
                    <th style="width:110px;">Qty Good</th>
                    <th style="width:120px;">Qty Damaged</th>
                    <th style="width:160px;">Notes</th>
                  </tr>
                </thead>
                <tbody>
                  {{-- akan diisi via JS dari /restocks/{id}/items --}}
                </tbody>
              </table>
              <small class="text-muted">
                Untuk item yang tidak diterima, biarkan Qty Good dan Qty Damaged = 0.
                Qty Good + Qty Damaged per baris tidak boleh lebih besar dari <strong>Qty Remaining</strong>.
              </small>
            </div>

            {{-- FOTO BARANG GOOD --}}
            <div class="mb-3">
              <label class="form-label text-uppercase small fw-semibold">
                Upload Foto Barang Bagus (opsional)
              </label>
              <input type="file" name="photos_good[]" class="form-control" multiple accept="image/*">
              <div class="form-text">
                Maks 4MB per file. Foto akan dikaitkan ke dokumen GR ini.
              </div>
            </div>

            {{-- FOTO BARANG RUSAK --}}
            <div class="mb-3">
              <label class="form-label text-uppercase small fw-semibold">
                Upload Foto Barang Rusak (opsional)
              </label>
              <input type="file" name="photos_damaged[]" class="form-control" multiple accept="image/*">
              <div class="form-text">
                Jika ada kerusakan, lampirkan foto detail kerusakan.
              </div>
            </div>

          </div>

          <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan Goods Received</button>
          </div>
        </form>
      </div>
    </div>
  </div>

{{-- ========== MODAL DETAIL REQUEST (SEMUA ITEM 1 RR) ========== --}}
<div class="modal fade" id="mdlDetail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0 pb-1">
        <div>
          <h5 class="modal-title fw-bold mb-0">Detail Request Restock</h5>
          <div class="small text-muted">
            No. Dokumen: <span id="detCode">-</span>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body pt-0">
        {{-- HEADER --}}
        <div class="border rounded p-3 mb-3">
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

        {{-- TABEL ITEM --}}
        <div class="table-responsive">
          <table class="table table-bordered align-middle" id="tblDetailItems">
            <thead class="table-light">
              <tr class="text-center">
                <th style="width:40px;">No.</th>
                <th>Product</th>
                <th>Supplier</th>
                <th style="width:110px;">Qty Requested</th>
                <th style="width:110px;">Qty Received</th>
                <th style="width:110px;">Qty Remaining</th>
                <th style="width:160px;">Note</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

      </div>

      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>


@endsection

@push('styles')
<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
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

<script>
$(function () {
  const dtUrl = @json(route('restocks.datatable'));
  const detailBaseUrl = @json(url('/restocks'));  

    // ========= DETAIL RR (SEMUA ITEM) =========
  $(document).on('click', '.js-detail', function () {
    const btn = $(this);
    const id  = btn.data('id');
    const codeFromRow = btn.data('code') || '-';

    const url = detailBaseUrl + '/' + id + '/items';

    $.getJSON(url, function (res) {
      if (res.status !== 'ok') {
        alert(res.message || 'Gagal memuat detail');
        return;
      }

      const h = res.header || {};

      $('#detCode').text(h.code || codeFromRow);
      $('#detWarehouse').text(h.warehouse || '-');
      $('#detDate').text(h.request_date || '-');
      $('#detRequester').text(h.requester || '-');
      $('#detStatus').text(h.status || '-');
      $('#detTotalItems').text(h.total_items || 0);

      const tbody = $('#tblDetailItems tbody');
      tbody.empty();

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

      const modal = new bootstrap.Modal(document.getElementById('mdlDetail'));
      modal.show();
    }).fail(function () {
      alert('Gagal memuat detail');
    });
  });


  const table = $('#tblRestocks').DataTable({
    processing: true,
    serverSide: true,
    lengthChange: false,
    ajax: {
      url: dtUrl,
      type: 'GET',
      data: function (d) {
        d.warehouse_id = $('#filterWarehouse').val() || '';
      }
    },
    order: [[1, 'desc']], // CODE desc
    columns: [
      { data: 'rownum',     orderable: false, searchable: false },
      { data: 'code' },
      { data: 'product' },
      { data: 'supplier' },
      { data: 'qty_req',    className: 'text-end' },
      { data: 'qty_rcv',    className: 'text-end' },
      { data: 'status',     orderable: false, searchable: false },
      { data: 'created_at' },
      { data: 'note' },
      { data: 'actions',    orderable: false, searchable: false }
    ]
  });

  // ganti warehouse -> reload
  $('#filterWarehouse').on('change', function () {
    table.ajax.reload();
  });

  // custom search box
  $('#searchBox').on('keyup change', function () {
    table.search(this.value).draw();
  });

  // page length
  $('#pageLength').on('change', function () {
    table.page.len(parseInt(this.value || 10, 10)).draw();
  });

  // ========= HANDLE RECEIVE BUTTON =========
    // ========= HANDLE RECEIVE BUTTON (MULTI ITEM) =========
  $(document).on('click', '.js-receive', function () {
    const btn = $(this);
    const id  = btn.data('id');
    const codeFromRow = btn.data('code') || '-';

    const url = detailBaseUrl + '/' + id + '/items';

    $.getJSON(url, function (res) {
      if (res.status !== 'ok') {
        alert(res.message || 'Gagal memuat data penerimaan');
        return;
      }

      const h = res.header || {};

      $('#rcvCode').text(h.code || codeFromRow);
      $('#rcvWarehouse').text(h.warehouse || '-');
      $('#rcvRequester').text(h.requester || '-');

      const tbody = $('#tblReceiveItems tbody');
      tbody.empty();

      (res.items || []).forEach((item, idx) => {
        const qtyReq      = parseInt(item.qty_req) || 0;
        const qtyRcv      = parseInt(item.qty_rcv) || 0;
        const qtyRem      = parseInt(item.qty_remaining) || 0;
        const maxAttr     = qtyRem > 0 ? qtyRem : 0;
        const productName = item.product ?? '-';
        const productCode = item.product_code ?? '';
        const supplier    = item.supplier ?? '-';

        tbody.append(`
          <tr data-id="${item.id}">
            <td class="text-center">${idx + 1}</td>
            <td>
              <div class="fw-semibold">${productName}</div>
              <div class="small text-muted">${productCode}</div>
            </td>
            <td>${supplier}</td>
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
              <input type="text"
                     name="items[${item.id}][notes]"
                     class="form-control form-control-sm"
                     placeholder="Catatan (opsional)">
            </td>
          </tr>
        `);
      });

      // set action ke route receive yg dikirim di data-action
      $('#formReceive').attr('action', btn.data('action'));

      // reset file
      $('#formReceive').find('input[type="file"]').val('');

      const modal = new bootstrap.Modal(document.getElementById('mdlReceive'));
      modal.show();
    }).fail(function () {
      alert('Gagal memuat data penerimaan.');
    });
  });


  // ========= MULTI ITEM RF =========
  let nextRfIndex = 1;

  function renumberRfRows() {
    $('#tblRfItems tbody tr').each(function (i, tr) {
      $(tr).find('.row-num').text(i + 1);
    });
  }

  $('#btnAddRfRow').on('click', function () {
    const tbody = $('#tblRfItems tbody');
    const idx   = nextRfIndex++;

    const rowHtml = `
      <tr data-index="${idx}">
        <td class="row-num"></td>
        <td>
          <select name="items[${idx}][product_id]" class="form-select form-select-sm" required>
            <option value="">— Pilih —</option>
            @foreach($products as $p)
              <option value="{{ $p->id }}">{{ $p->product_code }} — {{ $p->name }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <input type="number" min="1"
                 name="items[${idx}][quantity_requested]"
                 class="form-control form-control-sm text-end"
                 value="1" required>
        </td>
        <td>
          <input type="text"
                 name="items[${idx}][note]"
                 class="form-control form-control-sm"
                 placeholder="Catatan (opsional)">
        </td>
        <td class="text-center">
          <button type="button"
                  class="btn btn-xs btn-link text-danger btn-remove">&times;</button>
        </td>
      </tr>
    `;

    tbody.append(rowHtml);
    renumberRfRows();
  });

  $('#tblRfItems tbody').on('click', '.btn-remove', function () {
    const tbody = $('#tblRfItems tbody');
    const rows  = tbody.find('tr');

    // minimal harus ada 1 baris
    if (rows.length <= 1) return;

    $(this).closest('tr').remove();
    renumberRfRows();
  });

  // tiap kali modal dibuka, reset ke 1 baris
  $('#mdlAdd').on('shown.bs.modal', function () {
    const tbody = $('#tblRfItems tbody');
    tbody.find('tr:not(:first)').remove();
    nextRfIndex = 1;
    renumberRfRows();
    // reset nilai
    tbody.find('select,input').val('');
    tbody.find('input[name$="[quantity_requested]"]').val(1);
  });
});
</script>
@endpush
