@php
    $salesName = $handover->sales->name ?? 'Sales';
    $whName    = $handover->warehouse->warehouse_name
                 ?? $handover->warehouse->name
                 ?? 'Warehouse';
@endphp

<p>Halo {{ $salesName }},</p>

<p>
    Berikut detail handover pagi dan kode OTP yang akan dipakai juga saat tutup sore.
</p>

<p>
    <strong>Handover Pagi - Serah Terima Barang</strong><br>
    Kode      : {{ $handover->code }}<br>
    Tanggal   : {{ $handover->handover_date->format('Y-m-d') }}<br>
    Warehouse : {{ $whName }}<br>
    Sales     : {{ $salesName }}
</p>

<p><strong>Detail barang:</strong></p>
<ol>
    @foreach ($items as $i)
        <li>
            {{ $i['name'] }} ({{ $i['code'] }}) &rarr;
            Qty {{ $i['qty'] }} x {{ number_format($i['price'], 0, ',', '.') }}
            = {{ number_format($i['total'], 0, ',', '.') }}
        </li>
    @endforeach
</ol>

<p>
    <strong>Total nilai barang:</strong>
    {{ number_format($grandTotal, 0, ',', '.') }}
</p>

<p>
    <strong>OTP Handover (pagi & sore): {{ $otp }}</strong>
</p>

<p>
    Simpan baik-baik OTP ini. OTP harus diinput admin gudang ketika tutup sore
    supaya laporan hari itu bisa di-close dan besok kamu bisa ambil barang lagi.
</p>

<p>Terima kasih.</p>
