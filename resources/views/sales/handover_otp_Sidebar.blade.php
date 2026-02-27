@extends('layouts.home')

@section('content')
<style>
/* ================================
   MOBILE FRIENDLY IMPROVEMENT
================================ */

/* Filter more responsive on mobile */
@media (max-width: 768px) {
  #filterForm .col-md-3 {
    flex: 0 0 100%;
    max-width: 100%;
  }
}

/* Table to card layout (mobile) */
@media (max-width: 768px) {

  .table-responsive {
    overflow: visible;
  }

  table.table thead {
    display: none;
  }

  table.table tbody tr {
    display: block;
    background: #fff;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  }

  table.table tbody td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: none !important;
    padding: 6px 0;
    font-size: 14px;
  }

  table.table tbody td::before {
    content: attr(data-label);
    font-weight: 600;
    color: #696cff;
    margin-right: 10px;
  }

  table.table tbody td:first-child {
    display: none;
  }
}
</style>

<div class="container-xxl flex-grow-1 container-p-y">

  <h4 class="fw-bold py-2 mb-3">
    <span class="text-muted fw-light">Sales /</span> OTP Handover
  </h4>

  <div class="card mb-4">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start">
      <h5 class="mb-2 mb-md-0 fw-bold">OTP Handover List</h5>
      <small class="text-muted">
        This page displays the <b>morning</b> OTP for your handovers.
      </small>
    </div>

    <div class="card-body">

      {{-- Filter --}}
      <form id="filterForm" class="row g-2 mb-3">
        <div class="col-md-3">
          <label class="form-label">From Date</label>
          <input type="date" name="date_from" id="dateFrom" value="{{ $dateFrom }}" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label">To Date</label>
          <input type="date" name="date_to" id="dateTo" value="{{ $dateTo }}" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" id="statusFilter" class="form-select">
            @foreach($statusOptions as $value => $label)
              <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </form>

      {{-- Table --}}
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead>
            <tr>
              <th style="width: 6%">#</th>
              <th style="width: 12%">Date</th>
              <th style="width: 20%">Handover Code</th>
              <th>Warehouse</th>
              <th style="width: 14%">Status</th>
              <th style="width: 14%">Morning OTP</th>
            </tr>
          </thead>

          <tbody id="handoverBody">

            @forelse($handovers as $idx => $h)
              @php
                  $badgeClass = match ($h->status) {
                      'closed'               => 'bg-label-success',
                      'on_sales'             => 'bg-label-info',
                      'waiting_morning_otp',
                      'waiting_evening_otp'  => 'bg-label-warning',
                      'cancelled'            => 'bg-label-danger',
                      default                => 'bg-label-secondary',
                  };
              @endphp

              <tr>
                <td>{{ $idx + 1 }}</td>

                <td data-label="Date">
                  {{ optional($h->handover_date)->format('Y-m-d') }}
                </td>

                <td class="fw-semibold" data-label="Code">
                  {{ $h->code }}
                </td>

                <td data-label="Warehouse">
                  {{ $h->warehouse->warehouse_name
                      ?? $h->warehouse->name
                      ?? '-' }}
                </td>

                <td data-label="Status">
                  <span class="badge {{ $badgeClass }}">
                    {{ $h->status }}
                  </span>
                </td>

                <td data-label="Morning OTP">
                  @if($h->morning_otp_plain)
                    <span class="badge bg-label-primary fs-6">
                      {{ $h->morning_otp_plain }}
                    </span>
                    <div class="small text-muted">
                      Sent: {{ optional($h->morning_otp_sent_at)->format('H:i') }}
                    </div>
                  @else
                    <span class="text-muted small">Not available</span>
                  @endif
                </td>
              </tr>

            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">
                  No handovers found for this period.
                </td>
              </tr>
            @endforelse

          </tbody>
        </table>
      </div>

      <div class="form-text mt-2">
        <ul class="mb-0">
          <li>The OTP is still sent to your email, but can also be reviewed on this page.</li>
          <li>If the OTP does not appear, make sure the handover has been created by the warehouse admin.</li>
        </ul>
      </div>

    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function() {
    const bodyEl      = document.getElementById('handoverBody');
    const dateFromEl  = document.getElementById('dateFrom');
    const dateToEl    = document.getElementById('dateTo');
    const statusEl    = document.getElementById('statusFilter');
    const filterForm  = document.getElementById('filterForm');
    const dataUrl     = @json(url()->current());

    if (!bodyEl || !dateFromEl || !dateToEl || !statusEl || !filterForm) return;

    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
    });

    function setLoading() {
        bodyEl.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted">
                    Memuat data...
                </td>
            </tr>
        `;
    }

    function setEmpty() {
        bodyEl.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted">
                    Tidak ada handover pada periode ini.
                </td>
            </tr>
        `;
    }

    function renderRows(rows) {
        if (!rows || !rows.length) {
            setEmpty();
            return;
        }

        let html = '';
        rows.forEach(function (r) {
            const morningOtp = r.morning_otp_plain
                ? `<span class="badge bg-label-primary fs-6">${r.morning_otp_plain}</span>
                   <div class="small text-muted">Kirim: ${r.morning_otp_sent_at || '-'}</div>`
                : `<span class="text-muted small">Belum ada</span>`;

            html += `
                <tr>
                    <td>${r.no}</td>
                    <td data-label="Date">${r.date || '-'}</td>
                    <td class="fw-semibold" data-label="Handover Code">${r.code}</td>
                    <td data-label="Warehouse">${r.warehouse || '-'}</td>
                    <td data-label="Status">
                        <span class="badge ${r.status_badge_class}">
                            ${r.status}
                        </span>
                    </td>
                    <td data-label="Morning OTP">${morningOtp}</td>
                </tr>
            `;
        });

        bodyEl.innerHTML = html;
    }

    async function loadData() {
        setLoading();

        const params = new URLSearchParams({
            date_from: dateFromEl.value || '',
            date_to:   dateToEl.value   || '',
            status:    statusEl.value   || 'all',
        });

        try {
            const res = await fetch(`${dataUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error('Gagal memuat data');

            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Gagal memuat data');

            renderRows(json.rows || []);
        } catch (err) {
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        ${err.message}
                    </td>
                </tr>
            `;
        }
    }

    dateFromEl.addEventListener('change', loadData);
    dateToEl.addEventListener('change', loadData);
    statusEl.addEventListener('change', loadData);

    loadData();
})();
</script>
@endpush