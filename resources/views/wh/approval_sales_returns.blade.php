@extends('layouts.home')

@section('content')
<div class="container">
    <h4>Approval Sales Return</h4>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Sales</th>
                <th>Produk</th>
                <th>Qty</th>
                <th>Kondisi</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($returns as $r)
            <tr>
                <td>{{ $r->sales->name }}</td>
                <td>{{ $r->product->name }}</td>
                <td>{{ $r->quantity }}</td>
                <td>{{ ucfirst($r->condition) }}</td>
                <td>{{ ucfirst($r->status) }}</td>
                <td>
                    @if($r->status === 'pending')
                        <form method="POST" action="{{ route('warehouse.returns.approve',$r) }}" style="display:inline;">
                            @csrf
                            <button class="btn btn-success btn-sm">Approve</button>
                        </form>

                        <form method="POST" action="{{ route('warehouse.returns.reject',$r) }}" style="display:inline;">
                            @csrf
                            <button class="btn btn-danger btn-sm">Reject</button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection