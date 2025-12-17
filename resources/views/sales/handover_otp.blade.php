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
          <form id="paymentForm" method="POST"
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

                  <th class="text-end" style="width:7%">Qty Bayar</th>
                  <th class="text-center" style="width:10%">Metode</th>
                  <th class="text-end" style="width:10%">Nominal</th>
                  <th style="width:15%">Bukti TF</th>
                  <th style="width:12%">Status Payment</th>
                </tr>
                </thead>

                <tbody>
                @foreach($items as $row)
                    @php
                        $unitPrice = (int) $row->unit_price;

                        // ✅ FIX: jangan ngunci pakai qty_sold doang (sering 0).
                        // Prioritas: qty_sold (kalau sudah ada) -> payment_qty (kalau ada history) -> qty_start (fallback).
                        $maxQty = (int) $row->qty_sold;
                        if ($maxQty <= 0) $maxQty = (int) ($row->payment_qty ?? 0);
                        if ($maxQty <= 0) $maxQty = (int) $row->qty_start;

                        $maxNominal = $unitPrice * $maxQty;

                        $productName = $row->product?->name ?? ('Produk #' . $row->product_id);
                        $productCode = $row->product?->product_code ?? '';

                        $lineStart = (int) ($row->line_total_start ?? ($row->qty_start * $unitPrice));
                        $lineSold  = (int) ($row->line_total_sold  ?? ($row->qty_sold  * $unitPrice));

                        $payStatusKey = $row->payment_status ?: 'draft';
                        $payBadge     = $paymentBadgeMap[$payStatusKey] ?? $paymentBadgeMap['draft'];

                        // ✅ LOCK: pending/approved terkunci, rejected/draft boleh edit
                        $isLocked = ! $canEditPayment || in_array($payStatusKey, ['pending','approved'], true);
                    @endphp

                    <tr class="js-payment-row"
                        data-unit-price="{{ $unitPrice }}"
                        data-max-qty="{{ $maxQty }}"
                        data-max-amount="{{ $maxNominal }}">

                        {{-- PRODUK --}}
                        <td data-label="Produk">
                            <div class="product-name">{{ $productName }}</div>
                            @if($productCode)
                                <div class="product-code text-muted">{{ $productCode }}</div>
                            @endif
                        </td>

                        {{-- DIBAWA --}}
                        <td class="text-end" data-label="Dibawa">{{ (int) $row->qty_start }}</td>

                        {{-- KEMBALI --}}
                        <td class="text-end" data-label="Kembali">{{ (int) $row->qty_returned }}</td>

                        {{-- TERJUAL --}}
                        <td class="text-end" data-label="Terjual">{{ (int) $row->qty_sold }}</td>

                        {{-- HARGA --}}
                        <td class="text-end" data-label="Harga">{{ number_format($unitPrice, 0, ',', '.') }}</td>

                        {{-- NILAI DIBAWA --}}
                        <td class="text-end" data-label="Nilai Dibawa">{{ number_format($lineStart, 0, ',', '.') }}</td>

                        {{-- NILAI TERJUAL --}}
                        <td class="text-end" data-label="Nilai Terjual">{{ number_format($lineSold, 0, ',', '.') }}</td>

                        {{-- QTY BAYAR --}}
                        <td data-label="Qty Bayar">
                            <input type="number"
                                  class="form-control form-control-sm js-qty-bayar"
                                  name="items[{{ $row->id }}][payment_qty]"
                                  min="0"
                                  max="{{ $maxQty }}"
                                  value="{{ old("items.$row->id.payment_qty", $row->payment_qty) }}"
                                  @disabled($isLocked)>
                        </td>

                        {{-- METODE --}}
                        <td data-label="Metode">
                            @php
                                $oldMethod = old("items.$row->id.payment_method");
                                $valMethod = $oldMethod !== null ? $oldMethod : $row->payment_method;
                            @endphp
                            <select name="items[{{ $row->id }}][payment_method]"
                                    class="form-select form-select-sm"
                                    @disabled($isLocked)>
                                <option value="">- Pilih -</option>
                                <option value="cash"     @selected($valMethod === 'cash')>Cash</option>
                                <option value="transfer" @selected($valMethod === 'transfer')>Transfer</option>
                            </select>
                        </td>

                        {{-- NOMINAL --}}
                        <td data-label="Nominal">
                            <input type="number"
                                  class="form-control form-control-sm js-nominal"
                                  name="items[{{ $row->id }}][payment_amount]"
                                  min="0"
                                  max="{{ $maxNominal }}"
                                  value="{{ old("items.$row->id.payment_amount", $row->payment_amount) }}"
                                  @disabled($isLocked)>
                        </td>

                        {{-- BUKTI TF --}}
                        <td data-label="Bukti TF">
                            @if($row->payment_transfer_proof_path)
                                <div class="mb-1">
                                    <a href="{{ asset('storage/'.$row->payment_transfer_proof_path) }}" target="_blank">
                                        Lihat Bukti
                                    </a>
                                </div>
                            @endif
                            <input type="file"
                                  class="form-control form-control-sm"
                                  name="items[{{ $row->id }}][payment_proof]"
                                  @disabled($isLocked)>
                        </td>

                        {{-- STATUS PAYMENT --}}
                        <td data-label="Status Payment">
                            <span class="badge {{ $payBadge }}">
                                {{ strtoupper($payStatusKey) }}
                            </span>
                            @if($row->payment_status === 'rejected' && $row->payment_reject_reason)
                                <div class="small text-danger mt-1">
                                    {{ $row->payment_reject_reason }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
              </table> {{-- ✅ ini yang tadi ketutup --}}
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
        try { json = await res.json(); }
        catch (e) { throw new Error('Server mengembalikan respon tidak valid.'); }

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
            inputAttributes: { maxlength: 10, autocapitalize: 'off', autocorrect: 'off' },
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
                try { await submitOtp(code); }
                catch (err) { Swal.showValidationMessage(err.message); return false; }
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

    @if (session('success'))
        Swal.fire({ icon: 'success', title: 'Berhasil', html: {!! json_encode(session('success')) !!} });
    @endif

    @if (session('error'))
        Swal.fire({ icon: 'error', title: 'Gagal', html: {!! json_encode(session('error')) !!} });
    @endif

    @if ($errors->any())
        const errorList = {!! json_encode($errors->all()) !!};
        let errorHtml = '<ul style="text-align:left;margin:0;padding-left:20px;">';
        errorList.forEach(function (msg) { errorHtml += '<li>' + msg + '</li>'; });
        errorHtml += '</ul>';

        Swal.fire({ icon: 'error', title: 'Validasi gagal', html: errorHtml });
    @endif
});

