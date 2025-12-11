@extends('layouts.home')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <h4 class="fw-bold py-2 mb-3">
    <span class="text-muted fw-light">Sales /</span> OTP Handover
  </h4>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-bold">Daftar OTP Handover</h5>
      <small class="text-muted">
        Halaman ini menampilkan OTP <b>pagi</b> & <b>sore</b> untuk handover milik Anda.
      </small>
    </div>
    <div class="card-body">

      {{-- Filter (tanpa tombol, trigger via JS) --}}
      <form id="filterForm" class="row g-2 mb-3">
        <div class="col-md-3">
          <label class="form-label">Dari Tanggal</label>
          <input type="date" name="date_from" id="dateFrom" value="{{ $dateFrom }}" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Sampai Tanggal</label>
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
        {{-- tidak ada tombol tampilkan --}}
      </form>

      {{-- Tabel --}}
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead>
            <tr>
              <th style="width: 6%">#</th>
              <th style="width: 12%">Tanggal</th>
              <th style="width: 18%">Kode Handover</th>
              <th>Warehouse</th>
              <th style="width: 14%">Status</th>
              <th style="width: 12%">OTP Pagi</th>
              <th style="width: 12%">OTP Sore</th>
            </tr>
          </thead>
          <tbody id="handoverBody">
            {{-- initial render (biar nggak kosong kalau JS mati) --}}
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
                <td>{{ optional($h->handover_date)->format('Y-m-d') }}</td>
                <td class="fw-semibold">{{ $h->code }}</td>
                <td>
                  {{ $h->warehouse->warehouse_name
                      ?? $h->warehouse->name
                      ?? '-' }}
                </td>
                <td>
                  <span class="badge {{ $badgeClass }}">
                    {{ $h->status }}
                  </span>
                </td>
                <td>
                  @if($h->morning_otp_plain)
                    <span class="badge bg-label-primary fs-6">
                      {{ $h->morning_otp_plain }}
                    </span>
                    <div class="small text-muted">
                      Kirim: {{ optional($h->morning_otp_sent_at)->format('H:i') }}
                    </div>
                  @else
                    <span class="text-muted small">Belum ada</span>
                  @endif
                </td>
                <td>
                  @if($h->evening_otp_plain)
                    <span class="badge bg-label-info fs-6">
                      {{ $h->evening_otp_plain }}
                    </span>
                    <div class="small text-muted">
                      Kirim: {{ optional($h->evening_otp_sent_at)->format('H:i') }}
                    </div>
                  @else
                    <span class="text-muted small">Belum ada</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted">
                  Tidak ada handover pada periode ini.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="form-text mt-2">
        <ul class="mb-0">
          <li>OTP tetap dikirim ke email, namun bisa dicek ulang di halaman ini.</li>
          <li>Jika OTP tidak muncul, pastikan handover sudah dibuat oleh admin gudang.</li>
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

    if (!bodyEl || !dateFromEl || !dateToEl || !statusEl) {
        return;
    }

    // Jangan submit form ke server, kita pakai AJAX
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
    });

    function setLoading() {
        bodyEl.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    Memuat data...
                </td>
            </tr>
        `;
    }

    function setEmpty() {
        bodyEl.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    Tidak ada handover pada periode ini.
                </td>
            </tr>
        `;
    }

    function renderRows(rows) {
        if (!rows.length) {
            setEmpty();
            return;
        }

        let html = '';
        rows.forEach(function (r) {
            const morningOtp  = r.morning_otp_plain
                ? `<span class="badge bg-label-primary fs-6">${r.morning_otp_plain}</span>
                   <div class="small text-muted">Kirim: ${r.morning_otp_sent_at || '-'}</div>`
                : `<span class="text-muted small">Belum ada</span>`;

            const eveningOtp  = r.evening_otp_plain
                ? `<span class="badge bg-label-info fs-6">${r.evening_otp_plain}</span>
                   <div class="small text-muted">Kirim: ${r.evening_otp_sent_at || '-'}</div>`
                : `<span class="text-muted small">Belum ada</span>`;

            html += `
                <tr>
                    <td>${r.no}</td>
                    <td>${r.date || '-'}</td>
                    <td class="fw-semibold">${r.code}</td>
                    <td>${r.warehouse || '-'}</td>
                    <td>
                        <span class="badge ${r.status_badge_class}">
                            ${r.status}
                        </span>
                    </td>
                    <td>${morningOtp}</td>
                    <td>${eveningOtp}</td>
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
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) {
                throw new Error('Gagal memuat data');
            }

            const json = await res.json();
            if (!json.success) {
                throw new Error(json.message || 'Gagal memuat data');
            }

            renderRows(json.rows || []);
        } catch (err) {
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger">
                        ${err.message}
                    </td>
                </tr>
            `;
        }
    }

    // Trigger saat filter berubah
    dateFromEl.addEventListener('change', loadData);
    dateToEl.addEventListener('change', loadData);
    statusEl.addEventListener('change', loadData);

    // First load
    loadData();
})();
</script>
@endpush
