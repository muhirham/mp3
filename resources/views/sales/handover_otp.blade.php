@extends('layouts.home')

@push('styles')
<style>
.otp-page .title{font-size:1.8rem;font-weight:800;color:#4a5f7d}
.otp-page .sum-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:1rem}
.otp-page .sum-card{border:1px solid #e8edf6;border-radius:18px;padding:14px 16px;background:linear-gradient(180deg,#fff,#f8fbff)}
.otp-page .sum-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#8a97aa;font-weight:700;margin-bottom:6px}
.otp-page .sum-value{font-size:24px;font-weight:800;color:#34425b;line-height:1.1}
.otp-page .sum-note{font-size:12px;color:#7f8ba0;margin-top:6px}
.otp-page .helper{display:flex;justify-content:space-between;gap:14px;align-items:center;border:1px solid #e4ecff;border-radius:18px;padding:14px 16px;background:#f5f9ff;margin-bottom:1rem}
.otp-page .helper h6{margin:0 0 4px;font-weight:800;color:#3d4e6b}.otp-page .helper p{margin:0;font-size:12px;color:#72819a}
.otp-page .meta-label{font-size:12px;color:#8b97aa;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.otp-page .meta-value{font-size:1rem;font-weight:700;color:#354560}
.otp-page #itemsTable thead th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#738199;font-weight:700;padding:.85rem .65rem;white-space:nowrap}
.otp-page #itemsTable tbody td{padding:1rem .6rem;vertical-align:top}.otp-page #itemsTable tbody tr{border-bottom:1px solid #edf1f7}
.otp-page .pname{font-size:14px;font-weight:800;color:#34425b}.otp-page .pcode{font-size:11px;color:#8a96a8;margin-top:3px}
.otp-page .chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.otp-page .chip{border-radius:999px;background:#f3f6fb;color:#5d6d86;padding:5px 10px;font-size:11px;font-weight:700}
.otp-page .num{font-size:13px;font-weight:700;color:#33425c}.otp-page .note{font-size:11px;color:#8390a6;margin-top:3px}.otp-page .ok{color:#2fbb47;font-weight:700;margin-top:4px;font-size:11px}
.otp-page .grid2{display:grid;gap:8px}.otp-page .ibox{border:1px solid #e6ebf4;border-radius:14px;background:#fbfcff;padding:8px}
.otp-page .ilabel{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#8a97aa;font-weight:700;margin-bottom:6px}
.otp-page #itemsTable input.form-control-sm{height:36px;font-size:12px;border-radius:10px}
.otp-page .proof a{display:inline-block;margin-bottom:8px;font-size:12px;font-weight:700}.otp-page .status .badge{font-size:11px;padding:6px 9px}
.otp-page .actions{display:flex;justify-content:flex-end;gap:12px;margin-top:1rem}
@media (max-width:991.98px){.otp-page .sum-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:767.98px){
.otp-page .title{font-size:1.5rem}.otp-page .sum-grid{grid-template-columns:1fr}.otp-page .helper{flex-direction:column;align-items:flex-start}
.otp-page .picker .row>div,.otp-page .info .row>div{flex:0 0 100%;max-width:100%}
.otp-page #itemsTable,.otp-page #itemsTable tbody,.otp-page #itemsTable tr,.otp-page #itemsTable td{display:block;width:100%}
.otp-page #itemsTable thead{display:none}.otp-page #itemsTable tbody tr{background:#fff;border:1px solid #edf1f7;border-radius:18px;padding:13px;margin-bottom:14px;box-shadow:0 8px 24px rgba(24,39,75,.06)}
.otp-page #itemsTable tbody td{padding:0;margin-bottom:12px;border:0!important}.otp-page #itemsTable tbody td:last-child{margin-bottom:0}
.otp-page #itemsTable td:before{content:attr(data-label);display:block;margin-bottom:6px;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#8b97aa;font-weight:700}
.otp-page #itemsTable td[data-label="Product"]:before{display:none}.otp-page .grid2{grid-template-columns:1fr 1fr}.otp-page .actions{flex-direction:column}.otp-page .actions .btn{width:100%}
}
</style>
@endpush

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="container-xxl flex-grow-1 container-p-y otp-page">
    <div class="d-flex align-items-center mb-3"><h4 class="title mb-0">Items Dispatched Today</h4></div>
    @php
        $statusLabelMap=['draft'=>'Draft','waiting_morning_otp'=>'Waiting for Morning OTP','on_sales'=>'On Sales','waiting_evening_otp'=>'Waiting for Evening OTP','closed'=>'Closed','cancelled'=>'Cancelled'];
        $badgeClassMap=['closed'=>'bg-label-success','on_sales'=>'bg-label-info','waiting_morning_otp'=>'bg-label-warning','waiting_evening_otp'=>'bg-label-warning','cancelled'=>'bg-label-danger','default'=>'bg-label-secondary'];
        $paymentBadgeMap=['draft'=>'bg-label-secondary','pending'=>'bg-label-warning','approved'=>'bg-label-success','rejected'=>'bg-label-danger'];
        $statusKey=$handover?->status; $statusLabel=$statusLabelMap[$statusKey] ?? ($statusKey ?? '-'); $badgeClass=$badgeClassMap[$statusKey] ?? $badgeClassMap['default'];
        $itemsCollection=$items ?? collect(); $totalDispatchQty=(int)$itemsCollection->sum('qty_start'); $totalSoldQty=(int)$itemsCollection->sum(function($it){ return ($it->payment_status === 'rejected') ? 0 : (int)$it->qty_sold; }); $totalRemainingQty=max(0,$totalDispatchQty-$totalSoldQty); $totalSalesAmount=(int)$itemsCollection->sum(function($it){ return ($it->payment_status === 'rejected') ? 0 : (int)$it->line_total_sold; });
    @endphp

    @if (($handovers ?? collect())->count() > 1)
    <div class="card picker mb-3"><div class="card-body">
        <form method="GET" action="{{ route('sales.otp.items') }}" class="row g-2 align-items-end">
            <div class="col-md-10">
                <div class="small text-muted fw-semibold mb-1">Pilih Handover Aktif</div>
                <select name="handover_id" class="form-select" onchange="this.form.submit()">
                    @foreach ($handovers as $handoverOption)
                        @php
                            $statusText=$statusLabelMap[$handoverOption->status] ?? strtoupper($handoverOption->status);
                            $isVerifiedOption=$handoverOption->status !== 'waiting_morning_otp' && (session('sales_handover_otp_verified_'.$handoverOption->id,false) || ($handoverOption->status==='on_sales' && !is_null($handoverOption->morning_otp_verified_at)));
                        @endphp
                        <option value="{{ $handoverOption->id }}" @selected(($handover?->id ?? null)===$handoverOption->id)>{{ $handoverOption->code }} - {{ optional($handoverOption->handover_date)->format('Y-m-d') }} - {{ $statusText }} {{ $isVerifiedOption ? '- OTP OK' : '- OTP BELUM' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">Lihat</button></div>
        </form>
    </div></div>
    @endif

    <div class="card info mb-3"><div class="card-body">
        @if (!$handover)
            <div class="text-center text-muted"><div class="fw-semibold mb-1">No active handover for today.</div><div>Please contact the warehouse admin if items should have been assigned.</div></div>
        @else
            <div class="row g-3">
                <div class="col-md-4"><div class="meta-label">Handover Code</div><div class="meta-value">{{ $handover->code }}</div></div>
                <div class="col-md-4"><div class="meta-label">Date</div><div class="meta-value">{{ optional($handover->handover_date)->format('Y-m-d') }}</div></div>
                <div class="col-md-4"><div class="meta-label">Warehouse</div><div class="meta-value">{{ optional($handover->warehouse)->warehouse_name ?? (optional($handover->warehouse)->name ?? '-') }}</div></div>
                <div class="col-md-4"><div class="meta-label">Sales</div><div class="meta-value">{{ optional($handover->sales)->name ?? '-' }}</div></div>
                <div class="col-md-4"><div class="meta-label">Status</div><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></div>
                <div class="col-md-4">
                    <div class="meta-label">Morning OTP Verified</div>
                    @if ($handover->status !== 'waiting_morning_otp' && $isOtpVerified)
                        <div class="text-success fw-semibold">Verified in this menu</div>
                    @else
                        <div class="text-muted fw-semibold">Not yet verified</div>
                    @endif
                </div>
                @if (in_array($handover->status, ['waiting_morning_otp', 'on_sales'], true) && !$isOtpVerified && $items->isEmpty())
                    <div class="col-md-4"><button type="button" class="btn btn-warning btn-sm" id="btnInputOtp">Enter OTP</button></div>
                @endif
            </div>
        @endif
    </div></div>

    <div class="card items-card"><div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Today's Dispatched Items</h5>
        <small class="text-muted text-end">Save draft kapan saja selama masih jualan. Submit ke admin WH hanya saat closing akhir hari.</small>
    </div><div class="card-body">
        @if (!$handover)
            <div class="text-center text-muted">No active handover today.</div>
        @elseif (!$isOtpVerified)
            <div class="text-center text-muted">Please enter the morning OTP first to view the items list.</div>
        @else
            <div class="sum-grid">
                <div class="sum-card"><div class="sum-label">Dispatched Qty</div><div class="sum-value">{{ $totalDispatchQty }}</div><div class="sum-note">Total unit dibawa hari ini</div></div>
                <div class="sum-card"><div class="sum-label">Sold Progress</div><div class="sum-value">{{ $totalSoldQty }}</div><div class="sum-note">Unit yang sudah diinput sales</div></div>
                <div class="sum-card"><div class="sum-label">Remaining Qty</div><div class="sum-value">{{ $totalRemainingQty }}</div><div class="sum-note">Sisa unit yang masih bisa dijual</div></div>
                <div class="sum-card"><div class="sum-label">Draft Sales Value</div><div class="sum-value" style="font-size:20px">Rp {{ number_format($totalSalesAmount,0,',','.') }}</div><div class="sum-note">Akumulasi nilai jual sementara</div></div>
            </div>
            <div class="helper">
                <div><h6>Flow baru lebih enak dipakai</h6><p>Save Draft menyimpan progres jualan tanpa menutup handover. Submit to Admin WH dipakai saat closing akhir hari.</p></div>
                <span class="badge bg-label-info">{{ $handover->evening_filled_by_sales ? 'Submitted to WH' : 'Draft in progress' }}</span>
            </div>

            <form id="paymentForm" method="POST" action="{{ route('sales.otp.items.payments.save') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="handover_id" value="{{ $handover->id }}">
                <input type="hidden" name="submit_mode" id="submitModeInput" value="draft">
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="itemsTable">
                        <thead><tr><th style="width:24%">Product</th><th style="width:18%">Progress</th><th style="width:16%">Price</th><th style="width:16%">Payment Qty</th><th style="width:16%">Amount</th><th style="width:10%">Proof</th><th style="width:10%">Status</th></tr></thead>
                        <tbody>
                        @foreach ($items as $row)
                            @php
                                $unitPrice=(int)$row->unit_price; $effectiveUnitPrice=$row->discount_per_unit > 0 ? $unitPrice - (int)$row->discount_per_unit : $unitPrice;
                                $productName=$row->product?->name ?? 'Product #'.$row->product_id; $productCode=$row->product?->product_code ?? ''; $lineStart=(int)($row->line_total_start ?? $row->qty_start*$unitPrice); $lineSold=(int)($row->line_total_sold ?? $row->qty_sold*$unitPrice);
                                $payStatusKey=$row->payment_status ?: 'draft'; $payBadge=$paymentBadgeMap[$payStatusKey] ?? $paymentBadgeMap['draft']; $isLocked=!$canEditPayment || in_array($payStatusKey,['pending','approved'],true);
                                $existingPaymentQty=(int)($row->payment_qty ?? 0); $isRejectedReinput=$payStatusKey==='rejected'; $displaySoldQty=$isRejectedReinput ? 0 : (int)$row->qty_sold; $remainingQty=max(0,(int)$row->qty_start-$displaySoldQty); $displayLineSold=$isRejectedReinput ? 0 : $lineSold; $effectivePaymentQtyForReinput=$isRejectedReinput ? 0 : $existingPaymentQty; $isRejectedDraftProgress=!$isRejectedReinput && (int)$row->qty_sold > 0 && $existingPaymentQty > 0 && $existingPaymentQty < (int)$row->qty_sold; $isPaymentReinputMode=$isRejectedReinput || $isRejectedDraftProgress;
                                // 🔥 FIX: If REJECTED, the base is the FULL qty_start (10), not the previously reported wrong quantity (5).
                                $reinputBaseQty=($isRejectedReinput) ? (int)$row->qty_start : ((int)$row->qty_sold > 0 ? (int)$row->qty_sold : (int)$row->qty_start);
                                $maxQty=$isPaymentReinputMode ? max(0, $reinputBaseQty - $effectivePaymentQtyForReinput) : $remainingQty; $maxNominal=$effectiveUnitPrice*$maxQty;
                                $cashQtyValue=old("items.$row->id.payment_cash_qty", 0);
                                $transferQtyValue=old("items.$row->id.payment_transfer_qty", 0);
                                $cashAmountValue=old("items.$row->id.payment_cash_amount", 0);
                                $transferAmountValue=old("items.$row->id.payment_transfer_amount", 0);
                            @endphp
                            <tr class="js-payment-row" data-unit-price="{{ $effectiveUnitPrice }}" data-max-qty="{{ $maxQty }}" data-max-amount="{{ $effectiveUnitPrice * $maxQty }}">
                                <td data-label="Product">
                                    <div class="pname">{{ $productName }}</div>
                                    @if ($productCode)<div class="pcode">{{ $productCode }}</div>@endif
                                    <div class="chips"><span class="chip">Dibawa: {{ (int)$row->qty_start }}</span><span class="chip">Terjual: {{ $displaySoldQty }}</span><span class="chip">Sisa: {{ $remainingQty }}</span></div>
                                </td>
                                <td data-label="Progress">
                                    <div class="num">Dispatched: {{ (int)$row->qty_start }}</div>
                                    <div class="num">Sold: {{ $displaySoldQty }}</div>
                                    <div class="num">Remaining: {{ $remainingQty }}</div>
                                    @if ($isRejectedReinput)<div class="note text-danger fw-bold">Rejected by WH. Silakan input ulang (Max: {{ $maxQty }})</div>@elseif ($isRejectedDraftProgress)<div class="note text-warning">Pending payment for partial sold items (Max: {{ $maxQty }})</div>@endif
                                    @if ((int)$row->qty_returned > 0)<div class="note">Final returned: {{ (int)$row->qty_returned }}</div>@endif
                                </td>
                                <td data-label="Price">
                                    <div class="num">Rp {{ number_format($unitPrice,0,',','.') }}</div>
                                    <div class="note">Dispatched: Rp {{ number_format($lineStart,0,',','.') }}</div>
                                      <div class="note">Sales: Rp {{ number_format($displayLineSold,0,',','.') }}</div>
                                    @if ($row->discount_per_unit > 0)
                                        <div class="note">Discount: Rp {{ number_format($row->discount_per_unit,0,',','.') }}</div>
                                        <div class="ok">Net: Rp {{ number_format($row->unit_price_after_discount,0,',','.') }}</div>
                                    @endif
                                </td>
                                <td data-label="Payment Qty">
                                    <div class="grid2">
                                        <div class="ibox"><label class="ilabel">Cash Qty</label><input type="number" class="form-control form-control-sm" name="items[{{ $row->id }}][payment_cash_qty]" min="0" max="{{ $maxQty }}" value="{{ $cashQtyValue }}" @disabled($isLocked)></div>
                                        <div class="ibox"><label class="ilabel">Transfer Qty</label><input type="number" class="form-control form-control-sm" name="items[{{ $row->id }}][payment_transfer_qty]" min="0" max="{{ $maxQty }}" value="{{ $transferQtyValue }}" @disabled($isLocked)></div>
                                    </div>
                                </td>
                                <td data-label="Amount">
                                    <div class="grid2">
                                        <div class="ibox"><label class="ilabel">Cash Amount</label><input type="number" class="form-control form-control-sm" name="items[{{ $row->id }}][payment_cash_amount]" min="0" max="{{ $maxNominal }}" value="{{ $cashAmountValue }}" @disabled($isLocked)></div>
                                        <div class="ibox"><label class="ilabel">Transfer Amount</label><input type="number" class="form-control form-control-sm" name="items[{{ $row->id }}][payment_transfer_amount]" min="0" max="{{ $maxNominal }}" value="{{ $transferAmountValue }}" @disabled($isLocked)></div>
                                    </div>
                                    <div class="note mt-2">Cash dan transfer bisa diisi salah satu atau dua-duanya. Bukti transfer wajib saat submit akhir ke admin WH.</div>
                                </td>
                                <td data-label="Transfer Proof"><div class="proof">@if ($row->payment_transfer_proof_path)<a href="{{ asset('storage/'.$row->payment_transfer_proof_path) }}" target="_blank">View Proof</a>@endif<input type="file" class="form-control form-control-sm" name="items[{{ $row->id }}][payment_proof]" @disabled($isLocked)></div></td>
                                <td data-label="Payment Status"><div class="status"><span class="badge {{ $payBadge }}">{{ strtoupper($payStatusKey) }}</span>@if ($row->payment_status === 'rejected' && $row->payment_reject_reason)<div class="small text-danger mt-2">{{ $row->payment_reject_reason }}</div>@endif</div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($canEditPayment && $items->count())
                    <div class="actions">
                        <button type="submit" class="btn btn-outline-secondary js-submit-payment" data-submit-mode="draft">Save Draft (Keep Selling)</button>
                        <button type="submit" class="btn btn-primary js-submit-payment shadow-sm" data-submit-mode="submit" style="background:#4a5f7d; border-color:#4a5f7d">Submit to Admin WH (End Day)</button>
                    </div>
                @endif
            </form>
        @endif
    </div></div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const hasHandover = @json((bool) $handover);
    const isOtpVerified = @json($isOtpVerified);
    const handoverId = @json($handover->id ?? null);
    const verifyUrl = @json(route('sales.otp.items.verify'));
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const token = tokenMeta ? tokenMeta.getAttribute('content') : '';
    const btnOtp = document.getElementById('btnInputOtp');

    if (hasHandover && !isOtpVerified) {
        setTimeout(() => { showOtpModal(); }, 300);
    }

    async function submitOtp(code) {
        const res = await fetch(verifyUrl, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': token,'X-Requested-With': 'XMLHttpRequest','Content-Type': 'application/json'},
            body: JSON.stringify({otp_code: code})
        });
        let json;
        try { json = await res.json(); } catch (e) { throw new Error('Server returned an invalid response.'); }
        if (!res.ok || !json.success) throw new Error(json.message || 'OTP verification failed.');
        return json;
    }

    async function showOtpModal() {
        const { value: otpCode, isConfirmed } = await Swal.fire({
            title: 'Enter Morning OTP Code',
            text: 'The OTP code has been sent to your email. Please enter the code provided by the warehouse admin.',
            input: 'text',
            inputLabel: 'OTP Code',
            inputPlaceholder: 'Contoh: 123456',
            inputAttributes: { maxlength: 10, autocapitalize: 'off', autocorrect: 'off' },
            allowOutsideClick: false,
            showCancelButton: false,
            confirmButtonText: 'Verify',
            showLoaderOnConfirm: true,
            preConfirm: async (value) => {
                const code = (value || '').trim();
                if (!code) { Swal.showValidationMessage('OTP code cannot be empty.'); return false; }
                try { await submitOtp(code); } catch (err) { Swal.showValidationMessage(err.message); return false; }
            }
        });
        if (isConfirmed && otpCode) {
            sessionStorage.removeItem('otp-popup-shown-' + handoverId);
            await Swal.fire({icon:'success',title:'Success',text:'OTP verified successfully. The page will reload.',timer:1500,showConfirmButton:false});
            window.location.reload();
        }
    }

    if (btnOtp) btnOtp.addEventListener('click', function() { showOtpModal(); });

    @if (session('success'))
    Swal.fire({icon:'success',title:'Success',html:{!! json_encode(session('success')) !!}});
    @endif
    @if (session('error'))
    Swal.fire({icon:'error',title:'Error',html:{!! json_encode(session('error')) !!}});
    @endif
    @if ($errors->any())
    const errorList = {!! json_encode($errors->all()) !!};
    let errorHtml = '<ul style="text-align:left;margin:0;padding-left:20px;">';
    errorList.forEach(function(msg){ errorHtml += '<li>' + msg + '</li>'; });
    errorHtml += '</ul>';
    Swal.fire({icon:'error',title:'Validation Failed',html:errorHtml});
    @endif
});

(function() {
    const form = document.getElementById('paymentForm');
    if (!form) return;
    const rows = document.querySelectorAll('.js-payment-row');
    const submitModeInput = document.getElementById('submitModeInput');
    const submitButtons = document.querySelectorAll('.js-submit-payment');
    let selectedSubmitMode = 'draft';

    function clampNumber(value, min, max) {
        let v = parseInt(value || 0, 10);
        if (isNaN(v)) v = 0;
        if (v < min) v = min;
        if (v > max) v = max;
        return v;
    }

    rows.forEach(row => {
        const unitPrice = parseInt(row.dataset.unitPrice || '0', 10);
        const maxQty = parseInt(row.dataset.maxQty || '0', 10);
        const maxAmount = parseInt(row.dataset.maxAmount || '0', 10);
        const cashQtyInput = row.querySelector('input[name*="[payment_cash_qty]"]');
        const transferQtyInput = row.querySelector('input[name*="[payment_transfer_qty]"]');
        const cashAmountInput = row.querySelector('input[name*="[payment_cash_amount]"]');
        const transferAmountInput = row.querySelector('input[name*="[payment_transfer_amount]"]');
        if (!cashQtyInput || !transferQtyInput || !cashAmountInput || !transferAmountInput) return;

        function syncSplitQty() {
            let cashQty = clampNumber(cashQtyInput.value, 0, maxQty);
            let transferQty = clampNumber(transferQtyInput.value, 0, maxQty);
            if (cashQty + transferQty > maxQty) transferQty = Math.max(0, maxQty - cashQty);
            cashQtyInput.value = cashQty;
            transferQtyInput.value = transferQty;
            cashAmountInput.value = Math.max(0, Math.min(cashQty * unitPrice, maxAmount));
            transferAmountInput.value = Math.max(0, Math.min(transferQty * unitPrice, maxAmount));
        }

        cashQtyInput.addEventListener('input', syncSplitQty);
        transferQtyInput.addEventListener('input', syncSplitQty);
        cashAmountInput.addEventListener('input', function() {
            const cashQty = clampNumber(cashQtyInput.value, 0, maxQty);
            this.value = clampNumber(this.value, 0, cashQty * unitPrice);
        });
        transferAmountInput.addEventListener('input', function() {
            const transferQty = clampNumber(transferQtyInput.value, 0, maxQty);
            this.value = clampNumber(this.value, 0, transferQty * unitPrice);
        });
    });

    submitButtons.forEach(button => {
        button.addEventListener('click', function() {
            selectedSubmitMode = this.dataset.submitMode || 'draft';
            if (submitModeInput) submitModeInput.value = selectedSubmitMode;
        });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (submitModeInput) submitModeInput.value = selectedSubmitMode;
        const isFinalSubmit = selectedSubmitMode === 'submit';
        Swal.fire({
            title: isFinalSubmit ? 'Submit closing to Admin WH?' : 'Save draft sales data?',
            text: isFinalSubmit ? 'After submit, the sold items will be locked for warehouse admin approval.' : 'Draft will stay editable so you can continue selling the remaining items.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: isFinalSubmit ? 'Yes, Submit' : 'Yes, Save Draft',
            cancelButtonText: 'Review Again'
        }).then((result) => { if (result.isConfirmed) form.submit(); });
    });
})();
</script>
@endpush
