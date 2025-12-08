@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    /** @var \App\Models\User $me */
    $me = $me ?? auth()->user();
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Rekonsiliasi Sore (OTP)</h5>
        </div>

        <div class="card-body">

            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-6">
                    <label class="form-label">Pilih Handover</label>
                    <select id="selHandover" class="form-select">
                        <option value="">— Pilih —</option>
                        @foreach(($handovers ?? []) as $h)
                            <option value="{{ $h->id }}"
                                    data-code="{{ $h->code }}"
                                    data-status="{{ $h->status }}"
                                    data-sales="{{ $h->sales_name ?? '' }}"
                                    data-date="{{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }}">
                                {{ $h->code }} — {{ $h->sales_name ?? ('Sales #'.$h->sales_id) }}
                                — {{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }}
                                ({{ strtoupper($h->status) }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Hanya menampilkan handover dengan status <b>issued</b>.</div>
                </div>

                <div class="col-md-3">
                    <button id="btnLoadItems" class="btn btn-primary w-100" disabled>
                        <i class="bx bx-download"></i> Load Items
                    </button>
                </div>

                <div class="col-md-3">
                    <button id="btnClear" class="btn btn-light w-100" disabled>Clear</button>
                </div>
            </div>

            <hr>

            <form id="formReconcile" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row g-2 mb-3">
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
                        <label class="form-label">OTP (dari email sales)</label>
                        <input type="text"
                               name="otp_code"
                               id="otp_code"
                               class="form-control"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               placeholder="6 digit"
                               required>
                        <div class="form-text">Minta kode OTP dari email yang diterima sales.</div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Setor Tunai (Rp)</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control"
                               name="cash_amount" id="cash_amount">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Setor Transfer (Rp)</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control"
                               name="transfer_amount" id="transfer_amount">
                        <div class="form-text">Isi 0 kalau tidak ada transfer.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bukti Transfer (jika ada)</label>
                        <input type="file" class="form-control"
                               name="transfer_proof" accept="image/*">
                        <div class="form-text">Wajib diisi kalau ada setor transfer (jpg/png, max 2MB).</div>
                    </div>
                </div>

                <div class="table-responsive">
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
                        {{-- Diisi via JS --}}
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="submit" class="btn btn-primary" id="btnSubmit" disabled>
                        Submit Rekonsiliasi
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {

    function swalErr(msg){ Swal.fire({icon:'error', title:'Gagal', html:msg}); }

    const sel        = document.getElementById('selHandover');
    const btnLoad    = document.getElementById('btnLoadItems');
    const btnClear   = document.getElementById('btnClear');
    const tblBody    = document.querySelector('#tblReturn tbody');
    const txtCode    = document.getElementById('txtCode');
    const txtSales   = document.getElementById('txtSales');
    const txtDate    = document.getElementById('txtDate');
    const form       = document.getElementById('formReconcile');
    const btnSubmit  = document.getElementById('btnSubmit');
    const otpInput   = document.getElementById('otp_code');

    function resetForm() {
        txtCode.value  = '';
        txtSales.value = '';
        txtDate.value  = '';
        otpInput.value = '';
        document.getElementById('cash_amount').value = '';
        document.getElementById('transfer_amount').value = '';
        const proofInput = document.querySelector('input[name="transfer_proof"]');
        if (proofInput) proofInput.value = '';
        tblBody.innerHTML = '';
        btnSubmit.disabled = true;
    }

    function rowHtml(idx, it) {
        const sold = Math.max(
            (it.qty_dispatched || 0) -
            (it.qty_returned_good || 0) -
            (it.qty_returned_damaged || 0),
            0
        );
        return `
        <tr data-pid="${it.product_id}">
            <td>
                <input type="hidden" name="items[${idx}][product_id]" value="${it.product_id}">
                <div class="fw-semibold">${it.product_name || ('Produk #'+it.product_id)}</div>
                <div class="small text-muted">${it.product_code || ''}</div>
            </td>
            <td class="text-end">
                <span class="lbl-dispatched">${it.qty_dispatched || 0}</span>
            </td>
            <td class="text-end">
                <input type="number"
                       class="form-control form-control-sm inp-good"
                       min="0"
                       value="${it.qty_returned_good || 0}"
                       name="items[${idx}][qty_returned_good]">
            </td>
            <td class="text-end">
                <input type="number"
                       class="form-control form-control-sm inp-bad"
                       min="0"
                       value="${it.qty_returned_damaged || 0}"
                       name="items[${idx}][qty_returned_damaged]">
            </td>
            <td class="text-end">
                <span class="lbl-sold">${sold}</span>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary btnMax">Max</button>
            </td>
        </tr>
        `;
    }

    function recomputeSold(tr) {
        const dispatched = parseInt(tr.querySelector('.lbl-dispatched').textContent || '0', 10);
        const good = parseInt(tr.querySelector('.inp-good').value || '0', 10);
        const bad  = parseInt(tr.querySelector('.inp-bad').value || '0', 10);
        const sold = Math.max(dispatched - good - bad, 0);
        tr.querySelector('.lbl-sold').textContent = sold;
    }

    tblBody.addEventListener('input', (e) => {
        if (e.target.classList.contains('inp-good') || e.target.classList.contains('inp-bad')) {
            const tr = e.target.closest('tr');
            recomputeSold(tr);
        }
    });

    tblBody.addEventListener('click', (e) => {
        if (e.target.closest('.btnMax')) {
            const tr = e.target.closest('tr');
            const dispatched = parseInt(tr.querySelector('.lbl-dispatched').textContent || '0', 10);
            tr.querySelector('.inp-good').value = dispatched;
            tr.querySelector('.inp-bad').value  = 0;
            recomputeSold(tr);
        }
    });

    sel.addEventListener('change', () => {
        resetForm();
        const opt = sel.options[sel.selectedIndex];
        const id  = sel.value;

        if (!id) {
            btnLoad.disabled  = true;
            btnClear.disabled = true;
            return;
        }

        txtCode.value  = opt.dataset.code || '';
        txtSales.value = opt.dataset.sales || '';
        txtDate.value  = opt.dataset.date || '';

        const urlRecon = @json(route('sales.handover.reconcile', 0)).replace('/0', '/' + id);
        form.setAttribute('action', urlRecon);

        btnLoad.disabled  = true; // enable setelah sukses load
        btnClear.disabled = false;
    });

    btnClear.addEventListener('click', (e) => {
        e.preventDefault();
        resetForm();
    });

    btnLoad.addEventListener('click', async (e) => {
        e.preventDefault();
        const id = sel.value;
        if (!id) return;

        tblBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>';

        try {
            const url  = @json(route('sales.handover.items', 0)).replace('/0', '/' + id);
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            const items = json.items || [];

            if (!items.length) {
                tblBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada item.</td></tr>';
                btnSubmit.disabled = true;
                return;
            }

            let html = '';
            items.forEach((it, idx) => html += rowHtml(idx, it));
            tblBody.innerHTML = html;
            btnSubmit.disabled = false;
            otpInput.focus();
        } catch (err) {
            console.error(err);
            tblBody.innerHTML = '';
            btnSubmit.disabled = true;
            swalErr('Gagal memuat item handover.');
        }
    });

    // enable tombol load setelah pilih handover
    sel.addEventListener('change', () => {
        const id = sel.value;
        btnLoad.disabled = !id;
    });

    form.addEventListener('submit', (e) => {
        const id = sel.value;
        if (!id) {
            e.preventDefault();
            swalErr('Pilih handover terlebih dahulu.');
            return;
        }

        const otp = (otpInput.value || '').trim();
        if (!/^[0-9]{6}$/.test(otp)) {
            e.preventDefault();
            swalErr('OTP harus berupa 6 digit angka.');
            return;
        }

        const trs = [...tblBody.querySelectorAll('tr')];
        if (!trs.length) {
            e.preventDefault();
            swalErr('Items kosong. Klik Load Items dulu.');
            return;
        }

        let ok = true;
        trs.forEach(tr => {
            const dispatched = parseInt(tr.querySelector('.lbl-dispatched').textContent || '0', 10);
            const good = parseInt(tr.querySelector('.inp-good').value || '0', 10);
            const bad  = parseInt(tr.querySelector('.inp-bad').value || '0', 10);
            if (good < 0 || bad < 0 || (good + bad) > dispatched) {
                ok = false;
            }
        });

        if (!ok) {
            e.preventDefault();
            swalErr('Qty kembali (good + damaged) tidak boleh melebihi qty dibawa.');
            return;
        }

        const transferVal = parseFloat(document.getElementById('transfer_amount').value || '0');
        const proofInput  = document.querySelector('input[name="transfer_proof"]');

        if (transferVal > 0 && (!proofInput || !proofInput.files.length)) {
            e.preventDefault();
            swalErr('Jika ada setor transfer, bukti transfer wajib diupload.');
            return;
        }
    });

})();
</script>

@if (session('success'))
<script>
Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    html: {!! json_encode(session('success')) !!},
    allowOutsideClick: true
});
</script>
@endif

@if (session('error'))
<script>
Swal.fire({
    icon: 'error',
    title: 'Gagal',
    html: {!! json_encode(session('error')) !!}
});
</script>
@endif
@endpush
