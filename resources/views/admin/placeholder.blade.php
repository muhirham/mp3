@extends('layouts.home')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="mb-3">{{ $title ?? 'Coming Soon' }}</h4>
  <div class="card">
    <div class="card-body">
      The page <strong>{{ $title ?? '-' }}</strong> has not been created yet. Please fill it later.
    </div>
  </div>
</div>
@endsection
