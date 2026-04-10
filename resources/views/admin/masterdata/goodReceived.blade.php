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
    <h4 class="mb-0 fw-bold">Goods Received Dashboard</h4>
  </div>

  {{-- FILTER --}}
  <div class="card mb-3">
    <div class="card-body">
      <form id="gr-filter-form" class="row g-2" method="GET" action="{{ route('goodreceived.index') }}">
        {{-- Hidden q input — filled by global navbar search --}}
        <input type="hidden" name="q" id="gr_q_input" value="{{ $q }}">

        <div class="col-lg-2 col-md-4">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-select">
            <option value="">— All —</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}" {{ ($supplierId ?? '') == $s->id ? 'selected' : '' }}>
                {{ $s->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-lg-2 col-md-4">
            <label class="form-label">Type</label>
            <select name="gr_type" class="form-select">
              <option value="">— All —</option>
              <option value="po" {{ ($grType ?? '') == 'po' ? 'selected' : '' }}>PO (Procurement)</option>
              <option value="request_stock" {{ ($grType ?? '') == 'request_stock' ? 'selected' : '' }}>Request Stock</option>
              <option value="gr_transfer" {{ ($grType ?? '') == 'gr_transfer' ? 'selected' : '' }}>Warehouse Transfer</option>
              <option value="gr_return" {{ ($grType ?? '') == 'gr_return' ? 'selected' : '' }}>Sales Return / Damage</option>
            </select>
          </div>

        {{-- WAREHOUSE FILTER --}}
        <div class="col-lg-2 col-md-4">
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

        <div class="col-12 d-flex justify-content-between mt-2">
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-success" id="btnExportExcel">
                <i class="bx bx-export me-1"></i> Export Excel
            </button>
            <div class="ms-3 d-flex align-items-center gap-2">
              <span class="small text-muted text-uppercase fw-semibold">Show</span>
              <select name="per_page" class="form-select form-select-sm" style="width: 80px;">
                <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
              </select>
            </div>
          </div>
          <div>
            <button class="btn btn-primary me-2" type="submit">
                <i class="bx bx-search"></i> Filter
            </button>
            <a href="{{ route('goodreceived.index') }}" class="btn btn-outline-secondary">
                Reset
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- LIST PER PO --}}
  <div class="card" id="gr-table-container">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>Type</th>
            <th>GR Code</th>
            <th>Source Ref</th>
            <th>Summary</th>
            <th>Warehouse</th>
            <th>Received At</th>
            <th style="width:170px;" class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody id="gr-table-body">
          @forelse($receipts as $i => $rr)
            @php
              $typeLabel = match($rr->gr_type) {
                  'po' => ['text' => 'PO', 'color' => 'primary'],
                  'request_stock' => ['text' => 'Request', 'color' => 'warning'],
                  'gr_transfer' => ['text' => 'Transfer', 'color' => 'info'],
                  'gr_return' => ['text' => 'Return', 'color' => 'danger'],
                  default => ['text' => 'Other', 'color' => 'secondary'],
              };

              $sourceLabel = '-';
              if($rr->gr_type == 'po') {
                  $sourceLabel = $rr->purchaseOrder?->po_code ?? '-';
              } elseif($rr->gr_type == 'request_stock') {
                  $sourceLabel = $rr->request?->code ?? ('RS-'.$rr->request_id);
              }

              $recDate = optional($rr->received_at)->format('d/m/Y H:i') ?? '-';
              $summary = ($rr->total_items ?? 1) . ' items (' . ($rr->total_good ?? 0) . ' Good)';
              
              $whName = $rr->warehouse?->warehouse_name ?? ($rr->warehouse?->name ?? 'Central');
            @endphp
            <tr>
              <td>{{ $receipts->firstItem() + $i }}</td>
              <td>
                <span class="badge bg-label-{{ $typeLabel['color'] }}">
                  {{ $typeLabel['text'] }}
                </span>
              </td>
              <td class="fw-bold">{{ $rr->code }}</td>
              <td>{{ $sourceLabel }}</td>
              <td><small>{{ $summary }}</small></td>
              <td>{{ $whName }}</td>
              <td>{{ $recDate }}</td>

              <td class="text-center">
                <div class="d-inline-flex gap-1">
                  <button type="button"
                    class="btn btn-sm btn-outline-primary btn-detail-gr"
                    data-detail-url="{{ route('goodreceived.detail', ['code' => $rr->code]) }}">
                    <i class="bx bx-show me-1"></i> Detail
                  </button>
                  
                  @if($isSuperadmin && in_array($rr->gr_type, ['po', 'request_stock']))
                    <form method="POST"
                          action="{{ route('good-received.cancel', $rr->code) }}"
                          class="form-cancel-gr d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bx bx-undo me-1"></i> Cancel
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

    @if($receipts->total() > 0)
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing {{ $receipts->firstItem() }} to {{ $receipts->lastItem() }} of {{ $receipts->total() }} entries
        </small>
        <div class="gr-pagination-wrapper">
            @if($receipts->hasPages())
                {{ $receipts->onEachSide(1)->links('pagination::bootstrap-5') }}
            @endif
        </div>
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

    const autoSubmit = debounce(() => {
        const formData = new FormData(formFilter);
        const params = new URLSearchParams(formData);

        // Tambahkan q dari globalSearch jika ada
        if (globalSearch) {
            params.set('q', globalSearch.value);
        }

        fetchGrData("{{ route('goodreceived.index') }}?" + params.toString());
    }, 400);

    // Connect global navbar search → AJAX fetch
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
      globalSearch.value = @json($q ?? '');
      globalSearch.addEventListener('keyup', autoSubmit);
    }

    formFilter.querySelectorAll('select,input[type="date"]').forEach(el => {
      el.addEventListener('change', autoSubmit);
    });

    // Handle form submit (untuk tombol Filter)
    formFilter.addEventListener('submit', function(e) {
        e.preventDefault();
        autoSubmit();
    });

    // Handle AJAX Fetch & Table replacement
    function fetchGrData(url) {
        const container = document.getElementById('gr-table-container');
        if (!container) return;

        // Visual loading state
        container.style.opacity = '0.5';

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newTable = doc.getElementById('gr-table-container');

            if (newTable) {
                container.innerHTML = newTable.innerHTML;
            }

            container.style.opacity = '1';

            // Re-bind actions (Detail & Cancel)
            bindDetailButtons();
            bindCancelButtons();

            // Update browser URL
            window.history.pushState(null, '', url);
        })
        .catch(err => {
            console.error(err);
            container.style.opacity = '1';
        });
    }

    // Intercept pagination clicks
    document.addEventListener('click', function(e) {
        const link = e.target.closest('#gr-table-container .pagination a');
        if (link) {
            e.preventDefault();
            fetchGrData(link.href);
        }
    });

    // Handle Export Excel Button (tetap pake window.location.href karena ini file download)
    const btnExport = document.getElementById('btnExportExcel');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            const formData = new FormData(formFilter);
            const params = new URLSearchParams();
            
            if (globalSearch) {
                params.append('q', globalSearch.value);
            }

            for (const [key, value] of formData.entries()) {
                if (value) params.append(key, value);
            }

            const exportUrl = "{{ route('goodreceived.export') }}?" + params.toString();
            window.location.href = exportUrl;
        });
    }
  }

  // Konfirmasi Cancel GR (SweetAlert)
  function bindCancelButtons() {
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
  }
  bindCancelButtons();

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
