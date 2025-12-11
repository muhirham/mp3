@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $me = $me ?? auth()->user();
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- STEP 1: INPUT HASIL & GENERATE OTP SORE --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-bold">Rekonsiliasi Sore – Input Hasil & Kirim OTP</h5>
    </div>
    <div class="card-body">

      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-6">
          <label class="form-label">Pilih Handover (Status ON_SALES)</label>
          <select id="selHandover" class="form-select">
            <option value="">— Pilih —</option>
            @foreach(($onSales ?? []) as $h)
              <option value="{{ $h->id }}"
                      data-code="{{ $h->code }}"
                      data-sales="{{ $h->sales->name ?? ('Sales #'.$h->sales_id) }}"
                      data-date="{{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }}">
                {{ $h->code }} — {{ $h->sales->name ?? ('Sales #'.$h->sales_id) }}
                ({{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }})
              </option>
            @endforeach
          </select>
          <div class="form-text">Hanya handover yang sudah lock OTP pagi (status <b>ON_SALES</b>).</div>
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

      <form id="formEveningSave" method="POST" enctype="multipart/form-data">
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
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle" id="tblReturn">
            <thead>
            <tr>
              <th style="width:40%">Produk</th>
              <th class="text-end" style="width:12%">Dibawa</th>
              <th class="text-end" style="width:12%">Kembali (Qty)</th>
              <th class="text-end" style="width:12%">Terjual</th>
              <th style="width:24%">Harga</th>
            </tr>
            </thead>
            <tbody>
            {{-- diisi via JS --}}
            </tbody>
          </table>
        </div>

        {{-- Setoran uang --}}
        <div class="row g-2 mt-3">
          <div class="col-md-3">
            <label class="form-label">Setor Tunai (Rp)</label>
            <input type="number" name="cash_amount" class="form-control" min="0" value="0">
            <div class="form-text">Isi 0 kalau tidak ada tunai.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Setor Transfer (Rp)</label>
            <input type="number" name="transfer_amount" class="form-control" min="0" value="0">
            <div class="form-text">Isi 0 kalau semua tunai.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Bukti Transfer (jpg/png/pdf)</label>
            <input type="file" name="transfer_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
            <div class="form-text">Wajib diupload kalau ada nominal transfer.</div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button type="submit" class="btn btn-primary" id="btnSubmit" disabled>
            Simpan Hasil &amp; Kirim OTP Sore
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- STEP 2: VERIFIKASI OTP SORE --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 fw-bold">Verifikasi OTP Sore (Closing)</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('sales.handover.evening.verify') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-5">
          <label class="form-label">Pilih Handover (Waiting OTP Sore)</label>
          <select name="handover_id" class="form-select" required>
            <option value="">— Pilih —</option>
            @foreach(($waitingEvening ?? []) as $h)
              <option value="{{ $h->id }}">
                {{ $h->code }} — {{ $h->sales->name ?? ('Sales #'.$h->sales_id) }}
                ({{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">OTP Sore</label>
          <input type="text" name="otp_code" class="form-control"
                 inputmode="numeric" pattern="[0-9]*" placeholder="6 digit" required>
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-success w-100 mt-3 mt-md-0">
            Verifikasi OTP &amp; Tutup Handover
          </button>
        </div>
      </form>
      <div class="form-text mt-2">
        Setelah OTP sore valid, stok sales akan di-clear dan sisa stok kembali ke gudang. Status menjadi <b>CLOSED</b>.
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  function swalErr(msg){ Swal.fire({icon:'error', title:'Gagal', html:msg}); }

  const sel       = document.getElementById('selHandover');
  const btnLoad   = document.getElementById('btnLoadItems');
  const btnClear  = document.getElementById('btnClear');
  const tblBody   = document.querySelector('#tblReturn tbody');
  const txtCode   = document.getElementById('txtCode');
  const txtSales  = document.getElementById('txtSales');
  const txtDate   = document.getElementById('txtDate');
  const form      = document.getElementById('formEveningSave');
  const btnSubmit = document.getElementById('btnSubmit');

  const inpCash   = form.querySelector('input[name="cash_amount"]');
  const inpTf     = form.querySelector('input[name="transfer_amount"]');
  const inpProof  = form.querySelector('input[name="transfer_proof"]');

  function resetForm(){
    txtCode.value  = '';
    txtSales.value = '';
    txtDate.value  = '';
    tblBody.innerHTML = '';
    btnSubmit.disabled = true;
    btnClear.disabled  = true;
  }

  function rowHtml(idx, it){
    const sold = Math.max((it.qty_start || 0) - (it.qty_returned || 0), 0);
    const priceText = it.unit_price
      ? new Intl.NumberFormat('id-ID').format(it.unit_price)
      : '-';
    return `
      <tr data-pid="${it.product_id}">
        <td>
          <input type="hidden" name="items[${idx}][product_id]" value="${it.product_id}">
          <div class="fw-semibold">${it.product_name || ('Produk #'+it.product_id)}</div>
          <div class="small text-muted">${it.product_code ? 'Kode: '+it.product_code : ''}</div>
        </td>
        <td class="text-end">
          <span class="lbl-start">${it.qty_start || 0}</span>
        </td>
        <td class="text-end">
          <input type="number"
                 class="form-control form-control-sm inp-returned"
                 min="0"
                 value="${it.qty_returned || 0}"
                 name="items[${idx}][qty_returned]">
        </td>
        <td class="text-end">
          <span class="lbl-sold">${sold}</span>
        </td>
        <td>
          <span class="badge bg-label-secondary">Harga: ${priceText}</span>
        </td>
      </tr>
    `;
  }

  function recomputeSold(tr){
    const start = parseInt(tr.querySelector('.lbl-start').textContent || '0', 10);
    const ret   = parseInt(tr.querySelector('.inp-returned').value || '0', 10);
    const sold  = Math.max(start - ret, 0);
    tr.querySelector('.lbl-sold').textContent = sold;
  }

  tblBody.addEventListener('input', (e)=>{
    if (e.target.classList.contains('inp-returned')) {
      const tr = e.target.closest('tr');
      recomputeSold(tr);
    }
  });

  sel.addEventListener('change', () => {
    resetForm();

    const id  = sel.value;
    const opt = sel.options[sel.selectedIndex];
    if (!id) {
      btnLoad.disabled = true;
      return;
    }

    txtCode.value  = opt.dataset.code || '';
    txtSales.value = opt.dataset.sales || '';
    txtDate.value  = opt.dataset.date || '';

    // set action
    const urlSave = @json(route('sales.handover.evening.save', 0)).replace('/0', '/' + id);
    form.setAttribute('action', urlSave);

    btnLoad.disabled = false;
  });

  btnClear.addEventListener('click', (e)=>{
    e.preventDefault();
    resetForm();
    sel.value = '';
    btnLoad.disabled = true;
  });

  btnLoad.addEventListener('click', async (e)=>{
    e.preventDefault();
    const id = sel.value;
    if (!id) return;

    tblBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>';

    try {
      const url = @json(route('sales.handover.items', 0)).replace('/0', '/' + id);
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      const items = json.items || [];

      if (!items.length) {
        tblBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Tidak ada item.</td></tr>';
        btnSubmit.disabled = true;
        btnClear.disabled  = true;
        return;
      }

      let html = '';
      items.forEach((it, idx) => html += rowHtml(idx, it));
      tblBody.innerHTML = html;
      btnSubmit.disabled = true;   // baru enabled setelah validasi awal
      btnClear.disabled  = false;
      btnSubmit.disabled = false;
    } catch (err) {
      console.error(err);
      tblBody.innerHTML = '';
      btnSubmit.disabled = true;
      swalErr('Gagal memuat item handover.');
    }
  });

  form.addEventListener('submit', (e)=>{
    const id = sel.value;
    if (!id) {
      e.preventDefault();
      swalErr('Pilih handover terlebih dahulu.');
      return;
    }

    const trs = [...tblBody.querySelectorAll('tr')];
    if (!trs.length) {
      e.preventDefault();
      swalErr('Item masih kosong. Klik Load Items dulu.');
      return;
    }

    let ok = true;
    trs.forEach(tr => {
      const start = parseInt(tr.querySelector('.lbl-start').textContent || '0', 10);
      const ret   = parseInt(tr.querySelector('.inp-returned').value || '0', 10);
      if (ret < 0 || ret > start) {
        ok = false;
      }
    });

    if (!ok) {
      e.preventDefault();
      swalErr('Qty kembali tidak boleh negatif atau lebih besar dari qty dibawa.');
      return;
    }

    const cash = parseInt(inpCash.value || '0', 10);
    const tf   = parseInt(inpTf.value || '0', 10);

    if (cash < 0 || tf < 0) {
      e.preventDefault();
      swalErr('Nominal setor tidak boleh negatif.');
      return;
    }

    if (tf > 0 && (!inpProof.files || !inpProof.files.length)) {
      e.preventDefault();
      swalErr('Jika ada nominal transfer, bukti transfer wajib diupload.');
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
