@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

@php
    $me           = auth()->user();
    $roles        = $me?->roles ?? collect();
    $isSuperadmin = $roles->contains('slug', 'superadmin');
    $isWarehouse  = $roles->contains('slug', 'warehouse');
    $myWarehouse  = $me?->warehouse?->warehouse_name
                    ?? $me?->warehouse?->name
                    ?? null;
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Goods Received</h4>
  </div>

  {{-- FILTER --}}
  <div class="card mb-3">
    <div class="card-body">
      <form id="gr-filter-form" class="row g-2" method="GET" action="{{ route('goodreceived.index') }}">
        {{-- Hidden q input — filled by global navbar search --}}
        <input type="hidden" name="q" id="gr_q_input" value="{{ $q }}">

        <div class="col-lg-3 col-md-4">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-select">
            <option value="">— All —</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>
                {{ $s->name }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- WAREHOUSE FILTER --}}
        <div class="col-lg-3 col-md-4">
          <label class="form-label">Warehouse</label>

          @if($isWarehouse)
            {{-- Warehouse user: dikunci ke warehouse sendiri --}}
            <input type="text"
                   class="form-control"
                   value="{{ $myWarehouse ?? '-' }}"
                   disabled>
            <input type="hidden"
                   name="warehouse_id"
                   value="{{ $me?->warehouse_id }}">
          @else
            {{-- superadmin / admin: bisa pilih semua warehouse --}}
            <select name="warehouse_id" class="form-select">
              <option value="">— All —</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}" {{ $warehouseId == $w->id ? 'selected' : '' }}>
                  {{ $w->name ?? $w->warehouse_name }}
                </option>
              @endforeach
            </select>
          @endif
        </div>

        <div class="col-lg-3 col-md-4">
          <label class="form-label">Period</label>
          <div class="d-flex gap-1">
            <input type="date"
                   name="date_from"
                   class="form-control"
                   value="{{ $dateFrom }}">
            <input type="date"
                   name="date_to"
                   class="form-control"
                   value="{{ $dateTo }}">
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end mt-2">
          <button class="btn btn-primary me-2" type="submit">
            <i class="bx bx-search"></i> Filter
          </button>
          <a href="{{ route('goodreceived.index') }}" class="btn btn-outline-secondary">
            Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  {{-- LIST PER PO --}}
  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:60px;">#</th>
            <th>PO Code</th>
            <th>Latest GR Code</th>
            <th>Product (Summary)</th>
            <th>Supplier</th>
            <th>Warehouse</th>
            <th>Last Received At</th>
            <th style="width:180px;" class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pos as $i => $po)
            @php
              // SUMMARY PRODUCT
              $totalLines   = $po->items->count();
              $firstItem    = $po->items->first();
              $firstProduct = optional($firstItem?->product)->name;

              if ($totalLines > 1) {
                  $productSummary = $firstProduct
                      ? $firstProduct . ' + ' . ($totalLines - 1) . ' items'
                      : $totalLines . ' items';
              } else {
                  $productSummary = $firstProduct ?? '-';
              }

              // GR TERAKHIR
              $receipts      = $po->restockReceipts->sortByDesc('received_at');
              $lastReceipt   = $receipts->first();
              $lastGrCode    = $lastReceipt?->code;
              $lastReceiveAt = optional($lastReceipt?->received_at)?->format('Y-m-d H:i') ?? '-';

              // semua foto GR
              $photosAll = $receipts->flatMap(fn ($r) => $r->photos ?? collect());

              // SUPPLIER SUMMARY
              $supFromPo = optional($po->supplier)->name;

              $itemSuppliers = $po->items
                  ->map(fn ($it) => optional(optional($it->product)->supplier)->name)
                  ->filter();

              $receiptSuppliers = $receipts
                  ->map(fn ($r) => optional($r->supplier)->name)
                  ->filter();

              $supplierNames = collect([$supFromPo])
                  ->merge($itemSuppliers)
                  ->merge($receiptSuppliers)
                  ->filter()
                  ->unique()
                  ->values();

              if ($supplierNames->isEmpty()) {
                  $supplierLabel = '-';
              } elseif ($supplierNames->count() === 1) {
                  $supplierLabel = $supplierNames->first();
              } else {
                  $supplierLabel = $supplierNames->first() . ' + ' . ($supplierNames->count() - 1) . ' suppliers';
              }

              // WAREHOUSE LABEL
              $warehouseNames = $receipts
                  ->map(function ($r) {
                      if ($r->warehouse) {
                          return $r->warehouse->warehouse_name
                              ?? $r->warehouse->name
                              ?? 'Warehouse #' . $r->warehouse_id;
                      }
                      return 'Central Stock';
                  })
                  ->filter()
                  ->unique()
                  ->values();

              if ($warehouseNames->isEmpty()) {
                  $warehouseLabel = '-';
              } elseif ($warehouseNames->count() === 1) {
                  $warehouseLabel = $warehouseNames->first();
              } else {
                  $hasCentral = $warehouseNames->contains('Central Stock');
                  $otherCount = $warehouseNames->count() - 1;

                  if ($hasCentral) {
                      $warehouseLabel = 'Central Stock + ' . $otherCount . ' warehouses';
                  } else {
                      $warehouseLabel = $warehouseNames->first() . ' + ' . $otherCount . ' warehouses';
                  }
              }
            @endphp

            <tr>
              <td>{{ $pos->firstItem() + $i }}</td>

              <td>
                <span class="badge bg-label-primary">
                  {{ $po->po_code }}
                </span>
              </td>

              <td>{{ $lastGrCode ?? '-' }}</td>
              <td>{{ $productSummary }}</td>
              <td>{{ $supplierLabel }}</td>
              <td>{{ $warehouseLabel }}</td>
              <td>{{ $lastReceiveAt }}</td>

              <td class="text-center">
                <div class="d-inline-flex gap-1">
                  <button type="button"
                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-detail-gr"
                    data-detail-url="{{ route('goodreceived.detail', $po) }}">
                    <i class="bx bx-file"></i>
                    <span>Detail</span>
                    @if($photosAll->count() > 0)
                      <span class="badge bg-primary border-0 ms-1"
                            style="font-size:0.65rem;min-width:20px;">
                        {{ $photosAll->count() }}
                      </span>
                    @endif
                  </button>

                  
                  {{-- CANCEL GR (HANYA SUPERADMIN) --}}
                  @if($isSuperadmin && $lastReceipt)
                    <form method="POST"
                          action="{{ route('good-received.cancel', $lastReceipt) }}"
                          class="form-cancel-gr d-inline">
                      @csrf
                      {{-- kalau di route kamu pakai DELETE:
                      @method('DELETE')
                      --}}
                      <button type="submit"
                              class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                        <i class="bx bx-undo"></i>
                        <span>Cancel GR</span>
                      </button>
                    </form>
                  @endif

                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted">
                No Goods Received records found.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($pos->hasPages())
      <div class="card-footer d-flex justify-content-end">
        {{ $pos->onEachSide(1)->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </div>
</div>

<div class="modal fade" id="modal-po-gr-detail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" id="modal-po-gr-detail-content">
      {{-- isi akan di-load via AJAX --}}
      <div class="text-center text-muted py-4">Loading data...</div>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
  // SweetAlert flash
  @if(session('success'))
    Swal.fire({
      icon: 'success',
      title: 'Success',
      text: @json(session('success')),
      timer: 2500,
      showConfirmButton: false
    });
  @endif

  @if(session('error'))
    Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: @json(session('error')),
    });
  @endif

  // Auto-filter (search / select / date)
  const formFilter = document.getElementById('gr-filter-form');
  if (formFilter) {
    const debounce = (fn, delay = 400) => {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(null, args), delay);
      };
    };

    const autoSubmit = debounce(() => formFilter.submit(), 400);

    // Connect global navbar search → auto-submit form
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
      // Pre-fill globalSearch with current active q value
      globalSearch.value = @json($q ?? '');

      globalSearch.addEventListener('keyup', debounce(function () {
        const hidden = formFilter.querySelector('input[name="q"]');
        if (hidden) hidden.value = globalSearch.value;
        formFilter.submit();
      }, 400));
    }

    formFilter.querySelectorAll('select,input[type="date"]').forEach(el => {
      el.addEventListener('change', () => formFilter.submit());
    });
  }

  // Konfirmasi Cancel GR (SweetAlert)
  document.querySelectorAll('.form-cancel-gr').forEach(form => {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      Swal.fire({
        icon: 'warning',
        title: 'Cancel Goods Received?',
        text: 'Stock will be rolled back according to this Goods Received record.',
        showCancelButton: true,
        confirmButtonText: 'Yes, cancel',
        cancelButtonText: 'Cancel'
      }).then(result => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

    // ========== MODAL DETAIL GR (AJAX) ==========
  const modalEl   = document.getElementById('modal-po-gr-detail');
  const modalBody = document.getElementById('modal-po-gr-detail-content');
  const bsModal   = modalEl ? new bootstrap.Modal(modalEl) : null;

  function bindDetailButtons() {
    document.querySelectorAll('.btn-detail-gr').forEach(btn => {
      btn.addEventListener('click', function () {
        const url = this.dataset.detailUrl;
        if (!url || !bsModal) return;

        modalBody.innerHTML = '<div class="text-center text-muted py-4">Loading data...</div>';

        fetch(url, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
          .then(res => res.text())
          .then(html => {
            modalBody.innerHTML = html;

            // re-bind SweetAlert untuk tombol Cancel GR di dalam modal
            modalBody.querySelectorAll('.form-cancel-gr').forEach(form => {
              form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                  icon: 'warning',
                  title: 'Cancel Goods Received?',
                  text: 'Stock will be rolled back according to this Goods Received record.',
                  showCancelButton: true,
                  confirmButtonText: 'Yes, cancel',
                  cancelButtonText: 'Cancel'
                }).then(result => {
                  if (result.isConfirmed) {
                    form.submit();
                  }
                });
              });
            });
          })
          .catch(err => {
            console.error(err);
            modalBody.innerHTML = '<div class="text-danger text-center py-4">Failed to load Goods Received data.</div>';
          });

        bsModal.show();
      });
    });
  }
  bindDetailButtons();
});
</script>
@endsection
