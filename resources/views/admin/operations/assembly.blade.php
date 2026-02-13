@extends('layouts.home')

@section('content')
    <div class="container">

        <h4 class="mb-4">Assembly</h4>

        {{-- FORM --}}
        <div class="card mb-4">
            <div class="card-header">Create Assembly</div>
            <div class="card-body">
                <form action="{{ route('assembly.store') }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-4">
                            <label>Saldo Product</label>
                            <select name="saldo_id" class="form-control" required>
                                <option value="" selected disabled>-- Pilih Saldo --</option>

                                @foreach ($saldoProducts as $product)
                                    @php
                                        $stock = $product->stockLevels->first()->quantity ?? 0;
                                    @endphp
                                    <option value="{{ $product->id }}">
                                        {{ $product->name }} (Stock: {{ number_format($stock) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>KPK Product</label>
                            <select name="kpk_id" class="form-control" required>
                                <option value="" selected disabled>-- Pilih KPK --</option>
                                @foreach ($kpkProducts as $product)
                                    @php
                                        $stock = $product->stockLevels->first()->quantity ?? 0;
                                    @endphp
                                    <option value="{{ $product->id }}">
                                        {{ $product->name }} (Stock: {{ number_format($stock) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>Qty</label>
                            <input type="number" name="qty" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-2">
                            <label>Nominal / Kartu</label>
                            <input type="number" name="saldo_per_unit" class="form-control" min="1"
                                placeholder="contoh: 1000" required>
                        </div>

                        <div class="col-12 mt-2">
                            <div id="previewBox" class="text-muted small"></div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-success w-100">
                                Process
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- HISTORY --}}
        <div class="card">
            <div class="card-header">Assembly History</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Qty</th>
                            <th>Nominal</th>
                            <th>Saldo Awal</th>
                            <th>Saldo Dipakai</th>
                            <th>Saldo Sisa</th>
                            <th>Dibuat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $trx)
                            <tr>
                                <td>{{ $trx->created_at->format('d-m-Y H:i') }}</td>
                                <td>{{ $trx->qty }}</td>
                                <td>{{ number_format($trx->saldo_per_unit) }}</td>
                                <td>{{ number_format($trx->saldo_before) }}</td>
                                <td>{{ number_format($trx->saldo_used) }}</td>
                                <td>{{ number_format($trx->saldo_after) }}</td>
                                <td>{{ $trx->user->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{ $transactions->links() }}
            </div>
        </div>

    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const qtyInput = document.querySelector('input[name="qty"]');
    const nominalInput = document.querySelector('input[name="saldo_per_unit"]');
    const saldoSelect = document.querySelector('select[name="saldo_id"]');
    const kpkSelect = document.querySelector('select[name="kpk_id"]');
    const preview = document.getElementById('previewBox');
    const btn = document.querySelector('button[type="submit"]');

    function getStock(select) {
        let text = select.options[select.selectedIndex]?.text || '';
        let match = text.match(/Stock:\s*([\d,]+)/);
        if (!match) return 0;
        return parseInt(match[1].replace(/,/g, ''));
    }

    function calculate() {

        let qty = parseInt(qtyInput.value) || 0;
        let nominal = parseInt(nominalInput.value) || 0;

        let saldoStock = getStock(saldoSelect);
        let kpkStock = getStock(kpkSelect);

        let total = qty * nominal;

        if (!qty || !nominal) {
            preview.innerHTML = '';
            btn.disabled = false;
            return;
        }

        let maxBySaldo = Math.floor(saldoStock / nominal);
        let maxPossible = Math.min(maxBySaldo, kpkStock);

        if (qty > maxPossible) {
            preview.innerHTML =
                `<span class="text-danger">
                    ⚠ Melebihi stock. Maksimal: ${maxPossible}
                </span>`;
            btn.disabled = true;
        } else {
            preview.innerHTML = `
                Total saldo dipakai: <b>${total.toLocaleString()}</b><br>
                Sisa saldo: <b>${(saldoStock - total).toLocaleString()}</b><br>
                Sisa KPK: <b>${(kpkStock - qty).toLocaleString()}</b>
            `;
            btn.disabled = false;
        }
    }

    qtyInput.addEventListener('input', calculate);
    nominalInput.addEventListener('input', calculate);
    saldoSelect.addEventListener('change', calculate);
    kpkSelect.addEventListener('change', calculate);
});
</script>
@endpush
