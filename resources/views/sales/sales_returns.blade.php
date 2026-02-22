@extends('layouts.home')

@section('content')
<div class="container">
    <h4>Sales Return</h4>

    <div class="mb-3">
        <label>Pilih HDO</label>
        <select id="handoverSelect" class="form-control">
            <option value="">-- Pilih HDO --</option>
            @foreach($handovers as $h)
                <option value="{{ $h->id }}">
                    {{ $h->code }} - {{ $h->handover_date }}
                </option>
            @endforeach
        </select>
    </div>

    <form method="POST" action="{{ route('sales.returns.store') }}">
        @csrf
        <input type="hidden" name="handover_id" id="handover_id">

        <table class="table table-bordered" id="itemsTable" style="display:none;">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Sisa</th>
                    <th>Damaged</th>
                    <th>Expired</th>
                    <th>Good (Auto)</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <button class="btn btn-primary mt-2" id="submitBtn" style="display:none;">
            Submit Return
        </button>
    </form>
</div>

<script>
document.getElementById('handoverSelect').addEventListener('change', function(){
    let id = this.value;
    if(!id) return;

    document.getElementById('handover_id').value = id;

    fetch('/sales/returns/load/' + id)
    .then(res => res.json())
    .then(data => {

        let tbody = document.querySelector('#itemsTable tbody');
        tbody.innerHTML = '';

        data.forEach((item, index) => {

            tbody.innerHTML += `
                <tr>
                    <td>
                        ${item.product}
                        <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                        <input type="hidden" name="items[${index}][remaining]" value="${item.remaining}">
                    </td>
                    <td>${item.remaining}</td>
                    <td>
                        <input type="number" min="0" value="0"
                            name="items[${index}][damaged]"
                            class="damaged form-control">
                    </td>
                    <td>
                        <input type="number" min="0" value="0"
                            name="items[${index}][expired]"
                            class="expired form-control">
                    </td>
                    <td class="good">0</td>
                </tr>
            `;
        });

        document.getElementById('itemsTable').style.display = 'table';
        document.getElementById('submitBtn').style.display = 'inline-block';

        autoCalculate();
    });
});

function autoCalculate(){
    document.querySelectorAll('#itemsTable tbody tr').forEach(row => {

        let remaining = parseInt(row.querySelector('input[name*="[remaining]"]').value);
        let damaged   = row.querySelector('.damaged');
        let expired   = row.querySelector('.expired');
        let goodCell  = row.querySelector('.good');

        function update(){
            let d = parseInt(damaged.value) || 0;
            let e = parseInt(expired.value) || 0;

            let good = remaining - d - e;
            if(good < 0) good = 0;

            goodCell.innerText = good;
        }

        damaged.addEventListener('input', update);
        expired.addEventListener('input', update);

        update();
    });
}
</script>
@endsection