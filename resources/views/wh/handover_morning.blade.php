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
            <h5 class="mb-0 fw-bold">Morning Handover – Create & Send OTP</h5>
            <small id="handoverInfo" class="text-muted"></small>
            </div>

            <form id="formIssue" method="POST" action="{{ route('sales.handover.morning.store') }}">
            @csrf
            @if($selectedHandover)
                <input type="hidden" name="handover_id" value="{{ $selectedHandover->id }}">
            @endif
            <div class="card-body">
                <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="handover_date" class="form-control"
                        value="{{ old('handover_date', $today) }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Warehouse</label>
                    @if(!empty($me?->warehouse_id))
                    <input type="hidden" name="warehouse_id" value="{{ $me->warehouse_id }}">
                    <input type="text" class="form-control"
                            value="{{ $me->warehouse?->warehouse_name ?? ('Warehouse #'.$me->warehouse_id) }}"
                            readonly>
                    @else
                    <select name="warehouse_id" class="form-select" required>
                        <option value="">— Select Warehouse —</option>
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
                    <option value="">— Select Sales —</option>
                    @foreach(($salesUsers ?? []) as $u)
                        <option value="{{ $u->id }}"
                        @selected(($selectedHandover?->sales_id ?? old('sales_id')) == $u->id)>
                        {{ $u->name ?? ('User #'.$u->id) }}
                        @if($u->email) — {{ $u->email }} @endif
                        </option>
                    @endforeach
                    </select>
                </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Items Issued (Morning)</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                    <i class="bx bx-plus"></i> Add Item
                </button>
                </div>

                <div class="table-responsive">
                <table class="table table-sm align-middle" id="tblItems">
                    <thead>
                    <tr>
                    <th style="width:45%">Product</th>
                    <th style="width:10%">Qty</th>
                    <th style="width:15%">Price</th>
                    <th style="width:15%">Discount</th>
                    <th style="width:20%">Total</th>

                    <th style="width:5%"></th>
                    </tr>
                    </thead>
                    <tbody>
                        @php
                            $draftItems = $selectedHandover?->items ?? collect([null]);
                        @endphp

                        @foreach($draftItems as $i => $draft)
                    <tr>
                    <td>
                        <select class="form-select sel-product" name="items[{{ $i }}][product_id]" required>
                        <option value="">— Select Product —</option>
                        @foreach(($products ?? []) as $p)
                            <option value="{{ $p->id }}"
                                    data-price="{{ (int) $p->selling_price }}"
                                    @selected($draft?->product_id == $p->id)>
                            {{ $p->name }} ({{ $p->product_code }}) | WH Stock: {{ number_format((int) ($p->warehouse_stock ?? 0), 0, '.', ',') }}
                            </option>
                        @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number"
                            name="items[{{ $i }}][qty]"
                            class="form-control inp-qty"
                            min="1"
                            value="{{ $draft?->qty_start ?? 1 }}"
                            required>
                    </td>
                    <td>
                        <input type="text" class="form-control inp-price" readonly>
                    </td>
                    <td>
                        <input type="number"
                            class="form-control inp-discount"
                            name="items[{{ $i }}][discount_per_unit]"
                            min="0"
                            value="{{ $draft?->discount_per_unit ?? 0 }}">
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
                    @endforeach
                    </tbody>
                </table>
                </div>

                <div class="text-end fw-bold mt-2">
                Estimated Grand Total: <span id="grand_total">0</span>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-light">Reset</button>
                <button class="btn btn-primary">
                    {{ $selectedHandover ? 'Update Draft & Send Morning OTP' : 'Save Draft & Send Morning OTP' }}
                </button>
            </div>
            </form>
        </div>

        {{-- VERIFIKASI OTP PAGI --}}
        <div id="morningOtpContainer">
            <div class="card">
                <div class="card-header">
                <h5 class="mb-0 fw-bold">Verify Morning OTP</h5>
                </div>
                <div class="card-body">
                <form method="POST" action="{{ route('sales.handover.morning.verify') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-5">
                    <label class="form-label">Select Handover (Waiting Morning OTP)</label>
                    <select name="handover_id" class="form-select" required>
                        <option value="">— Select —</option>
                        @foreach(($waitingMorning ?? []) as $h)
                        <option value="{{ $h->id }}"
                        @selected(($selectedHandoverId ?? null) == $h->id)>
                            {{ $h->code }} — {{ $h->sales->name ?? 'Sales #'.$h->sales_id }}
                            ({{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }})
                        </option>
                        @endforeach
                    </select>
                    </div>
                    <div class="col-md-3">
                    <label class="form-label">Morning OTP</label>
                    <input type="text" name="otp_code" class="form-control"
                            inputmode="numeric" pattern="[0-9]*" placeholder="6 digits" required>
                    </div>
                    <div class="col-md-4">
                    <button type="submit" class="btn btn-success w-100 mt-3 mt-md-0">
                        Verify OTP &amp; Lock Stock
                    </button>
                    </div>
                </form>
                <div class="form-text mt-2">
                    Once the morning OTP is valid, warehouse stock will be transferred to sales stock and initial values will be saved.
                </div>
                </div>
            </div>
        </div>

        </div>
        @endsection

        @push('scripts')
    <script>
        const warehouseSelect = document.querySelector('select[name="warehouse_id"]');
        const salesSelect     = document.querySelector('select[name="sales_id"]');
        const infoLabel       = document.getElementById('handoverInfo');

        let productsContext   = @json($products ?? []);

        // Fungsi Helper buat update Produk di semua baris
        function updateAllProductSelects(){
            const selects = document.querySelectorAll('.sel-product');
            selects.forEach(sel => {
                const currentVal = sel.value;
                let html = `<option value="">— Select Product —</option>`;
                productsContext.forEach(p => {
                    const stock = parseInt(p.warehouse_stock || 0);
                    const stockFmt = new Intl.NumberFormat('en-US').format(stock);
                    html += `<option value="${p.id}" data-price="${p.selling_price}" ${currentVal == p.id ? 'selected' : ''}>
                        ${p.name} (${p.product_code}) | WH Stock: ${stockFmt}
                    </option>`;
                });
                sel.innerHTML = html;
            });
        }

        async function refreshWarehouseContext(whId){
            if(!whId) return;

            try {
                // 1. Fetch Sales
                const resSales = await fetch(`{{ route('sales.handover.ajax-sales') }}?warehouse_id=${whId}`);
                const dataSales = await resSales.json();
                
                let salesHtml = `<option value="">— Select Sales —</option>`;
                (dataSales.items || []).forEach(u => {
                    salesHtml += `<option value="${u.id}">${u.name} — ${u.email}</option>`;
                });
                salesSelect.innerHTML = salesHtml;

                // 2. Fetch Products (untuk update STOK per WH)
                const resProds = await fetch(`{{ route('sales.handover.ajax-products') }}?warehouse_id=${whId}`);
                const dataProds = await resProds.json();
                productsContext = dataProds.items || [];

                updateAllProductSelects();

            } catch(e) {
                console.error('Failed to sync warehouse context', e);
            }
        }

        warehouseSelect?.addEventListener('change', function(){
            refreshWarehouseContext(this.value);
        });

        salesSelect?.addEventListener('change', async function(){
            
            const salesId = this.value;
            
            infoLabel.textContent = '';

            if(!salesId) return;
            const draftRes = await fetch(`/sales/${salesId}/draft-handover`);
            const dataDraft = await draftRes.json();

            if(dataDraft.handover_id){
                window.location.href = `/sales/handover/morning?handover_id=${dataDraft.handover_id}`;
                return;
            }

            try{
                const res = await fetch(`/sales/${salesId}/active-handover-count`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await res.json();

                if(data.active >= data.limit){
                    infoLabel.innerHTML = `<span class="text-danger">
                        This salesperson has ${data.active} active handovers (max ${data.limit}). Please complete previous handovers immediately!!!
                    </span>`;
                }else{
                    infoLabel.innerHTML = `
                        Active handovers: <strong>${data.active}</strong> •
                        This will be handover <strong>#${data.next}</strong>
                    `;
                }

            }catch(e){
                infoLabel.textContent = 'Failed to load sales handover info';
            }
        });

    (function(){
    const tbody      = document.querySelector('#tblItems tbody');
    const btnAddRow  = document.getElementById('btnAddRow');
    const grandLabel = document.getElementById('grand_total');

    function formatIdr(num){
        return new Intl.NumberFormat('en-US').format(num || 0);
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
            <option value="">— Select Product —</option>
            ${productsContext.map(p => {
                 const stock = parseInt(p.warehouse_stock || 0);
                 const stockFmt = new Intl.NumberFormat('en-US').format(stock);
                 return `<option value="${p.id}" data-price="${p.selling_price}">
                    ${p.name} (${p.product_code}) | WH Stock: ${stockFmt}
                 </option>`;
            }).join('')}
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

        // 🔥 AJAX Submission buat Create HDO Pagi
        document.getElementById('formIssue')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = this;

            // Optional: Tambahin validasi frontend lagi di sini cok kalau mau
            let ok = true;
            [...tbody.querySelectorAll('tr')].forEach(tr => {
                const pid = tr.querySelector('.sel-product')?.value;
                const qty = parseInt(tr.querySelector('.inp-qty')?.value || '0', 10);
                if (!pid || !qty || qty <= 0) ok = false;
            });
            if (!ok) {
                Swal.fire({ icon: 'error', title: 'Invalid Items', text: 'Ensure every item has a valid product and quantity.' });
                if (window.resetSubmitButton) window.resetSubmitButton(form);
                return;
            }

            const formData = new FormData(form);
            
            Swal.fire({
                title: 'Creating Handover...',
                text: 'Please wait while we prepare the items and OTP.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({ icon: 'success', title: 'Success', text: result.message });
                    // Segerin list HDO nunggu verifikasi di bawah
                    if (window.refreshMorningStatus) await window.refreshMorningStatus();
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: result.message || 'Error occurred.' });
                }
            } catch (err) {
                console.error('AJAX Error:', err);
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Failed to reach server.' });
            } finally {
                if (window.resetSubmitButton) window.resetSubmitButton(form);
            }
        });

        // 🔥 AJAX Submission buat Verifikasi OTP Pagi (Warehouse Side)
        document.addEventListener('submit', async function(e) {
            const form = e.target;
            if (!form.action.includes('morning/verify')) return; // Tangkap form verifikasi
            
            e.preventDefault();

            const formData = new FormData(form);
            
            Swal.fire({
                title: 'Verifying OTP...',
                text: 'Processing stock movement and locking data.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({ icon: 'success', title: 'Verified!', text: result.message });
                    // Segerin UI biar dropdown nunggu verifikasi update
                    if (window.refreshMorningStatus) await window.refreshMorningStatus();
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: result.message || 'Invalid OTP code.' });
                }
            } catch (err) {
                console.error('AJAX Error:', err);
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Failed to reach server.' });
            } finally {
                if (window.resetSubmitButton) window.resetSubmitButton(form);
            }
        });

        reindexRows();
        [...tbody.querySelectorAll('tr')].forEach(tr => recomputeRow(tr));
        recomputeGrand();
    })();
    </script>

    @if (session('success'))
    <script>
    Swal.fire({
    icon: 'success',
    title: 'Success',
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
    title: 'Failed',
    html: {!! json_encode(session('error')) !!},
    });
    </script>
    @endif

    <script>
        // 🔥 Fungsi Global buat Real-time
        window.refreshMorningStatus = async function() {
            try {
                const res = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newContainer = doc.querySelector('#morningOtpContainer');
                if (newContainer) {
                    document.querySelector('#morningOtpContainer').innerHTML = newContainer.innerHTML;
                }
            } catch (err) { console.error('Failed to refresh morning status:', err); }
        };
    </script>
    @endpush
