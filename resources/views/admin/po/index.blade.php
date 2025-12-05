@extends('layouts.home')

@section('content')

@php
    $me    = auth()->user();
    $roles = $me?->roles ?? collect();

    // kalau controller sudah kirim $isSuperadmin / $isProcurement / $isCeo, pakai itu
    $isSuperadmin  = $isSuperadmin  ?? $roles->contains('slug', 'superadmin');
    $isProcurement = $isProcurement ?? $roles->contains('slug', 'procurement');
    $isCeo         = $isCeo         ?? $roles->contains('slug', 'ceo');
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0 fw-bold">Purchase Orders</h5>

      <div class="d-flex flex-wrap gap-2 ms-auto">
        {{-- Search PO (akan di-handle AJAX, tidak perlu tekan enter) --}}
        <form class="d-flex gap-2" id="po-search-form" method="get">
          <input id="po-search"
                 class="form-control"
                 name="q"
                 value="{{ $q ?? '' }}"
                 placeholder="Cari PO code...">
          <button class="btn btn-outline-secondary" type="submit">Cari</button>
        </form>

        {{-- Create new PO --}}
        <form action="{{ route('po.store') }}" method="POST">
          @csrf
          <button type="submit" class="btn btn-primary">
            <i class="bx bx-plus"></i> New PO
          </button>
        </form>
      </div>
    </div>

    <div class="card-body pt-2 pb-0">
      <div class="alert alert-info small py-2 mb-0">
        <strong>Rule Approval:</strong>
        <ul class="mb-0 ps-3">
          <li>Grand total &le; Rp 1.000.000 &rarr; cukup disetujui <strong>Procurement</strong>.</li>
          <li>Grand total &gt; Rp 1.000.000 &rarr; wajib 2 lapis: <strong>Procurement → CEO</strong>.</li>
        </ul>
      </div>
    </div>

    {{-- WRAPPER YANG NANTI DI-GANTI VIA AJAX --}}
    <div id="po-table-wrapper">

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>PO CODE</th>
              <th>Supplier</th>
              <th>Status</th>
              <th>Approval</th>
              <th class="text-end">Subtotal</th>
              <th class="text-end">Discount</th>
              <th class="text-end">Grand</th>
              <th>Lines</th>
              <th>Warehouse</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pos as $po)
              @php
                // cek GR
                $hasGr = (int)($po->gr_count ?? 0) > 0;

                // GR HANYA SUPERADMIN & PO SUDAH APPROVED
                $canReceive = $isSuperadmin
                              && !$hasGr
                              && $po->status === 'ordered'
                              && $po->approval_status === 'approved'
                              && $po->items_count > 0;

                // ==== SUMMARY SUPPLIER ====
                $supplierNames = collect();

                if (!empty($po->supplier?->name)) {
                    $supplierNames->push($po->supplier->name);
                }

                foreach ($po->items as $it) {
                    $sName = $it->product->supplier->name ?? null;
                    if ($sName) {
                        $supplierNames->push($sName);
                    }
                }

                $supplierNames = $supplierNames->unique()->values();

                if ($supplierNames->isEmpty()) {
                    $supplierLabel = '-';
                } elseif ($supplierNames->count() === 1) {
                    $supplierLabel = $supplierNames->first();
                } else {
                    $supplierLabel = $supplierNames->first().' + '.($supplierNames->count() - 1).' supplier';
                }

                // ==== SUMMARY WAREHOUSE ====
                $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

                if (!$fromRequest) {
                    // PO manual dari pusat → selalu Central Stock
                    $warehouseLabel = 'Central Stock';
                } else {
                    // PO dari Request Restock → ambil nama warehouse dari item
                    $warehouseNames = collect();

                    foreach ($po->items as $it) {
                        if ($it->warehouse) {
                            $warehouseNames->push(
                                $it->warehouse->warehouse_name
                                ?? $it->warehouse->name
                            );
                        }
                    }

                    $warehouseNames = $warehouseNames->filter()->unique()->values();

                    if ($warehouseNames->isEmpty()) {
                        $warehouseLabel = '-';
                    } elseif ($warehouseNames->count() === 1) {
                        $warehouseLabel = $warehouseNames->first();
                    } else {
                        $warehouseLabel = $warehouseNames->first().' + '.($warehouseNames->count() - 1).' wh';
                    }
                }

                // ==== APPROVAL STATUS ====
                $approvalStatus = $po->approval_status ?: 'draft';

                if ($approvalStatus === 'draft') {
                    $approvalBadge = '<span class="badge bg-label-secondary">Draft</span>';
                } elseif ($approvalStatus === 'waiting_procurement') {
                    $approvalBadge = '<span class="badge bg-label-warning">Waiting Procurement</span>';
                } elseif ($approvalStatus === 'waiting_ceo') {
                    $approvalBadge = '<span class="badge bg-label-info">Waiting CEO</span>';
                } elseif ($approvalStatus === 'approved') {
                    $approvalBadge = '<span class="badge bg-label-success">Approved</span>';
                } elseif ($approvalStatus === 'rejected') {
                    $approvalBadge = '<span class="badge bg-label-danger">Rejected</span>';
                } else {
                    $approvalBadge = '<span class="badge bg-label-secondary">'.e($approvalStatus).'</span>';
                }

                $procName = $po->procurementApprover->name ?? '-';
                $ceoName  = $po->ceoApprover->name ?? '-';
              @endphp

              <tr>
                <td class="fw-bold">{{ $po->po_code }}</td>
                <td>{{ $supplierLabel }}</td>
                <td>
                  <span class="badge bg-label-info text-uppercase">{{ $po->status }}</span>
                  @if($hasGr)
                    <span class="badge bg-label-success ms-1">GR EXIST</span>
                  @endif
                </td>

                <td>
                  {!! $approvalBadge !!}
                  <div class="small text-muted mt-1">
                    Proc: {{ $procName }}<br>
                    CEO&nbsp;: {{ $ceoName }}
                  </div>
                </td>

                <td class="text-end">{{ number_format($po->subtotal,0,',','.') }}</td>
                <td class="text-end">{{ number_format($po->discount_total,0,',','.') }}</td>
                <td class="text-end">{{ number_format($po->grand_total,0,',','.') }}</td>
                <td>{{ $po->items_count }}</td>
                <td>{{ $warehouseLabel }}</td>

                <td class="text-end">
                  <div class="btn-group">
                    <a class="btn btn-sm btn-primary" href="{{ route('po.edit',$po->id) }}">Open</a>

                    {{-- Tombol Receive (GR) hanya SUPERADMIN & PO APPROVED --}}
                    @if($canReceive)
                      <button type="button"
                              class="btn btn-sm btn-success"
                              data-bs-toggle="modal"
                              data-bs-target="#mdlGR-{{ $po->id }}">
                        <i class="bx bx-download"></i> Receive
                      </button>
                    @endif

                    {{-- APPROVAL Procurement --}}
                    @if($isProcurement && in_array($approvalStatus, [null, 'draft', 'waiting_procurement']))
                      <form method="POST"
                            action="{{ route('po.approve', $po->id) }}"
                            class="d-inline form-approve">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                          <i class="bx bx-check"></i> Approve (Proc)
                        </button>
                      </form>
                    @endif

                    {{-- APPROVAL CEO --}}
                    @if($isCeo && $approvalStatus === 'waiting_ceo')
                      <form method="POST"
                            action="{{ route('po.approve', $po->id) }}"
                            class="d-inline form-approve">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">
                          <i class="bx bx-check"></i> Approve CEO
                        </button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="text-center text-muted">Belum ada PO.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($pos->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="small text-muted">
            Menampilkan {{ $pos->firstItem() }}–{{ $pos->lastItem() }} dari {{ $pos->total() }} PO
          </div>
          <div>
            {{ $pos->withQueryString()->links('pagination::bootstrap-5') }}
          </div>
        </div>
      @endif

      {{-- ======= MODAL GR PER PO (di dalam wrapper) ======= --}}
      @foreach($pos as $po)
        @php
          $hasGr      = (int)($po->gr_count ?? 0) > 0;
          $canReceive = $isSuperadmin
                        && !$hasGr
                        && $po->status === 'ordered'
                        && $po->approval_status === 'approved'
                        && $po->items_count > 0;

          // summary supplier sama seperti di tabel
          $supplierNames = collect();
          if (!empty($po->supplier?->name)) {
              $supplierNames->push($po->supplier->name);
          }
          foreach ($po->items as $it) {
              $sName = $it->product->supplier->name ?? null;
              if ($sName) {
                  $supplierNames->push($sName);
              }
          }
          $supplierNames = $supplierNames->unique()->values();

          if ($supplierNames->isEmpty()) {
              $supplierLabel = '-';
          } elseif ($supplierNames->count() === 1) {
              $supplierLabel = $supplierNames->first();
          } else {
              $supplierLabel = $supplierNames->first().' + '.($supplierNames->count() - 1).' supplier';
          }

          // summary warehouse untuk modal
          $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();
          if (!$fromRequest) {
              $whLabel = 'Central Stock';
          } else {
              $whNames = collect();
              foreach ($po->items as $it) {
                  if ($it->warehouse) {
                      $whNames->push($it->warehouse->warehouse_name ?? $it->warehouse->name);
                  }
              }
              $whNames = $whNames->filter()->unique()->values();
              if ($whNames->isEmpty()) {
                  $whLabel = '-';
              } elseif ($whNames->count() === 1) {
                  $whLabel = $whNames->first();
              } else {
                  $whLabel = $whNames->first().' + '.($whNames->count() - 1).' wh';
              }
          }
        @endphp

        @if($canReceive)
          <div class="modal fade mdl-gr-po" id="mdlGR-{{ $po->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Goods Received – {{ $po->po_code }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                {{-- route lama: po.gr.store --}}
                <form action="{{ route('po.gr.store', $po) }}"
                      method="POST"
                      enctype="multipart/form-data">
                  @csrf
                  <div class="modal-body">
                    <p class="mb-3">
                      Supplier: <strong>{{ $supplierLabel }}</strong><br>
                      Warehouse: <strong>{{ $whLabel }}</strong>
                    </p>

                    <div class="table-responsive">
                      <table class="table table-sm align-middle">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Qty Ordered</th>
                            <th>Qty Received</th>
                            <th>Qty Remaining</th>
                            <th>Qty Good</th>
                            <th>Qty Damaged</th>
                            <th>Notes</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($po->items as $i => $item)
                            @php
                              $ordered   = (int)($item->qty_ordered ?? 0);
                              $received  = (int)($item->qty_received ?? 0);
                              $remaining = max(0, $ordered - $received);
                              $key       = $item->id;
                            @endphp
                            <tr>
                              <td>{{ $i + 1 }}</td>
                              <td>
                                {{ $item->product->name ?? '-' }}<br>
                                <small class="text-muted">{{ $item->product->product_code ?? '' }}</small>
                              </td>
                              <td>{{ $ordered }}</td>
                              <td>{{ $received }}</td>
                              <td class="js-remaining"
                                  data-remaining="{{ $remaining }}">
                                  {{ $remaining }}
                              </td>

                              <td style="width:120px">
                                <input type="number"
                                       class="form-control form-control-sm js-qty-good"
                                       name="receives[{{ $key }}][qty_good]"
                                       min="0"
                                       value="{{ $remaining }}">
                              </td>
                              <td style="width:120px">
                                <input type="number"
                                       class="form-control form-control-sm js-qty-damaged"
                                       name="receives[{{ $key }}][qty_damaged]"
                                       min="0"
                                       value="0">
                              </td>
                              <td style="width:180px">
                                <input type="text"
                                       class="form-control form-control-sm"
                                       name="receives[{{ $key }}][notes]"
                                       placeholder="Catatan (opsional)">
                                <small class="text-danger small js-row-msg"></small>
                              </td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>

                    <small class="text-muted d-block mb-3">
                      Qty Good + Qty Damaged tidak boleh lebih besar dari Qty Remaining.
                    </small>

                    {{-- FOTO GOOD --}}
                    <div class="mb-3">
                      <label class="form-label">Upload foto barang bagus (opsional)</label>
                      <input type="file" name="photos_good[]" class="form-control" multiple>
                      <small class="text-muted">
                        Upload foto barang dalam kondisi baik. Maks 8MB per file.
                      </small>
                    </div>

                    {{-- FOTO DAMAGED --}}
                    <div class="mb-3">
                      <label class="form-label">Upload foto barang rusak (opsional)</label>
                      <input type="file" name="photos_damaged[]" class="form-control" multiple>
                      <small class="text-muted">
                        Upload bukti kerusakan barang. Maks 8MB per file.
                      </small>
                    </div>
                  </div>

                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                      Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                      <i class="bx bx-save"></i> Simpan Goods Received
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        @endif
      @endforeach
      {{-- ======= END MODALS ======= --}}

    </div> {{-- /#po-table-wrapper --}}
  </div>

</div>

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function bindApproveForms() {
    document.querySelectorAll('.form-approve').forEach(form => {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
          icon: 'question',
          title: 'Approve PO?',
          text: 'PO akan diproses sesuai tahap approval.',
          showCancelButton: true,
          confirmButtonText: 'Ya, approve',
          cancelButtonText: 'Batal'
        }).then(res => {
          if (res.isConfirmed) form.submit();
        });
      });
    });
  }

  // flash message
  document.addEventListener('DOMContentLoaded', function () {
    @if(session('success'))
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: @json(session('success')),
        timer: 2500,
        showConfirmButton: false
      });
    @endif

    @if(session('error'))
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: @json(session('error')),
      });
    @endif

    bindApproveForms();
  });

  // ===== VALIDASI & HITUNG LIVE DI MODAL GR =====
  function bindGrValidation() {
    document.querySelectorAll('.mdl-gr-po').forEach(function (modalEl) {
      modalEl.addEventListener('input', function (e) {
        if (!e.target.classList.contains('js-qty-good') &&
            !e.target.classList.contains('js-qty-damaged')) {
          return;
        }

        const row = e.target.closest('tr');
        if (!row) return;

        const goodEl   = row.querySelector('.js-qty-good');
        const badEl    = row.querySelector('.js-qty-damaged');
        const remCell  = row.querySelector('.js-remaining');
        const msgEl    = row.querySelector('.js-row-msg');

        const maxRem = parseInt(remCell.dataset.remaining || '0', 10);

        let good = parseInt(goodEl.value || '0', 10);
        let bad  = parseInt(badEl.value  || '0', 10);

        if (isNaN(good) || good < 0) good = 0;
        if (isNaN(bad)  || bad  < 0) bad  = 0;

        let total = good + bad;

        if (total > maxRem) {
          if (e.target === goodEl) {
            good = Math.max(0, maxRem - bad);
            goodEl.value = good;
          } else {
            bad = Math.max(0, maxRem - good);
            badEl.value = bad;
          }
          total = good + bad;

          if (msgEl) {
            msgEl.textContent = 'Total Good + Damaged max ' + maxRem + '.';
            setTimeout(() => { msgEl.textContent = ''; }, 2500);
          }
        }

        const sisa = maxRem - total;
        remCell.textContent = sisa;
      });
    });
  }

  // ====== FUNGSI RELOAD TABLE VIA AJAX ======
  function loadPoTable(url) {
    const wrapper = document.getElementById('po-table-wrapper');
    if (!wrapper) return;

    fetch(url, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(res => res.text())
      .then(html => {
        const parser = new DOMParser();
        const doc    = parser.parseFromString(html, 'text/html');
        const newWrap = doc.querySelector('#po-table-wrapper');
        if (!newWrap) return;

        wrapper.innerHTML = newWrap.innerHTML;

        // re-bind setelah konten ke-replace
        bindGrValidation();
        bindApproveForms();
      })
      .catch(err => console.error(err));
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindGrValidation();

    const searchForm  = document.getElementById('po-search-form');
    const searchInput = document.getElementById('po-search');
    let typingTimer   = null;

    function doSearch() {
      const q   = searchInput ? (searchInput.value || '') : '';
      const url = '{{ route('po.index') }}' + '?q=' + encodeURIComponent(q);
      loadPoTable(url);
    }

    // submit form → pakai AJAX, tidak reload page
    if (searchForm) {
      searchForm.addEventListener('submit', function (e) {
        e.preventDefault();
        doSearch();
      });
    }

    // ketik tanpa enter (debounce 400ms)
    if (searchInput) {
      searchInput.addEventListener('keyup', function () {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(doSearch, 400);
      });
    }

    // pagination via AJAX (delegation)
    document.addEventListener('click', function (e) {
      const link = e.target.closest('#po-table-wrapper .pagination a');
      if (!link) return;
      e.preventDefault();
      const url = link.getAttribute('href');
      if (!url) return;
      loadPoTable(url);
    });
  });
</script>
@endsection
