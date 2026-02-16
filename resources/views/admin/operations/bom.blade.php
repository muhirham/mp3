@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" />

    <div class="container-xxl container-p-y">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-1">BOM & Production</h4>
                <p class="text-muted mb-0">Kelola Bill of Material dan produksi</p>
            </div>
            <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#mdlBom">
                <i class="bx bx-plus"></i> Create BOM
            </button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table id="tblBom" class="table table-striped table-bordered table-hover w-100">
                    <thead class="table-light">
                        <tr>
                            <th>NO</th>
                            <th>BOM CODE</th>
                            <th>PRODUCT</th>
                            <th>VERSION</th>
                            <th>STATUS</th>
                            <th>CREATED BY</th>
                            <th>UPDATED BY</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- ============================= --}}
    {{-- MODAL CREATE / EDIT BOM --}}
    {{-- ============================= --}}

    <div class="modal fade" id="mdlBom" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form id="formBom">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" name="bom_id" id="bom_id">

                    <div class="modal-header">
                        <h5 class="modal-title">Create BOM</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        {{-- ================= STEP 1 ================= --}}
                        <div class="step step-1">

                            {{-- BASIC INFO --}}
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">BOM Code</label>
                                    <input type="text" name="bom_code" id="bom_code" class="form-control"
                                        value="{{ $nextCode }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Version</label>
                                    <input type="number" name="version" value="1" class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="is_active" class="form-select">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>

                            {{-- PRODUCT --}}
                            <div class="card border mb-4">
                                <div class="card-body">

                                    <label class="form-label fw-bold">Finished Product</label>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="product_mode" value="existing"
                                            checked>
                                        <label class="form-check-label">Use Existing Product</label>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="product_mode" value="new">
                                        <label class="form-check-label">Create New Product</label>
                                    </div>

                                    {{-- EXISTING --}}
                                    <div id="existingProductBox">
                                        <select name="product_id" class="form-select">
                                            <option value="">Choose product</option>
                                            @foreach ($products as $p)
                                                <option value="{{ $p->id }}">
                                                    {{ $p->name }}
                                                    (Stock: {{ number_format($p->stock) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- NEW --}}
                                    <div id="newProductBox" class="mt-3 d-none">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <input type="text" name="new_product_code" class="form-control"
                                                    placeholder="Product Code">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" name="new_product_name" class="form-control"
                                                    placeholder="Product Name">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" name="new_description" class="form-control"
                                                    placeholder="Description">
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            {{-- MATERIALS --}}
                            <div class="card border">
                                <div class="card-body">

                                    <div class="d-flex mb-3">
                                        <h6 class="fw-bold">Materials</h6>
                                        <button type="button" class="btn btn-sm btn-success ms-auto" id="btnAddRow">
                                            + Add Material
                                        </button>
                                    </div>

                                    <table class="table table-bordered" id="tblItems">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Material</th>
                                                <th width="150">Qty</th>
                                                <th width="80">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>

                                </div>
                            </div>

                        </div>

                        {{-- ================= STEP 2 ================= --}}
                        <div class="step step-2 d-none">

                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>Summary</h6>
                                    <p><strong>BOM Code:</strong> <span id="previewBomCode"></span></p>
                                    <p><strong>Product:</strong> <span id="previewProduct"></span></p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label>Production Batch</label>
                                <input type="number" id="previewBatch" class="form-control" min="1"
                                    value="1">
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h6>Material Calculation</h6>

                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Material</th>
                                                <th>Qty / Batch</th>
                                                <th>Total Qty</th>
                                                <th>Cost / Unit</th>
                                                <th>Total Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody id="previewMaterials"></tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-end">Grand Total</th>
                                                <th id="previewGrandTotal">0</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- FOOTER --}}
                    <div class="modal-footer">

                        <div class="footer-step-1">
                            <button type="button" class="btn btn-primary" id="btnNext">
                                Next
                            </button>
                        </div>

                        <div class="footer-step-2 d-none">
                            <button type="button" class="btn btn-secondary" id="btnBack">
                                Back
                            </button>

                            <button type="button" class="btn btn-primary" id="btnSaveOnly">
                                Save
                            </button>

                            <button type="button" class="btn btn-success" id="btnSaveProduce">
                                Save & Produce
                            </button>
                        </div>

                    </div>

                </form>
            </div>
        </div>
    </div>


    {{-- ============================= --}}
    {{-- MODAL DETAIL BOM --}}
    {{-- ============================= --}}
    <div class="modal fade" id="mdlBomDetail" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">BOM Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <strong>Product:</strong>
                        <span id="detailProduct"></span>
                    </div>

                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Material</th>
                                <th width="150">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="detailItems"></tbody>
                    </table>

                </div>

            </div>
        </div>
    </div>
    <div class="modal fade" id="mdlProduce">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formProduce">
                    <div class="modal-header">
                        <h5 class="modal-title">Execute Production</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="produce_bom_id">
                        <div class="mb-3">
                            <label>Batch Qty</label>
                            <input type="number" id="production_qty" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Produce</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(function() {

            /* =========================================================
                GLOBAL CONFIG
            ========================================================= */

            const dtUrl = @json(route('bom.datatable'));
            const storeUrl = @json(route('bom.store'));
            const csrf = $('meta[name="csrf-token"]').attr('content');

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrf
                }
            });

            /* =========================================================
                STEPPER
            ========================================================= */

            function goToStep(step) {
                $('.step').addClass('d-none');
                $('.step-' + step).removeClass('d-none');

                if (step === 1) {
                    $('.footer-step-1').removeClass('d-none');
                    $('.footer-step-2').addClass('d-none');
                } else {
                    $('.footer-step-1').addClass('d-none');
                    $('.footer-step-2').removeClass('d-none');
                }
            }


            $('#btnNext').on('click', function() {

                $('#previewBomCode').text($('#bom_code').val());

                let productText = $('select[name="product_id"] option:selected').text();
                $('#previewProduct').text(productText);

                generatePreview();
                goToStep(2);
            });


            $('#btnBack').on('click', function() {
                goToStep(1);
            });

            /* =========================================================
                DATATABLE INIT
            ========================================================= */

            const table = $('#tblBom').DataTable({
                processing: true,
                serverSide: true,
                dom: 'lrtip',
                ajax: {
                    url: dtUrl,
                    type: 'GET'
                },
                order: [
                    [1, 'desc']
                ],
                columns: [{
                        data: 'rownum',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'bom_code'
                    },
                    {
                        data: 'product_name'
                    },
                    {
                        data: 'version'
                    },
                    {
                        data: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'created_block',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'updated_block',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'actions',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            /* =========================================================
                PRODUCT MODE TOGGLE
            ========================================================= */

            $('input[name="product_mode"]').on('change', function() {
                if (this.value === 'new') {
                    $('#newProductBox').removeClass('d-none');
                    $('#existingProductBox').addClass('d-none');
                } else {
                    $('#newProductBox').addClass('d-none');
                    $('#existingProductBox').removeClass('d-none');
                }
            });

            /* =========================================================
                ADD / REMOVE MATERIAL ROW
            ========================================================= */

            $('#btnAddRow').on('click', function() {

                let row = `
        <tr>
            <td>
                <select name="materials[]" class="form-select">
                    <option value="">Choose material</option>
                    @foreach ($materials as $m)
                        <option value="{{ $m->id }}"
        data-cost="{{ $m->standard_cost ?? 0 }}">
    {{ $m->name }} (Stock: {{ number_format($m->stock) }})
</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="number" name="quantities[]" class="form-control" min="1" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger btnRemove">X</button>
            </td>
        </tr>
        `;

                $('#tblItems tbody').append(row);
            });

            $(document).on('click', '.btnRemove', function() {
                $(this).closest('tr').remove();
            });

            /* =========================================================
                FORM SUBMIT (CREATE / UPDATE)
            ========================================================= */

            $('#formBom').on('submit', function(e) {
                e.preventDefault();

                let id = $('#bom_id').val();
                let method = $('#formMethod').val();
                let url = method === 'PUT' ?
                    '/bom/' + id :
                    storeUrl;

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(res) {

                        $('#mdlBom').modal('hide');
                        table.ajax.reload(null, false);

                        Swal.fire({
                            icon: 'success',
                            title: res.success,
                            timer: 1500,
                            showConfirmButton: false
                        });

                        resetForm();
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi kesalahan'
                        });
                    }
                });
            });

            /* =========================================================
                SAVE & PRODUCE (STEP 2)
            ========================================================= */

            $('#btnSaveProduce').on('click', function() {

                let id = $('#bom_id').val();
                let method = $('#formMethod').val();
                let url = method === 'PUT' ?
                    '/bom/' + id :
                    storeUrl;

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: $('#formBom').serialize(),
                    success: function(res) {

                        let bomId = method === 'PUT' ? id : res.id;

                        $.post('/bom/' + bomId + '/produce', {
                            production_qty: $('#previewBatch').val()
                        }, function(res2) {

                            Swal.fire({
                                icon: 'success',
                                title: 'Saved & Produced'
                            });

                            $('#mdlBom').modal('hide');
                            table.ajax.reload(null, false);
                            goToStep(1);
                            resetForm();
                        });
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi kesalahan'
                        });
                    }
                });

            });


            /* =========================================================
                EDIT
            ========================================================= */

            $(document).on('click', '.js-edit', function() {

                const id = $(this).data('id');

                $.get('/bom/' + id + '/edit', function(res) {

                    resetForm();

                    $('#bom_id').val(res.id);
                    $('#formMethod').val('PUT');
                    $('.modal-title').text('Edit BOM');

                    $('#bom_code').val(res.bom_code);
                    $('input[name="version"]').val(res.version);
                    $('select[name="is_active"]').val(res.is_active ? 1 : 0);
                    $('select[name="product_id"]').val(res.product_id);

                    $('#tblItems tbody').empty();

                    res.items.forEach(item => {

                        let row = `
                <tr>
                    <td>
                        <select name="materials[]" class="form-select">
                            @foreach ($materials as $m)
                                <<option value="{{ $m->id }}"
        data-cost="{{ $m->standard_cost ?? 0 }}">
    {{ $m->name }} (Stock: {{ number_format($m->stock) }})
</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" name="quantities[]" class="form-control" min="1" required>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger btnRemove">X</button>
                    </td>
                </tr>
                `;

                        $('#tblItems tbody').append(row);

                        let lastRow = $('#tblItems tbody tr').last();
                        lastRow.find('select').val(item.material_id);
                        lastRow.find('input').val(item.quantity);
                    });

                    new bootstrap.Modal(document.getElementById('mdlBom')).show();
                });
            });

            /* =========================================================
                DELETE
            ========================================================= */

            $(document).on('click', '.js-del', function() {

                const id = $(this).data('id');

                Swal.fire({
                    title: 'Hapus BOM?',
                    text: 'Data tidak bisa dikembalikan!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {

                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: '/bom/' + id,
                        type: 'POST',
                        data: {
                            _method: 'DELETE'
                        },
                        success: function(res) {

                            table.ajax.reload(null, false);

                            Swal.fire({
                                icon: 'success',
                                title: res.success,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal hapus data'
                            });
                        }
                    });
                });
            });

            /* =========================================================
                DETAIL
            ========================================================= */

            $(document).on('click', '.js-detail', function() {
                let id = $(this).data('id');
                window.location.href = '/bom/' + id + '/page';
            });

            /* =========================================================
                PRODUCE (MODAL TERPISAH)
            ========================================================= */

            $(document).on('click', '.js-produce', function() {
                let id = $(this).data('id');
                $('#produce_bom_id').val(id);
                new bootstrap.Modal(document.getElementById('mdlProduce')).show();
            });

            $('#formProduce').on('submit', function(e) {
                e.preventDefault();

                let id = $('#produce_bom_id').val();

                $.post('/bom/' + id + '/produce', {
                    production_qty: $('#production_qty').val()
                }, function(res) {

                    Swal.fire({
                        icon: 'success',
                        title: res.success
                    });

                    $('#mdlProduce').modal('hide');
                });
            });

            /* =========================================================
                SEARCH (DEBOUNCE)
            ========================================================= */

            let debounceTimer;

            $('#globalSearch').on('keyup', function() {

                clearTimeout(debounceTimer);

                let value = this.value;

                debounceTimer = setTimeout(function() {
                    table.search(value).draw();
                }, 400);
            });

            $('#mdlBom').on('hidden.bs.modal', function() {
                goToStep(1);
            });


            /* =========================================================
                RESET FORM
            ========================================================= */

            function resetForm() {
                $('#formBom')[0].reset();
                $('#bom_id').val('');
                $('#formMethod').val('POST');
                $('#tblItems tbody').empty();
                $('.modal-title').text('Create BOM');
            }

            function generatePreview() {

                let batch = parseInt($('#previewBatch').val()) || 1;
                let grandTotal = 0;

                $('#previewMaterials').empty();

                $('#tblItems tbody tr').each(function() {

                    let selectedOption = $(this).find('select option:selected');

                    let materialName = selectedOption.text();
                    let qtyPerBatch = parseFloat($(this).find('input').val()) || 0;
                    let costPerUnit = parseFloat(selectedOption.data('cost')) || 0;

                    let totalQty = qtyPerBatch * batch;
                    let totalCost = totalQty * costPerUnit;

                    grandTotal += totalCost;

                    $('#previewMaterials').append(`
            <tr>
                <td>${materialName}</td>
                <td>${qtyPerBatch}</td>
                <td>${totalQty}</td>
                <td>${costPerUnit.toLocaleString()}</td>
                <td>${totalCost.toLocaleString()}</td>
            </tr>
        `);
                });

                $('#previewGrandTotal').text(grandTotal.toLocaleString());
            }


            $('#previewBatch').on('keyup change', function() {
                generatePreview();
            });

        });
    </script>
@endpush
