        @extends('welcome')
        @section('title','Submit Restock Request')

        @section('content')
        <div class="container-fluid flex-grow-1 container-p-y px-3">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
            <h4 class="fw-bold mb-1">Submit Restock Request</h4>
            <small class="text-muted">Form pengajuan + daftar pengajuan (dummy user)</small>
            </div>
            <button id="btnReload" class="btn btn-outline-secondary"><i class="bx bx-refresh"></i> Reload List</button>
        </div>

        {{-- FORM --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>New Request</strong></div>
            <div class="card-body">
            <form id="formRestock" class="row g-3">
                <div class="col-md-6">
                <label class="form-label">Product</label>
                <select name="product_id" id="uProduct" class="form-select" required>
                    <option value="">Select product</option>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}" data-price="{{ $p->purchase_price ?? 0 }}">{{ $p->label }}</option>
                    @endforeach
                </select>
                </div>
                <div class="col-md-6">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" id="uSupplier" class="form-select" required>
                    <option value="">Select supplier</option>
                    @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->label }}</option>
                    @endforeach
                </select>
                </div>
                <div class="col-md-6">
                <label class="form-label">Warehouse</label>
                <select name="warehouse_id" id="uWarehouse" class="form-select" required>
                    <option value="">Select warehouse</option>
                    @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->label }}</option>
                    @endforeach
                </select>
                </div>
                <div class="col-md-6">
                <label class="form-label">Request Date</label>
                <input type="date" name="request_date" id="uDate" class="form-control" required value="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-4">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" id="uQty" class="form-control" min="1" step="1" value="1" required>
                </div>
                <div class="col-md-4">
                <label class="form-label">Unit Price</label>
                <input type="number" name="unit_price" id="uPrice" class="form-control" min="0" step="0.01" placeholder="Auto from product">
                </div>
                <div class="col-md-4">
                <label class="form-label">Total Cost</label>
                <input type="text" id="uTotal" class="form-control" readonly>
                </div>
                <div class="col-12">
                <label class="form-label">Description / Note</label>
                <textarea name="description" id="uDesc" rows="2" class="form-control" placeholder="Optional"></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                @csrf
                <button type="submit" class="btn btn-primary"><i class="bx bx-send"></i> Submit Request</button>
                <button type="reset" class="btn btn-outline-secondary">Reset</button>
                </div>
            </form>
            </div>
        </div>

        {{-- LIST --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="filterStatus" class="form-select">
                    <option value="" selected>All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                </div>
                <div class="col-md-3">
                <label class="form-label">Search</label>
                <input id="searchBox" class="form-control" placeholder="Product/Warehouse/Supplier...">
                </div>
                <div class="col-md-2">
                <label class="form-label">Per Page</label>
                <select id="perPage" class="form-select">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                </div>
            </div>
            </div>
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tbl">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Warehouse</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th>Description</th>
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
        #tbl { width:100%; } @media (max-width:1200px){ #tbl{ min-width:1000px; } }
        </style>

        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
    const LIST_ENDPOINT  = "{{ route('requestForm.json') }}";
    const STORE_ENDPOINT = "{{ route('requestForm.store') }}";

    let state = { page:1, per_page:10, status:'', search:'' };

    function fmtIDR(n){ return 'Rp' + (Number(n||0)).toLocaleString('id-ID'); }

    const $qty   = $('#uQty');
    const $price = $('#uPrice');
    const $prod  = $('#uProduct');
    const $total = $('#uTotal');

    function recalc(){
    const q = +($qty.val()||0);
    const p = +($price.val()||0);
    $total.val(fmtIDR(q*p));
    }

    // Selalu update harga saat product berubah + hitung ulang total
    $prod.on('change', function(){
    const price = +($(this).find(':selected').data('price')||0);
    $price.val(price);   // BEDA DARI SEBELUMNYA: langsung set, ga nunggu kosong
    recalc();
    });

    // Recalc realtime saat qty/price diganti
    $qty.on('input', recalc);
    $price.on('input', recalc);

    // Trigger awal
    $(document).ready(function(){ recalc(); });

    /* ===== SUBMIT FORM ===== */
    $('#formRestock').on('submit', async function(e){
    e.preventDefault();
    const payload = {
        product_id:  $prod.val(),
        supplier_id: $('#uSupplier').val(),
        warehouse_id:$('#uWarehouse').val(),
        request_date:$('#uDate').val(),
        quantity:    $qty.val(),
        unit_price:  $price.val() || null,
        description: $('#uDesc').val()
    };

    const res = await fetch(STORE_ENDPOINT, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body: JSON.stringify(payload)
    });
    const j = await res.json();

    if(res.ok && j.ok){
        Swal.fire({icon:'success', title:'Request submitted', timer:1200, showConfirmButton:false, position:'center'});
        this.reset();
        recalc();
        load(); // refresh list
    }else{
        Swal.fire({icon:'error', title:'Submit failed', text:j.message||'Cek isian kamu', position:'center'});
    }
    });

    /* ===== LIST (tetap sama) ===== */
    async function fetchList(){
    const params = new URLSearchParams({
        page: state.page, per_page: state.per_page, status: state.status, search: state.search, _ts: Date.now()
    });
    const res = await fetch(LIST_ENDPOINT+'?'+params.toString(), { headers:{Accept:'application/json'}, cache:'no-store' });
    if(!res.ok){
        const t=await res.text();
        Swal.fire({icon:'error', title:'Error '+res.status, html:'<pre style="text-align:left">'+t.replace(/[<>&]/g,s=>({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]))+'</pre>', width:800, position:'center'});
        throw new Error('HTTP '+res.status);
    }
    return res.json();
    }
    function renderRows(rows){
    const tb = $('#tbl tbody'); tb.empty();
    if(rows.length===0){ tb.append('<tr><td colspan="9" class="text-center text-muted py-4">No data</td></tr>'); return; }
    rows.forEach(r=>{
        const badge = r.status==='approved' ? '<span class="badge bg-success">Approved</span>'
                : r.status==='rejected' ? '<span class="badge bg-danger">Rejected</span>'
                : '<span class="badge bg-warning text-dark">Pending</span>';
        tb.append(`
        <tr>
            <td>${r.id}</td>
            <td>${r.request_date ?? ''}</td>
            <td>${r.product_name ?? ''}</td>
            <td>${r.supplier_name ?? ''}</td>
            <td>${r.warehouse_name ?? ''}</td>
            <td class="text-end">${(Number(r.quantity_requested||0)).toLocaleString('id-ID')}</td>
            <td class="text-end">${fmtIDR(r.total_cost)}</td>
            <td>${badge}</td>
            <td>${r.description ?? ''}</td>
        </tr>
        `);
    });
    }
    function renderPagination(pg){
    const ul = $('#pagination'); ul.empty();
    const page=pg.page, last=pg.last_page, per=pg.per_page, total=pg.total;
    const from = total ? ((page-1)*per + 1) : 0, to = Math.min(page*per, total);
    $('#pageInfo').text(`Showing ${from}-${to} of ${total}`);
    function li(txt,p,dis=false,act=false){ ul.append(`<li class="page-item ${dis?'disabled':''} ${act?'active':''}"><a class="page-link" href="#" data-page="${p}">${txt}</a></li>`); }
    li('«', page-1, page<=1);
    for(let p=Math.max(1,page-3); p<=Math.min(last,page+3); p++) li(p,p,false,p===page);
    li('»', page+1, page>=last);
    $('#pagination .page-link').off('click').on('click', e=>{
        e.preventDefault();
        const p=+$(e.target).data('page');
        if(p>=1 && p<=last && p!==page){ state.page=p; load(); }
    });
    }
    function apply(){ state.page=1; state.status=$('#filterStatus').val(); state.per_page=+($('#perPage').val()||10); state.search=$('#searchBox').val().trim(); load(); }
    $('#btnReload').on('click', load);
    $('#filterStatus,#perPage').on('change', apply);
    $('#searchBox').on('keyup', e=>{ if(e.key==='Enter') apply(); });

    async function load(){ try{ const j=await fetchList(); renderRows(j.data); renderPagination(j.pagination); }catch(e){} }
    load();
    </script>

    @endsection
