@extends('layouts.home')

@section('content')

@php
    $me    = auth()->user();
    $roles = $me?->roles ?? collect();

    $isSuperadmin  = $roles->contains('slug', 'superadmin');
    $isProcurement = $roles->contains('slug', 'procurement');
    $isCeo         = $roles->contains('slug', 'ceo');
    $isWarehouse   = $roles->contains('slug', 'warehouse');

    $statusOptions = $statusOptions ?? [
        '' => 'All Status',
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'partially_received' => 'Partially Received',
        'received' => 'Received',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    $approvalOptions = $approvalOptions ?? [
        '' => 'All Approval',
        'draft' => 'Draft',
        'waiting_procurement' => 'Waiting Procurement',
        'waiting_ceo' => 'Waiting CEO',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    $warehouses = $warehouses ?? \App\Models\Warehouse::query()
        ->select('id','warehouse_name')
        ->orderBy('warehouse_name')
        ->get();

    $perPage = (int) request('per_page', 10);
    $myWhId = $me->warehouse_id ?? null;
@endphp

<style>
  .po-card-title { font-size: 1rem; }
  .po-filters .form-label { font-size: .75rem; color: #6c757d; margin-bottom: .25rem; }
  .po-filters .form-control,
  .po-filters .form-select,
  .po-filters .input-group-text { font-size: .8125rem; }
  .po-filters .input-group-text { padding: .25rem .5rem; }
  .po-filters .form-control-sm,
  .po-filters .form-select-sm { padding-top: .35rem; padding-bottom: .35rem; }

  .po-table thead th { font-size: .75rem; letter-spacing: .02em; text-transform: uppercase; color: #6c757d; }
  .po-table tbody td { font-size: .8125rem; }
  .po-table .badge { font-size: .7rem; }
  .po-muted { font-size: .75rem; }

  @media (max-width: 576px) {
    .po-actions { width: 100%; justify-content: flex-start !important; }
    .po-actions form { width: 100%; }
    .po-actions .btn { width: 100%; }
  }

  .swal2-container { z-index: 99999 !important; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
      <div class="d-flex flex-column">
        <h5 class="mb-0 fw-bold po-card-title">Purchase Orders</h5>
        <span class="text-muted po-muted">Filter otomatis (AJAX) — tanpa tombol apply.</span>
      </div>

      <div class="d-flex flex-wrap gap-2 ms-auto po-actions justify-content-end">
        <form id="po-export-form"
              method="GET"
              action="{{ route('po.export.index') }}"
              class="d-flex align-items-center">

          <input type="hidden" name="q" value="{{ request('q') }}">
          <input type="hidden" name="status" value="{{ request('status') }}">
          <input type="hidden" name="approval_status" value="{{ request('approval_status') }}">
          <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}">
          <input type="hidden" name="from" value="{{ request('from') }}">
          <input type="hidden" name="to" value="{{ request('to') }}">

          <button class="btn btn-outline-success btn-sm" type="submit">
            <i class="bx bx-download"></i> Excel
          </button>
        </form>

        @if($isSuperadmin)
          <form action="{{ route('po.store') }}" method="POST" class="d-flex align-items-center">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bx bx-plus"></i> New PO
            </button>
          </form>
        @endif
      </div>
    </div>

    <div class="card-body pt-3 pb-2">
      <form id="po-filter-form"
            method="get"
            action="{{ route('po.index') }}"
            class="po-filters">

        <input type="hidden" name="q" value="{{ request('q') }}">

        <div class="row g-2 align-items-end">

          <div class="col-6 col-sm-3 col-lg-2">
            <label class="form-label">From</label>
            <input type="date"
                   class="form-control form-control-sm"
                   name="from"
                   value="{{ request('from') }}">
          </div>

          <div class="col-6 col-sm-3 col-lg-2">
            <label class="form-label">To</label>
            <input type="date"
                   class="form-control form-control-sm"
                   name="to"
                   value="{{ request('to') }}">
          </div>

          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm">
              @foreach($statusOptions as $k => $v)
                <option value="{{ $k }}" @selected((string)request('status','') === (string)$k)>{{ $v }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label">Approval</label>
            <select name="approval_status" class="form-select form-select-sm">
              @foreach($approvalOptions as $k => $v)
                <option value="{{ $k }}" @selected((string)request('approval_status','') === (string)$k)>{{ $v }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label">Warehouse</label>
            <select name="warehouse_id" class="form-select form-select-sm">
              <option value="" @selected(request('warehouse_id','')==='')>All Warehouse</option>
              <option value="central" @selected(request('warehouse_id','')==='central')>Central Stock</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}" @selected((string)request('warehouse_id','') === (string)$w->id)>
                  {{ $w->warehouse_name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-6 col-sm-3 col-lg-2">
            <label class="form-label">Per Page</label>
            <select name="per_page" class="form-select form-select-sm">
              @foreach([10,25,50,100] as $n)
                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}/page</option>
              @endforeach
            </select>
          </div>
        </div>
      </form>

      <div class="alert alert-info small py-2 mt-3 mb-0">
        <strong>Rule Approval:</strong>
        <ul class="mb-0 ps-3">
          <li>Grand total &le; Rp 1.000.000 → cukup disetujui <strong>Procurement</strong>.</li>
          <li>Grand total &gt; Rp 1.000.000 → wajib 2 lapis: <strong>Procurement → CEO</strong>.</li>
        </ul>
      </div>
    </div>

    <div id="po-table-wrapper">

      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0 po-table">
          <thead>
            <tr>
              <th class="text-nowrap">PO CODE</th>
              <th>Supplier</th>
              <th>Status</th>
              <th>Approval</th>
              <th class="text-end">Subtotal</th>
              <th class="text-end">Discount</th>
              <th class="text-end">Grand</th>
              <th>Lines</th>
              <th>Warehouse</th>
              <th class="text-end text-nowrap">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pos as $po)
              @php
                $hasGr = (int)($po->gr_count ?? 0) > 0;

                $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

                $poWhIds = $po->items->pluck('warehouse_id')->filter()->unique();
                $isMyWarehousePo = !$myWhId || $poWhIds->contains($myWhId);

                $canReceive = !$hasGr
                              && $po->status === 'ordered'
                              && $po->approval_status === 'approved'
                              && $po->items_count > 0
                              && (
                                  ($fromRequest && $isWarehouse && $isMyWarehousePo)
                                  || (!$fromRequest && $isSuperadmin)
                              );

                $showBlockedReceive = $isSuperadmin
                                      && $fromRequest
                                      && !$hasGr
                                      && $po->status === 'ordered'
                                      && $po->approval_status === 'approved'
                                      && $po->items_count > 0;

                $supplierNames = collect();
                if (!empty($po->supplier?->name)) {
                    $supplierNames->push($po->supplier->name);
                }
                foreach ($po->items as $it) {
                    $sName = $it->product->supplier->name ?? null;
                    if ($sName) $supplierNames->push($sName);
                }
                $supplierNames = $supplierNames->unique()->values();

                if ($supplierNames->isEmpty()) $supplierLabel = '-';
                elseif ($supplierNames->count() === 1) $supplierLabel = $supplierNames->first();
                else $supplierLabel = $supplierNames->first().' + '.($supplierNames->count() - 1).' supplier';

                if (! $fromRequest) {
                    $warehouseLabel = 'Central Stock';
                } else {
                    $warehouseNames = collect();
                    foreach ($po->items as $it) {
                        if ($it->warehouse) {
                            $warehouseNames->push($it->warehouse->warehouse_name ?? $it->warehouse->name);
                        }
                    }
                    $warehouseNames = $warehouseNames->filter()->unique()->values();

                    if ($warehouseNames->isEmpty()) $warehouseLabel = '-';
                    elseif ($warehouseNames->count() === 1) $warehouseLabel = $warehouseNames->first();
                    else $warehouseLabel = $warehouseNames->first().' + '.($warehouseNames->count() - 1).' wh';
                }

                $approvalStatus = $po->approval_status ?: 'draft';

                if ($approvalStatus === 'draft') $approvalBadge = '<span class="badge bg-label-secondary">DRAFT</span>';
                elseif ($approvalStatus === 'waiting_procurement') $approvalBadge = '<span class="badge bg-label-warning">WAITING PROCUREMENT</span>';
                elseif ($approvalStatus === 'waiting_ceo') $approvalBadge = '<span class="badge bg-label-info">WAITING CEO</span>';
                elseif ($approvalStatus === 'approved') $approvalBadge = '<span class="badge bg-label-success">APPROVED</span>';
                elseif ($approvalStatus === 'rejected') $approvalBadge = '<span class="badge bg-label-danger">REJECTED</span>';
                else $approvalBadge = '<span class="badge bg-label-secondary">'.e(strtoupper($approvalStatus)).'</span>';

                $procName = $po->procurementApprover->name ?? '-';
                $ceoName  = $po->ceoApprover->name ?? '-';
              @endphp

              <tr>
                <td class="fw-semibold">{{ $po->po_code }}</td>
                <td>{{ $supplierLabel }}</td>
                <td>
                  <span class="badge bg-label-info text-uppercase">{{ $po->status }}</span>
                  @if($hasGr)
                    <span class="badge bg-label-success ms-1">GR EXIST</span>
                  @endif
                </td>

                <td>
                  {!! $approvalBadge !!}
                  <div class="po-muted text-muted mt-1">
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

                    @if($canReceive)
                      <button type="button"
                              class="btn btn-sm btn-success"
                              data-bs-toggle="modal"
                              data-bs-target="#mdlGR-{{ $po->id }}">
                        <i class="bx bx-download"></i> Receive
                      </button>
                    @elseif($showBlockedReceive)
                      <button type="button"
                              class="btn btn-sm btn-outline-success js-gr-blocked"
                              data-po="{{ $po->po_code }}">
                        <i class="bx bx-info-circle"></i> Receive
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

      {{-- MODAL GR --}}
      @foreach($pos as $po)
        @php
          $hasGr = (int)($po->gr_count ?? 0) > 0;
          $fromRequest = $po->items->whereNotNull('request_id')->isNotEmpty();

          $poWhIds = $po->items->pluck('warehouse_id')->filter()->unique();
          $isMyWarehousePo = !$myWhId || $poWhIds->contains($myWhId);

          $canReceive = !$hasGr
                        && $po->status === 'ordered'
                        && $po->approval_status === 'approved'
                        && $po->items_count > 0
                        && (
                            ($fromRequest && $isWarehouse && $isMyWarehousePo)
                            || (!$fromRequest && $isSuperadmin)
                        );

          if (! $canReceive) { continue; }

          $supplierNames = collect();
          if (!empty($po->supplier?->name)) $supplierNames->push($po->supplier->name);
          foreach ($po->items as $it) {
              $sName = $it->product->supplier->name ?? null;
              if ($sName) $supplierNames->push($sName);
          }
          $supplierNames = $supplierNames->unique()->values();

          if ($supplierNames->isEmpty()) $supplierLabel = '-';
          elseif ($supplierNames->count() === 1) $supplierLabel = $supplierNames->first();
          else $supplierLabel = $supplierNames->first().' + '.($supplierNames->count() - 1).' supplier';

          if (! $fromRequest) {
              $whLabel = 'Central Stock';
          } else {
              $whNames = collect();
              foreach ($po->items as $it) {
                  if ($it->warehouse) $whNames->push($it->warehouse->warehouse_name ?? $it->warehouse->name);
              }
              $whNames = $whNames->filter()->unique()->values();
              if ($whNames->isEmpty()) $whLabel = '-';
              elseif ($whNames->count() === 1) $whLabel = $whNames->first();
              else $whLabel = $whNames->first().' + '.($whNames->count() - 1).' wh';
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
                                  max="{{ $remaining }}"
                                  value="{{ $remaining }}">
                            </td>
                            <td style="width:120px">
                              <input type="number"
                                  class="form-control form-control-sm js-qty-damaged"
                                  name="receives[{{ $key }}][qty_damaged]"
                                  min="0"
                                  max="{{ $remaining }}"
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

    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- ✅ INI KUNCI: Swal sukses setelah GR tersimpan (dari session server) --}}
@if(session('gr_success'))
<script>
  document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
      icon: 'success',
      title: 'Sukses',
      text: @json(session('gr_success')),
      timer: 1500,
      showConfirmButton: false
    });
  });
