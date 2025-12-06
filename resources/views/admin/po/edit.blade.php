@extends('layouts.home')

@section('content')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

@php
    $me    = auth()->user();
    $roles = $me?->roles ?? collect();

    $isSuperadmin  = $roles->contains('slug', 'superadmin');
    $isProcurement = $roles->contains('slug', 'procurement');
    $isCeo         = $roles->contains('slug', 'ceo');

    $approvalStatus = $po->approval_status ?? 'draft';

    $baseLocked     = $isLocked ?? false;
    $approvalLocked = in_array($approvalStatus, ['waiting_procurement','waiting_ceo','approved'], true);

    $poLocked = $baseLocked || $approvalLocked;

    // Kalau status REJECTED dan buka dari akun Procurement/CEO → full read-only
    if ($approvalStatus === 'rejected' && ! $isSuperadmin) {
        $poLocked = true;
    }

    // Ajukan approval hanya boleh oleh SUPERADMIN
    $canSubmitApproval = $isSuperadmin
        && ! $poLocked
        && in_array($approvalStatus, ['draft','rejected'], true)
        && $po->items->count() > 0
        && $po->grand_total > 0;

    $canApproveProc = $isProcurement && $approvalStatus === 'waiting_procurement';
    $canApproveCeo  = $isCeo        && $approvalStatus === 'waiting_ceo';
@endphp

<style>
  /* Compact style biar nggak banyak scroll */
  .po-compact {
    font-size: 0.82rem;
  }
  .po-compact h5,
  .po-compact h6 {
    font-size: 0.9rem;
  }
  .po-compact .card-header,
  .po-compact .card-body,
  .po-compact .card-footer {
    padding: 0.75rem 1rem;
  }
  .po-compact .form-control,
  .po-compact .form-select,
  .po-compact textarea,
  .po-compact .btn {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
  }
  .po-compact table td,
  .po-compact table th {
    padding: 0.35rem 0.4rem;
  }
</style>

