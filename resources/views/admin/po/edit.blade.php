@extends('layouts.home')

@section('content')
@php
  // dikirim dari controller: $isLocked = ($po->status === 'completed');
  $poLocked = $isLocked ?? false;
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="mb-1">
        PO: {{ $po->po_code }}
        <span class="badge bg-label-info text-uppercase">{{ $po->status }}</span>

        @if($isFromRequest)
          <span class="badge bg-label-secondary ms-1">FROM REQUEST</span>
        @else
          <span class="badge bg-label-secondary ms-1">MANUAL</span>
        @endif
      </h5>
      <small class="text-muted">
        @if($poLocked)
          PO sudah COMPLETED, tidak dapat diubah. Jika GR dihapus (approved), status PO akan dibuka lagi.
        @else
          PO baru / aktif, silakan isi item dan simpan.
        @endif
      </small>
    </div>

    <div class="btn-group">
      <a href="{{ route('po.pdf',$po->id) }}"  class="btn btn-outline-secondary btn-sm">PDF</a>
      <a href="{{ route('po.excel',$po->id) }}" class="btn btn-outline-secondary btn-sm">Excel</a>
    </div>
  </div>

  {{-- HEADER: info supplier & notes --}}
  <form method="POST" action="{{ route('po.update',$po->id) }}" id="formPo">
    @csrf
    @method('PUT')

    <div class="card mb-3">
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label">Informasi Supplier</label>
          <input type="text"
                 class="form-control"
                 value="Supplier mengikuti masing-masing produk (multi supplier diperbolehkan)."
                 disabled>
        </div>
        <div class="col-md-8">
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="2" class="form-control" @disabled($poLocked)>{{ old('notes',$po->notes) }}</textarea>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Items</h6>
        @unless($poLocked)
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
            <i class="bx bx-plus"></i> Tambah Baris
          </button>
        @endunless
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tblItems">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Product</th>
              <th>Warehouse</th>
              <th style="width:110px" class="text-end">Qty</th>
              <th style="width:120px" class="text-end">Unit Price</th>
              <th style="width:120px">Disc Type</th>
              <th style="width:120px" class="text-end">Disc Value</th>
              <th style="width:130px" class="text-end">Line Total</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            @php $idx = 0; @endphp
            @foreach($po->items as $it)
              <tr data-index="{{ $idx }}">
                <td class="row-num"></td>

                {{-- PRODUCT + hidden id & request_id --}}
                <td>
                  <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $it->id }}">
                  <input type="hidden" name="items[{{ $idx }}][request_id]" value="{{ $it->request_id }}">

                  <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm product-select" @disabled($poLocked)>
                    <option value="">— Pilih —</option>
                    @foreach($products as $p)
                      <option value="{{ $p->id }}"
                              data-price="{{ (int)$p->selling_price }}"
                              data-supplier="{{ $p->supplier->name ?? '-' }}"
                              @selected($p->id == $it->product_id)>
                        {{ $p->product_code }} — {{ $p->name }}
                      </option>
                    @endforeach
                  </select>
                  <div class="small text-muted js-supplier-label">
                    {{ $it->product->supplier->name ?? '-' }}
                  </div>
                </td>

                {{-- WAREHOUSE --}}
                <td>
                  @if($isFromRequest)
                    <select name="items[{{ $idx }}][warehouse_id]" class="form-select form-select-sm" @disabled($poLocked)>
                      <option value="">— Pilih —</option>
                      @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected($w->id == $it->warehouse_id)>
                          {{ $w->warehouse_name }}
                        </option>
                      @endforeach
                    </select>
                  @else
                    <input type="hidden" name="items[{{ $idx }}][warehouse_id]" value="">
                    <span class="text-muted">– Central Stock –</span>
                  @endif
                </td>

                {{-- QTY --}}
                <td>
                  <input type="number" min="1"
                         class="form-control text-end qty"
                         name="items[{{ $idx }}][qty]"
                         value="{{ $it->qty_ordered }}"
                         @disabled($poLocked)>
                </td>

                {{-- UNIT PRICE --}}
                <td>
                  <input type="number" min="0" step="1"
                         class="form-control form-control-sm text-end price"
                         name="items[{{ $idx }}][unit_price]"
                         value="{{ (int)$it->unit_price }}"
                         @disabled($poLocked)>
                </td>

                {{-- DISC TYPE --}}
                <td>
                  <select name="items[{{ $idx }}][discount_type]" class="form-select form-select-sm disc-type" @disabled($poLocked)>
                    <option value="">-</option>
                    <option value="percent" @selected($it->discount_type==='percent')>%</option>
                    <option value="amount"  @selected($it->discount_type==='amount')>Rp</option>
                  </select>
                </td>

                {{-- DISC VALUE --}}
                <td>
                  <input type="number" min="0" step="1"
                         class="form-control form-control-sm text-end disc-val"
                         name="items[{{ $idx }}][discount_value]"
                         value="{{ (int)$it->discount_value }}"
                         @disabled($poLocked)>
                </td>

                {{-- LINE TOTAL --}}
                <td class="text-end line-total">
                  {{ number_format($it->line_total, 0, ',', '.') }}
                </td>

                {{-- HAPUS --}}
                <td class="text-center">
                  @unless($poLocked)
                    <button type="button" class="btn btn-xs btn-link text-danger btn-remove">&times;</button>
                  @endunless
                </td>
              </tr>
              @php $idx++; @endphp
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="7" class="text-end">SUBTOTAL</th>
              <th class="text-end" id="ftSubtotal">0</th>
              <th></th>
            </tr>
            <tr>
              <th colspan="7" class="text-end">DISCOUNT</th>
              <th class="text-end" id="ftDiscount">0</th>
              <th></th>
            </tr>
            <tr>
              <th colspan="7" class="text-end">GRAND TOTAL</th>
              <th class="text-end fw-bold" id="ftGrand">0</th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('po.index') }}" class="btn btn-outline-secondary">Kembali</a>
        @unless($poLocked)
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        @endunless
      </div>
    </div>
  </form>

  {{-- FORM ORDER & CANCEL (TERPISAH, BUKAN NESTED) --}}
  <div class="d-flex justify-content-end mt-2 gap-2">
    @unless($poLocked)
    <form method="POST" action="{{ route('po.order', $po->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-warning me-2">
                Set ORDERED
            </button>
            <a href="{{ route('po.index') }}" class="btn btn-outline-secondary">
                Kembali
            </a>
        </form>

      <form method="POST" action="{{ route('po.cancel',$po->id) }}">
        @csrf
        <button type="submit" class="btn btn-outline-danger">Cancel</button>
      </form>
    @endunless
  </div>

