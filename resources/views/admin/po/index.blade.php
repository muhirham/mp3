@extends('layouts.home')

@section('content')

@php
    $me    = auth()->user();
    $roles = $me?->roles ?? collect();

    $isSuperadmin  = $roles->contains('slug', 'superadmin');
    $isProcurement = $roles->contains('slug', 'procurement');
    $isCeo         = $roles->contains('slug', 'ceo');
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0 fw-bold">Purchase Orders</h5>

      <div class="d-flex flex-wrap gap-2 ms-auto">
        {{-- Search PO --}}
        <form class="d-flex gap-2" id="po-search-form" method="get">
          <input id="po-search"
                 class="form-control"
                 name="q"
                 value="{{ $q ?? '' }}"
                 placeholder="Cari PO code...">
          <button class="btn btn-outline-secondary" type="submit">Cari</button>
        </form>

        {{-- Create new PO (biasanya hanya superadmin) --}}
        @if($isSuperadmin)
          <form action="{{ route('po.store') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary">
              <i class="bx bx-plus"></i> New PO
            </button>
          </form>
        @endif
      </div>
    </div>

    <div class="card-body pt-2 pb-0">
      <div class="alert alert-info small py-2 mb-0">
        <strong>Rule Approval:</strong>
        <ul class="mb-0 ps-3">
          <li>Grand total &le; Rp 1.000.000 → cukup disetujui <strong>Procurement</strong>.</li>
          <li>Grand total &gt; Rp 1.000.000 → wajib 2 lapis: <strong>Procurement → CEO</strong>.</li>
        </ul>
      </div>
    </div>

    {{-- WRAPPER TABEL (dipakai juga untuk AJAX) --}}
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
                // apakah sudah punya GR
                $hasGr = (int)($po->gr_count ?? 0) > 0;

                // Receive hanya superadmin + status ordered + approved + belum pernah GR
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

                if (! $fromRequest) {
                    $warehouseLabel = 'Central Stock';
                } else {
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

                // ==== APPROVAL STATUS BADGE ====
                $approvalStatus = $po->approval_status ?: 'draft';

                if ($approvalStatus === 'draft') {
                    $approvalBadge = '<span class="badge bg-label-secondary">DRAFT</span>';
                } elseif ($approvalStatus === 'waiting_procurement') {
                    $approvalBadge = '<span class="badge bg-label-warning">WAITING PROCUREMENT</span>';
                } elseif ($approvalStatus === 'waiting_ceo') {
                    $approvalBadge = '<span class="badge bg-label-info">WAITING CEO</span>';
                } elseif ($approvalStatus === 'approved') {
                    $approvalBadge = '<span class="badge bg-label-success">APPROVED</span>';
                } elseif ($approvalStatus === 'rejected') {
                    $approvalBadge = '<span class="badge bg-label-danger">REJECTED</span>';
                } else {
                    $approvalBadge = '<span class="badge bg-label-secondary">'.e(strtoupper($approvalStatus)).'</span>';
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

      {{-- ======= MODAL GR PER PO ======= --}}
      @foreach($pos as $po)
        @php
          $hasGr      = (int)($po->gr_count ?? 0) > 0;
          $canReceive = $isSuperadmin
                        && !$hasGr
                        && $po->status === 'ordered'
                        && $po->approval_status === 'approved'
                        && $po->items_count > 0;

          if (! $canReceive) {
              continue;
          }

          // summary supplier
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

          // summary warehouse
          $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();
          if (! $fromRequest) {
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

        <div class="modal fade mdl-gr-po" id="mdlGR-{{ $po->id }}" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Goods Received – {{ $po->po_code }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <form action="{{ route('po.gr.store', $po) }}"
                    method="POST"
                    enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                  <p class="mb-3 small">
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
                            <td class="js-remaining" data-remaining="{{ $remaining }}">{{ $remaining }}</td>

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

                  <div class="mb-3">
                    <label class="form-label">Upload foto barang bagus (opsional)</label>
                    <input type="file" name="photos_good[]" class="form-control" multiple>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Upload foto barang rusak (opsional)</label>
                    <input type="file" name="photos_damaged[]" class="form-control" multiple>
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
      @endforeach
      {{-- ======= END MODALS ======= --}}

    </div> {{-- /#po-table-wrapper --}}
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // FLASH
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
  });

  // VALIDASI DI MODAL GR
  function bindGrValidation() {
    document.querySelectorAll('.mdl-gr-po').forEach(function (modalEl) {
      modalEl.addEventListener('input', function (e) {
        if (!e.target.classList.contains('js-qty-good') &&
            !e.target.classList.contains('js-qty-damaged')) {
          return;
        }

        const row = e.target.closest('tr');
        if (!row) return;

        const goodEl  = row.querySelector('.js-qty-good');
        const badEl   = row.querySelector('.js-qty-damaged');
        const remCell = row.querySelector('.js-remaining');
        const msgEl   = row.querySelector('.js-row-msg');

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

        remCell.textContent = maxRem - total;
      });
    });
  }

  bindGrValidation();

  // Pencarian & pagination AJAX (opsional, kalau mau tetap pakai bisa lanjutkan logic lama di sini)
</script>
@endsection
