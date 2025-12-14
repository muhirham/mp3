@extends('layouts.home')

@push('styles')
<style>
    /* ====== MOBILE STACKED TABLE ====== */
    @media (max-width: 768px) {
        #itemsTable {
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        #itemsTable thead {
            display: none;
        }

        #itemsTable tbody tr {
            display: block;
            background: #ffffff;
            border-radius: .75rem;
            padding: .5rem .75rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            margin-bottom: .75rem;
        }

        #itemsTable tbody tr:last-child {
            margin-bottom: 0;
        }

        #itemsTable td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 0;
            padding: .15rem 0;
            font-size: 11px;
        }

        /* Baris produk ditampilkan di atas tanpa label kiri */
        #itemsTable td[data-label="Produk"] {
            display: block;
            padding-bottom: .25rem;
        }

        #itemsTable td[data-label="Produk"]::before {
            content: '';
            margin: 0;
        }

        #itemsTable td[data-label="Produk"] .product-name {
            font-weight: 600;
            font-size: 12px;
        }

        #itemsTable td[data-label="Produk"] .product-code {
            font-size: 10px;
        }

        #itemsTable td::before {
            content: attr(data-label);
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #6c757d;
            margin-right: .75rem;
        }

        #itemsTable input.form-control-sm,
        #itemsTable select.form-select-sm {
            font-size: 11px;
            padding: .15rem .35rem;
            max-width: 130px;
        }

        #itemsTable .badge {
            font-size: 10px;
        }

        #itemsTable .form-text {
            font-size: 9px;
        }
    }