<div class="container-xxl flex-grow-1 container-p-y po-compact">

  @if(session('success'))
    <div class="alert alert-success small">{{ session('success') }}</div>
  @endif
  @if(session('info'))
    <div class="alert alert-info small">{{ session('info') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger small">
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
          PO sudah berstatus <strong>{{ strtoupper($po->status) }}</strong>, tidak dapat diubah.
          Jika seluruh Goods Received dihapus (approve cancel) dan status PO dibuka lagi (mis. ke DRAFT),
          maka PO bisa diedit kembali.
        @else
          PO baru / aktif, silakan isi item dan simpan.
        @endif
      </small>
      <small class="text-muted d-block mt-1">
        Approval status:
        <strong class="text-uppercase">{{ $po->approval_status ?? 'draft' }}</strong>
        @if(($po->approval_status ?? '') === 'rejected' && $po->notes)
          <br>
          <span class="text-danger">Alasan ditolak: {{ $po->notes }}</span>
        @endif
      </small>
    </div>

    {{-- TOMBOL EXPORT --}}
    <div class="d-flex gap-2">
      {{-- Dropdown PDF --}}
      <div class="btn-group">
        <button type="button"
                class="btn btn-outline-secondary btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false">
          PDF
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item"
               href="{{ route('po.pdf', ['po' => $po->id, 'tpl' => 'default']) }}"
               target="_blank">
              Template Standar
            </a>
          </li>
          <li>
            <a class="dropdown-item"
               href="{{ route('po.pdf', ['po' => $po->id, 'tpl' => 'partner']) }}"
               target="_blank">
              Template Partner
            </a>
          </li>
        </ul>
      </div>

      {{-- Excel biasa --}}
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
                 value="{{ $po->supplier->supplier_name ?? $po->supplier->name ?? $po->supplier_id }}"
                 disabled>
        </div>
        <div class="col-md-8">
          <label class="form-label">Notes</label>
          <textarea name="notes"
                    rows="2"
                    class="form-control"
                    @disabled($poLocked)>{{ old('notes',$po->notes) }}</textarea>
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
              <th style="width:100px" class="text-end">Qty</th>
              <th style="width:120px" class="text-end">Unit Price</th>
              <th style="width:100px">Disc Type</th>
              <th style="width:110px" class="text-end">Disc Value</th>
              <th style="width:130px" class="text-end">Line Total</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            @php $idx = 0; @endphp
            @foreach($po->items as $it)
              @php
                $rowFromRequest = $isFromRequest && $it->request_id;
              @endphp
              <tr data-index="{{ $idx }}">
                <td class="row-num"></td>

                {{-- PRODUCT --}}
                <td>
                  <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $it->id }}">
                  <input type="hidden" name="items[{{ $idx }}][request_id]" value="{{ $it->request_id }}">

                  @if($rowFromRequest)
                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $it->product_id }}">
                    <div class="fw-semibold">
                      {{ $it->product->product_code ?? '-' }} — {{ $it->product->name ?? '-' }}
                    </div>
                    <div class="small text-muted js-supplier-label">
                      {{ $it->product->supplier->name ?? '-' }}
                    </div>
                  @else
                    <select name="items[{{ $idx }}][product_id]"
                            class="form-select form-select-sm product-select"
                            @disabled($poLocked)>
                      <option value="">— Pilih —</option>
                      @foreach($products as $p)
                        @php
                          $buy  = (int)($p->purchase_price ?? $p->buy_price ?? $p->cost_price ?? 0);
                          $sell = (int)($p->selling_price ?? 0);
                        @endphp
                        <option value="{{ $p->id }}"
                                data-buy="{{ $buy }}"
                                data-sell="{{ $sell }}"
                                data-supplier="{{ $p->supplier->name ?? '-' }}"
                                @selected($p->id == $it->product_id)>
                          {{ $p->product_code }} — {{ $p->name }}
                        </option>
                      @endforeach
                    </select>
                    <div class="small text-muted js-supplier-label">
                      {{ $it->product->supplier->name ?? '-' }}
                    </div>
                  @endif
                </td>

                {{-- WAREHOUSE --}}
                <td>
                  @if($rowFromRequest)
                    <input type="hidden"
                           name="items[{{ $idx }}][warehouse_id]"
                           value="{{ $it->warehouse_id }}">
                    <span>
                      {{ $it->warehouse->warehouse_name ?? $it->warehouse->name ?? 'Warehouse #'.$it->warehouse_id }}
                    </span>
                  @else
                    @if($isFromRequest)
                      <select name="items[{{ $idx }}][warehouse_id]"
                              class="form-select form-select-sm"
                              @disabled($poLocked)>
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
                  <select name="items[{{ $idx }}][discount_type]"
                          class="form-select form-select-sm disc-type"
                          @disabled($poLocked)>
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
                  @unless($poLocked || $rowFromRequest)
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
    @if($canSubmitApproval)
      <form method="POST" action="{{ route('po.order', $po->id) }}" class="d-inline frm-order">
        @csrf
        <button type="submit" class="btn btn-warning">
          Ajukan Approval
        </button>
      </form>
    @endif

    {{-- Cancel hanya kalau belum dalam flow approval & tidak locked --}}
    @if(!$poLocked && !in_array($po->approval_status, ['waiting_procurement','waiting_ceo','approved'], true))
      <form method="POST" action="{{ route('po.cancel',$po->id) }}" class="d-inline frm-cancel">
        @csrf
        <button type="submit" class="btn btn-outline-danger">Cancel PO</button>
      </form>
    @endif
  </div>

  {{-- CARD APPROVAL (hanya kalau sedang menunggu approval) --}}
  @if($canApproveProc || $canApproveCeo)
    <div class="card mt-3">
      <div class="card-header text-center">
        <h6 class="mb-0">
          Approval
          @if($canApproveProc) Procurement @endif
          @if($canApproveProc && $canApproveCeo) & @endif
          @if($canApproveCeo) CEO @endif
        </h6>
      </div>
      <div class="card-body d-flex flex-column align-items-center gap-3 small">

        @if($canApproveProc)
          <div class="text-center">
            <form method="POST"
                  action="{{ route('po.approve.proc', $po->id) }}"
                  class="d-inline-block me-2 frm-approve-proc">
              @csrf
              <button type="submit" class="btn btn-success btn-sm">
                Approve Procurement
              </button>
            </form>

            <form method="POST"
                  action="{{ route('po.reject.proc', $po->id) }}"
                  class="d-inline-block frm-reject-proc">
              @csrf
              <button type="button" class="btn btn-outline-danger btn-sm btn-reject-proc">
                Reject
              </button>
            </form>
          </div>
        @endif

        @if($canApproveCeo)
          <hr class="w-100 my-2">
          <div class="text-center">
            <form method="POST"
                  action="{{ route('po.approve.ceo', $po->id) }}"
                  class="d-inline-block me-2 frm-approve-ceo">
              @csrf
              <button type="submit" class="btn btn-success btn-sm">
                Approve CEO
              </button>
            </form>

            <form method="POST"
                  action="{{ route('po.reject.ceo', $po->id) }}"
                  class="d-inline-block frm-reject-ceo">
              @csrf
              <button type="button" class="btn btn-outline-danger btn-sm btn-reject-ceo">
                Reject
              </button>
            </form>
          </div>
        @endif

      </div>
    </div>
  @endif

</div>

