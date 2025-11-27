@extends('layouts.home')
@section('title','Restock Approval')

@section('content')
<div class="container-fluid flex-grow-1 container-p-y px-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="fw-bold mb-1">Restock Requests (Admin)</h4>
      <small class="text-muted">Approve / Reject / Review → PO</small>
    </div>
    <div class="d-flex gap-2">
      <button id="btnReload" class="btn btn-outline-secondary">
        <i class="bx bx-refresh"></i> Reload
      </button>
      <button id="btnBulkPO" class="btn btn-primary">
        <i class="bx bx-list-check"></i> Review → PO
      </button>
    </div>
  </div>

  {{-- FILTER --}}
  <div class="card mb-3 border-0 shadow-sm">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select id="filterStatus" class="form-select">
            <option value="" selected>All</option>
            <option value="pending">Pending</option>
            <option value="approved">Reviewed</option> {{-- status=approved, label=REVIEW --}}
            <option value="ordered">Ordered</option>
            <option value="received">Received</option>
            <option value="cancelled">Cancelled</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Supplier</label>
          <select id="filterSupplier" class="form-select">
            <option value="">All Suppliers</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}">{{ $s->label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Warehouse</label>
          <select id="filterWarehouse" class="form-select">
            <option value="">All Warehouses</option>
            @foreach($warehouses as $w)
              <option value="{{ $w->id }}">{{ $w->label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Product</label>
          <select id="filterProduct" class="form-select">
            <option value="">All Products</option>
            @foreach($products as $p)
              <option value="{{ $p->id }}">{{ $p->label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Date from</label>
          <input id="dateFrom" type="date" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Date to</label>
          <input id="dateTo" type="date" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Search</label>
          <input id="searchBox" class="form-control" placeholder="Product/Supplier/Warehouse...">
        </div>
        <div class="col-md-2">
          <label class="form-label">Per page</label>
          <select id="perPage" class="form-select">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- TABLE --}}
  <div class="card shadow-sm border-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="tbl">
        <thead class="table-light">
          <tr>
            <th style="width:32px">
              <input type="checkbox" id="chkAll">
            </th>
            <th>ID</th>
            <th>Date</th>
            <th>Product</th>
            <th>Supplier</th>
            <th>Warehouse</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Total Cost</th>
            <th>Status</th>
            <th>Description</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small id="pageInfo" class="text-muted">—</small>
      <nav><ul id="pagination" class="pagination mb-0"></ul></nav>
    </div>
  </div>
</div>

<style>
  .swal2-container{ z-index:20000 !important; }
  .layout-page .content-wrapper { width:100% !important; }
  .container-xxl, .content-wrapper > .container-xxl { max-width:100% !important; }
  .card .table-responsive { overflow-x:auto; }
  #tbl { width:100%; }
  @media (max-width:1200px){ #tbl{ min-width:1100px; } }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const ENDPOINT    = "{{ route('stockRequest.json') }}";
const REJECT_URL  = id => "{{ route('stockRequest.reject', ':id') }}".replace(':id', id);
const BULK_PO_URL = "{{ route('stockRequest.bulkpo') }}";

let state = {
  page:1, per_page:10, status:'', supplier_id:'',
  warehouse_id:'', product_id:'', date_from:'',
  date_to:'', search:''
};

function escHtml(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function fetchList(){
  const params = new URLSearchParams({ ...state, _ts: Date.now() });
  const res = await fetch(ENDPOINT + '?' + params.toString(), {
    headers: { Accept: 'application/json' },
    cache: 'no-store'
  });
  if(!res.ok){
    const t = await res.text();
    Swal.fire({
      icon: 'error',
      title: 'Error ' + res.status,
      html: '<pre style="text-align:left">' + t.replace(/[<>&]/g, s=>({'<':'&lt;','>':'&gt;','&':'&amp;'}[s])) + '</pre>',
      width: 800,
      position: 'center'
    });
    throw new Error('HTTP ' + res.status);
  }
  return res.json();
}

function statusBadge(st){
  switch (st) {
    case 'approved':  return '<span class="badge bg-info text-dark">REVIEW</span>';      // sudah masuk proses PO
    case 'ordered':   return '<span class="badge bg-secondary">ORDERED</span>';
    case 'received':  return '<span class="badge bg-primary">RECEIVED</span>';
    case 'cancelled': return '<span class="badge bg-dark">CANCELLED</span>';
    case 'rejected':  return '<span class="badge bg-danger">REJECTED</span>';
    case 'pending':
    default:          return '<span class="badge bg-warning text-dark">PENDING</span>';
  }
}

function renderRows(rows){
  const tbody = $('#tbl tbody');
  tbody.empty();

  rows.forEach(r => {
    const badge = statusBadge(r.status);
    const qty   = Number(r.quantity_requested || 0);
    const total = Number(r.total_cost || 0);

    // hanya pending yang bisa di-reject
    const actions = (r.status === 'pending')
      ? `<button class="btn btn-sm btn-outline-danger btn-reject" data-id="${r.id}">Reject</button>`
      : '';

    tbody.append(`
      <tr>
        <td>
          <input type="checkbox" class="chk-row" value="${r.id}">
        </td>
        <td>${r.id}</td>
        <td>${r.request_date ?? ''}</td>
        <td>${escHtml(r.product_name ?? '')}</td>
        <td>${escHtml(r.supplier_name ?? '')}</td>
        <td>${escHtml(r.warehouse_name ?? '')}</td>
        <td class="text-end">${qty.toLocaleString('id-ID')}</td>
        <td class="text-end">Rp${total.toLocaleString('id-ID')}</td>
        <td>${badge}</td>
        <td>${escHtml(r.description ?? '')}</td>
        <td class="text-end">${actions}</td>
      </tr>
    `);
  });
}

function renderPagination(pg){
  const ul = $('#pagination'); ul.empty();
  const { page, last_page:last, per_page:per, total } = pg;
  const from = total ? ((page-1)*per + 1) : 0;
  const to   = Math.min(page*per, total);
  $('#pageInfo').text(`Showing ${from}-${to} of ${total}`);

  function li(txt,p,dis=false,act=false){
    ul.append(`<li class="page-item ${dis?'disabled':''} ${act?'active':''}">
      <a class="page-link" href="#" data-page="${p}">${txt}</a></li>`);
  }

  li('«', page-1, page<=1);
  for(let p=Math.max(1,page-3); p<=Math.min(last,page+3); p++) li(p,p,false,p===page);
  li('»', page+1, page>=last);

  $('#pagination .page-link').off('click').on('click',e=>{
    e.preventDefault();
    const p = +$(e.target).data('page');
    if(p>=1 && p<=last && p!==page){ state.page=p; load(); }
  });
}

async function load(){
  try{
    const j = await fetchList();
    renderRows(j.data);
    renderPagination(j.pagination);
    $('#chkAll').prop('checked', false);
  }catch(e){}
}

function applyFilters(){
  state.page        = 1;
  state.status      = $('#filterStatus').val();
  state.supplier_id = $('#filterSupplier').val();
  state.warehouse_id= $('#filterWarehouse').val();
  state.product_id  = $('#filterProduct').val();
  state.date_from   = $('#dateFrom').val();
  state.date_to     = $('#dateTo').val();
  state.per_page    = +($('#perPage').val() || 10);
  state.search      = $('#searchBox').val().trim();
  load();
}

// event filter
$('#btnReload').on('click', load);
$('#filterStatus,#filterSupplier,#filterWarehouse,#filterProduct,#dateFrom,#dateTo,#perPage')
  .on('change', applyFilters);
$('#searchBox').on('keyup', e=>{ if(e.key==='Enter') applyFilters(); });

// checkbox all
$(document).on('change','#chkAll', function(){
  $('.chk-row').prop('checked', this.checked);
});

// ===== BULK REVIEW → PO =====
// ===== BULK REVIEW → PO =====
$('#btnBulkPO').on('click', async function(){
  const ids = $('.chk-row:checked').get().map(el => parseInt(el.value,10)).filter(Boolean);
  if (!ids.length) {
    Swal.fire({ icon:'info', title:'Tidak ada data', text:'Pilih minimal satu request.', position:'center' });
    return;
  }

  const ok = await Swal.fire({
    icon: 'question',
    title: 'Review → PO',
    html: `Buat PO draft dari <b>${ids.length}</b> request restock terpilih?`,
    showCancelButton: true,
    confirmButtonText: 'Ya, buat PO',
    cancelButtonText: 'Batal',
    position:'center'
  });

  if (!ok.isConfirmed) return;

  Swal.fire({
    title:'Processing...',
    allowOutsideClick:false,
    didOpen:()=>Swal.showLoading(),
    position:'center'
  });

  try{
    const res = await fetch(BULK_PO_URL, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-CSRF-TOKEN':'{{ csrf_token() }}',
        'Accept':'application/json, text/html'
      },
      body: JSON.stringify({ ids })
    });

    // kalau server balas redirect, ikuti saja
    if (res.redirected) {
      window.location.href = res.url;
      return;
    }

    // kalau status gagal, tampilkan error
    if (!res.ok) {
      const txtErr = await res.text();
      Swal.fire({
        icon:'error',
        title:'Gagal',
        text: txtErr || ('HTTP ' + res.status),
        position:'center'
      });
      return;
    }

    // Sukses → langsung buka halaman PO index
    window.location.href = "{{ route('po.index') }}";

  } catch (err) {
    Swal.fire({
      icon:'error',
      title:'Network error',
      text:String(err),
      position:'center'
    });
  }
});

// ===== REJECT ACTION =====
$(document).on('click', '.btn-reject', async function(){
  const id = $(this).data('id');
  const { value: reason } = await Swal.fire({
    title: 'Reject reason',
    input: 'text',
    inputPlaceholder: 'Optional',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    position:'center'
  });

  if (reason === undefined) return;

  Swal.fire({ title:'Processing...', allowOutsideClick:false, didOpen:()=>Swal.showLoading(), position:'center' });

  try{
    const res = await fetch(REJECT_URL(id), {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-CSRF-TOKEN':'{{ csrf_token() }}',
        'Accept':'application/json'
      },
      body: JSON.stringify({ reason })
    });
    const j = await res.json().catch(()=>({}));
    if(res.ok && j.ok){
      Swal.fire({ icon:'success', title:'Rejected', timer:1000, showConfirmButton:false, position:'center' });
      load();
    }else{
      Swal.fire({ icon:'error', title:'Reject failed', text:j.message||'Server error', position:'center' });
    }
  }catch(err){
    Swal.fire({ icon:'error', title:'Network error', text:String(err), position:'center' });
  }
});

load();
</script>
@endsection