</style>
@endpush

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

      $paymentBadgeMap = [
          'draft'    => 'bg-label-secondary',
          'pending'  => 'bg-label-warning',
          'approved' => 'bg-label-success',
          'rejected' => 'bg-label-danger',
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

  {{-- TABEL ITEM + PAYMENT --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-bold">Daftar Barang Dibawa Hari Ini</h5>
      <small class="text-muted text-end">
        Isi payment per item setelah penjualan.
        Setelah dikirim (status <strong>PENDING</strong>), item terkunci
        sampai <strong>REJECTED</strong> oleh admin warehouse.
      </small>
    </div>
    <div class="card-body">
      @if(!$handover)
        <div class="text-center text-muted">
          Tidak ada handover aktif hari ini.
        </div>
      @else
        @if(!$isOtpVerified)
          <div class="text-center text-muted">
            Masukkan OTP pagi terlebih dahulu untuk melihat daftar barang.
          </div>
        @else
          <form method="POST"
                action="{{ route('sales.otp.items.payments.save') }}"
                enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="handover_id" value="{{ $handover->id }}">

            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle" id="itemsTable">
                <thead>
                <tr>
                  <th style="width:20%">Produk</th>
                  <th class="text-end" style="width:7%">Dibawa</th>
                  <th class="text-end" style="width:7%">Kembali</th>
                  <th class="text-end" style="width:7%">Terjual</th>
                  <th class="text-end" style="width:10%">Harga</th>
                  <th class="text-end" style="width:10%">Nilai Dibawa</th>
                  <th class="text-end" style="width:10%">Nilai Terjual</th>

                  {{-- PAYMENT --}}
                  <th class="text-end" style="width:7%">Qty Bayar</th>
                  <th class="text-center" style="width:10%">Metode</th>
                  <th class="text-end" style="width:10%">Nominal</th>
                  <th style="width:15%">Bukti TF</th>
                  <th style="width:12%">Status Payment</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $row)
                  @php
                    $status      = $row->payment_status ?? 'draft';
                    $statusBadge = $paymentBadgeMap[$status] ?? 'bg-label-secondary';

                    // Boleh edit kalau status = draft / rejected & user masih boleh edit
                    $disabled = !($canEditPayment && in_array($status, ['draft', 'rejected'], true));
                  @endphp
                  <tr>
                    <td data-label="Produk">
                      <div class="product-name">
                        {{ $row->product->name ?? $row->product->product_name ?? '-' }}
                      </div>
                      <div class="small text-muted product-code">
                        {{ $row->product->product_code ?? '' }}
                      </div>
                    </td>
                    <td data-label="Dibawa" class="text-end">{{ (int) $row->qty_start }}</td>
                    <td data-label="Kembali" class="text-end">{{ (int) $row->qty_returned }}</td>
                    <td data-label="Terjual" class="text-end">{{ (int) $row->qty_sold }}</td>
                    <td data-label="Harga" class="text-end">
                      {{ 'Rp ' . number_format((int) $row->unit_price, 0, ',', '.') }}
                    </td>
                    <td data-label="Nilai Dibawa" class="text-end">
                      {{ 'Rp ' . number_format((int) $row->line_total_start, 0, ',', '.') }}
                    </td>
                    <td data-label="Nilai Terjual" class="text-end">
                      {{ 'Rp ' . number_format((int) $row->line_total_sold, 0, ',', '.') }}
                    </td>

                    {{-- PAYMENT INPUT --}}
                    <td data-label="Qty Bayar" class="text-end">
                      <input type="number"
                             min="0"
                             class="form-control form-control-sm text-end"
                             name="items[{{ $row->id }}][payment_qty]"
                             value="{{ old("items.$row->id.payment_qty", $row->payment_qty) }}"
                             @if($disabled) disabled @endif>
                    </td>
                    <td data-label="Metode" class="text-center">
                      <select class="form-select form-select-sm"
                              name="items[{{ $row->id }}][payment_method]"
                              @if($disabled) disabled @endif>
                        <option value="">- Pilih -</option>
                        <option value="cash"
                          @selected(old("items.$row->id.payment_method", $row->payment_method) === 'cash')>
                          Cash
                        </option>
                        <option value="transfer"
                          @selected(old("items.$row->id.payment_method", $row->payment_method) === 'transfer')>
                          Transfer
                        </option>
                      </select>
                    </td>
                    <td data-label="Nominal" class="text-end">
                      <input type="number"
                             min="0"
                             class="form-control form-control-sm text-end"
                             name="items[{{ $row->id }}][payment_amount]"
                             value="{{ old("items.$row->id.payment_amount", $row->payment_amount) }}"
                             @if($disabled) disabled @endif>
                    </td>
                    <td data-label="Bukti TF">
                      @if($row->payment_transfer_proof_path)
                        <div class="mb-1">
                          <a href="{{ asset('storage/'.$row->payment_transfer_proof_path) }}"
                             target="_blank">
                            Lihat bukti
                          </a>
                        </div>
                      @endif

                      @if(!$disabled)
                        <input type="file"
                               class="form-control form-control-sm"
                               name="items[{{ $row->id }}][payment_proof]">
                        <div class="form-text small">
                          Wajib diisi untuk metode transfer.
                        </div>
                      @endif
                    </td>
                    <td data-label="Status Payment">
                      <span class="badge {{ $statusBadge }}">
                        {{ strtoupper($status) }}
                      </span>
                      @if($status === 'rejected' && $row->payment_reject_reason)
                        <div class="small text-danger mt-1">
                          {{ $row->payment_reject_reason }}
                        </div>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="12" class="text-center text-muted">
                      Tidak ada item pada handover ini.
                    </td>
                  </tr>
                @endforelse
                </tbody>
              </table>
            </div>

            @if($canEditPayment && $items->count())
              <div class="mt-3 text-end">
                <button type="submit" class="btn btn-primary">
                  Simpan Payment
                </button>
              </div>
            @endif
          </form>
        @endif
      @endif
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hasHandover   = @json((bool) $handover);
    const isOtpVerified = @json($isOtpVerified);
    const handoverId    = @json($handover->id ?? null);
    const verifyUrl     = @json(route('sales.otp.items.verify'));
    const tokenMeta     = document.querySelector('meta[name="csrf-token"]');
    const token         = tokenMeta ? tokenMeta.getAttribute('content') : '';

    // ================== OTP MODAL PAGI ==================
    if (hasHandover && !isOtpVerified) {
        showOtpModal();
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

        let json;
        try {
            json = await res.json();
        } catch (e) {
            throw new Error('Server mengembalikan respon tidak valid.');
        }

        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Gagal verifikasi OTP.');
        }

        return json;
    }

    async function showOtpModal() {
        const { value: otpCode, isConfirmed } = await Swal.fire({
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

        if (isConfirmed && otpCode) {
            await Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'OTP berhasil diverifikasi. Halaman akan direload.',
                timer: 1500,
                showConfirmButton: false
            });

            window.location.reload();
        }
    }

    // ================== SWEETALERT FLASH MESSAGE ==================

    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            html: {!! json_encode(session('success')) !!},
        });
    @endif

    @if (session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            html: {!! json_encode(session('error')) !!},
        });
    @endif

    @if ($errors->any())
        const errorList = {!! json_encode($errors->all()) !!};
        let errorHtml = '<ul style="text-align:left;margin:0;padding-left:20px;">';
        errorList.forEach(function (msg) {
            errorHtml += '<li>' + msg + '</li>';
        });
        errorHtml += '</ul>';

        Swal.fire({
            icon: 'error',
            title: 'Validasi gagal',
            html: errorHtml,
        });
    @endif
});
</script>
@endpush