</div>

@push('scripts')
<script>
(function () {
  let nextIndex = {{ $idx ?? 0 }};
  const poLocked = @json($poLocked);

  function renumberRows() {
    document.querySelectorAll('#tblItems tbody tr').forEach((tr, i) => {
      const cell = tr.querySelector('.row-num');
      if (cell) cell.innerText = i + 1;
    });
  }

  function recalc() {
    let subtotal = 0, discount = 0;

    document.querySelectorAll('#tblItems tbody tr').forEach(tr => {
      const qtyEl       = tr.querySelector('.qty');
      const priceEl     = tr.querySelector('.price');
      const discTypeEl  = tr.querySelector('.disc-type');
      const discValEl   = tr.querySelector('.disc-val');

      const qty      = parseFloat(qtyEl?.value || 0);
      const price    = parseFloat(priceEl?.value || 0);
      const discType = discTypeEl?.value || '';
      const discVal  = parseFloat(discValEl?.value || 0);

      let line = qty * price;
      let disc = 0;

      if (discType === 'percent') {
        disc = Math.min(100, Math.max(0, discVal)) / 100 * line;
      } else if (discType === 'amount') {
        disc = Math.min(discVal, line);
      }

      const net = line - disc;

      subtotal += line;
      discount += disc;

      const lineCell = tr.querySelector('.line-total');
      if (lineCell) {
        lineCell.innerText = net.toLocaleString('id-ID');
      }
    });

    document.getElementById('ftSubtotal').innerText = subtotal.toLocaleString('id-ID');
    document.getElementById('ftDiscount').innerText = discount.toLocaleString('id-ID');
    document.getElementById('ftGrand').innerText    = (subtotal - discount).toLocaleString('id-ID');
  }

  function attachProductAutoPrice(tr) {
    const select = tr.querySelector('.product-select');
    if (!select) return;

    select.addEventListener('change', function () {
      if (poLocked) return; // kalau locked, jangan ubah apa-apa

      const opt       = this.options[this.selectedIndex];
      const priceAttr = opt ? parseFloat(opt.dataset.price || 0) : 0;
      const supplier  = opt ? (opt.dataset.supplier || '-') : '-';

      const priceInput = tr.querySelector('.price');
      const supLabel   = tr.querySelector('.js-supplier-label');

      if (priceInput) {
        priceInput.value = priceAttr;
      }

      if (supLabel) {
        supLabel.textContent = supplier;
      }

      recalc();
    });

    const selectedOpt = select.options[select.selectedIndex];
    if (selectedOpt && selectedOpt.value) {
      const supplier  = selectedOpt.dataset.supplier || '-';
      const supLabel  = tr.querySelector('.js-supplier-label');
      if (supLabel) supLabel.textContent = supplier;
    }
  }

  // Tambah baris baru
  const btnAdd = document.getElementById('btnAddRow');
  if (btnAdd) {
    btnAdd.addEventListener('click', () => {
      const tbody = document.querySelector('#tblItems tbody');
      const idx = nextIndex++;

      const tr = document.createElement('tr');
      tr.setAttribute('data-index', idx);
      tr.innerHTML = `
        <td class="row-num"></td>

        <td>
          <input type="hidden" name="items[${idx}][id]" value="">
          <input type="hidden" name="items[${idx}][request_id]" value="">
          <select name="items[${idx}][product_id]" class="form-select form-select-sm product-select">
            <option value="">— Pilih —</option>
            @foreach($products as $p)
              <option value="{{ $p->id }}"
                      data-price="{{ (int)$p->selling_price }}"
                      data-supplier="{{ $p->supplier->name ?? '-' }}">
                {{ $p->product_code }} — {{ $p->name }}
              </option>
            @endforeach
          </select>
          <div class="small text-muted js-supplier-label">-</div>
        </td>

        <td>
          @if($isFromRequest)
            <select name="items[${idx}][warehouse_id]" class="form-select form-select-sm">
              <option value="">— Pilih —</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
              @endforeach
            </select>
          @else
            <input type="hidden" name="items[${idx}][warehouse_id]" value="">
            <span class="text-muted">– Central Stock –</span>
          @endif
        </td>

        <td>
          <input type="number" min="1" class="form-control text-end qty"
                 name="items[${idx}][qty]" value="1">
        </td>

        <td>
          <input type="number" min="0" step="1"
                 class="form-control form-control-sm text-end price"
                 name="items[${idx}][unit_price]" value="0">
        </td>

        <td>
          <select name="items[${idx}][discount_type]" class="form-select form-select-sm disc-type">
            <option value="">-</option>
            <option value="percent">%</option>
            <option value="amount">Rp</option>
          </select>
        </td>

        <td>
          <input type="number" min="0" step="1"
                 class="form-control form-control-sm text-end disc-val"
                 name="items[${idx}][discount_value]" value="0">
        </td>

        <td class="text-end line-total">0</td>

        <td class="text-center">
          <button type="button" class="btn btn-xs btn-link text-danger btn-remove">&times;</button>
        </td>
      `;
      tbody.appendChild(tr);
      attachProductAutoPrice(tr);
      renumberRows();
      recalc();
    });
  }

  // remove row
  document.querySelector('#tblItems tbody').addEventListener('click', function (e) {
    if (poLocked) return;
    if (e.target.classList.contains('btn-remove')) {
      e.target.closest('tr').remove();
      renumberRows();
      recalc();
    }
  });

  // recalc saat input berubah
  document.querySelector('#tblItems tbody').addEventListener('input', function (e) {
    if (poLocked) return;
    if (
      e.target.classList.contains('qty') ||
      e.target.classList.contains('price') ||
      e.target.classList.contains('disc-val') ||
      e.target.classList.contains('disc-type')
    ) {
      recalc();
    }
  });

  // pasang auto-price ke baris awal
  document.querySelectorAll('#tblItems tbody tr').forEach(tr => attachProductAutoPrice(tr));

  renumberRows();
  recalc();
})();
</script>
@endpush

@endsection
