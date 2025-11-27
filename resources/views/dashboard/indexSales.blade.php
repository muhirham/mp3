    @extends('layouts.home')
    @section('title', 'Sales Dashboard')

    @section('content')
    <div class="container py-4">
    <h3>Sales Dashboard</h3>
    <p>Hai {{ auth()->user()->name }} ({{ auth()->user()->role }})</p>
    <p>Ini halaman utama sales — tempat input laporan harian, retur, dan stok.</p>
    </div>
    @endsection
