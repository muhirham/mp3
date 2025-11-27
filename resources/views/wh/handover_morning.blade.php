    @extends('layouts.home')

    @section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
    // Expect: $me (user), $warehouses, $salesUsers, $products
    $me = $me ?? auth()->user();
    $today = now()->format('Y-m-d');
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Handover Pagi (Issue ke Sales)</h5>
        </div>

        <form id="formIssue" method="POST" action="{{ route('sales.handover.issue') }}">
        @csrf
        <div class="card-body">
            <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tanggal</label>
                <input type="date" name="handover_date" class="form-control" value="{{ $today }}" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Warehouse</label>
                @if(!empty($me?->warehouse_id))
                {{-- kalau user terikat gudang, kunci ke gudang itu --}}
                <input type="hidden" name="warehouse_id" value="{{ $me->warehouse_id }}">
                <input type="text" class="form-control" value="Gudang #{{ $me->warehouse_id }}" readonly>
                @else
                <select name="warehouse_id" class="form-select" required>
                    <option value="">— Pilih Gudang —</option>
                    @foreach(($warehouses ?? []) as $w)
                    <option value="{{ $w->id }}">{{ $w->name ?? ('Warehouse #'.$w->id) }}</option>
                    @endforeach
                </select>
                @endif
            </div>

            <div class="col-md-5">
                <label class="form-label">Sales</label>
                <select name="sales_id" class="form-select" required>
                <option value="">— Pilih Sales —</option>
                @foreach(($salesUsers ?? []) as $u)
                    <option value="{{ $u->id }}">{{ $u->name ?? ('User #'.$u->id) }}</option>
                @endforeach
                </select>
            </div>
            </div>

            <hr>

            <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Item yang Dibawa</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                <i class="bx bx-plus"></i> Tambah Item
            </button>
            </div>

            <div class="table-responsive">
            <table class="table table-sm align-middle" id="tblItems">
                <thead>
                <tr>
                    <th style="width:55%">Produk</th>
                    <th style="width:20%">Qty</th>
                    <th style="width:10%"></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                    <select class="form-select sel-product" name="items[0][product_id]" required>
                        <option value="">— Pilih Produk —</option>
                        @foreach(($products ?? []) as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->product_code }})</option>
                        @endforeach
                    </select>
                    </td>
                    <td>
                    <input type="number" name="items[0][qty]" class="form-control" min="1" placeholder="0" required>
                    </td>
                    <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
                        <i class="bx bx-trash"></i>
                    </button>
                    </td>
                </tr>
                </tbody>
            </table>
            </div>

        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <button type="reset" class="btn btn-light">Reset</button>
            <button class="btn btn-primary">Terbitkan Handover</button>
        </div>
        </form>
    </div>

    </div>
    @endsection

    @push('scripts')
    <script>
    (function(){
    const tbody = document.querySelector('#tblItems tbody');
    const btnAdd = document.getElementById('btnAddRow');

    function reindexRows(){
        [...tbody.querySelectorAll('tr')].forEach((tr, idx) => {
        tr.querySelectorAll('select, input').forEach(el => {
            if (el.name.includes('product_id')) el.name = `items[${idx}][product_id]`;
            if (el.name.includes('qty]')) el.name = `items[${idx}][qty]`;
        });
        });
    }

    btnAdd?.addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
        <td>
            <select class="form-select sel-product" required>
            <option value="">— Pilih Produk —</option>
            @foreach(($products ?? []) as $p)
                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->product_code }})</option>
            @endforeach
            </select>
        </td>
        <td><input type="number" class="form-control" min="1" placeholder="0" required></td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
            <i class="bx bx-trash"></i>
            </button>
        </td>`;
        tbody.appendChild(tr);
        reindexRows();
        // set proper names after append
        const sel = tr.querySelector('select'); sel.name = `items[${tbody.rows.length-1}][product_id]`;
        const qty = tr.querySelector('input'); qty.name = `items[${tbody.rows.length-1}][qty]`;
    });

    tbody.addEventListener('click', (e) => {
        if (e.target.closest('.btnRemoveRow')) {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 1) return; // minimal 1 baris
        e.target.closest('tr').remove();
        reindexRows();
        }
    });

    document.getElementById('formIssue')?.addEventListener('submit', function(e){
        // validasi minimal satu item dengan product & qty
        let ok = true;
        [...tbody.querySelectorAll('tr')].forEach(tr=>{
        const pid = tr.querySelector('select')?.value;
        const q   = parseInt(tr.querySelector('input')?.value||'0',10);
        if (!pid || !q || q<=0) ok = false;
        });
        if (!ok) {
        e.preventDefault();
        alert('Pastikan setiap item terisi produk & qty yang valid.');
        }
    });
    })();
    </script>

    @if (session('success'))
    <script>Swal.fire({icon:'success', title:'Berhasil', text:@json(session('success')), timer:2000, showConfirmButton:false});</script>
    @endif
    @if (session('error'))
    <script>Swal.fire({icon:'error', title:'Gagal', text:@json(session('error'))});</script>
    @endif
    @endpush
