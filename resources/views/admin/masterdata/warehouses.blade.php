    @extends('layouts.home')
    @section('title','Warehouses')

    @section('content')
    @push('styles')
    <style>
        .swal2-container { z-index: 20000 !important; }
    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
        <h4 class="fw-bold mb-1">Warehouses</h4>
        <small class="text-muted">Animated cards • SweetAlert CRUD • Client pagination</small>
        </div>
        <div class="d-flex gap-2">
        <input id="searchBox" class="form-control" placeholder="Search warehouse..." style="min-width:260px">
        </div>
    </div>

    <div id="gridWarehouses" class="row g-4"></div>

    <div class="pager-wrap d-flex justify-content-center align-items-center mt-3">
        <nav><ul id="pager" class="pagination mb-0"></ul></nav>
    </div>
    </div>

    {{-- deps --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    .swal2-container { z-index: 20000 !important; }

    /* ==== Kartu putih polos ==== */
    :root{
        --card-bg: #ffffff;
        --card-border: #e5e7eb;
        --card-radius: 16px;
        --card-shadow: 0 10px 30px rgba(15,23,42,.08);
        --card-shadow-hover: 0 18px 45px rgba(15,23,42,.16);
        --muted:#6b7280;
    }

    html[data-color-scheme="dark"]{
        /* tetap kartu putih di dark mode biar konsisten */
        --card-bg: #ffffff;
        --card-border: #e5e7eb;
        --card-shadow: 0 10px 30px rgba(0,0,0,.45);
        --card-shadow-hover: 0 18px 45px rgba(0,0,0,.60);
        --muted:#6b7280;
    }

    .wh-card{ perspective:1100px; }
    .wh-inner{
        position:relative;width:100%;height:240px;transform-style:preserve-3d;
        transition:transform .65s cubic-bezier(.22,.61,.36,1),box-shadow .25s ease,translate .25s ease;
    }
    .wh-inner:hover{ translate:0 -4px; box-shadow:var(--card-shadow-hover); }
    .wh-inner.flipped{ transform:rotateY(180deg); }

    .wh-face{
        position:absolute; inset:0; backface-visibility:hidden;
        border-radius:var(--card-radius);
        background:var(--card-bg);
        box-shadow:var(--card-shadow);
        overflow:hidden;
        border:1px solid var(--card-border);
    }

    .wh-front{
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        text-align:center; padding:1.25rem;
    }
    .wh-front .meta{ font-size:.8rem; color:var(--muted); }
    .wh-front h5{
        margin:.25rem 0 .35rem;
        font-weight:800;
        color:#0f172a;
    }
    .small-dim{
        color:var(--muted);
        font-size:.88rem;
    }

    .wh-back{
        transform:rotateY(180deg);
        padding:1rem;
        background:#ffffff; /* belakang juga putih */
    }
    .wh-back .form-control{ height:36px; font-size:.9rem; }

    #gridWarehouses .col-sm-6.col-lg-4{ display:flex; }
    #gridWarehouses .wh-card{ width:100%; }

    .btn-3d{
        border-radius:50%; width:56px; height:56px;
        background: radial-gradient(120px 120px at 30% 30%, #34d399, #16a34a);
        color:#fff; border:none; box-shadow:0 10px 24px rgba(22,163,74,.35); transition:transform .2s, box-shadow .2s;
    }
    .btn-3d:hover{ transform:translateY(-3px); box-shadow:0 14px 34px rgba(22,163,74,.45); }

    .pager-wrap{ min-height:56px; }
    .pagination .page-link{ cursor:pointer; }

    /* Input di modal SweetAlert tetap jelas */
    .swal2-popup .form-control{
        background-color:#ffffff;
        color:#111827;
    }
    .swal2-popup .form-control::placeholder{
        color:#9ca3af;
        opacity:1;
    }
    </style>

    <script>
    $(function(){
    const baseUrl = @json(url('warehouses'));
    const CSRF   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const grid   = $('#gridWarehouses');
    const pager  = $('#pager');

    const Alert     = Swal.mixin({ buttonsStyling:false, customClass:{ confirmButton:'btn btn-primary', cancelButton:'btn btn-outline-secondary ms-2' }});
    const confirmBx = (t, m, c='Yes') => Alert.fire({ icon:'question', title:t, text:m, showCancelButton:true, confirmButtonText:c });
    const okBx      = (t='Success', m='') => Alert.fire({ icon:'success', title:t, text:m });
    const errBx     = (t='Error', m='') => Alert.fire({ icon:'error', title:t, html:m });
    const warnBx    = (t='Validation', m='') => Alert.fire({ icon:'warning', title:t, html:m });

    // Data awal dari controller
    let warehouses = @json(
        $warehouses instanceof \Illuminate\Pagination\AbstractPaginator ? $warehouses->items() : $warehouses
    ) || [];

    // kode default dari controller (buat fallback)
    const initialNextCode = @json($nextCode ?? 'WH-001');

    const pageSize = 9;
    let currentPage = 1;
    let keyword = '';

    function filtered(){
        if(!keyword) return warehouses;
        const q = keyword.toLowerCase();
        return warehouses.filter(w =>
            [w.warehouse_code, w.warehouse_name, w.address, w.note]
                .join(' ')
                .toLowerCase()
                .includes(q)
        );
    }

    function totalPages(){ return Math.max(1, Math.ceil(filtered().length / pageSize)); }
    function clamp(p){ return Math.min(Math.max(1,p), totalPages()); }

    function cardHTML(w, no){
        return `
        <div class="col-sm-6 col-lg-4">
            <div class="wh-card" data-id="${w.id}">
            <div class="wh-inner">
                <div class="wh-face wh-front">
                <div class="meta">${no} • ${w.warehouse_code || '-'}</div>
                <h5 class="fw-bold mb-1">${w.warehouse_name}</h5>
                <div class="small-dim">${w.address || '-'}</div>
                <div class="small-dim mt-1">${w.note || ''}</div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-outline-dark btn-sm js-edit">Edit</button>
                    <button class="btn btn-outline-danger btn-sm js-del">Del</button>
                </div>
                </div>
                <div class="wh-face wh-back">
                <form class="edit-form h-100 d-flex flex-column justify-content-between">
                    <div class="mb-2">
                    <input class="form-control mb-1 f-code" value="${w.warehouse_code||''}" placeholder="Code">
                    <input class="form-control mb-1 f-name" value="${w.warehouse_name||''}" placeholder="Name">
                    <input class="form-control mb-1 f-addr" value="${w.address||''}" placeholder="Address">
                    <input class="form-control mb-1 f-note" value="${w.note||''}" placeholder="Note">
                    </div>
                    <div class="d-flex gap-2">
                    <button class="btn btn-primary w-100 btn-save">Save</button>
                    <button type="button" class="btn btn-outline-secondary w-100 btn-cancel">Back</button>
                    </div>
                </form>
                </div>
            </div>
            </div>
        </div>`;
    }

    function render(){
        grid.empty();
        const list  = filtered();
        const start = (currentPage-1)*pageSize;
        const slice = list.slice(start, start+pageSize);
        slice.forEach((w,i)=> grid.append(cardHTML(w, start+i+1)));

        // Add-card
        grid.append(`
        <div class="col-sm-6 col-lg-4">
            <div class="wh-card add-card" id="cardAdd">
            <div class="wh-inner d-flex align-items-center justify-content-center">
                <button class="btn-3d" title="Add warehouse">
                <i class="bx bx-plus fs-3"></i>
                </button>
            </div>
            </div>
        </div>
        `);

        // pager
        pager.empty();
        const tp = totalPages();
        pager.append(`<li class="page-item ${currentPage===1?'disabled':''}">
                        <a class="page-link" data-goto="${currentPage-1}">«</a>
                        </li>`);
        for(let p=1;p<=tp;p++){
            pager.append(`<li class="page-item ${p===currentPage?'active':''}">
                            <a class="page-link" data-goto="${p}">${p}</a>
                        </li>`);
        }
        pager.append(`<li class="page-item ${currentPage===tp?'disabled':''}">
                        <a class="page-link" data-goto="${currentPage+1}">»</a>
                        </li>`);
    }

    function goto(p){ currentPage = clamp(p); render(); }

    // hitung kode berikutnya dari data yg ada (fallback kalo user sudah tambah banyak)
    function suggestNextCode(){
        if (!warehouses.length) return initialNextCode || 'WH-001';
        const maxId = Math.max.apply(null, warehouses.map(w => w.id || 0));
        const num   = maxId + 1;
        return 'WH-' + String(num).padStart(3,'0');
    }

    // init
    render();

    // Search
    $('#searchBox').on('input', function(){
        keyword = this.value || '';
        currentPage = 1;
        render();
    });

    // Pager click
    pager.on('click','.page-link', function(){
        const p = parseInt(this.dataset.goto,10);
        if(!isNaN(p)) goto(p);
    });

    // flip edit
    grid.on('click','.js-edit', function(e){
        e.stopPropagation();
        const inner=$(this).closest('.wh-inner');
        inner.addClass('flipped');
        anime({ targets: inner[0], rotateY:[0,180], duration:600, easing:'easeInOutQuart' });
    });
    grid.on('click','.btn-cancel', function(e){
        e.preventDefault();
        const inner=$(this).closest('.wh-inner');
        anime({
            targets: inner[0],
            rotateY:[180,0],
            duration:600,
            easing:'easeInOutQuart',
            complete:()=>inner.removeClass('flipped')
        });
    });

    // DELETE
    grid.on('click','.js-del', async function(e){
        e.stopPropagation();
        const id = $(this).closest('.wh-card').data('id');
        const wh = warehouses.find(x=>x.id==id);
        const ok = await confirmBx('Delete?', `Delete ${wh?.warehouse_name||'warehouse'}?`, 'Delete');
        if(!ok.isConfirmed) return;

        try{
            const res = await fetch(`${baseUrl}/${id}`, {
            method:'DELETE',
            headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN': CSRF }
            });
            if(!res.ok) throw new Error('Failed: '+res.status);
            warehouses = warehouses.filter(x=>x.id!=id);

            currentPage = clamp(currentPage);
            render();
            await okBx('Deleted', 'Warehouse removed.');
        }catch(err){ errBx('Error', err.message); }
    });

    // UPDATE
    grid.on('submit','.edit-form', async function(e){
        e.preventDefault();
        const id = $(this).closest('.wh-card').data('id');
        const payload = {
            warehouse_code: $(this).find('.f-code').val().trim(),
            warehouse_name: $(this).find('.f-name').val().trim(),
            address:        $(this).find('.f-addr').val().trim(),
            note:           $(this).find('.f-note').val().trim(),
        };
        if(!payload.warehouse_code || !payload.warehouse_name)
            return warnBx('Validation','Code & Name are required.');

        try{
            const res = await fetch(`${baseUrl}/${id}`, {
            method:'PUT',
            headers:{
                'Accept':'application/json',
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':CSRF
            },
            body: JSON.stringify(payload)
            });

            if(res.status===422){
            const j=await res.json();
            return warnBx('Validation', Object.values(j.errors||{}).flat().join('<br>'));
            }
            if(!res.ok) throw new Error('Failed: '+res.status);

            const j = await res.json(); // {row: {...}}
            warehouses = warehouses.map(x=> x.id==id ? j.row : x);
            render();
            await okBx('Saved','Warehouse updated.');
        }catch(err){ errBx('Error', err.message); }
    });

    // CREATE
    grid.on('click','#cardAdd', function(){
        const defaultCode = suggestNextCode();

        Alert.fire({
            title:'Add Warehouse',
            html:`
            <input id="sw_code" class="form-control mb-2" placeholder="Code (auto / manual)" value="${defaultCode}">
            <input id="sw_name" class="form-control mb-2" placeholder="Name">
            <input id="sw_addr" class="form-control mb-2" placeholder="Address">
            <input id="sw_note" class="form-control" placeholder="Note">
            <small class="text-muted d-block mt-2">
                Kosongkan kolom <b>Code</b> jika ingin auto-generate.
            </small>
            `,
            showCancelButton:true,
            confirmButtonText:'Save',
            preConfirm:()=>{
            const code = $('#sw_code').val().trim();
            const name = $('#sw_name').val().trim();
            if(!name){
                Alert.showValidationMessage('Name required');
                return false;
            }
            return {
                warehouse_code: code || null,
                warehouse_name: name,
                address: $('#sw_addr').val().trim(),
                note:    $('#sw_note').val().trim(),
            };
            }
        }).then(async r=>{
            if(!r.isConfirmed) return;
            try{
            const res = await fetch(baseUrl, {
                method:'POST',
                headers:{
                'Accept':'application/json',
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':CSRF
                },
                body: JSON.stringify(r.value)
            });

            if(res.status===422){
                const j=await res.json();
                return warnBx('Validation', Object.values(j.errors||{}).flat().join('<br>'));
            }
            if(!res.ok) throw new Error('Failed: '+res.status);

            const j = await res.json(); // {row: {...}}
            warehouses.push(j.row);
            if(keyword){ keyword=''; $('#searchBox').val(''); }
            currentPage = Math.ceil(warehouses.length / pageSize);
            render();
            await okBx('Added','New warehouse created.');
            }catch(err){ errBx('Error', err.message); }
        });
    });

    console.log('[Warehouses] Ready: client paging + CRUD via fetch (JSON).');
    });
    </script>
    @endsection