</script>
@endif

<script>
  // ===== AUTO SYNC GOOD / DAMAGED BIAR GA NGACO (tanpa Swal validasi submit) =====
  (function () {
    const clampInt = (v, min, max) => {
      v = parseInt(v ?? 0, 10);
      if (isNaN(v)) v = 0;
      return Math.min(max, Math.max(min, v));
    };

    function syncRow(tr, changed) {
      const remEl  = tr.querySelector('.js-remaining');
      const goodEl = tr.querySelector('.js-qty-good');
      const badEl  = tr.querySelector('.js-qty-damaged');

      if (!remEl || !goodEl || !badEl) return;

      const remaining = parseInt(remEl.dataset.remaining ?? '0', 10) || 0;

      goodEl.max = remaining;
      badEl.max  = remaining;

      let good = clampInt(goodEl.value, 0, remaining);
      let bad  = clampInt(badEl.value,  0, remaining);

      if (changed === 'good') {
        bad = remaining - good;
      } else if (changed === 'bad') {
        good = remaining - bad;
      } else {
        if (good + bad !== remaining) {
          if (good > 0) bad = remaining - good;
          else if (bad > 0) good = remaining - bad;
          else { good = remaining; bad = 0; }
        }
      }

      goodEl.value = good;
      badEl.value  = bad;
    }

    document.addEventListener('shown.bs.modal', function (e) {
      const modal = e.target;
      if (!modal || !modal.classList.contains('mdl-gr-po')) return;
      modal.querySelectorAll('tbody tr').forEach(tr => syncRow(tr, 'init'));
    });

    document.addEventListener('input', function (e) {
      const good = e.target.closest('.mdl-gr-po .js-qty-good');
      const bad  = e.target.closest('.mdl-gr-po .js-qty-damaged');

      if (good) { syncRow(good.closest('tr'), 'good'); return; }
      if (bad)  { syncRow(bad.closest('tr'), 'bad');  return; }
    });
  })();

  // ===== AJAX FILTER + PAGINATION (NO RELOAD) =====
  (function () {
    const filterForm   = document.getElementById('po-filter-form');
    const exportForm   = document.getElementById('po-export-form');
    const wrapper      = document.getElementById('po-table-wrapper');
    const globalSearch = document.getElementById('globalSearch');

    if (!filterForm || !wrapper) return;

    const actionUrl = filterForm.getAttribute('action');
    const qHidden   = filterForm.querySelector('input[name="q"]');

    const debounce = (fn, ms = 450) => {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
      };
    };

    function sameMonth(fromStr, toStr) {
      if (!fromStr || !toStr) return true;
      return fromStr.slice(0,7) === toStr.slice(0,7);
    }

    function buildQueryFromForm(formEl) {
      const fd = new FormData(formEl);
      const params = new URLSearchParams();
      fd.forEach((v,k) => {
        v = String(v ?? '').trim();
        if (v !== '') params.append(k, v);
      });
      return params.toString();
    }

    function syncQFromGlobal() {
      if (!qHidden) return;
      const val = (globalSearch?.value || '').trim();
      qHidden.value = val;
    }

    function syncGlobalFromHidden() {
      if (!globalSearch || !qHidden) return;
      globalSearch.value = qHidden.value || '';
    }

    function syncExportHidden() {
      if (!exportForm) return;

      const getVal = (name) => (filterForm.querySelector(`[name="${name}"]`)?.value || '');

      exportForm.querySelector('input[name="q"]').value = getVal('q');
      exportForm.querySelector('input[name="status"]').value = getVal('status');
      exportForm.querySelector('input[name="approval_status"]').value = getVal('approval_status');
      exportForm.querySelector('input[name="warehouse_id"]').value = getVal('warehouse_id');
      exportForm.querySelector('input[name="from"]').value = getVal('from');
      exportForm.querySelector('input[name="to"]').value = getVal('to');
    }

    function validateRange() {
      const fromVal = filterForm.querySelector('[name="from"]')?.value || '';
      const toVal   = filterForm.querySelector('[name="to"]')?.value || '';

      if (fromVal && toVal && fromVal > toVal) {
        Swal.fire({ icon:'error', title:'Range salah', text:'Tanggal "from" harus <= "to".' });
        return false;
      }

      if (!sameMonth(fromVal, toVal)) {
        Swal.fire({ icon:'error', title:'Range salah', text:'Range maksimal 1 bulan. From & To harus di bulan yang sama.' });
        return false;
      }
      return true;
    }

    async function loadPage(url) {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const html = await res.text();

      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newWrapper = doc.querySelector('#po-table-wrapper');

      if (!newWrapper) {
        Swal.fire({ icon:'error', title:'Gagal', text:'Wrapper table tidak ditemukan di response.' });
        return;
      }

      wrapper.innerHTML = newWrapper.innerHTML;
    }

    function applyFilters(historyMode = 'replace') {
      syncQFromGlobal();
      if (!validateRange()) return;

      syncExportHidden();

      const qs  = buildQueryFromForm(filterForm);
      const url = actionUrl + (qs ? ('?' + qs) : '');

      loadPage(url).then(() => {
        if (historyMode === 'push') history.pushState({}, '', url);
        else history.replaceState({}, '', url);
      });
    }

    filterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      applyFilters('replace');
    });

    if (globalSearch) {
      globalSearch.setAttribute('placeholder', 'Cari PO code...');
      globalSearch.addEventListener('input', debounce(() => applyFilters('replace'), 450));
    }

    filterForm.querySelectorAll('select, input[type="date"]').forEach(el => {
      el.addEventListener('change', () => applyFilters('replace'));
    });

    wrapper.addEventListener('click', function (e) {
      const a = e.target.closest('.pagination a');
      if (!a) return;
      e.preventDefault();

      syncQFromGlobal();
      syncExportHidden();

      loadPage(a.href).then(() => {
        history.pushState({}, '', a.href);
      });
    });

    window.addEventListener('popstate', function () {
      const urlQ = new URL(location.href).searchParams.get('q') || '';
      if (qHidden) qHidden.value = urlQ;
      syncGlobalFromHidden();

      loadPage(location.href);
      syncExportHidden();
    });

    syncGlobalFromHidden();
    syncExportHidden();
  })();

  // Swal info untuk tombol blocked (biarin)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-gr-blocked');
    if (!btn) return;

    const poCode = btn.getAttribute('data-po') || '';
    Swal.fire({
      icon: 'info',
      title: 'Tidak bisa GR dari Superadmin',
      text: `PO ${poCode} berasal dari Request Restock (Warehouse). Goods Received wajib dilakukan oleh Admin Warehouse terkait.`,
    });
  });
</script>
@endsection