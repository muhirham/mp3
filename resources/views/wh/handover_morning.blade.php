@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    /** @var \App\Models\User $me */
    $me = $me ?? auth()->user();
    $today = $today ?? now()->format('Y-m-d');
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
                  <option value="{{ $w->id }}" @selected(old('warehouse_id')==$w->id)>
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
                <option value="{{ $u->id }}" @selected(old('sales_id')==$u->id)>
                  {{ $u->name ?? ('User #'.$u->id) }}
                  @if($u->email) — {{ $u->email }} @endif
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Item yang Dibawa (Barang Fisik)</h6>
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
              <th style="width:20%">Harga Satuan</th>
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
                            data-price="{{ (float) $p->selling_price }}"
                            @selected(optional(old('items.0'))['product_id'] == $p->id)>
                      {{ $p->name }} ({{ $p->product_code }})
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <input type="number" name="items[0][qty]"
                       class="form-control inp-qty"
                       min="1" value="{{ old('items.0.qty',1) }}" required>
              </td>
              <td>
                <input type="text" class="form-control inp-price" readonly>
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
          Grand Total: <span id="grand_total">0</span>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  const tbody      = document.querySelector('#tblItems tbody');
  const btnAddRow  = document.getElementById('btnAddRow');
  const grandLabel = document.getElementById('grand_total');

  function formatIdr(num){
    return new Intl.NumberFormat('id-ID').format(num || 0);
  }

  function reindexRows(){
    [...tbody.querySelectorAll('tr')].forEach((tr, idx) => {
      const sel = tr.querySelector('.sel-product');
      const qty = tr.querySelector('.inp-qty');
      if (sel) sel.name = `items[${idx}][product_id]`;
      if (qty) qty.name = `items[${idx}][qty]`;
    });
  }

  function recomputeRow(tr){
    const qtyInput   = tr.querySelector('.inp-qty');
    const priceInput = tr.querySelector('.inp-price');
    const totalInput = tr.querySelector('.inp-total');
    const select     = tr.querySelector('.sel-product');

    const qty   = parseInt(qtyInput.value || '0', 10);
    const price = parseFloat(select.selectedOptions[0]?.dataset.price || '0');
    const total = qty * price;

    priceInput.value = price ? formatIdr(price) : '0';
    totalInput.value = total ? formatIdr(total) : '0';
  }

  function recomputeGrand(){
    let grand = 0;
    [...tbody.querySelectorAll('tr')].forEach(tr => {
      const select = tr.querySelector('.sel-product');
      const qty    = parseInt(tr.querySelector('.inp-qty').value || '0', 10);
      const price  = parseFloat(select.selectedOptions[0]?.dataset.price || '0');
      grand += qty * price;
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
  });

  tbody.addEventListener('input', (e)=>{
    if (e.target.classList.contains('inp-qty')) {
      const tr = e.target.closest('tr');
      recomputeRow(tr);
      recomputeGrand();
    }
  });

  btnAddRow?.addEventListener('click', () => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select class="form-select sel-product" required>
          <option value="">— Pilih Produk —</option>
          @foreach(($products ?? []) as $p)
            <option value="{{ $p->id }}" data-price="{{ (float) $p->selling_price }}">
              {{ $p->name }} ({{ $p->product_code }})
            </option>
          @endforeach
        </select>
      </td>
      <td>
        <input type="number" class="form-control inp-qty" min="1" value="1" required>
      </td>
      <td><input type="text" class="form-control inp-price" readonly></td>
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
      Swal.fire({
        icon: 'error',
        title: 'Validasi',
        text: 'Pastikan setiap item terisi produk & qty yang valid.'
      });
    }
  });

  // init awal
  [...tbody.querySelectorAll('tr')].forEach(tr => { recomputeRow(tr); });
  recomputeGrand();

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
