        @extends('layouts.home')
        @section('title','Stock')

        @section('content')
        <div class="container-fluid flex-grow-1 container-p-y px-3">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
            <h4 class="fw-bold mb-1">Product Stock</h4>
            <small class="text-muted">Server-side filter & pagination</small>
            </div>
            <button id="btnReload" class="btn btn-outline-secondary"><i class="bx bx-refresh"></i> Reload</button>
        </div>

        {{-- Filters --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
            <div class="row g-3 align-items-end">
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
                <div class="col-md-3">
                <label class="form-label">Stock Status</label>
                <select id="filterStatus" class="form-select">
                    <option value="">All</option>
                    <option value="low">Low Stock</option>
                    <option value="ok">Normal</option>
                    <option value="empty">Empty</option>
                </select>
                </div>
                <div class="col-md-3">
                <label class="form-label">Search</label>
                <input id="searchBox" class="form-control" placeholder="Search product/warehouse...">
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

        {{-- Table --}}
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tbl">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Warehouse</th>
                        <th class="text-end">Initial</th>
                        <th class="text-end">In</th>
                        <th class="text-end">Out</th>
                        <th class="text-end">Final</th>
                        <th>Last Update</th>
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

        {{-- Layout tweaks --}}
        <style>
        .layout-page .content-wrapper { width: 100% !important; }
        .container-xxl, .content-wrapper > .container-xxl { max-width: 100% !important; }
        .card .table-responsive { overflow-x:auto; }
        #tbl { width:100%; }
        @media (max-width:1200px){ #tbl{ min-width:900px; } }
        </style>

        {{-- Deps --}}
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
        const ENDPOINT = "{{ route('stock.json') }}";

        let state = { page:1, per_page:10, search:'', status:'', warehouse_id:'', product_id:'' };

        async function fetchData(){
        const params = new URLSearchParams({
            page:state.page, per_page:state.per_page, search:state.search,
            status:state.status, warehouse_id:state.warehouse_id, product_id:state.product_id
        });
        const res = await fetch(ENDPOINT+'?'+params.toString(), { headers:{Accept:'application/json'} });
        if(!res.ok){
            const txt = await res.text();
            Swal.fire({icon:'error', title:'Error '+res.status, html:'<pre style="text-align:left">'+txt.replace(/[<>&]/g,s=>({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]))+'</pre>', width:800, position:'center'});
            throw new Error('HTTP '+res.status);
        }
        return res.json();
        }

        function renderRows(rows){
            const tbody = $('#tbl tbody'); tbody.empty();

            rows.forEach(r=>{
                const badge = (r.final_stock <= 0) ? '<span class="badge bg-danger">Empty</span>'
                        : (r.final_stock <= 10) ? '<span class="badge bg-warning text-dark">Low</span>'
                        : '<span class="badge bg-success">OK</span>';

                // Actions: Transfer, Adjust, Ledger (sesuaikan URL/data jika perlu)
                const actions = `
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary btn-transfer" data-id="${r.id}" title="Transfer">Transfer</button>
                    <button class="btn btn-outline-warning btn-adjust"  data-id="${r.id}" title="Adjust">Adjust</button>
                    <button class="btn btn-outline-secondary btn-ledger" data-id="${r.id}" title="Ledger">Ledger</button>
                </div>
                `;

                tbody.append(`
                <tr data-id="${r.id}">
                    <td>${r.id}</td>
                    <td>${r.product_name ?? ''}</td>
                    <td>${r.warehouse_name ?? ''}</td>

                    <td class="text-end">${Number(r.initial_ui ?? 0).toLocaleString('id-ID')}</td>
                    <td class="text-end">${Number(r.last_in    ?? 0).toLocaleString('id-ID')}</td>
                    <td class="text-end">${Number(r.stock_out  ?? 0).toLocaleString('id-ID')}</td>

                    <td class="text-end fw-bold">${Number(r.final_stock ?? 0).toLocaleString('id-ID')} ${badge}</td>

                    <td>${r.last_update ?? ''}</td>
                    <td class="text-end">${actions}</td>
                </tr>
                `);
            });
            }



        function renderPagination(pg){
        const ul = $('#pagination'); ul.empty();
        const page=pg.page, last=pg.last_page, per=pg.per_page, total=pg.total;
        const from = total ? ((page-1)*per+1) : 0;
        const to   = Math.min(page*per, total);
        $('#pageInfo').text(`Showing ${from}-${to} of ${total}`);

        function li(label, target, disabled=false, active=false){
            ul.append(`<li class="page-item ${disabled?'disabled':''} ${active?'active':''}">
            <a class="page-link" href="#" data-page="${target}">${label}</a></li>`);
        }
        li('«', page-1, page<=1);
        const win=3, start=Math.max(1,page-win), end=Math.min(last,page+win);
        for(let p=start;p<=end;p++) li(p,p,false,p===page);
        li('»', page+1, page>=last);

        $('#pagination .page-link').off('click').on('click', function(e){
            e.preventDefault();
            const t = Number($(this).data('page'));
            if(t>=1 && t<=last && t!==page){ state.page=t; load(); }
        });
        }

        async function load(){
        try{
            const json = await fetchData();
            renderRows(json.data);
            renderPagination(json.pagination);
        }catch(e){}
        }

        // Auto-apply filters (tanpa tombol Apply)
        function applyFromInputs(){
        state.page = 1;
        state.search       = $('#searchBox').val().trim();
        state.status       = $('#filterStatus').val();
        state.warehouse_id = $('#filterWarehouse').val();
        state.product_id   = $('#filterProduct').val();
        state.per_page     = Number($('#perPage').val() || 10);
        load();
        }

        $('#btnReload').on('click', load);
        $('#searchBox').on('keyup', e => { if(e.key==='Enter') applyFromInputs(); });
        $('#filterStatus,#filterWarehouse,#filterProduct,#perPage').on('change', applyFromInputs);

        // first load
        load();
        </script>
        @endsection
