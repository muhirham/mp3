<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Goods Received {{ $first->code }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
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

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .mt-10 {
            margin-top: 10px;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .small {
            font-size: 10px;
        }

        .text-muted {
            color: #777;
        }
        
        .text-success {
            color: #198754;
        }
        
        .text-danger {
            color: #dc3545;
        }
    </style>
</head>

<body>

    {{-- HEADER COMPANY / KOP SURAT --}}
    @if ($company)
        <table>
            <tr>
                <td style="width: 25%; vertical-align: top;">
                    @if ($company && $company->logo_url)
                        <img src="{{ $company->logo_url }}" style="max-width: 140px;">
                    @endif
                </td>
                <td style="vertical-align: top;">
                    <div style="font-size: 16px; font-weight:bold; margin-bottom: 3px;">
                        {{ $company->legal_name }}
                    </div>
                    @if ($company->address)
                        <div>{{ $company->address }}</div>
                    @endif
                    @if ($company->city || $company->province)
                        <div>
                            {{ $company->city }}
                            @if ($company->city && $company->province)
                                -
                            @endif
                            {{ $company->province }}
                        </div>
                    @endif
                    @if ($company->phone || $company->email || $company->website)
                        <div>
                            @if ($company->phone)
                                Tel: {{ $company->phone }}
                            @endif
                            @if ($company->phone && ($company->email || $company->website))
                                &nbsp;|&nbsp;
                            @endif
                            @if ($company->email)
                                Email: {{ $company->email }}
                            @endif
                            @if ($company->website)
                                @if ($company->email)
                                    &nbsp;|&nbsp;
                                @endif
                                {{ $company->website }}
                            @endif
                        </div>
                    @endif
                    @if ($company->tax_number)
                        <div>NPWP: {{ $company->tax_number }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <hr style="margin-top:8px; margin-bottom:8px;">
    @endif

    <div class="title">GOODS RECEIVED</div>

    @php
        $supplier = $po ? $po->supplier : ($first->supplier ?? null);
        $formatRupiah = fn($v) => 'Rp ' . number_format($v ?? 0, 0, ',', '.');
        
        // Coba ambil nama supplier
        $supplierLabel = '-';
        if ($supplier) {
            $supplierLabel = $supplier->name;
        } elseif ($po) {
            $itemSuppliers = $po->items->map(fn($it) => $it->product?->supplier)->filter();
            if ($itemSuppliers->isNotEmpty()) {
                $supplierNames = $itemSuppliers->pluck('name')->unique()->values();
                if ($supplierNames->count() === 1) {
                    $supplierLabel = $supplierNames->first();
                } else {
                    $supplierLabel = $supplierNames->first() . ' + ' . ($supplierNames->count() - 1) . ' supplier';
                }
            }
        }
        
        $sourceLabel = '-';
        if ($first->gr_type == 'po') {
            $sourceLabel = 'PO: ' . ($po?->po_code ?? '-');
        } elseif ($first->gr_type == 'request_stock') {
            $sourceLabel = 'Request: ' . ($first->request?->code ?? ('RS-'.$first->request_id));
        } elseif ($first->gr_type == 'gr_transfer') {
            $sourceLabel = 'Warehouse Transfer';
        } elseif ($first->gr_type == 'gr_return') {
            $sourceLabel = 'Sales Return';
        }
        
        $whName = $first->warehouse?->warehouse_name ?? ($first->warehouse?->name ?? 'Central Stock');
    @endphp

    <table>
        <tr>
            {{-- Kiri: Info Supplier & Gudang --}}
            <td style="width:50%; vertical-align:top;">
                <table>
                    <tr>
                        <td style="width:30%;"><strong>Supplier</strong></td>
                        <td style="width:5%;">:</td>
                        <td>{{ $supplierLabel }}</td>
                    </tr>
                    @if ($supplier && $supplier->address)
                        <tr>
                            <td><strong>Alamat</strong></td>
                            <td>:</td>
                            <td>{{ $supplier->address }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><strong>Warehouse</strong></td>
                        <td>:</td>
                        <td>{{ $whName }}</td>
                    </tr>
                </table>
            </td>

            {{-- Kanan: Info GR --}}
            <td style="width: 50%; vertical-align: top;">
                <table>
                    <tr>
                        <td style="width: 35%;"><strong>GR Code</strong></td>
                        <td style="width: 5%;">:</td>
                        <td>{{ $first->code }}</td>
                    </tr>
                    <tr>
                        <td><strong>Sumber</strong></td>
                        <td>:</td>
                        <td>{{ $sourceLabel }}</td>
                    </tr>
                    <tr>
                        <td><strong>Tanggal GR</strong></td>
                        <td>:</td>
                        <td>{{ \Carbon\Carbon::parse($first->received_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Diterima oleh</strong></td>
                        <td>:</td>
                        <td>{{ $first->receiver?->name ?? '-' }}</td>
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
                <th style="width: 10%;">Qty Target</th>
                <th style="width: 10%;">Received</th>
                <th style="width: 10%;">Good</th>
                <th style="width: 10%;">Damaged</th>
                <th style="width: 15%;" class="text-right">Harga Satuan</th>
                <th style="width: 15%;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $rowNo = 1; 
                $grandTotal = 0;
            @endphp
            @foreach ($displayItems as $item)
                @php
                    $product = $item->product ?? null;
                    if(!$product) continue;
                    
                    // Filter receipts khusus untuk item ini (Product, Request ID, dan Warehouse ID yang sama)
                    $itemReceipts = $receipts->filter(function($r) use ($item) {
                        return $r->product_id == $item->product_id && 
                               ($r->warehouse_id ?: null) == ($item->warehouse_id ?: null) &&
                               ($r->request_id ?: null) == ($item->request_id ?: null);
                    });
                    
                    $qtyGood = $itemReceipts->sum('qty_good');
                    $qtyDamaged = $itemReceipts->sum('qty_damaged');
                    $qtyReceived = $qtyGood + $qtyDamaged;
                    
                    $subtotal = $qtyGood * ($item->unit_price ?? 0);
                    $grandTotal += $subtotal;
                @endphp
                <tr>
                    <td class="text-center">{{ $rowNo++ }}</td>
                    <td>
                        {{ $product->name ?? '-' }}<br>
                        <span class="small text-muted">
                            {{ $product->product_code ?? '' }}
                        </span>
                    </td>
                    <td class="text-center">{{ $item->qty_ordered ?? 0 }}</td>
                    <td class="text-center">{{ $qtyReceived }}</td>
                    <td class="text-center text-success"><strong>{{ $qtyGood }}</strong></td>
                    <td class="text-center text-danger">{{ $qtyDamaged }}</td>
                    <td class="text-right">{{ $formatRupiah($item->unit_price) }}</td>
                    <td class="text-right"><strong>{{ $formatRupiah($subtotal) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTAL --}}
    @php
        $discountTotal = $po ? (float) ($po->discount_total ?? 0) : 0;
        $finalTotal = $grandTotal - $discountTotal;
    @endphp
    <table class="mt-10" style="width: 40%; margin-left: auto;">
        <tr>
            <td style="width: 50%;">Subtotal Amount</td>
            <td class="text-right">{{ $formatRupiah($grandTotal) }}</td>
        </tr>
        @if($discountTotal > 0)
        <tr>
            <td>Discount PO</td>
            <td class="text-right text-danger">- {{ $formatRupiah($discountTotal) }}</td>
        </tr>
        @endif
        <tr>
            <td style="border-top: 1px solid #000; padding-top: 5px;"><strong>Grand Total (Payable)</strong></td>
            <td class="text-right" style="border-top: 1px solid #000; padding-top: 5px;"><strong>{{ $formatRupiah($finalTotal) }}</strong></td>
        </tr>
    </table>

    {{-- NOTES --}}
    @php
        $allNotes = $receipts->pluck('notes')->filter()->unique();
    @endphp
    @if ($allNotes->isNotEmpty())
        <div class="mt-10">
            <strong>Catatan Penerimaan:</strong>
            <ul style="margin-top: 5px; padding-left: 20px;">
                @foreach ($allNotes as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- TANDA TANGAN --}}
    <div class="mt-20"></div>

    <table style="width: 100%; margin-top: 40px;">
        <tr>
            <td class="text-center" style="width: 50%;">
                <div class="small">Diserahkan oleh,</div>
                <div style="height: 60px;"></div>
                <div style="font-weight:bold;">
                    ________________________
                </div>
                <div class="small">
                    Kurir / Ekspedisi / Pemasok
                </div>
            </td>

            <td class="text-center" style="width: 50%;">
                <div class="small">Diterima oleh,</div>
                <div style="height: 60px;">
                    @if ($first->receiver?->signature_url)
                        <img src="{{ $first->receiver->signature_url }}" style="height:55px;">
                    @endif
                </div>
                <div style="font-weight:bold;">
                    {{ $first->receiver?->name ?? '________________________' }}
                </div>
                <div class="small">
                    {{ $first->receiver?->position ?? 'Warehouse Admin' }}
                </div>
            </td>
        </tr>
    </table>

    @if (!empty($autoPrint))
        <script>
            window.addEventListener('load', function() {
                window.focus();
                window.print();
            });
        </script>
    @endif

</body>

</html>
