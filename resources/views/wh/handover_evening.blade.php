    @extends('layouts.home')

    @section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
    // Expect: $handovers = list handover today (issued/waiting_otp), $me
    $me = $me ?? auth()->user();
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Rekonsiliasi Sore (OTP)</h5>
        </div>

        <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
            <label class="form-label">Pilih Handover</label>
            <select id="selHandover" class="form-select">
                <option value="">— Pilih —</option>
                @foreach(($handovers ?? []) as $h)
                <option value="{{ $h->id }}"
                    data-code="{{ $h->code }}"
                    data-status="{{ $h->status }}"
                    data-sales="{{ $h->sales_name ?? '' }}"
                    data-date="{{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }}">
                    {{ $h->code }} — {{ $h->sales_name ?? 'Sales #'.$h->sales_id }} — {{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }} ({{ strtoupper($h->status) }})
                </option>
                @endforeach
            </select>
            <div class="form-text">Tampilkan hanya status <b>issued / waiting_otp</b>.</div>
            </div>

            <div class="col-md-3">
            <button id="btnGenerateOtp" class="btn btn-outline-dark w-100" disabled>
                <i class="bx bx-key"></i> Generate OTP
            </button>
            </div>

            <div class="col-md-4 d-flex gap-2">
            <button id="btnLoadItems" class="btn btn-primary flex-grow-1" disabled>
                <i class="bx bx-download"></i> Load Items
            </button>
            <button id="btnClear" class="btn btn-light" disabled>Clear</button>
            </div>
        </div>

        <hr>

        <form id="formReconcile" method="POST">
            @csrf
            <input type="hidden" id="reconcileAction" value="">
            <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Kode Handover</label>
                <input type="text" id="txtCode" class="form-control" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sales</label>
                <input type="text" id="txtSales" class="form-control" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal</label>
                <input type="text" id="txtDate" class="form-control" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label">OTP</label>
                <input type="text" name="otp_code" id="otp_code" class="form-control" inputmode="numeric" pattern="[0-9]*" placeholder="6 digit" required>
                <div class="form-text">Minta ke user pemegang OTP.</div>
            </div>
            </div>

            <div class="table-responsive mt-3">
            <table class="table table-sm align-middle" id="tblReturn">
                <thead>
                <tr>
                    <th style="width:40%">Produk</th>
                    <th class="text-end" style="width:12%">Dibawa</th>
                    <th class="text-end" style="width:12%">Kembali (Good)</th>
                    <th class="text-end" style="width:12%">Kembali (Damaged)</th>
                    <th class="text-end" style="width:12%">Terjual</th>
                    <th style="width:12%"></th>
                </tr>
                </thead>
                <tbody>
                {{-- Diisi via JS dari endpoint items --}}
                </tbody>
            </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="submit" class="btn btn-primary" id="btnSubmit" disabled>Submit Rekonsiliasi</button>
            </div>
        </form>

        </div>
    </div>

    </div>
    @endsection

    @push('scripts')
    <script>
    // helper swal
    function swalOk(msg){ Swal.fire({icon:'success', title:'OK', text:msg, timer:1800, showConfirmButton:false}); }
    function swalErr(msg){ Swal.fire({icon:'error', title:'Gagal', text:msg}); }

    (function(){
    const sel    = document.getElementById('selHandover');
    const btnOTP = document.getElementById('btnGenerateOtp');
    const btnLoad= document.getElementById('btnLoadItems');
    const btnClr = document.getElementById('btnClear');
    const tblBody= document.querySelector('#tblReturn tbody');
    const txtCode= document.getElementById('txtCode');
    const txtSales=document.getElementById('txtSales');
    const txtDate= document.getElementById('txtDate');
    const form   = document.getElementById('formReconcile');
    const actionHolder = document.getElementById('reconcileAction');
    const btnSubmit = document.getElementById('btnSubmit');
    const otpInput  = document.getElementById('otp_code');

    function resetForm(){
        txtCode.value = ''; txtSales.value=''; txtDate.value='';
        tblBody.innerHTML = '';
        btnSubmit.disabled = true;
        otpInput.value = '';
    }

    function rowHtml(idx, it){
        const sold = Math.max((it.qty_dispatched||0) - (it.qty_returned_good||0) - (it.qty_returned_damaged||0), 0);
        return `
        <tr data-pid="${it.product_id}">
            <td>
            <input type="hidden" name="items[${idx}][product_id]" value="${it.product_id}">
            <div class="fw-semibold">${(it.product_name||'Produk #'+it.product_id)}</div>
            <div class="small text-muted">ID: ${it.product_id}</div>
            </td>
            <td class="text-end"><span class="lbl-dispatched">${it.qty_dispatched||0}</span></td>
            <td class="text-end">
            <input type="number" class="form-control form-control-sm inp-good" min="0" value="${it.qty_returned_good||0}"
                    name="items[${idx}][qty_returned_good]">
            </td>
            <td class="text-end">
            <input type="number" class="form-control form-control-sm inp-bad" min="0" value="${it.qty_returned_damaged||0}"
                    name="items[${idx}][qty_returned_damaged]">
            </td>
            <td class="text-end"><span class="lbl-sold">${sold}</span></td>
            <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-secondary btnMax">Max</button>
            </td>
        </tr>
        `;
    }

    function recomputeSold(tr){
        const dispatched = parseInt(tr.querySelector('.lbl-dispatched').textContent||'0',10);
        const good = parseInt(tr.querySelector('.inp-good').value||'0',10);
        const bad  = parseInt(tr.querySelector('.inp-bad').value||'0',10);
        const sold = Math.max(dispatched - good - bad, 0);
        tr.querySelector('.lbl-sold').textContent = sold;
    }

    tblBody.addEventListener('input', (e)=>{
        if (e.target.classList.contains('inp-good') || e.target.classList.contains('inp-bad')) {
        const tr = e.target.closest('tr');
        recomputeSold(tr);
        }
    });

    tblBody.addEventListener('click', (e)=>{
        if (e.target.closest('.btnMax')) {
        const tr = e.target.closest('tr');
        const dispatched = parseInt(tr.querySelector('.lbl-dispatched').textContent||'0',10);
        tr.querySelector('.inp-good').value = dispatched;
        tr.querySelector('.inp-bad').value  = 0;
        recomputeSold(tr);
        }
    });

    sel.addEventListener('change', ()=>{
        resetForm();
        const opt = sel.options[sel.selectedIndex];
        const id  = sel.value;
        if (!id) { btnOTP.disabled = true; btnLoad.disabled = true; btnClr.disabled=true; return; }

        txtCode.value = opt.dataset.code||'';
        txtSales.value= opt.dataset.sales||'';
        txtDate.value = opt.dataset.date||'';

        // set reconcile action url
        const urlRecon = @json(route('sales.handover.reconcile', 0)).replace('/0','/'+id);
        form.setAttribute('action', urlRecon);
        actionHolder.value = urlRecon;

        btnOTP.disabled  = false;
        btnLoad.disabled = true; // akan aktif tergantung status
        btnClr.disabled  = false;

        const status = (opt.dataset.status||'').toLowerCase();
        // waiting_otp → sudah generate otp, tinggal load items & submit
        // issued → bisa generate OTP lalu load items
        if (status === 'issued' || status === 'waiting_otp') {
        btnLoad.disabled = false;
        }
    });

    btnClr.addEventListener('click', (e)=>{ e.preventDefault(); resetForm(); });

    // Generate OTP (submit hard reload biar dapat flash message OTP)
    btnOTP.addEventListener('click', (e)=>{
        e.preventDefault();
        const id = sel.value;
        if (!id) return;
        const formTmp = document.createElement('form');
        formTmp.method = 'POST';
        formTmp.action = @json(route('sales.handover.otp', 0)).replace('/0','/'+id);
        formTmp.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
        document.body.appendChild(formTmp);
        formTmp.submit();
    });

    // Load items via endpoint JSON
    btnLoad.addEventListener('click', async (e)=>{
        e.preventDefault();
        const id = sel.value;
        if (!id) return;
        tblBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>';

        try{
        const url = @json(route('sales.handover.items', 0)).replace('/0','/'+id);
        const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        if (!res.ok) throw new Error('HTTP '+res.status);
        const json = await res.json();
        const items = json.items || [];
        if (!items.length) {
            tblBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada item.</td></tr>';
            btnSubmit.disabled = true;
            return;
        }
        let html = '';
        items.forEach((it, idx)=> html += rowHtml(idx, it));
        tblBody.innerHTML = html;
        btnSubmit.disabled = false;
        // fokus ke OTP bila status waiting_otp
        otpInput.focus();
        }catch(err){
        tblBody.innerHTML = '';
        btnSubmit.disabled = true;
        swalErr('Gagal memuat item handover. Pastikan route items sudah tersedia.');
        console.error(err);
        }
    });

    // Submit reconcile: validasi total tidak melebihi dibawa
    form.addEventListener('submit', (e)=>{
        const trs = [...tblBody.querySelectorAll('tr')];
        if (!trs.length) { e.preventDefault(); swalErr('Items kosong. Klik Load Items dulu.'); return; }
        let ok = true;
        trs.forEach(tr=>{
        const dispatched = parseInt(tr.querySelector('.lbl-dispatched').textContent||'0',10);
        const good = parseInt(tr.querySelector('.inp-good').value||'0',10);
        const bad  = parseInt(tr.querySelector('.inp-bad').value||'0',10);
        if (good<0 || bad<0 || (good+bad) > dispatched) ok = false;
        });
        if (!ok) { e.preventDefault(); swalErr('Qty kembali (good+damaged) tidak boleh melebihi qty dibawa.'); return; }
    });

    })();
    </script>

    @if (session('success'))
    <script>Swal.fire({icon:'success', title:'Berhasil', html:@json(session('success')), allowOutsideClick:true});</script>
    @endif
    @if (session('error'))
    <script>Swal.fire({icon:'error', title:'Gagal', text:@json(session('error'))});</script>
    @endif
    @endpush
