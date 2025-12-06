<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Partner PO {{ $po->po_code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }

        .mt-5  { margin-top: 5px; }
        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }

        .small  { font-size: 8px; }
        .nowrap { white-space: nowrap; }

        .table-items th,
        .table-items td {
            border: 1px solid #000;
            padding: 3px 4px;
        }
        .table-items th {
            font-weight: bold;
        }

        .draft-watermark {
            position: fixed;
            top: 45%;
            left: 25%;
            font-size: 60px;
            color: rgba(200, 0, 0, 0.15);
            transform: rotate(-20deg);
            z-index: -1;
        }

        .sign-table {
            width: 100%;
            margin-top: 35px;
        }
        .sign-table td {
            vertical-align: top;
            text-align: center;
        }
        .sign-2col td {
            width: 50%;
        }
        .sign-3col td {
            width: 33.33%;
        }
    </style>
</head>
<body>

@php
    // default kalau variabel belum dikirim
    $isDraft = $isDraft ?? ($po->approval_status !== 'approved');

    $formatRupiah = fn($v) => 'Rp ' . number_format($v ?? 0, 0, ',', '.');

    $company = $company ?? null;

    // batas perlu CEO (ikut rule di index: > 1.000.000)
    $needsCeo = ($po->grand_total ?? 0) > 1_000_000;

    $creator   = $po->user ?? null;
    $procUser  = $po->procurementApprover ?? null;
    $ceoUser   = $po->ceoApprover ?? null;

    $partnerId   = $company?->short_name ?: 'MAND4U';
    $partnerName = $company?->legal_name ?? $company?->name ?? 'PT Mandiri Daya Utama Nusantara';
    $partnerAddr = $company?->address ?? 'Komplek Golden Plaza Blok C17, Jl. RS Fatmawati No. 15, Jakarta Selatan, DKI Jakarta';
    $partnerPhone = $company?->phone ?? '+62 21 7590 9945';
    $partnerFax   = $company?->fax   ?? '-';
    $partnerCp    = $creator->name ?? 'Admin Pusat';
    $partnerBillTo = $partnerAddr;

    $logoPath = $company?->logo_path
        ? public_path('storage/'.$company->logo_path)
        : null;

    $shipToDefault = 'CENTRAL STOCK';

    $signPath = function($user) {
        if (! $user) return null;
        $path = $user->signature_path
            ? public_path('storage/'.$user->signature_path)
            : null;
        if ($path && file_exists($path)) {
            return $path;
        }
        return null;
    };
@endphp

@if($isDraft)
    <div class="draft-watermark">DRAFT</div>
@endif

{{-- HEADER ATAS (LOGO + TITLE) --}}
<table>
    <tr>
        <td style="width: 60%;">
            <div style="font-weight: bold; font-size: 11px; margin-bottom: 15px;">
                PARTNER PURCHASE ORDER
            </div>
        </td>
        <td style="width: 40%; text-right;">
            @if($logoPath && file_exists($logoPath))
                <img src="{{ $logoPath }}" style="max-width: 90px;">
            @endif
        </td>
    </tr>
</table>

<table class="small">
    <tr>
        <td style="width: 60%;">
            <table>
                <tr>
                    <td style="width:70px;">No.</td>
                    <td style="width:5px;">:</td>
                    <td>{{ $po->po_code }}</td>
                    <td class="text-right nowrap" style="width:60px;">REG</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td>:</td>
                    <td colspan="2">
                        {{ ($po->ordered_at ?? $po->created_at)->format('d-M-y') }}
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 40%;"></td>
    </tr>
</table>

<div class="mt-10"></div>