@push('scripts')
<script>
(function () {
  let nextIndex      = {{ $idx ?? 0 }};
  const poLocked     = @json($poLocked);
  const isFromRequest = @json($isFromRequest);

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

    const priceMode = isFromRequest ? 'sell' : 'buy';

    select.addEventListener('change', function () {
      if (poLocked) return;

      const opt       = this.options[this.selectedIndex];
      const buyPrice  = opt ? parseFloat(opt.dataset.buy  || '0') : 0;
      const sellPrice = opt ? parseFloat(opt.dataset.sell || '0') : 0;
      const supplier  = opt ? (opt.dataset.supplier || '-') : '-';

      const priceInput = tr.querySelector('.price');
      const supLabel   = tr.querySelector('.js-supplier-label');

      let price;
      if (priceMode === 'sell') {
        price = sellPrice || buyPrice;
      } else {
        price = buyPrice || sellPrice;
      }

      if (priceInput) {
        priceInput.value = price || 0;
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

  const btnAdd = document.getElementById('btnAddRow');
  if (btnAdd && !poLocked) {
    btnAdd.addEventListener('click', () => {
      const tbody = document.querySelector('#tblItems tbody');
      const idx   = nextIndex++;

      let warehouseCellHtml;
      if (isFromRequest) {
        warehouseCellHtml = `
          <select name="items[${idx}][warehouse_id]" class="form-select form-select-sm">
            <option value="">— Pilih —</option>
            @foreach($warehouses as $w)
              <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
            @endforeach
          </select>
        `;
      } else {
        warehouseCellHtml = `
          <input type="hidden" name="items[${idx}][warehouse_id]" value="">
          <span class="text-muted">– Central Stock –</span>
        `;
      }

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
              @php
                $buy  = (int)($p->purchase_price ?? $p->buy_price ?? $p->cost_price ?? 0);
                $sell = (int)($p->selling_price ?? 0);
              @endphp
              <option value="{{ $p->id }}"
                      data-buy="{{ $buy }}"
                      data-sell="{{ $sell }}"
                      data-supplier="{{ $p->supplier->name ?? '-' }}">
                {{ $p->product_code }} — {{ $p->name }}
              </option>
            @endforeach
          </select>
          <div class="small text-muted js-supplier-label">-</div>
        </td>

        <td>${warehouseCellHtml}</td>

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

  document.querySelector('#tblItems tbody').addEventListener('click', function (e) {
    if (poLocked) return;
    if (e.target.classList.contains('btn-remove')) {
      e.target.closest('tr').remove();
      renumberRows();
      recalc();
    }
  });

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

  document.querySelectorAll('#tblItems tbody tr').forEach(tr => attachProductAutoPrice(tr));

  renumberRows();
  recalc();
})();
</script>

<script>
  // SweetAlert untuk ORDER, CANCEL, dan REJECT
  document.addEventListener('DOMContentLoaded', function () {
    // Ajukan Approval
    document.querySelectorAll('.frm-order').forEach(form => {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
          icon: 'question',
          title: 'Ajukan approval?',
          text: 'PO akan dikirim ke Procurement / CEO sesuai rule approval.',
          showCancelButton: true,
          confirmButtonText: 'Ya, ajukan',
          cancelButtonText: 'Batal'
        }).then(res => {
          if (res.isConfirmed) form.submit();
        });
      });
    });

    // Cancel PO
    document.querySelectorAll('.frm-cancel').forEach(form => {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Cancel PO?',
          text: 'PO yang dicancel tidak dapat digunakan untuk GR.',
          showCancelButton: true,
          confirmButtonText: 'Ya, cancel',
          cancelButtonText: 'Batal'
        }).then(res => {
          if (res.isConfirmed) form.submit();
        });
      });
    });

    // Reject Procurement
    document.querySelectorAll('.btn-reject-proc').forEach(btn => {
      btn.addEventListener('click', function () {
        const form = this.closest('form');
        Swal.fire({
          title: 'Alasan reject Procurement',
          input: 'textarea',
          inputPlaceholder: 'Tuliskan alasan penolakan...',
          inputAttributes: { 'aria-label': 'Alasan reject' },
          showCancelButton: true,
          confirmButtonText: 'Kirim Reject',
          cancelButtonText: 'Batal',
          inputValidator: value => {
            if (!value) {
              return 'Alasan wajib diisi';
            }
            if (value.length > 1000) {
              return 'Maksimal 1000 karakter.';
            }
            return null;
          }
        }).then(res => {
          if (res.isConfirmed) {
            let input = form.querySelector('input[name="reason"]');
            if (!input) {
              input = document.createElement('input');
              input.type = 'hidden';
              input.name = 'reason';
              form.appendChild(input);
            }
            input.value = res.value;
            form.submit();
          }
        });
      });
    });

    // Reject CEO
    document.querySelectorAll('.btn-reject-ceo').forEach(btn => {
      btn.addEventListener('click', function () {
        const form = this.closest('form');
        Swal.fire({
          title: 'Alasan reject CEO',
          input: 'textarea',
          inputPlaceholder: 'Tuliskan alasan penolakan...',
          inputAttributes: { 'aria-label': 'Alasan reject' },
          showCancelButton: true,
          confirmButtonText: 'Kirim Reject',
          cancelButtonText: 'Batal',
          inputValidator: value => {
            if (!value) {
              return 'Alasan wajib diisi';
            }
            if (value.length > 1000) {
              return 'Maksimal 1000 karakter.';
            }
            return null;
          }
        }).then(res => {
          if (res.isConfirmed) {
            let input = form.querySelector('input[name="reason"]');
            if (!input) {
              input = document.createElement('input');
              input.type = 'hidden';
              input.name = 'reason';
              form.appendChild(input);
            }
            input.value = res.value;
            form.submit();
          }
        });
      });
    });
  });
</script>
@endpush

@endsection
