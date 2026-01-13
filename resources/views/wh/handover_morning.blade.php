        @extends('layouts.home')

        @section('content')
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $me    = $me ?? auth()->user();
            $today = now()->format('Y-m-d');
        @endphp

        <div class="container-xxl flex-grow-1 container-p-y">

        {{-- FORM BUAT HANDOVER PAGI + KIRIM OTP --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Handover Pagi – Buat & Kirim OTP</h5>
            </div>

            <form id="formIssue" method="POST" action="{{ route('sales.handover.morning.store') }}">
            @csrf
            <div class="card-body">
                <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="handover_date" class="form-control"
                        value="{{ old('handover_date', $today) }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Warehouse</label>
                    @if(!empty($me?->warehouse_id))
                    <input type="hidden" name="warehouse_id" value="{{ $me->warehouse_id }}">
                    <input type="text" class="form-control"
                            value="{{ $me->warehouse?->warehouse_name ?? ('Gudang #'.$me->warehouse_id) }}"
                            readonly>
                    @else
                    <select name="warehouse_id" class="form-select" required>
                        <option value="">— Pilih Gudang —</option>
                        @foreach(($warehouses ?? []) as $w)
                        <option value="{{ $w->id }}" @selected(old('warehouse_id') == $w->id)>
                            {{ $w->name }}
                        </option>
                        @endforeach
                    </select>
                    @endif
                </div>

                <div class="col-md-5">
                    <label class="form-label">Sales</label>
                    <select name="sales_id" class="form-select" required>
                    <option value="">— Pilih Sales —</option>
                    @foreach(($salesUsers ?? []) as $u)
                        <option value="{{ $u->id }}" @selected(old('sales_id') == $u->id)>
                        {{ $u->name ?? ('User #'.$u->id) }}
                        @if($u->email) — {{ $u->email }} @endif
                        </option>
                    @endforeach
                    </select>
                </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Item yang Dibawa Pagi</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                    <i class="bx bx-plus"></i> Tambah Item
                </button>
                </div>

                <div class="table-responsive">
                <table class="table table-sm align-middle" id="tblItems">
                    <thead>
                    <tr>
                    <th style="width:45%">Produk</th>
                    <th style="width:10%">Qty</th>
                    <th style="width:15%">Harga</th>
                    <th style="width:15%">Diskon</th>
                    <th style="width:20%">Total</th>

                    <th style="width:5%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                    <td>
                        <select class="form-select sel-product" name="items[0][product_id]" required>
                        <option value="">— Pilih Produk —</option>
                        @foreach(($products ?? []) as $p)
                            <option value="{{ $p->id }}"
                                    data-price="{{ (int) $p->selling_price }}"
                                    @selected(optional(old('items.0'))['product_id'] == $p->id)>
                            {{ $p->name }} ({{ $p->product_code }})
                            </option>
                        @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" name="items[0][qty]"
                            class="form-control inp-qty"
                            min="1" value="{{ old('items.0.qty', 1) }}" required>
                    </td>
                    <td>
                        <input type="text" class="form-control inp-price" readonly>
                    </td>
                    <td>
                        <input type="number"
                        class="form-control inp-discount"
                            name="items[0][discount_per_unit]"
                            min="0"
                            value="{{ old('items.0.discount_per_unit', 0) }}">
                    </td>
                    <td>
                        <input type="text" class="form-control inp-total" readonly>
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

                <div class="text-end fw-bold mt-2">
                Estimasi Grand Total: <span id="grand_total">0</span>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-light">Reset</button>
                <button class="btn btn-primary">Simpan Draft &amp; Kirim OTP Pagi</button>
            </div>
            </form>
        </div>

        {{-- VERIFIKASI OTP PAGI --}}
        <div class="card">
            <div class="card-header">
            <h5 class="mb-0 fw-bold">Verifikasi OTP Pagi</h5>
            </div>
            <div class="card-body">
            <form method="POST" action="{{ route('sales.handover.morning.verify') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                <label class="form-label">Pilih Handover (Waiting OTP Pagi)</label>
                <select name="handover_id" class="form-select" required>
                    <option value="">— Pilih —</option>
                    @foreach(($waitingMorning ?? []) as $h)
                    <option value="{{ $h->id }}">
                        {{ $h->code }} — {{ $h->sales->name ?? 'Sales #'.$h->sales_id }}
                        ({{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }})
                    </option>
                    @endforeach
                </select>
                </div>
                <div class="col-md-3">
                <label class="form-label">OTP Pagi</label>
                <input type="text" name="otp_code" class="form-control"
                        inputmode="numeric" pattern="[0-9]*" placeholder="6 digit" required>
                </div>
                <div class="col-md-4">
                <button type="submit" class="btn btn-success w-100 mt-3 mt-md-0">
                    Verifikasi OTP &amp; Lock Stok
                </button>
                </div>
            </form>
            <div class="form-text mt-2">
                Setelah OTP pagi valid, stok gudang akan dipindah ke stok sales dan nilai bawaan tersimpan.
            </div>
            </div>
        </div>

        </div>
        @endsection

        @push('scripts')
    <script>
    (function(){
    const tbody      = document.querySelector('#tblItems tbody');
    const btnAddRow  = document.getElementById('btnAddRow');
    const grandLabel = document.getElementById('grand_total');

    function formatIdr(num){
        return new Intl.NumberFormat('id-ID').format(num || 0);
    }

    // reindex semua row supaya name items[0]..items[n] selalu rapi
    function reindexRows(){
        [...tbody.querySelectorAll('tr')].forEach((tr, idx) => {
        const sel = tr.querySelector('.sel-product');
        const qty = tr.querySelector('.inp-qty');
        if (sel) sel.name = `items[${idx}][product_id]`;
        if (qty) qty.name = `items[${idx}][qty]`;
        const disc = tr.querySelector('.inp-discount');
        if (disc) disc.name = `items[${idx}][discount_per_unit]`;
        });
    }

    function recomputeRow(tr){
        const qtyInput   = tr.querySelector('.inp-qty');
        const priceInput = tr.querySelector('.inp-price');
        const totalInput = tr.querySelector('.inp-total');
        const discountEl = tr.querySelector('.inp-discount');
        const select     = tr.querySelector('.sel-product');

        const qty      = parseInt(qtyInput.value || '0', 10);
        const price    = parseInt(select.selectedOptions[0]?.dataset.price || '0', 10);
        const discount = parseInt(discountEl?.value || '0', 10);

        const netPrice = Math.max(price - discount, 0);
        const total    = qty * netPrice;

        priceInput.value = price ? formatIdr(price) : '0';
        totalInput.value = formatIdr(total);
    }


        function recomputeGrand(){
            let grand = 0;
            [...tbody.querySelectorAll('tr')].forEach(tr => {
                const select   = tr.querySelector('.sel-product');
                const qty      = parseInt(tr.querySelector('.inp-qty').value || '0', 10);
                const price    = parseInt(select.selectedOptions[0]?.dataset.price || '0', 10);
                const discount = parseInt(tr.querySelector('.inp-discount')?.value || '0', 10);

                const netPrice = Math.max(price - discount, 0);
                grand += qty * netPrice;
            });
            grandLabel.textContent = formatIdr(grand);
        }


    tbody.addEventListener('change', (e)=>{
        if (e.target.classList.contains('sel-product') ||
            e.target.classList.contains('inp-qty')) {
        const tr = e.target.closest('tr');
        recomputeRow(tr);
        recomputeGrand();
        }
        if (e.target.classList.contains('inp-discount')) {
        const tr = e.target.closest('tr');
        recomputeRow(tr);
        recomputeGrand();
}

    });

    tbody.addEventListener('input', (e)=>{
        if (e.target.classList.contains('inp-qty')) {
        const tr = e.target.closest('tr');
        recomputeRow(tr);
        recomputeGrand();
        }
        if (e.target.classList.contains('inp-discount')) {
        const tr = e.target.closest('tr');
        recomputeRow(tr);
        recomputeGrand();
        }

    });

    // === FIX DI SINI: row baru langsung dikasih name items[index] ===
    btnAddRow?.addEventListener('click', () => {
        const newIndex = tbody.querySelectorAll('tr').length;

        const tr = document.createElement('tr');
        tr.innerHTML = `
        <td>
            <select class="form-select sel-product"
                    name="items[${newIndex}][product_id]" required>
            <option value="">— Pilih Produk —</option>
            @foreach(($products ?? []) as $p)
                <option value="{{ $p->id }}" data-price="{{ (int) $p->selling_price }}">
                {{ $p->name }} ({{ $p->product_code }})
                </option>
            @endforeach
            </select>
        </td>
        <td>
            <input type="number"
                class="form-control inp-qty"
                name="items[${newIndex}][qty]"
                min="1" value="1" required>
        </td>
        <td><input type="text" class="form-control inp-price" readonly></td>
        <td>
            <input type="number"
                class="form-control inp-discount"
                name="items[${newIndex}][discount_per_unit]"
                min="0" value="0">
        </td>
        <td><input type="text" class="form-control inp-total" readonly></td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
            <i class="bx bx-trash"></i>
            </button>
        </td>`;
        tbody.appendChild(tr);
        reindexRows();
        recomputeRow(tr);
        recomputeGrand();
    });

    tbody.addEventListener('click', (e)=>{
        if (e.target.closest('.btnRemoveRow')) {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 1) return;
        e.target.closest('tr').remove();
        reindexRows();
        recomputeGrand();
        }
    });

    document.getElementById('formIssue')?.addEventListener('submit', function(e){
        let ok = true;
        [...tbody.querySelectorAll('tr')].forEach(tr => {
        const pid = tr.querySelector('.sel-product')?.value;
        const qty = parseInt(tr.querySelector('.inp-qty')?.value || '0', 10);
        if (!pid || !qty || qty <= 0) ok = false;
        });
        if (!ok) {
        e.preventDefault();
        alert('Pastikan setiap item terisi produk & qty yang valid.');
        }
    });

    // init awal (row pertama)
    reindexRows();
    [...tbody.querySelectorAll('tr')].forEach(tr => recomputeRow(tr));
    recomputeGrand();
    })();
    </script>

    @if (session('success'))
    <script>
    Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    html: {!! json_encode(session('success')) !!},
    confirmButtonText: 'OK',
    allowOutsideClick: true,
    });
    </script>
    @endif

    @if (session('error'))
    <script>
    Swal.fire({
    icon: 'error',
    title: 'Gagal',
    html: {!! json_encode(session('error')) !!},
    });
    </script>
    @endif
    @endpush