(function () {
    const form = document.getElementById('paymentForm');
    if (!form) return;

    const rows = document.querySelectorAll('.js-payment-row');

    function clampNumber(value, min, max) {
        let v = parseInt(value || 0, 10);
        if (isNaN(v)) v = 0;
        if (v < min) v = min;
        if (v > max) v = max;
        return v;
    }

    rows.forEach(row => {
        const unitPrice    = parseInt(row.dataset.unitPrice || '0', 10);
        const maxQty       = parseInt(row.dataset.maxQty || '0', 10);
        const maxAmount    = parseInt(row.dataset.maxAmount || '0', 10);
        const qtyInput     = row.querySelector('.js-qty-bayar');
        const nominalInput = row.querySelector('.js-nominal');

        if (!qtyInput || !nominalInput) return;

        qtyInput.addEventListener('input', function () {
            let qty = clampNumber(this.value, 0, maxQty);
            this.value = qty;

            let nominal = qty * unitPrice;
            if (nominal > maxAmount) nominal = maxAmount;
            if (nominal < 0) nominal = 0;

            nominalInput.value = nominal;
        });

        nominalInput.addEventListener('input', function () {
            let val = clampNumber(this.value, 0, maxAmount);
            this.value = val;
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        Swal.fire({
            title: 'Simpan data penjualan?',
            text: 'Pastikan qty & nominal sudah benar. Setelah disimpan, data akan dikunci untuk approval admin.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, sudah benar',
            cancelButtonText: 'Cek lagi'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
})();
</script>
@endpush
