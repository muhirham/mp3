    @extends('layouts.home')

    @section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
    $me = $me ?? auth()->user();
    $canAdjustPusat = empty($me->warehouse_id);
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0 fw-bold">Stock Adjustment</h4>

        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlCreateAdj">
                <i class="bx bx-plus"></i> Buat Adjustment
            </button>

            <button type="button" id="btnExportExcel" class="btn btn-success">
                <i class="bx bx-download"></i> Export Excel
            </button>
        </div>
    </div>


    {{-- FILTER + TABLE RIWAYAT --}}
    <div class="card">
        <div class="card-body">
        <div class="row g-2 align-items-end">

            <div class="col-6 col-md-2">
            <label class="form-label mb-1">Dari Tgl (dokumen)</label>
            <input id="fltFrom" type="date" class="form-control">
            </div>

            <div class="col-6 col-md-2">
            <label class="form-label mb-1">Sampai</label>
            <input id="fltTo" type="date" class="form-control">
            </div>

            <div class="col-12 col-md-4">
            <label class="form-label mb-1">Warehouse</label>
            <select id="fltWarehouse" class="form-select">
                <option value="">— Semua —</option>
                <option value="central">Stock Central</option>
                @foreach($warehouses as $w)
                <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                @endforeach
            </select>
            </div>
        </div>
        </div>

        <div class="table-responsive">
        <table id="tblAdjustments" class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:70px">ID</th>
                <th>Kode</th>
                <th>Tgl Adjustment</th>
                <th>Warehouse</th>
                <th class="text-center" style="width:120px">Jumlah Item</th>
                <th>Dibuat Oleh</th>
                <th>Jam Input</th>
                <th class="text-end" style="width:130px">Aksi</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>

        <div class="card-body pt-3">
        <small class="text-muted">
            * “Tgl Adjustment” = tanggal dokumen (effective date). “Created At” = waktu input di sistem. Dua-duanya beda fungsi.
        </small>
        </div>
    </div>

    </div>

    {{-- ================== MODAL CREATE ================== --}}
    <div class="modal fade" id="mdlCreateAdj" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <div>
            <h5 class="modal-title fw-bold">Buat Stock Adjustment</h5>
            <small class="text-muted">Gunakan <code>Alt+N</code> tambah baris, <code>Alt+S</code> simpan (di dalam modal)</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <form id="frmAdjust" action="{{ route('stock-adjustments.store') }}" method="POST">
            @csrf

            <div class="modal-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                <label class="form-label">Tanggal (dokumen)</label>
                <input type="date" name="adj_date" id="adj_date" class="form-control"
                        value="{{ now()->toDateString() }}">
                </div>

                <div class="col-md-4">
                <label class="form-label">Warehouse</label>
                <select name="warehouse_id" id="warehouse_id" class="form-select">
                    @if($canAdjustPusat)
                    <option value="">Stock Central</option>
                    @else
                    <option value="">— Pilih —</option>
                    @endif

                    @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                    @endforeach
                </select>
                </div>

                <div class="col-md-5">
                <label class="form-label">Catatan (global)</label>
                <input type="text" name="notes" id="notes_header" class="form-control" placeholder="Opsional...">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-4">
                <label class="form-label">Mode Stok</label>
                <select name="stock_scope_mode" id="stock_scope_mode" class="form-select">
                    <option value="single" selected>Item (manual)</option>
                    <option value="all">All Produk </option>
                </select>
                <small class="text-muted d-block">
                    Mode “All Produk” akan memuat semua item sesuai warehouse/central.
                </small>
                </div>

                <div class="col-md-4">
                <label class="form-label">Mode Update</label>
                <select name="price_update_mode" id="price_update_mode" class="form-select">
                    <option value="stock" selected>Update Stok Fisik</option>
                    <option value="purchase">Update Harga Beli</option>
                    <option value="selling">Update Harga Jual</option>
                    <option value="purchase_selling">Update Harga Beli & Jual</option>
                    <option value="stock_purchase_selling">Update Stok + Harga Beli & Jual</option>
                </select>
                <small class="text-muted d-block">
                    Kolom tabel menyesuaikan mode.
                </small>
                </div>
            </div>

                <div class="table-responsive items-scroll">
                <table class="table table-bordered align-middle mb-2" id="tblItems">
                <thead class="table-light">
                    <tr class="text-center">
                    <th style="width:30%">Produk</th>
                    <th class="col-stock" style="width:12%">Stok Fisik</th>
                    <th class="col-price-beli" style="width:14%">Harga Beli</th>
                    <th class="col-price-jual" style="width:14%">Harga Jual</th>
                    <th style="width:22%">Catatan Item</th>
                    <th style="width:8%">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" id="btnAddRow" class="btn btn-outline-secondary">
                <i class="bx bx-plus"></i> Tambah Baris (Alt+N)
                </button>

                <button type="submit" id="btnSave" class="btn btn-primary">
                <i class="bx bx-save"></i> Simpan (Alt+S)
                </button>
            </div>
            </div>
        </form>
        </div>
    </div>
    </div>

    {{-- ================== MODAL DETAIL (1 modal, isi via AJAX) ================== --}}
    <div class="modal fade" id="mdlAdjDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <div>
            <h5 class="modal-title fw-bold">FORM STOCK ADJUSTMENT</h5>
            <small class="text-muted">Detail dokumen penyesuaian stok</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="border rounded p-3 mb-3">
            <div class="row">
                <div class="col-md-6">
                <div><strong>No. Dokumen</strong> : <span id="d_adj_code">-</span></div>
                <div><strong>Tanggal</strong> : <span id="d_adj_date">-</span></div>
                <div><strong>Warehouse</strong> : <span id="d_wh">-</span></div>
                </div>
                <div class="col-md-6">
                <div><strong>Dibuat oleh</strong> : <span id="d_creator">-</span></div>
                <div><strong>Dibuat pada</strong> : <span id="d_created_at">-</span></div>
                <div><strong>Jumlah item</strong> : <span id="d_items_count">-</span></div>
                </div>
            </div>
            <div class="mt-2">
                <strong>Catatan Global</strong><br>
                <span class="text-muted" id="d_notes">-</span>
            </div>
            </div>

            <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                <tr class="text-center">
                    <th style="width:40px">No.</th>
                    <th>Produk</th>
                    <th class="text-end" style="width:110px">Stok Sebelum</th>
                    <th class="text-end" style="width:110px">Stok Sesudah</th>
                    <th class="text-end" style="width:110px">Selisih</th>
                    <th class="text-end" style="width:160px">Harga Beli (Sblm → Ssdh)</th>
                    <th class="text-end" style="width:160px">Harga Jual (Sblm → Ssdh)</th>
                    <th style="width:220px">Catatan Item</th>
                </tr>
                </thead>
                <tbody id="d_items_body"></tbody>
            </table>
            </div>
        </div>

        </div>
    </div>
    </div>
    @endsection

    @push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <style>
    /* ================== GENERAL SMALLER UI ================== */
    .container-xxl { font-size: 12.5px; }
    .form-label { font-size: 12px; }
    .form-control, .form-select { font-size: 12.5px; padding: .45rem .6rem; }
    .btn { font-size: 12.5px; padding: .45rem .75rem; }
    .table { font-size: 12.2px; }
    .table thead th { font-size: 12px; font-weight: 600; }
    code { font-size: 12px; }

    /* ================== DATATABLE: NO HORIZONTAL SCROLL ================== */
    /* bikin kolom wrap, jadi ga perlu scroll kiri kanan */
    #tblAdjustments { width: 100% !important; table-layout: fixed; }
    #tblAdjustments td, #tblAdjustments th {
        white-space: normal !important;
        word-break: break-word;
        vertical-align: middle;
    }
    /* rapihin padding cell */
    #tblAdjustments td { padding: .55rem .6rem; }
    #tblAdjustments th { padding: .6rem .6rem; }

    /* wrapper jangan maksa scroll-x */
    .table-responsive { overflow-x: hidden; }

    /* ================== MODAL SCROLL FIX ================== */
    #mdlCreateAdj .modal-body{
        max-height: calc(100vh - 180px);
        overflow-y: auto;
    }
    #mdlCreateAdj .items-scroll{
        max-height: 55vh;
        overflow-y: auto;
        overflow-x: hidden; /* penting: jangan scroll kiri-kanan */
    }
    #mdlCreateAdj .items-scroll table{ table-layout: fixed; width: 100%; }
    #mdlCreateAdj .items-scroll td, 
    #mdlCreateAdj .items-scroll th{
        white-space: normal !important;
        word-break: break-word;
    }

    /* sticky header */
    #mdlCreateAdj .items-scroll thead th{
        position: sticky;
        top: 0;
        z-index: 5;
        background: #f8f9fa;
    }

    /* ================== DATATABLE UI (clean) ================== */
    .dataTables_wrapper .dataTables_info { font-size: 12px; padding-top: .35rem; }
    .dataTables_wrapper .pagination .page-link { font-size: 12px; padding: .3rem .55rem; }
    .dataTables_wrapper .dataTables_length label { font-size: 12px; }
    .dataTables_wrapper .dataTables_length select { font-size: 12px; padding: .25rem .5rem; }
    </style>
    @endpush

   @push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function(){

  const csrf = $('meta[name="csrf-token"]').attr('content');

  const datatableUrl   = @json(route('stock-adjustments.datatable'));
  const storeUrl       = @json(route('stock-adjustments.store'));
  const detailTpl      = @json(route('stock-adjustments.detail', ['adjustment' => '__ID__']));
  const productsUrl    = @json(route('stock-adjustments.products'));
  const ajaxProductsUrl= @json(route('stock-adjustments.ajax-products'));

  // =================== DATATABLE RIWAYAT ===================
  const table = $('#tblAdjustments').DataTable({
    processing: true,
    serverSide: true,
    lengthChange: true,
    pageLength: 10,
    order: [[0,'desc']],
    searching: false,
    autoWidth: false,          // ✅ biar table-layout fixed jalan
    responsive: false,

    // ✅ pagination ATAS DIHAPUS (tinggal bawah)
    dom:
      "<'row'<'col-12 col-md-6'l><'col-12 col-md-6 text-md-end'>>" +
      "rt" +
      "<'row mt-2'<'col-12 col-md-6'i><'col-12 col-md-6 text-md-end'p>>",

    ajax: {
      url: datatableUrl,
      type: 'GET',
      data: function(d){
        d.q = ($('#globalSearch').val() || '').trim();
        d.warehouse_id = $('#fltWarehouse').val() || '';
        d.date_from    = $('#fltFrom').val() || '';
        d.date_to      = $('#fltTo').val() || '';
      }
    },

    columns: [
      { data: 'id', width: '70px' },
      { data: 'adj_code', width: '130px' },
      { data: 'adj_date', width: '140px' },
      { data: 'warehouse' },
      { data: 'items_count', className:'text-center', width: '110px' },
      { data: 'creator', width: '170px' },
      { data: 'created_at', width: '120px' },
      { data: 'action', orderable:false, searchable:false, className:'text-end', width: '130px' },
    ],
  });

  // debounce reload
  let tSearch;
  function reloadDebounced(){
    clearTimeout(tSearch);
    tSearch = setTimeout(()=> table.ajax.reload(null, true), 250);
  }

  // filter date & warehouse reload
  $('#fltWarehouse, #fltFrom, #fltTo').on('change', function(){
    table.ajax.reload(null, true);
  });

  // global search navbar jadi search utama
  $(document)
    .off('input.stockAdjSearch', '#globalSearch')
    .on('input.stockAdjSearch', '#globalSearch', function(){
      reloadDebounced();
    });

  // =================== DETAIL MODAL (AJAX) ===================
  $('#tblAdjustments').on('click', '.btn-detail', async function(){
    const id = $(this).data('id');
    const url = detailTpl.replace('__ID__', id);

    try{
      const res = await fetch(url, { headers:{'Accept':'application/json'} });
      const json = await res.json();
      if(!res.ok || json.status !== 'ok') throw new Error(json.message || 'Gagal ambil detail');

      $('#d_adj_code').text(json.header.adj_code || '-');
      $('#d_adj_date').text(json.header.adj_date || '-');
      $('#d_wh').text(json.header.warehouse || '-');
      $('#d_creator').text(json.header.creator || '-');
      $('#d_created_at').text(json.header.created_at || '-');
      $('#d_items_count').text(json.header.items_count ?? '-');
      $('#d_notes').text(json.header.notes || '-');

      const body = $('#d_items_body');
      body.html('');

      const fmt = (n)=> new Intl.NumberFormat('id-ID').format(n ?? 0);
      const fmtRp = (n)=> (n===null || n===undefined) ? '-' : ('Rp' + new Intl.NumberFormat('id-ID').format(n));

      (json.items || []).forEach((it, idx)=>{
        const diff = parseInt(it.qty_diff || 0, 10);
        let diffHtml = `<span class="text-muted">0</span>`;
        if(diff > 0) diffHtml = `<span class="text-success fw-semibold">+${fmt(diff)}</span>`;
        if(diff < 0) diffHtml = `<span class="text-danger fw-semibold">${fmt(diff)}</span>`;

        body.append(`
          <tr>
            <td class="text-center">${idx+1}</td>
            <td>
              <div class="fw-semibold">${it.product || '-'}</div>
              <div class="text-muted small">${it.product_code || ''}</div>
            </td>
            <td class="text-end">${fmt(it.qty_before)}</td>
            <td class="text-end">${fmt(it.qty_after)}</td>
            <td class="text-end">${diffHtml}</td>
            <td class="text-end">${fmtRp(it.pb)} <span class="text-muted">→</span> ${fmtRp(it.pa)}</td>
            <td class="text-end">${fmtRp(it.sb)} <span class="text-muted">→</span> ${fmtRp(it.sa)}</td>
            <td>${it.notes || '-'}</td>
          </tr>
        `);
      });

      new bootstrap.Modal(document.getElementById('mdlAdjDetail')).show();
    }catch(err){
      console.error(err);
      Swal.fire({ icon:'error', title:'Gagal', text: err.message || 'Gagal memuat detail.' });
    }
  });

  // =================== CREATE MODAL (AJAX SUBMIT) ===================
  const createModalEl = document.getElementById('mdlCreateAdj');
  const frm = document.getElementById('frmAdjust');
  const tbody = document.querySelector('#tblItems tbody');
  const btnAddRow = document.getElementById('btnAddRow');
  const btnSave = document.getElementById('btnSave');

  const stockScope = document.getElementById('stock_scope_mode');
  const updateMode = document.getElementById('price_update_mode');
  const warehouseSel = document.getElementById('warehouse_id');

  let productsCache = [];
  let rowIndex = 0;
  let isSubmitting = false;

  async function ensureProductsLoaded(){
    if(productsCache.length) return;
    const res = await fetch(productsUrl, { headers:{'Accept':'application/json'} });
    const json = await res.json();
    if(!res.ok || json.status !== 'ok') throw new Error('Gagal load products');
    productsCache = json.items || [];
  }

  function productOptionsHtml(){
    let html = `<option value="">— Pilih Produk —</option>`;
    for(const p of productsCache){
      html += `<option value="${p.id}">${p.product_code} — ${p.name}</option>`;
    }
    return html;
  }

  function applyUpdateModeUI(){
    const mode = updateMode.value;

    const showStock = (mode === 'stock' || mode === 'stock_purchase_selling');
    const showBeli  = (mode === 'purchase' || mode === 'purchase_selling' || mode === 'stock_purchase_selling');
    const showJual  = (mode === 'selling'  || mode === 'purchase_selling' || mode === 'stock_purchase_selling');

    document.querySelectorAll('#mdlCreateAdj .col-stock, #mdlCreateAdj .td-stock')
      .forEach(el => el.classList.toggle('d-none', !showStock));

    document.querySelectorAll('#mdlCreateAdj .col-price-beli, #mdlCreateAdj .td-price-beli')
      .forEach(el => el.classList.toggle('d-none', !showBeli));

    document.querySelectorAll('#mdlCreateAdj .col-price-jual, #mdlCreateAdj .td-price-jual')
      .forEach(el => el.classList.toggle('d-none', !showJual));

    document.querySelectorAll('#mdlCreateAdj input[name$="[qty_after]"]').forEach(i => i.required = showStock);
    document.querySelectorAll('#mdlCreateAdj input[name$="[purchasing_price]"]').forEach(i => i.required = showBeli);
    document.querySelectorAll('#mdlCreateAdj input[name$="[selling_price]"]').forEach(i => i.required = showJual);
  }

  function makeRowSingle(idx){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select name="items[${idx}][product_id]" class="form-select item-product" required>
          ${productOptionsHtml()}
        </select>
      </td>
      <td class="td-stock">
        <input type="number" name="items[${idx}][qty_after]" class="form-control" min="0">
      </td>
      <td class="td-price-beli">
        <input type="number" name="items[${idx}][purchasing_price]" class="form-control" min="0">
      </td>
      <td class="td-price-jual">
        <input type="number" name="items[${idx}][selling_price]" class="form-control" min="0">
      </td>
      <td>
        <input type="text" name="items[${idx}][notes]" class="form-control">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
          <i class="bx bx-trash"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
    applyUpdateModeUI();
  }

  function makeRowAll(idx, item){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <input type="hidden" name="items[${idx}][product_id]" value="${item.id}">
        <div class="fw-semibold">${item.product_code} — ${item.name}</div>
      </td>
      <td class="td-stock">
        <input type="number" name="items[${idx}][qty_after]" class="form-control" min="0" value="${item.qty_before}">
      </td>
      <td class="td-price-beli">
        <input type="number" name="items[${idx}][purchasing_price]" class="form-control" min="0">
      </td>
      <td class="td-price-jual">
        <input type="number" name="items[${idx}][selling_price]" class="form-control" min="0">
      </td>
      <td>
        <input type="text" name="items[${idx}][notes]" class="form-control">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
          <i class="bx bx-trash"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
    applyUpdateModeUI();
  }

  function resetForm(){
    frm.reset();
    document.getElementById('adj_date').value = new Date().toISOString().slice(0,10);

    tbody.innerHTML = '';
    rowIndex = 0;
    stockScope.value = 'single';

    makeRowSingle(rowIndex++);
    btnAddRow.disabled = false;
    applyUpdateModeUI();
    isSubmitting = false;
    btnSave.disabled = false;
  }

  async function loadAllProducts(){
    const whId = warehouseSel.value || '';
    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Memuat data produk...</td></tr>`;

    const params = new URLSearchParams({ warehouse_id: whId });
    const res = await fetch(ajaxProductsUrl + '?' + params.toString(), { headers: { 'Accept':'application/json' } });
    const json = await res.json();
    if(!res.ok || json.status !== 'ok') throw new Error('Gagal load produk (all)');

    tbody.innerHTML = '';
    rowIndex = 0;

    for(const item of (json.items || [])){
      makeRowAll(rowIndex++, item);
    }

    if(rowIndex === 0){
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Tidak ada produk.</td></tr>`;
    }
  }

  createModalEl.addEventListener('shown.bs.modal', async function(){
    try{
      await ensureProductsLoaded();
      resetForm();
    }catch(err){
      console.error(err);
      Swal.fire({ icon:'error', title:'Gagal', text:'Gagal memuat master produk.' });
    }
  });

  updateMode.addEventListener('change', applyUpdateModeUI);

  stockScope.addEventListener('change', async function(){
    if(this.value === 'all'){
      btnAddRow.disabled = true;
      try{ await loadAllProducts(); }
      catch(err){
        console.error(err);
        Swal.fire({ icon:'error', title:'Gagal', text: err.message || 'Gagal memuat produk.' });
      }
    }else{
      btnAddRow.disabled = false;
      tbody.innerHTML = '';
      rowIndex = 0;
      makeRowSingle(rowIndex++);
    }
  });

  warehouseSel.addEventListener('change', async function(){
    if(stockScope.value === 'all'){
      try{ await loadAllProducts(); }
      catch(err){
        console.error(err);
        Swal.fire({ icon:'error', title:'Gagal', text: err.message || 'Gagal memuat produk.' });
      }
    }
  });

  btnAddRow.addEventListener('click', function(){
    if(stockScope.value !== 'single') return;
    makeRowSingle(rowIndex++);
  });

  tbody.addEventListener('click', function(e){
    if(e.target.closest('.btnRemoveRow')){
      const rows = tbody.querySelectorAll('tr');
      if(rows.length <= 1){
        rows[0].querySelectorAll('input,select').forEach(el => el.value = '');
      }else{
        e.target.closest('tr').remove();
      }
    }
  });

  createModalEl.addEventListener('keydown', function(e){
    if(e.altKey && e.key.toLowerCase()==='n'){
      e.preventDefault();
      if(stockScope.value === 'single') btnAddRow.click();
    }
    if(e.altKey && e.key.toLowerCase()==='s'){
      e.preventDefault();
      if(!isSubmitting) frm.requestSubmit();
    }
  });

  frm.addEventListener('submit', async function(e){
    e.preventDefault();
    if(isSubmitting) return;

    isSubmitting = true;
    btnSave.disabled = true;

    try{
      const fd = new FormData(frm);

      const res = await fetch(storeUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: fd
      });

      const json = await res.json().catch(()=> ({}));

      if(res.status === 422){
        const errs = json.errors || {};
        const list = Object.values(errs).flat().join('<br>');
        Swal.fire({ icon:'error', title:'Validasi gagal', html: list || 'Periksa input.' });
        isSubmitting = false;
        btnSave.disabled = false;
        return;
      }

      if(!res.ok || json.status !== 'ok'){
        throw new Error(json.message || 'Gagal menyimpan.');
      }

      Swal.fire({ icon:'success', title:'Berhasil', text: json.message || 'Tersimpan.' });

      bootstrap.Modal.getInstance(createModalEl).hide();
      table.ajax.reload(null, false);
    }catch(err){
      console.error(err);
      Swal.fire({ icon:'error', title:'Gagal', text: err.message || 'Gagal menyimpan.' });
      isSubmitting = false;
      btnSave.disabled = false;
    }
  });
    const exportUrl = @json(route('stock-adjustments.exportIndexExcel'));

  function buildExportUrl(){
    const q  = ($('#globalSearch').val() || '').trim();
    const wh = ($('#fltWarehouse').val() || '').trim();

    let from = ($('#fltFrom').val() || '').trim();
    let to   = ($('#fltTo').val() || '').trim();

    // kalau cuma isi salah satu, samain (biar konsisten sama parseExportRangeOptional)
    if (from && !to) to = from;
    if (to && !from) from = to;

    const params = new URLSearchParams();
    if (q)  params.set('q', q);
    if (wh) params.set('warehouse_id', wh);

    // pakai key yang controller udah support: date_from / date_to
    if (from) params.set('date_from', from);
    if (to)   params.set('date_to', to);

    const qs = params.toString();
    return qs ? `${exportUrl}?${qs}` : exportUrl;
  }

  $('#btnExportExcel').on('click', function(){
    const q    = ($('#globalSearch').val() || '').trim();
    const wh   = ($('#fltWarehouse').val() || '').trim();
    const from = ($('#fltFrom').val() || '').trim();
    const to   = ($('#fltTo').val() || '').trim();

    const noFilter = (!q && !wh && !from && !to);

    if (noFilter) {
      Swal.fire({
        icon: 'warning',
        title: 'Filter masih kosong',
        text: 'Kamu belum milih filter apa pun. Export semua data bisa berat. Lanjut export ALL?',
        showCancelButton: true,
        confirmButtonText: 'Ya, export ALL',
        cancelButtonText: 'Batal'
      }).then((r)=>{
        if (r.isConfirmed) {
          window.location.href = buildExportUrl();
        }
      });
      return;
    }

    // kalau ada filter => langsung export
    window.location.href = buildExportUrl();
  });

});
</script>
@endpush
