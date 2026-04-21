@extends('layouts.home')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="mb-3">{{ $title ?? 'Coming Soon' }}</h4>
  <div class="card">
    <div class="card-body">
      Halaman <strong>{{ $title ?? '-' }}</strong> belum dibuat. Silakan isi nanti.
    </div>
  </div>
</div>
@endsection
