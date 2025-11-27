    @extends('layouts.home')
    @section('title','Transaction Details')

    @section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
        <h4 class="fw-bold">Transaction #{{ $transaction->id }}</h4>
        <div class="text-muted">User: {{ $transaction->user->name ?? '-' }} • Warehouse: {{ $transaction->warehouse->warehouse_name ?? '-' }}</div>
        </div>
        <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddDetail">+ Add Details</button>
        <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
        <table class="table table-borderless">
            <tr><th>Date</th><td>{{ $transaction->transaction_date }}</td></tr>
            <tr><th>Type</th><td>{{ $transaction->transaction_type }}</td></tr>
            <tr><th>Status</th><td>{{ $transaction->status }}</td></tr>
            <tr><th>Total</th><td>Rp <span id="txTotal">{{ number_format($transaction->total ?? 0,0,',','.') }}</span></td></tr>
        </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">Details</h6></div>
        <div class="card-body table-responsive">
        <table class="table table-striped" id="detailsTable">
            <thead><tr><th>#</th><th>Product</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th><th>Actions</th></tr></thead>
            <tbody></tbody>
        </table>
        </div>
    </div>
    </div>

    {{-- Modal Add Detail --}}
    <div class="modal fade" id="modalAddDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formAddDetail" class="modal-content" method="POST">
        @csrf
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Add Details to Transaction #{{ $transaction->id }}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div id="newDetailRows"></div>
            <button type="button" id="btnAddDetailRow" class="btn btn-sm btn-outline-primary mt-2">+ Add Row</button>
        </div>

        <div class="modal-footer">
            <button class="btn btn-primary" type="submit">Save Details</button>
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        </div>
        </form>
    </div>
    </div>
    @endsection

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(function(){

    // txId from blade
    var txId = {{ (int)$transaction->id }};

    // fetch products server-side and JSON-encode safely
    var products = {!! json_encode(\App\Models\Product::select('id','product_name','selling_price')->get()->map(function($p){
        return ['id'=>$p->id,'product_name'=>$p->product_name,'price'=> (float) $p->selling_price];
    })->values()) !!};

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    var $tbody = $('#detailsTable tbody');
    var $txTotal = $('#txTotal');

    // build <option> string using concatenation (safe)
    function buildOptions() {
        var s = '';
        for (var i = 0; i < products.length; i++) {
        var p = products[i];
        var price = (typeof p.price === 'number') ? p.price : parseFloat(p.price || 0);
        s += '<option value="' + p.id + '" data-price="' + price + '">' + (p.product_name || '') + '</option>';
        }
        return s;
    }

    // fetch details
    function fetchDetails() {
        $tbody.html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');
        $.get('/transactions/' + txId + '/details/json')
        .done(function(res) {
            if (!res || !res.ok) {
            $tbody.html('<tr><td colspan="6" class="text-center text-danger">Failed to load</td></tr>');
            return;
            }
            var rows = res.data || [];
            if (!rows.length) {
            $tbody.html('<tr><td colspan="6" class="text-center text-muted">No details</td></tr>');
            $txTotal.text('0');
            return;
            }
            var html = '';
            var total = 0;
            for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var qty = Number(r.quantity || 0);
            var price = Number(r.price || 0);
            var subtotal = Number(r.subtotal || (qty * price));
            total += subtotal;
            html += '<tr data-id="' + (r.id || '') + '">' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + (r.product_name || '') + '</td>' +
                        '<td class="text-end">' + qty.toLocaleString() + '</td>' +
                        '<td class="text-end">' + price.toLocaleString() + '</td>' +
                        '<td class="text-end">' + subtotal.toLocaleString() + '</td>' +
                        '<td><button class="btn btn-sm btn-danger btnDeleteDetail" data-id="' + (r.id || '') + '">Delete</button></td>' +
                    '</tr>';
            }
            $tbody.html(html);
            $txTotal.text(Number(total).toLocaleString('id-ID'));
        })
        .fail(function(xhr) {
            console.error(xhr);
            $tbody.html('<tr><td colspan="6" class="text-center text-danger">Load failed</td></tr>');
        });
    }

    fetchDetails();

    // Add row in modal
    function addDetailRow() {
        var opts = buildOptions();
        var $row = $('<div class="row g-2 mb-2 detailRow">' +
        '<div class="col-md-6">' +
            '<select name="product_id[]" class="form-select productRow">' +
            '<option value="">Select product...</option>' + opts +
            '</select>' +
        '</div>' +
        '<div class="col-md-2">' +
            '<input type="number" name="quantity[]" class="form-control qtyRow" value="1" min="0" step="1">' +
        '</div>' +
        '<div class="col-md-2">' +
            '<input type="text" name="price[]" class="form-control priceRow" readonly>' +
        '</div>' +
        '<div class="col-md-1">' +
            '<button type="button" class="btn btn-danger btn-sm removeRow">×</button>' +
        '</div>' +
        '</div>');
        $('#newDetailRows').append($row);
    }

    $('#btnAddDetailRow').on('click', addDetailRow);
    $('#modalAddDetail').on('show.bs.modal', function(){ $('#newDetailRows').empty(); addDetailRow(); });

    // set price when product chosen
    $('#newDetailRows').on('change', '.productRow', function(){
        var price = $(this).find('option:selected').data('price') || 0;
        $(this).closest('.detailRow').find('.priceRow').val(price);
    });

    // remove row
    $('#newDetailRows').on('click', '.removeRow', function(){ $(this).closest('.detailRow').remove(); });

    // submit add details
    $('#formAddDetail').on('submit', function(e){
        e.preventDefault();
        var any = $('#newDetailRows').find('.productRow').toArray().some(function(s){ return s.value; });
        if(!any){
        Swal.fire({title:'Validation', text:'Please add at least one product', icon:'warning'});
        return;
        }

        var fd = new FormData(this);
        Swal.fire({ title:'Saving...', didOpen:function(){ Swal.showLoading(); }, allowOutsideClick:false });

        $.ajax({
        url: '/transactions/' + txId + '/details',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(res){
            Swal.close();
            if(res && res.ok){
            $('#modalAddDetail').modal('hide');
            Swal.fire({icon:'success', title:'Saved', text:'Details added', confirmButtonText:'OK'}).then(fetchDetails);
            } else {
            Swal.fire('Error', (res && res.error) ? res.error : 'Failed','error');
            }
        },
        error: function(xhr){
            Swal.close();
            Swal.fire('Error','Save failed','error');
            console.error(xhr);
        }
        });
    });

    // delete detail
    $tbody.on('click', '.btnDeleteDetail', function(){
        var id = $(this).data('id');
        Swal.fire({ title:'Delete detail?', icon:'warning', showCancelButton:true }).then(function(ans){
        if(!ans.isConfirmed) return;
        $.ajax({ url: '/transaction-details/' + id, method: 'DELETE' })
            .done(function(res){
            if(res && res.ok){
                Swal.fire({icon:'success', title:'Deleted', confirmButtonText:'OK'}).then(fetchDetails);
            } else {
                Swal.fire('Error', (res && res.error) ? res.error : 'Failed','error');
            }
            })
            .fail(function(xhr){
            Swal.fire('Error','Delete failed','error');
            console.error(xhr);
            });
        });
    });

    });
    </script>
    @endpush
