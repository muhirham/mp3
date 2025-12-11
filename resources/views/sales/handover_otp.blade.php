@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Barang Dibawa Hari Ini</h4>
  </div>

  @php
      $statusLabelMap = [
          'draft'               => 'Draft',
          'waiting_morning_otp' => 'Menunggu OTP Pagi',
          'on_sales'            => 'On Sales',
          'waiting_evening_otp' => 'Menunggu OTP Sore',
          'closed'              => 'Closed',
          'cancelled'           => 'Cancelled',
      ];

      $badgeClassMap = [
          'closed'              => 'bg-label-success',
          'on_sales'            => 'bg-label-info',
          'waiting_morning_otp' => 'bg-label-warning',
          'waiting_evening_otp' => 'bg-label-warning',
          'cancelled'           => 'bg-label-danger',
          'default'             => 'bg-label-secondary',
      ];

      $statusKey   = $handover?->status;
      $statusLabel = $statusLabelMap[$statusKey] ?? ($statusKey ?? '-');
      $badgeClass  = $badgeClassMap[$statusKey] ?? $badgeClassMap['default'];
  @endphp

  {{-- INFO HANDOVER / NO DATA --}}
  <div class="card mb-3">
    <div class="card-body">
      @if(!$handover)
        <div class="text-center text-muted">
          <div class="fw-semibold mb-1">Tidak ada handover aktif untuk hari ini.</div>
          <div>Silakan hubungi admin warehouse jika seharusnya ada barang yang dibawa.</div>
        </div>
      @else
        <div class="row">
          <div class="col-md-4 mb-2">
            <div class="small text-muted fw-semibold">Kode Handover</div>
            <div class="fs-6 fw-bold">{{ $handover->code }}</div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="small text-muted fw-semibold">Tanggal</div>
            <div>{{ optional($handover->handover_date)->format('Y-m-d') }}</div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="small text-muted fw-semibold">Warehouse</div>
            <div>
              {{ optional($handover->warehouse)->warehouse_name
                  ?? optional($handover->warehouse)->name
                  ?? '-' }}
            </div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="small text-muted fw-semibold">Sales</div>
            <div>{{ optional($handover->sales)->name ?? '-' }}</div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="small text-muted fw-semibold">Status</div>
            <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
          </div>
          <div class="col-md-4 mb-2">
            <div class="small text-muted fw-semibold">OTP Pagi Terverifikasi</div>
            <div id="otpStatusText">
              @if($isOtpVerified)
                ✅ <span class="text-success">Sudah diverifikasi di menu ini</span>
              @else
                ❌ <span class="text-muted">Belum</span>
              @endif
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>

  {{-- TABEL ITEM (READ ONLY) --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-bold">Daftar Barang Dibawa Hari Ini</h5>
      <small class="text-muted">Read only – tidak bisa diubah dari menu ini.</small>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="itemsTable">
          <thead>
          <tr>
            <th style="width:30%">Produk</th>
            <th class="text-end" style="width:10%">Dibawa</th>
            <th class="text-end" style="width:10%">Kembali</th>
            <th class="text-end" style="width:10%">Terjual</th>
            <th class="text-end" style="width:15%">Harga</th>
            <th class="text-end" style="width:12%">Nilai Dibawa</th>
            <th class="text-end" style="width:13%">Nilai Terjual</th>
          </tr>
          </thead>
          <tbody id="itemsBody">
          @if(!$handover)
            <tr>
              <td colspan="7" class="text-center text-muted">
                Tidak ada handover aktif hari ini.
              </td>
            </tr>
          @else
            @if(!$isOtpVerified)
              <tr>
                <td colspan="7" class="text-center text-muted">
                  Masukkan OTP pagi terlebih dahulu untuk melihat daftar barang.
                </td>
              </tr>
            @else
              @foreach($items as $row)
                <tr>
                  <td>
                    <div class="fw-semibold">
                      {{ $row->product->name ?? $row->product->product_name ?? '-' }}
                    </div>
                    <div class="small text-muted">
                      {{ $row->product->product_code ?? '' }}
                    </div>
                  </td>
                  <td class="text-end">{{ (int) $row->qty_start }}</td>
                  <td class="text-end">{{ (int) $row->qty_returned }}</td>
                  <td class="text-end">{{ (int) $row->qty_sold }}</td>
                  <td class="text-end">
                    {{ 'Rp ' . number_format((int) $row->unit_price, 0, ',', '.') }}
                  </td>
                  <td class="text-end">
                    {{ 'Rp ' . number_format((int) $row->line_total_start, 0, ',', '.') }}
                  </td>
                  <td class="text-end">
                    {{ 'Rp ' . number_format((int) $row->line_total_sold, 0, ',', '.') }}
                  </td>
                </tr>
              @endforeach
            @endif
          @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const hasHandover   = @json((bool) $handover);
    const isOtpVerified = @json($isOtpVerified);
    const handoverId    = @json($handover->id ?? null);
    const verifyUrl     = @json(route('sales.otp.items.verify'));
    const token         = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const itemsBody     = document.getElementById('itemsBody');
    const otpStatusText = document.getElementById('otpStatusText');

    if (!hasHandover) return;

    function formatRp(num) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(num || 0);
    }

    async function submitOtp(code) {
        const res = await fetch(verifyUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                otp_code: code,
                handover_id: handoverId
            })
        });

        const json = await res.json();

        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Gagal verifikasi OTP.');
        }

        const rows = json.items || [];
        if (!rows.length) {
            itemsBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        Tidak ada item pada handover ini.
                    </td>
                </tr>
            `;
        } else {
            let html = '';
            rows.forEach(function (row) {
                html += `
                    <tr>
                        <td>
                            <div class="fw-semibold">${row.product_name}</div>
                            <div class="small text-muted">${row.product_code || ''}</div>
                        </td>
                        <td class="text-end">${row.qty_start ?? 0}</td>
                        <td class="text-end">${row.qty_returned ?? 0}</td>
                        <td class="text-end">${row.qty_sold ?? 0}</td>
                        <td class="text-end">${formatRp(row.unit_price || 0)}</td>
                        <td class="text-end">${formatRp(row.line_start || 0)}</td>
                        <td class="text-end">${formatRp(row.line_sold || 0)}</td>
                    </tr>
                `;
            });
            itemsBody.innerHTML = html;
        }

        // update label OTP terverifikasi
        if (otpStatusText && json.handover && json.handover.verified_at) {
            otpStatusText.innerHTML =
                '✅ <span class="text-success">Sudah diverifikasi: '
                + json.handover.verified_at +
                '</span>';
        }
        return json;
    }

    async function showOtpModal() {
        const { value: otpCode } = await Swal.fire({
            title: 'Masukkan Kode OTP Pagi',
            text: 'Kode OTP dikirim ke email kamu. Masukkan kode yang sama dengan yang diberikan admin warehouse.',
            input: 'text',
            inputLabel: 'Kode OTP',
            inputPlaceholder: 'Contoh: 123456',
            inputAttributes: {
                maxlength: 10,
                autocapitalize: 'off',
                autocorrect: 'off'
            },
            allowOutsideClick: false,
            showCancelButton: false,
            confirmButtonText: 'Verifikasi',
            showLoaderOnConfirm: true,
            preConfirm: async (value) => {
                const code = (value || '').trim();
                if (!code) {
                    Swal.showValidationMessage('Kode OTP tidak boleh kosong.');
                    return false;
                }

                try {
                    await submitOtp(code);
                } catch (err) {
                    Swal.showValidationMessage(err.message);
                    return false;
                }
            }
        });

        if (otpCode) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'OTP berhasil diverifikasi. Daftar barang sudah ditampilkan.',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }

    // kalau OTP untuk halaman ini belum diverifikasi, langsung munculkan modal OTP
    if (!isOtpVerified) {
        showOtpModal();
    }

})();
</script>
@endpush