{{-- PARTNER DATA --}}
<table class="small">
    <tr>
        <td style="width: 60%; vertical-align: top;">
            <strong>PARTNER DATA</strong><br>

            <table>
                <tr>
                    <td style="width:80px;">Partner ID</td>
                    <td style="width:5px;">:</td>
                    <td>{{ $partnerId }}</td>
                </tr>
                <tr>
                    <td>Partner Name</td>
                    <td>:</td>
                    <td>{{ $partnerName }}</td>
                </tr>
                <tr>
                    <td>Partner Address</td>
                    <td>:</td>
                    <td>{{ $partnerAddr }}</td>
                </tr>
            </table>
        </td>

        <td style="width: 40%; vertical-align: top;">
            <table>
                <tr>
                    <td style="width:80px;">Phone</td>
                    <td style="width:5px;">:</td>
                    <td>{{ $partnerPhone }}</td>
                </tr>
                <tr>
                    <td>Fax</td>
                    <td>:</td>
                    <td>{{ $partnerFax }}</td>
                </tr>
                <tr>
                    <td>Contact Person</td>
                    <td>:</td>
                    <td>{{ $partnerCp }}</td>
                </tr>
                <tr>
                    <td>Bill to Address</td>
                    <td>:</td>
                    <td>{{ $partnerBillTo }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="mt-10"></div>

{{-- TABEL ITEM --}}
<table class="table-items small">
    <thead>
        <tr>
            <th style="width: 4%;">NO</th>
            <th style="width: 26%;">ITEM</th>
            <th style="width: 8%;">QUANTITY</th>
            <th style="width: 8%;">UNIT</th>
            <th style="width: 14%;">PRICE PER UNIT (Rp.)</th>
            <th style="width: 6%;">DISC %</th>
            <th style="width: 14%;">TOTAL (Rp.)</th>
            <th style="width: 10%;">SHIP TO</th>
            <th style="width: 10%;">REMARKS</th>
        </tr>
    </thead>
    <tbody>
        @php $rowNo = 1; @endphp
        @foreach($po->items as $item)
            @php
                $product    = $item->product;
                $warehouse  = $item->warehouse;
                $shipTo     = $warehouse->warehouse_name ?? $shipToDefault;
                $qty        = (int) ($item->qty_ordered ?? $item->qty ?? 0);
                $unit       = $product?->package?->package_name ?? $product?->unit ?? 'PCS';
                $discType   = $item->discount_type;
                $discVal    = (float) ($item->discount_value ?? 0);
                $discPct    = $discType === 'percent' ? $discVal : 0;
                $lineTotal  = $item->line_total ?? ($qty * ($item->unit_price ?? 0) - $item->discount_amount ?? 0);
            @endphp
            <tr>
                <td class="text-center">{{ $rowNo++ }}</td>
                <td>
                    {{ $product->name ?? '-' }}<br>
                    <span class="small">{{ $product->product_code ?? '' }}</span>
                </td>
                <td class="text-center">{{ $qty }}</td>
                <td class="text-center">{{ $unit }}</td>
                <td class="text-right">{{ $formatRupiah($item->unit_price) }}</td>
                <td class="text-center">{{ $discPct > 0 ? rtrim(rtrim(number_format($discPct,2,'.',''), '0'), '.') : '' }}</td>
                <td class="text-right">{{ $formatRupiah($lineTotal) }}</td>
                <td class="text-left">{{ $shipTo }}</td>
                <td></td>
            </tr>
        @endforeach

        {{-- baris kosong biar tabel keliatan fixed --}}
        @for($i = $rowNo; $i <= 7; $i++)
            <tr>
                <td>&nbsp;</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endfor

        <tr>
            <td colspan="6" class="text-right"><strong>TOTAL</strong></td>
            <td class="text-right"><strong>{{ $formatRupiah($po->grand_total) }}</strong></td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

{{-- REMARKS --}}
<table class="table-items small" style="margin-top: 10px;">
    <tr>
        <td style="width: 10%;">REMARKS</td>
        <td style="width: 90%; height: 60px;">
            @if(!$isDraft && $po->notes)
                {{ $po->notes }}
            @endif
        </td>
    </tr>
</table>

{{-- TANDA TANGAN --}}
@php
    $signTableClass = $needsCeo ? 'sign-table sign-3col' : 'sign-table sign-2col';
@endphp

<table class="{{ $signTableClass }}">
    <tr>
        <td class="text-left small">Requested by,</td>

        @if($needsCeo)
            <td class="small">Approved by Procurement,</td>
            <td class="text-right small">Approved by CEO,</td>
        @else
            <td class="text-right small">Approved by,</td>
        @endif
    </tr>

    <tr>
        {{-- PIC --}}
        <td>
            <div style="height:50px; margin-top:5px; margin-bottom:5px;">
                @if($signPath($creator))
                    <img src="{{ $signPath($creator) }}" style="height:45px;">
                @endif
            </div>
        </td>

        {{-- Procurement --}}
        <td>
            <div style="height:50px; margin-top:5px; margin-bottom:5px;">
                @if($needsCeo)
                    @if($signPath($procUser))
                        <img src="{{ $signPath($procUser) }}" style="height:45px;">
                    @endif
                @else
                    @if($signPath($procUser ?: $ceoUser))
                        <img src="{{ $signPath($procUser ?: $ceoUser) }}" style="height:45px;">
                    @endif
                @endif
            </div>
        </td>

        {{-- CEO (hanya kalau perlu) --}}
        @if($needsCeo)
            <td>
                <div style="height:50px; margin-top:5px; margin-bottom:5px;">
                    @if($signPath($ceoUser))
                        <img src="{{ $signPath($ceoUser) }}" style="height:45px;">
                    @endif
                </div>
            </td>
        @endif
    </tr>

    <tr class="small">
        <td class="text-left">
            ( {{ $creator->name ?? 'Admin Pusat' }} )<br>
            Partner PIC
        </td>

        @if($needsCeo)
            <td>
                ( {{ $procUser->name ?? 'User Procurement' }} )<br>
                Branch Sales Manager
            </td>
            <td class="text-right">
                ( {{ $ceoUser->name ?? 'CEO' }} )<br>
                CEO
            </td>
        @else
            <td class="text-right">
                ( {{ ($procUser ?: $ceoUser)->name ?? 'User Procurement' }} )<br>
                Branch Sales Manager
            </td>
        @endif
    </tr>
</table>

</body>
</html>
