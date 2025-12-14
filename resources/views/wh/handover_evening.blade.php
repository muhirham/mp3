@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $me           = $me ?? auth()->user();
    $handover     = $handover ?? null;
    $handoverList = $handoverList ?? collect();

    $roles       = $me?->roles ?? collect();
    $isWarehouse = $roles->contains('slug', 'warehouse');
    $isSales     = $roles->contains('slug', 'sales');
    $isAdminLike = $roles->contains('slug', 'admin')
                    || $roles->contains('slug', 'superadmin');

    $isApprovalMode = ! is_null($handover);

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

    // list khusus untuk dropdown OTP sore (hanya waiting_evening_otp)
    $waitingEvening = $handoverList->where('status', 'waiting_evening_otp');

    if ($isApprovalMode) {
        $statusKey   = $handover->status;
        $statusLabel = $statusLabelMap[$statusKey] ?? $statusKey;
        $badgeClass  = $badgeClassMap[$statusKey] ?? $badgeClassMap['default'];

        $canEdit      = $handover->status !== 'closed';
        $canVerifyOtp = $handover->status === 'waiting_evening_otp';

        // Hitung apakah boleh generate OTP sore
        $itemsSold      = $handover->items->where('qty_sold', '>', 0);
        $allApproved    = $itemsSold->count() > 0
                          && $itemsSold->every(fn($it) => $it->payment_status === 'approved');
        $hasEveningOtp  = ! empty($handover->evening_otp_hash);
        $canGenerateOtp = $canEdit && $allApproved && ! $hasEveningOtp;
    }
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  <h4 class="fw-bold py-2 mb-3">
    <span class="text-muted fw-light">
      Warehouse /
    </span>
    Reconcile + OTP (Sore)
  </h4>

  {{-- FLASH MESSAGE --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- ================== PILIH HANDOVER UNTUK APPROVAL ================== --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-bold">Pilih Handover untuk Approval Payment</h5>
      <small class="text-muted">
        Hanya handover dengan status <b>ON_SALES</b> / <b>WAITING_EVENING_OTP</b>
        dan sudah diisi sore oleh sales.
      </small>
    </div>
    <div class="card-body">
      <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end">
        <div class="col-md-7">
          <label class="form-label">Handover</label>
          <select name="handover_id" class="form-select">
            <option value="">— Pilih —</option>
            @foreach($handoverList as $h)
              @php
                  $stKey   = $h->status;
                  $stLabel = $statusLabelMap[$stKey] ?? $stKey;
              @endphp
              <option value="{{ $h->id }}"
                @selected($handover && $handover->id === $h->id)>
                {{ $h->code }}
                — {{ $h->sales->name ?? ('Sales #'.$h->sales_id) }}
                ({{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }})
                [{{ $stLabel }}]
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Lihat</button>
        </div>
        <div class="col-md-3">
          @if($handover)
            <a href="{{ url()->current() }}" class="btn btn-outline-secondary w-100">
              Clear Pilihan
            </a>
          @endif
        </div>
      </form>
      <div class="form-text mt-2">
        Kalau handover belum muncul di sini, pastikan sales sudah mengisi penjualan &amp; payment sore.
      </div>
    </div>
  </div>

  {{-- ================== JIKA SUDAH PILIH HANDOVER: TAMPILKAN APPROVAL ================== --}}
  @if($isApprovalMode)

    {{-- HEADER HANDOVER --}}
    <div class="card mb-4">
      <div class="card-body">
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
            <div class="small text-muted fw-semibold">Info OTP</div>
            <div class="small">
              OTP Pagi dikirim:
              {{ optional($handover->morning_otp_sent_at)->format('Y-m-d H:i') ?? '-' }}<br>
              OTP Pagi verif:
              {{ optional($handover->morning_otp_verified_at)->format('Y-m-d H:i') ?? '-' }}<br>
              OTP Sore dikirim:
              {{ optional($handover->evening_otp_sent_at)->format('Y-m-d H:i') ?? '-' }}<br>
              OTP Sore verif:
              {{ optional($handover->evening_otp_verified_at)->format('Y-m-d H:i') ?? '-' }}
            </div>

            {{-- KODE OTP SORE (kalau sudah dibuat) --}}
            <div class="mt-2">
              <span class="small text-muted d-block">Kode OTP Sore:</span>
              @if(!empty($handover->evening_otp_plain))
                <span class="badge bg-label-info fs-6">
                  {{ $handover->evening_otp_plain }}
                </span>
              @else
                <span class="text-muted small">Belum dibuat</span>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- APPROVAL PAYMENT PER ITEM --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0 fw-bold">Approval Payment Per Item</h5>
          <small class="text-muted">
            Approve / reject pembayaran yang sudah diinput sales.
            Item yang sudah <strong>APPROVED</strong> tidak bisa diubah lagi.
          </small>
        </div>
        <div class="d-flex gap-2">
          @if($canGenerateOtp)
            <form method="POST"
                  action="{{ route('warehouse.handovers.evening.generate-otp', $handover) }}"
                  onsubmit="return confirm('Generate OTP sore untuk handover ini?');">
              @csrf
              <button type="submit" class="btn btn-success btn-sm">
                Generate OTP Sore
              </button>
            </form>
          @elseif($handover->status === 'waiting_evening_otp')
            <span class="badge bg-label-info">
              OTP Sore sudah dibuat (WAITING_EVENING_OTP)
            </span>
          @endif
        </div>
      </div>

      <div class="card-body">
        <form method="POST"
              action="{{ route('warehouse.handovers.payments.approve', $handover) }}">
          @csrf

          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead>
              <tr>
                <th style="width:20%">Produk</th>
                <th class="text-end" style="width:7%">Dibawa</th>
                <th class="text-end" style="width:7%">Kembali</th>
                <th class="text-end" style="width:7%">Terjual</th>
                <th class="text-end" style="width:10%">Harga</th>
                <th class="text-end" style="width:10%">Nilai Terjual</th>
                <th class="text-end" style="width:7%">Qty Bayar</th>
                <th class="text-center" style="width:8%">Metode</th>
                <th class="text-end" style="width:10%">Nominal</th>
                <th style="width:10%">Bukti TF</th>
                <th style="width:10%">Decision</th>
                <th style="width:11%">Alasan Reject</th>
              </tr>
              </thead>
              <tbody>
              @forelse($handover->items as $item)
                @php
                  $status      = $item->payment_status ?? 'pending';
                  $statusBadge = match($status) {
                      'approved' => 'bg-label-success',
                      'rejected' => 'bg-label-danger',
                      default    => 'bg-label-warning',
                  };
                  $lockItem = ! $canEdit || $status === 'approved';
                @endphp
                <tr>
                  <td>
                    <div class="fw-semibold">
                      {{ $item->product->name ?? $item->product->product_name ?? '-' }}
                    </div>
                    <div class="small text-muted">
                      {{ $item->product->product_code ?? '' }}
                    </div>
                  </td>
                  <td class="text-end">{{ (int) $item->qty_start }}</td>
                  <td class="text-end">{{ (int) $item->qty_returned }}</td>
                  <td class="text-end">{{ (int) $item->qty_sold }}</td>
                  <td class="text-end">
                    {{ 'Rp ' . number_format((int) $item->unit_price, 0, ',', '.') }}
                  </td>
                  <td class="text-end">
                    {{ 'Rp ' . number_format((int) $item->line_total_sold, 0, ',', '.') }}
                  </td>

                  <td class="text-end">{{ (int) $item->payment_qty }}</td>
                  <td class="text-center">
                    {{ $item->payment_method ? strtoupper($item->payment_method) : '-' }}
                  </td>
                  <td class="text-end">
                    {{ 'Rp ' . number_format((int) $item->payment_amount, 0, ',', '.') }}
                  </td>
                  <td>
                    @if($item->payment_transfer_proof_path)
                      <a href="{{ asset('storage/'.$item->payment_transfer_proof_path) }}"
                         target="_blank">
                        Lihat Bukti
                      </a>
                    @else
                      <span class="text-muted small">-</span>
                    @endif
                  </td>

                  <td>
                    <span class="badge {{ $statusBadge }} mb-1 d-inline-block">
                      {{ strtoupper($status) }}
                    </span>

                    @if($canEdit)
                      <div class="d-flex flex-column mt-1">
                        <div class="form-check form-check-inline">
                          <input class="form-check-input"
                                 type="radio"
                                 name="decisions[{{ $item->id }}][status]"
                                 id="approve-{{ $item->id }}"
                                 value="approved"
                                 @checked($status === 'approved')
                                 @if($lockItem) disabled @endif>
                          <label class="form-check-label" for="approve-{{ $item->id }}">
                            Approve
                          </label>
                        </div>
                        <div class="form-check form-check-inline mt-1">
                          <input class="form-check-input"
                                 type="radio"
                                 name="decisions[{{ $item->id }}][status]"
                                 id="reject-{{ $item->id }}"
                                 value="rejected"
                                 @checked($status === 'rejected')
                                 @if($lockItem) disabled @endif>
                          <label class="form-check-label" for="reject-{{ $item->id }}">
                            Reject
                          </label>
                        </div>
                      </div>
                    @endif
                  </td>

                  <td>
                    @if($status === 'rejected' && $item->payment_reject_reason)
                      <div class="small text-danger mb-1">
                        {{ $item->payment_reject_reason }}
                      </div>
                    @endif

                    @if($canEdit && ! $lockItem)
                      <input type="text"
                             class="form-control form-control-sm"
                             name="decisions[{{ $item->id }}][reason]"
                             value="{{ old("decisions.$item->id.reason", $item->payment_reject_reason) }}"
                             placeholder="Alasan jika reject">
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

          <div class="mt-3 text-end">
            <a href="{{ url()->current() }}" class="btn btn-outline-secondary">
              Kembali ke daftar
            </a>

            @if($canEdit && $handover->items->count())
              <button type="submit" class="btn btn-primary">
                Simpan Approval
              </button>
            @endif
          </div>
        </form>
      </div>
    </div>

  @else
    {{-- BELUM PILIH HANDOVER (untuk approval) --}}
    <div class="card mb-4">
      <div class="card-body text-center text-muted">
        Pilih salah satu handover di atas untuk melakukan approval payment.
      </div>
    </div>
  @endif

  {{-- ================== VERIFIKASI OTP SORE (LAYOUT MIRIP PAGI) ================== --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 fw-bold">Verifikasi OTP Sore</h5>
    </div>
    <div class="card-body">
      <form method="POST"
            action="{{ route('sales.handover.evening.verify') }}"
            class="row g-2 align-items-end">
        @csrf

        <div class="col-md-5">
          <label class="form-label">Pilih Handover (WAITING_EVENING_OTP)</label>
          <select name="handover_id" class="form-select" required>
            <option value="">— Pilih —</option>
            @foreach($waitingEvening as $h)
              <option value="{{ $h->id }}"
                @selected($handover && $handover->id === $h->id && $h->status === 'waiting_evening_otp')>
                {{ $h->code }}
                — {{ $h->sales->name ?? ('Sales #'.$h->sales_id) }}
                ({{ \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d') }})
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">OTP Sore</label>
          <input type="text"
                 name="otp_code"
                 class="form-control"
                 inputmode="numeric"
                 pattern="[0-9]*"
                 placeholder="6 digit"
                 required>
        </div>

        <div class="col-md-4">
          <button type="submit" class="btn btn-success w-100 mt-3 mt-md-0">
            Verifikasi OTP &amp; Tutup Handover
          </button>
        </div>
      </form>

      <div class="form-text mt-2">
        OTP sore hanya bisa diverifikasi untuk handover dengan status
        <b>WAITING_EVENING_OTP</b>. Setelah OTP valid, stok sales akan di-clear,
        sisa stok kembali ke gudang, dan status menjadi <b>CLOSED</b>.
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

@if (session('success'))
<script>
Swal.fire({
  icon: 'success',
  title: 'Berhasil',
  html: {!! json_encode(session('success')) !!},
  allowOutsideClick: true
});
</script>
@endif

@if (session('error'))
<script>
Swal.fire({
  icon: 'error',
  title: 'Gagal',
  html: {!! json_encode(session('error')) !!}
});
</script>
@endif
@endpush
