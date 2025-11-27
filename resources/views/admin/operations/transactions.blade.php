@extends('layouts.home')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl flex-grow-1 container-p-y">
<h4 class="fw-bold py-0 mb-2">Transactions</h4>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
<style>
    .swal2-container{ z-index:20000 !important; }
</style>
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3" style="min-width:400px">
            <h5 class="mb-0">Transaction List</h5>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <input id="txSearchBox" class="form-control" style="min-width:280px" placeholder="Search transactions (id, user, warehouse, type)...">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddTransaction">
                <i class="bx bx-plus"></i> Add Transaction
            </button>
        </div>
    </div>

    <div class="card-body table-responsive">
    <table class="table table-striped table-hover align-middle" id="transactionsTable">
        <thead class="table-light">
        <tr>
            <th>ID</th><th>User</th><th>Warehouse</th><th>Date</th>
            <th>Type</th><th>Status</th><th class="text-end">Total (Rp)</th>
            <th class="text-end">Paid (Rp)</th><th class="text-end">Change (Rp)</th><th>Actions</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>

    {{-- Pagination container --}}
    <div id="transactionsPagination" class="mt-3 d-flex justify-content-center"></div>

    </div>
</div>
</div>

{{-- Modal Add Transaction --}}
<div class="modal fade" id="modalAddTransaction" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="formAddTransaction" class="modal-content" method="POST">
    @csrf
    <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Add Transaction</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
        <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">User</label>
            <select name="user_id" class="form-select" required>
            <option value="">-- choose user --</option>
            @foreach($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Warehouse</label>
            <select name="warehouse_id" class="form-select" required>
            <option value="">-- choose warehouse --</option>
            @foreach($warehouses as $w) <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option> @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Date</label>
            <input type="datetime-local" name="transaction_date" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Type</label>
            <select name="transaction_type" class="form-select" required>
            <option value="sale">Sale</option>
            <option value="purchase">Purchase</option>
            </select>
        </div>
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0">Transaction Details</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddDetail"><i class="bx bx-plus"></i> Add Product</button>
        </div>

        <div id="transactionDetailsContainer"></div>

        <div class="row mt-3 g-3">
        <div class="col-md-4">
            <label>Grand Total (Rp)</label>
            <input type="text" id="grandTotalDisplay" class="form-control" readonly value="0">
            <input type="hidden" name="total" id="grandTotalInput" value="0">
        </div>

        <div class="col-md-4">
            <label>Paid Amount (Rp)</label>
            <input type="number" name="paid_amount" id="paidAmount" class="form-control" min="0" value="0" step="0.01">
        </div>

        <div class="col-md-4">
            <label>Change (Rp)</label>
            <input type="text" id="changeDisplay" class="form-control" readonly value="0">
            <input type="hidden" name="change_amount" id="changeInput" value="0">
        </div>
        </div>

        <div class="mt-2 text-muted small">
        Payment method: <strong>Cash</strong>
        </div>
    </div>

    <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" type="submit">Save Transaction</button>
    </div>
    </form>
</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function(){
    const products = @json($products->map(function($p){
        return ['id' => $p->id, 'product_name' => $p->product_name, 'price' => (float) ($p->selling_price ?? 0)];
    })->values()->toArray());

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const $tbody = $('#transactionsTable tbody');
    const $container = $('#transactionDetailsContainer');
    const $grandDisplay = $('#grandTotalDisplay');
    const $grandInput = $('#grandTotalInput');
    const $paidAmount = $('#paidAmount');
    const $changeDisplay = $('#changeDisplay');
    const $changeInput = $('#changeInput');
    const $pagination = $('#transactionsPagination');
    const $searchBox = $('#txSearchBox');

    function formatID(n){ return Number(n||0).toLocaleString('id-ID'); }
    function buildOptions(){
        return products.map(function(p){
            return '<option value="'+p.id+'" data-price="'+p.price+'">'+(p.product_name||'')+'</option>';
        }).join('');
    }

    function addRow(init){
        init = init || {};
        var opts = buildOptions();
        var $row = $(
        '<div class="row g-2 align-items-center mb-2 detailRow border rounded p-2 bg-light">' +
            '<div class="col-md-5">' +
            '<select name="product_id[]" class="form-select productSelect" required>' +
                '<option value="">-- select product --</option>' + opts +
            '</select>' +
            '</div>' +
            '<div class="col-md-2"><input type="number" name="quantity[]" class="form-control qty" min="1" value="'+(init.qty||1)+'" required></div>' +
            '<div class="col-md-3">' +
            '<input type="text" class="form-control priceDisplay" readonly placeholder="Rp 0">' +
            '<input type="hidden" name="price[]" class="price" value="'+(init.price||0)+'">' +
            '</div>' +
            '<div class="col-md-1 text-center">' +
            '<button type="button" class="btn btn-danger btn-sm btnRemoveDetail"><i class="bx bx-x"></i></button>' +
            '</div>' +
            '<input type="hidden" name="subtotal[]" class="subtotal" value="'+(init.subtotal||0)+'">' +
        '</div>'
        );
        $container.append($row);
        if(init.product_id) $row.find('.productSelect').val(init.product_id).trigger('change');
    }

    $('#modalAddTransaction').on('show.bs.modal', function(){
        $container.empty();
        addRow();
        updateGrand();
        $paidAmount.val(0);
        $changeDisplay.val('0');
        $changeInput.val(0);
    });

    $('#btnAddDetail').on('click', function(){ addRow(); });

    $container.on('change', '.productSelect', function(){
        var $r = $(this).closest('.detailRow');
        var raw = $(this).find('option:selected').data('price');
        var price = (typeof raw === 'number') ? raw : parseFloat(raw || 0);
        $r.find('.price').val(price);
        $r.find('.priceDisplay').val('Rp ' + formatID(price));
        updateRow($r);
    });

    $container.on('input', '.qty', function(){
        updateRow($(this).closest('.detailRow'));
    });

    $container.on('click', '.btnRemoveDetail', function(){
        $(this).closest('.detailRow').remove();
        updateGrand();
    });

    function updateRow($r){
        var price = parseFloat($r.find('.price').val() || 0);
        var qty = parseFloat($r.find('.qty').val() || 0);
        var sub = price * qty;
        $r.find('.subtotal').val(sub);
        updateGrand();
    }

    function updateGrand(){
        var total = 0;
        $container.find('.detailRow').each(function(){
            total += parseFloat($(this).find('.subtotal').val() || 0);
        });
        $grandDisplay.val('Rp ' + formatID(total));
        $grandInput.val(total);
        updateChange();
    }

    function updateChange(){
        var total = parseFloat($grandInput.val() || 0);
        var paid = parseFloat($paidAmount.val() || 0);
        var change = Math.max(0, (paid - total));
        $changeDisplay.val('Rp ' + formatID(change));
        $changeInput.val(change);
    }

    $paidAmount.on('input', updateChange);

    /* ====== TRANSACTIONS LIST + PAGINATION + SEARCH (AJAX) ====== */
    const listUrl = @json(route('transactions.json'));

    function enhancePagination(container){
        if(!container || !container.length) return;
        container.find('a').each(function(){
            var $a = $(this);
            if ($a.data('page')) {
                $a.addClass('transactions-page-link');
                return;
            }
            try {
                var href = $a.attr('href');
                var search = new URL(href, window.location.origin).searchParams;
                var p = search.get('page') || 1;
                $a.data('page', p);
                $a.addClass('transactions-page-link');
            } catch(e){
                // ignore
            }
        });
        attachPageHandlers(container);
    }

    function attachPageHandlers(container){
        container.find('a.transactions-page-link').each(function(){
            var $a = $(this);
            if ($a.data('_bound')) return;
            $a.data('_bound', 1);
            $a.on('click', function(ev){
                ev.preventDefault();
                var p = $(this).data('page') || 1;
                fetchTransactions(p, $searchBox.val().trim());
            });
        });
    }

    function renderRows(rows){
        if(!rows || !rows.length){
            $tbody.html('<tr><td colspan="10" class="text-center text-muted">No transactions</td></tr>');
            return;
        }
        var html = '';
        rows.forEach(function(t){
            var badge = (t.status === 'completed') ? '<span class="badge bg-success">Completed</span>' : '<span class="badge bg-warning text-dark">Pending</span>';
            var approveBtn = '';
            if(t.transaction_type === 'purchase' && t.status === 'pending'){
                approveBtn = '<button class="btn btn-sm btn-success btn-approve" data-id="'+t.id+'">Approve</button>';
            }
            html += '<tr>' +
                '<td>'+ (t.id||'') +'</td>' +
                '<td>'+ (t.user_name||'-') +'</td>' +
                '<td>'+ (t.warehouse_name||'-') +'</td>' +
                '<td>'+ (t.transaction_date||'') +'</td>' +
                '<td>'+ (t.transaction_type||'') +'</td>' +
                '<td>'+ badge +'</td>' +
                '<td class="text-end">'+ formatID(t.total || 0) +'</td>' +
                '<td class="text-end">'+ formatID(t.paid_amount || 0) +'</td>' +
                '<td class="text-end">'+ formatID(t.change_amount || 0) +'</td>' +
                '<td><a class="btn btn-sm btn-info" href="/transactions/'+(t.id||'')+'/details">Details</a> '+ approveBtn +'</td>' +
            '</tr>';
        });
        $tbody.html(html);
    }

    function fetchTransactions(page=1, q=''){
        $tbody.html('<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>');
        $pagination.html('');
        $.get(listUrl, { page: page, q: q })
        .done(function(res){
            var rows = [];
            if(res && res.data) rows = res.data;
            else if(Array.isArray(res)) rows = res;

            renderRows(rows);

            if(res && res.pagination){
                $pagination.html(res.pagination);
                enhancePagination($pagination);
            } else {
                $pagination.html('');
            }
        })
        .fail(function(xhr){
            console.error('fetchTransactions failed', xhr);
            $tbody.html('<tr><td colspan="10" class="text-center text-danger">Failed to load transactions</td></tr>');
        });
    }

    // debounce helper
    function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

    const doSearch = debounce(function(q){
        fetchTransactions(1, q);
    }, 300);

    // wire search input
    $searchBox.on('input', function(){ doSearch($(this).val().trim()); });

    // initial load
    fetchTransactions();

    // SUBMIT FORM handler
    $('#formAddTransaction').on('submit', function(e){
        e.preventDefault();
        var any = $container.find('.productSelect').toArray().some(function(s){ return s.value; });
        if(!any){
            Swal.fire({ icon:'warning', title:'Validation', text:'Please add at least one product', confirmButtonText:'OK', allowOutsideClick:false });
            return;
        }
        var fd = new FormData(this);
        fd.set('total', $grandInput.val());
        fd.set('change_amount', $changeInput.val());

        Swal.fire({ title:'Saving...', html:'Please wait', allowOutsideClick:false, didOpen: ()=> Swal.showLoading() });

        $.ajax({
            url: '{{ route("transactions.store") }}',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res){
                Swal.close();
                if(res && res.ok){
                    $('#modalAddTransaction').one('hidden.bs.modal', function(){
                        Swal.fire({ icon:'success', title:'Saved', text:'Transaction saved successfully', confirmButtonText:'OK' })
                        .then(function(){
                            $container.empty();
                            addRow();
                            updateGrand();
                            $paidAmount.val(0);
                            $changeDisplay.val('Rp 0');
                            $changeInput.val(0);
                            fetchTransactions();
                        });
                    });
                    $('#modalAddTransaction').modal('hide');
                } else {
                    Swal.fire({ icon:'error', title:'Save failed', text: (res && res.error) ? res.error : 'Unknown server error' });
                }
            },
            error: function(xhr){
                Swal.close();
                console.error(xhr);
                Swal.fire({ icon:'error', title:'Error', text: (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Save failed — check server logs' });
            }
        });
    });

    // Approve handler
    $(document).on('click', '.btn-approve', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: 'Approve purchase?',
            text: 'Approve this purchase and mark as completed?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'Cancel'
        }).then(function(result){
            if(!result.isConfirmed) return;
            Swal.fire({ title:'Approving...', allowOutsideClick:false, didOpen: ()=> Swal.showLoading() });

            $.post('/transactions/' + id + '/approve', {_token: $('meta[name="csrf-token"]').attr('content')})
            .done(function(res){
                Swal.close();
                if(res && res.ok){
                    Swal.fire({ icon:'success', title:'Approved', timer:1400, showConfirmButton:false }).then(function(){ fetchTransactions(); });
                } else {
                    Swal.fire({ icon:'error', title:'Failed', text: res && res.error ? res.error : 'Approve failed' });
                }
            }).fail(function(xhr){
                Swal.close();
                console.error(xhr);
                Swal.fire({ icon:'error', title:'Error', text:'Approve failed' });
            });
        });
    });

});
</script>
@endpush
