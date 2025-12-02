<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $po->po_code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-decoration: underline;
            margin: 10px 0 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-items th,
        .table-items td {
            border: 1px solid #555;
            padding: 4px;
        }
        .table-items th {
            background: #f2f2f2;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }

        .small { font-size: 10px; }

        .draft-watermark {
            position: fixed;
            top: 40%;
            left: 15%;
            font-size: 60px;
            color: rgba(200, 0, 0, 0.15);
            transform: rotate(-20deg);
            z-index: -1;
        }
    </style>
</head>
<body>

@if($isDraft)
    <div class="draft-watermark">DRAFT</div>
@endif

{{-- HEADER COMPANY / KOP SURAT --}}
@if($company)
    @php
        $logoPath = $company->logo_path
            ? public_path('storage/'.$company->logo_path)
            : null;
    @endphp

    <table>
        <tr>
            <td style="width: 25%; vertical-align: top;">
                @if($logoPath && file_exists($logoPath))
                    <img src="{{ $logoPath }}" style="max-width: 140px;">
                @endif
            </td>
            <td style="vertical-align: top;">
                <div style="font-size: 16px; font-weight:bold; margin-bottom: 3px;">
                    {{ $company->name }}
                </div>
                @if($company->address)
                    <div>{{ $company->address }}</div>
                @endif
                @if($company->city || $company->province)
                    <div>
                        {{ $company->city }}
                        @if($company->city && $company->province) - @endif
                        {{ $company->province }}
                    </div>
                @endif
                @if($company->phone || $company->email || $company->website)
                    <div>
                        @if($company->phone)
                            Tel: {{ $company->phone }}
                        @endif
                        @if($company->phone && ($company->email || $company->website))
                            &nbsp;|&nbsp;
                        @endif
                        @if($company->email)
                            Email: {{ $company->email }}
                        @endif
                        @if($company->website)
                            @if($company->email)&nbsp;|&nbsp;@endif
                            {{ $company->website }}
                        @endif
                    </div>
                @endif
                @if($company->tax_number)
                    <div>NPWP: {{ $company->tax_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <hr style="margin-top:8px; margin-bottom:8px;">
@endif

<div class="title">PURCHASE ORDER</div>

{{-- INFO PO & SUPPLIER --}}
@php
    $supplier = $po->supplier;
    $formatRupiah = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp

<table>
    <tr>
        {{-- Supplier --}}
        <td style="width: 50%; vertical-align: top;">
            <table>
                <tr>
                    <td style="width: 30%;"><strong>Supplier</strong></td>
                    <td style="width: 5%;">:</td>
                    <td>{{ $supplier->name ?? '-' }}</td>
                </tr>
                @if($supplier?->address)
                    <tr>
                        <td><strong>Alamat</strong></td>
                        <td>:</td>
                        <td>{{ $supplier->address }}</td>
                    </tr>
                @endif
                @if($supplier?->phone || $supplier?->email)
                    <tr>
                        <td><strong>Kontak</strong></td>
                        <td>:</td>
                        <td>
                            @if($supplier?->phone)
                                {{ $supplier->phone }}
                            @endif
                            @if($supplier?->phone && $supplier?->email)
                                &nbsp;|&nbsp;
                            @endif
                            @if($supplier?->email)
                                {{ $supplier->email }}
                            @endif
                        </td>
                    </tr>
                @endif
            </table>
        </td>

        {{-- Info PO --}}
        <td style="width: 50%; vertical-align: top;">
            <table>
                <tr>
                    <td style="width: 35%;"><strong>No. PO</strong></td>
                    <td style="width: 5%;">:</td>
                    <td>{{ $po->po_code }}</td>
                </tr>
                <tr>
                    <td><strong>Tanggal</strong></td>
                    <td>:</td>
                    <td>
                        @if($po->ordered_at)
                            {{ \Carbon\Carbon::parse($po->ordered_at)->format('d/m/Y') }}
                        @else
                            {{ $po->created_at->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Dibuat oleh</strong></td>
                    <td>:</td>
                    <td>{{ $po->user->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td><strong>Status Approval</strong></td>
                    <td>:</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $po->approval_status)) }}</td>
                </tr>
                <tr>
                    <td><strong>Sumber</strong></td>
                    <td>:</td>
                    <td>
                        @if($po->items->pluck('request_id')->filter()->count() > 0)
                            Dari Request Restock
                        @else
                            Manual Purchase
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ITEM LIST --}}
<div class="mt-10"></div>

<table class="table-items">
    <thead>
        <tr>
            <th style="width: 5%;">No</th>
            <th>Nama Barang</th>
            <th style="width: 10%;">Qty</th>
            <th style="width: 10%;">Satuan</th>
            <th style="width: 15%;" class="text-right">Harga Satuan</th>
            <th style="width: 15%;" class="text-right">Discount</th>
            <th style="width: 15%;" class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @php
            $rowNo = 1;
        @endphp
        @foreach($po->items as $item)
            @php
                $product   = $item->product;
                $discount  = $item->discount_value ?? 0;
                $discountLabel = '';
                if ($item->discount_type === 'percent') {
                    $discountLabel = $discount . ' %';
                } elseif ($item->discount_type === 'amount') {
                    $discountLabel = $formatRupiah($discount);
                }
            @endphp
            <tr>
                <td class="text-center">{{ $rowNo++ }}</td>
                <td>
                    {{ $product->name ?? '-' }}<br>
                    <span class="small text-muted">
                        {{ $product->product_code ?? '' }}
                    </span>
                </td>
                <td class="text-center">{{ $item->qty_ordered }}</td>
                <td class="text-center">
                    {{ $product->unit ?? '-' ?? '' }}
                </td>
                <td class="text-right">{{ $formatRupiah($item->unit_price) }}</td>
                <td class="text-right">{{ $discountLabel }}</td>
                <td class="text-right">{{ $formatRupiah($item->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- TOTAL --}}
<table class="mt-10" style="width: 40%; margin-left: auto;">
    <tr>
        <td style="width: 50%;">Subtotal</td>
        <td class="text-right">{{ $formatRupiah($po->subtotal) }}</td>
    </tr>
    <tr>
        <td>Discount</td>
        <td class="text-right">- {{ $formatRupiah($po->discount_total) }}</td>
    </tr>
    <tr>
        <td><strong>Grand Total</strong></td>
        <td class="text-right"><strong>{{ $formatRupiah($po->grand_total) }}</strong></td>
    </tr>
</table>

{{-- NOTES --}}
@if($po->notes)
    <div class="mt-10">
        <strong>Catatan:</strong>
        <div>{{ $po->notes }}</div>
    </div>
@endif

{{-- TANDA TANGAN --}}
<div class="mt-20"></div>

@php
    $sign = function($user) {
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

<table>
    <tr>
        {{-- Dibuat oleh --}}
        <td class="text-center" style="width: 33%;">
            <div class="small">Dibuat oleh</div>
            <div style="height: 50px; margin-top: 5px; margin-bottom: 5px;">
                @if($sign($po->creator))
                    <img src="{{ $sign($po->creator) }}" style="height:45px;">
                @endif
            </div>
            <div style="font-weight:bold;">
                {{ $po->creator->name ?? '________________' }}
            </div>
            <div class="small">
                {{ $po->creator->position ?? 'Pembuat PO' }}
            </div>
        </td>

        {{-- Disetujui Procurement --}}
        <td class="text-center" style="width: 33%;">
            <div class="small">Disetujui Procurement</div>
            <div style="height: 50px; margin-top: 5px; margin-bottom: 5px;">
                @if($sign($po->procurementApprover))
                    <img src="{{ $sign($po->procurementApprover) }}" style="height:45px;">
                @endif
            </div>
            <div style="font-weight:bold;">
                {{ $po->procurementApprover->name ?? '________________' }}
            </div>
            <div class="small">
                {{ $po->procurementApprover->position ?? 'Procurement' }}
            </div>
        </td>

        {{-- Disetujui CEO --}}
        <td class="text-center" style="width: 33%;">
            <div class="small">Disetujui CEO</div>
            <div style="height: 50px; margin-top: 5px; margin-bottom: 5px;">
                @if($sign($po->ceoApprover))
                    <img src="{{ $sign($po->ceoApprover) }}" style="height:45px;">
                @endif
            </div>
            <div style="font-weight:bold;">
                {{ $po->ceoApprover->name ?? '________________' }}
            </div>
            <div class="small">
                {{ $po->ceoApprover->position ?? 'CEO' }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>
