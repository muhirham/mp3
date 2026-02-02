    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="UTF-8">
        <title>Surat Jalan {{ $transfer->transfer_code }}</title>
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

            table {
                width: 100%;
                border-collapse: collapse;
            }

            .title {
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                text-decoration: underline;
                margin: 10px 0 15px 0;
            }

            .table-items th,
            .table-items td {
                border: 1px solid #555;
                padding: 5px;
            }

            .table-items th {
                background: #f2f2f2;
            }

            .text-center {
                text-align: center;
            }

            .text-right {
                text-align: right;
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

            .watermark {
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

        {{-- WATERMARK --}}
        @if (in_array($transfer->status, ['draft', 'canceled']))
            <div class="watermark">
                {{ strtoupper($transfer->status) }}
            </div>
        @endif

        {{-- ================= HEADER / KOP ================= --}}
        @if ($company)
            @php
                $logoUrl = $company->logo_path ? asset('storage/' . $company->logo_path) : null;
            @endphp

            <table>
                <tr>
                    <td style="width:25%; vertical-align:top;">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" style="max-width:140px;">
                        @endif
                    </td>
                    <td style="vertical-align:top;">
                        <div style="font-size:16px; font-weight:bold;">
                            {{ $company->legal_name }}
                        </div>
                        <div>{{ $company->address }}</div>
                        <div>
                            {{ $company->city }}
                            @if ($company->city && $company->province)
                                -
                            @endif
                            {{ $company->province }}
                        </div>
                        <div class="small">
                            Tel: {{ $company->phone }}
                            @if ($company->email)
                                | {{ $company->email }}
                            @endif
                        </div>
                        @if ($company->tax_number)
                            <div class="small">NPWP: {{ $company->tax_number }}</div>
                        @endif
                    </td>
                </tr>
            </table>
            <hr style="margin:8px 0;">
        @endif

        {{-- ================= TITLE ================= --}}
        <div class="title">SURAT JALAN TRANSFER GUDANG</div>

        {{-- ================= INFO TRANSFER ================= --}}
        <table>
            <tr>
                <td style="width:50%; vertical-align:top;">
                    <table>
                        <tr>
                            <td style="width:35%;"><strong>No Transfer</strong></td>
                            <td style="width:5%;">:</td>
                            <td>{{ $transfer->transfer_code }}</td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal</strong></td>
                            <td>:</td>
                            <td>{{ $transfer->approved_destination_at?->format('d/m/Y') ?? $transfer->created_at->format('d/m/Y') }}
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>:</td>
                            <td>{{ strtoupper($transfer->status) }}</td>
                        </tr>
                    </table>
                </td>

                <td style="width:50%; vertical-align:top;">
                    <table>
                        <tr>
                            <td style="width:40%;"><strong>Requested by Warehouse</strong></td>
                            <td style="width:5%;">:</td>
                            <td>{{ $transfer->sourceWarehouse->warehouse_name }}</td>

                        </tr>
                        <tr>
                            <td><strong>Delivered by Warehouse</strong></td>
                            <td>:</td>
                            <td>{{ $transfer->destinationWarehouse->warehouse_name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Dibuat oleh</strong></td>
                            <td>:</td>
                            <td>{{ $transfer->creator->name ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ================= ITEMS ================= --}}
        <div class="mt-10"></div>

        <table class="table-items">
            <thead>
                <tr>
                    <th style="width:5%;">No</th>
                    <th>Nama Barang</th>
                    <th style="width:15%;">Kode Produk</th>
                    <th style="width:10%;" class="text-center">Qty Kirim</th>
                    <th style="width:20%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transfer->items as $i => $item)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>
                            {{ $item->product->name }}
                            <div class="small text-muted">
                                {{ $item->product->unit ?? '' }}
                            </div>
                        </td>
                        <td class="text-center">{{ $item->product->product_code }}</td>
                        <td class="text-center">{{ $item->qty_transfer }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ================= NOTE ================= --}}
        @if ($transfer->note)
            <div class="mt-10">
                <strong>Catatan:</strong>
                <div>{{ $transfer->note }}</div>
            </div>
        @endif

        {{-- ================= SIGNATURE ================= --}}
        @php
            $sign = function ($user) {
                if (!$user || !$user->signature_path) {
                    return null;
                }
                return asset('storage/' . $user->signature_path);
            };
        @endphp

        <div class="mt-20"></div>

        <table>
            <tr>
                {{-- GUDANG PENGIRIM --}}
                <td class="text-center" style="width:50%;">
                    <div class="small">Dikirim oleh</div>
                    <div class="small text-muted">
                        {{ $transfer->destinationWarehouse->warehouse_name }}
                    </div>

                    <div style="height:50px; margin:8px 0;">
                        @if ($transfer->approvedDestinationBy?->signature_path)
                            <img src="{{ asset('storage/' . $transfer->approvedDestinationBy->signature_path) }}"
                                style="height:45px;">
                        @endif
                    </div>

                    <strong>
                        {{ $transfer->approvedDestinationBy->name ?? '______________' }}
                    </strong>
                    <div class="small">Admin Gudang Pengirim</div>
                </td>

                {{-- GUDANG PENERIMA --}}
                <td class="text-center" style="width:50%;">
                    <div class="small">Diterima oleh</div>
                    <div class="small text-muted">
                        {{ $transfer->sourceWarehouse->warehouse_name }}
                    </div>

                    <div style="height:50px; margin:8px 0;">
                        @if ($receivedLog && $receivedLog->user?->signature_path)
                            <img src="{{ asset('storage/' . $receivedLog->user->signature_path) }}" style="height:45px;">
                        @endif
                    </div>

                    <strong>{{ $receivedLog->user->name ?? '______________' }}</strong>
                    <div class="small">Admin Gudang Penerima</div>
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
