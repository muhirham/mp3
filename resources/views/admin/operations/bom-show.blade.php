@extends('layouts.home')

@section('content')
<div class="container-xxl container-p-y">

    {{-- HEADER --}}
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('bom.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            ← Back
        </a>

        <div>
            <h4 class="mb-1">
                {{ $bom->bom_code }} - {{ $bom->product->name }}
            </h4>
            <small class="text-muted">
                Version {{ $bom->version }} |
                Output per Batch: {{ $bom->output_qty }} |
                Status:
                @if($bom->is_active)
                    <span class="badge bg-success">ACTIVE</span>
                @else
                    <span class="badge bg-secondary">INACTIVE</span>
                @endif
            </small>
        </div>
    </div>


    {{-- TABS --}}
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOverview">
                Overview
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabProduction">
                Production
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHistory">
                History
            </button>
        </li>
    </ul>

    <div class="tab-content">

        {{-- ================= OVERVIEW ================= --}}
        <div class="tab-pane fade show active" id="tabOverview">

            <div class="card mb-4">
                <div class="card-header">
                    <strong>Recipe Materials</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Qty per Batch</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bom->items as $item)
                                <tr>
                                    <td>{{ $item->material->name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>BOM Information</strong>
                </div>
                <div class="card-body">
                    <p><strong>Created By:</strong> {{ $bom->creator?->name }}</p>
                    <p><strong>Created At:</strong> {{ $bom->created_at }}</p>
                    <p><strong>Updated By:</strong> {{ $bom->updater?->name }}</p>
                    <p><strong>Updated At:</strong> {{ $bom->updated_at }}</p>
                </div>
            </div>

        </div>


        {{-- ================= PRODUCTION ================= --}}
        <div class="tab-pane fade" id="tabProduction">

            <div class="card">
                <div class="card-header">
                    <strong>Execute Production</strong>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ route('bom.produce', $bom) }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4">
                                <label>Batch Qty</label>
                                <input type="number"
                                       name="production_qty"
                                       class="form-control"
                                       min="1"
                                       required>
                            </div>

                            <div class="col-md-4">
                                <label>Estimated Output</label>
                                <input type="text"
                                       class="form-control"
                                       value="Auto calculated"
                                       disabled>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-success">
                                Produce
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>


        {{-- ================= HISTORY ================= --}}
        <div class="tab-pane fade" id="tabHistory">

            <div class="card">
                <div class="card-header">
                    <strong>Production History</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Batch</th>
                                <th>Total Cost</th>
                                <th>User</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bom->productions as $trx)
                                <tr>
                                    <td>{{ $trx->created_at }}</td>
                                    <td>{{ $trx->production_qty }}</td>
                                    <td>{{ number_format($trx->total_cost,2) }}</td>
                                    <td>{{ $trx->user->name }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-info"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#trx{{ $trx->id }}">
                                            View
                                        </button>
                                    </td>
                                </tr>

                                <tr class="collapse" id="trx{{ $trx->id }}">
                                    <td colspan="5">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Material</th>
                                                    <th>Qty Used</th>
                                                    <th>Cost/Unit</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($trx->items as $detail)
                                                    <tr>
                                                        <td>{{ $detail->material->name }}</td>
                                                        <td>{{ $detail->qty_used }}</td>
                                                        <td>{{ $detail->cost_per_unit }}</td>
                                                        <td>{{ $detail->total_cost }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>

                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
