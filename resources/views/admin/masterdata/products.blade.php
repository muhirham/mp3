@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" />

    <style>
        .swal2-container {
            z-index: 20000 !important;
        }
        /* Matikan scroll horizontal */
        .card-body {
    overflow-x: auto !important;
}


        /* Table */
        #tblProducts {
            width: 100% !important;
            table-layout: fixed;
            font-size: 13px;
        }

        /* Semua cell 1 baris */
        #tblProducts th,
        #tblProducts td {
            white-space: nowrap;
            vertical-align: middle;
            padding: 6px 8px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Perbesar kolom penting */
        #tblProducts th:nth-child(3),
        #tblProducts td:nth-child(3) {
            width: 180px;
        }

        #tblProducts th:nth-child(8),
        #tblProducts td:nth-child(8) {
            width: 180px;
        }

        /* KECILIN KOLOM ANGKA */
        #tblProducts th:nth-child(9),
        #tblProducts td:nth-child(9),
        #tblProducts th:nth-child(10),
        #tblProducts td:nth-child(10),
        #tblProducts th:nth-child(11),
        #tblProducts td:nth-child(11) {
            width: 75px;
        }
    </style>




    <div class="container-xxl flex-grow-1 container-p-y">

        {{-- HEADER + BUTTON --}}
        <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
            <div>
                <h4 class="mb-1">Products</h4>
                <p class="mb-0 text-muted">Kelola daftar produk, stok pusat, kategori, dan supplier.</p>
            </div>
            <div class="ms-auto">
                <button class="btn btn-primary d-flex align-items-center gap-1" data-bs-toggle="modal"
                    data-bs-target="#mdlProduct" id="btnShowAdd">
                    <i class="bx bx-plus"></i>
                    <span>Add Product</span>
                </button>
            </div>
        </div>

        {{-- FILTER BAR --}}
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-center">

                            {{-- Title kecil di kiri --}}
                            <div class="col-12 col-md-2 col-lg-1">
                                <span class="text-muted text-uppercase small fw-semibold">Filter</span>
                            </div>

                            {{-- Show --}}
                            <div class="col-6 col-md-2 col-lg-1">
                                <label class="form-label mb-1 text-muted small d-block">Show</label>
                                <select id="pageLength" class="form-select form-select-sm">
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>

                            {{-- Category --}}
                            <div class="col-6 col-md-4 col-lg-4">
                                <label class="form-label mb-1 text-muted small d-block">Category</label>
                                <select id="filterCategory" class="form-select form-select-sm">
                                    <option value="">All categories</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->category_name }}">{{ $c->category_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Supplier --}}
                            <div class="col-12 col-md-4 col-lg-4">
                                <label class="form-label mb-1 text-muted small d-block">Supplier</label>
                                <select id="filterSupplier" class="form-select form-select-sm">
                                    <option value="">All suppliers</option>
                                    @foreach ($suppliers as $s)
                                        <option value="{{ $s->name }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>


        {{-- TABEL PRODUK --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tblProducts" class="table table-striped table-hover align-middle mb-0 table-bordered w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px">NO</th>
                                <th style="width: 80px">CODE</th>
                                <th>PRODUCT NAME</th>
                                <th style="width: 110px">CATEGORY</th>
                                <th style="width:100px">TYPE</th>
                                <th style="width: 70px">UOM</th>
                                <th style="width: 140px">SUPPLIER</th>
                                <th>DESCRIPTION</th>
                                <th class="text-end" style="width: 70px">STOCK</th>
                                <th class="text-end" style="width: 90px">MIN STOCK</th>
                                <th style="width: 70px">STATUS</th>
                                <th class="text-end" style="width: 100px">PURCHASING</th>
                                <th class="text-end" style="width: 100px">HPP</th>
                                <th class="text-end" style="width: 100px">SELLING</th>
                                <th style="width: 110px">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- MODAL ADD / EDIT PRODUCT --}}
    <div class="modal fade" id="mdlProduct" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="modalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form id="formProduct" class="modal-body">
                    @csrf
                    <input type="hidden" name="_method" id="method" value="POST">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Product Code <span class="text-danger">*</span></label>
                            <input type="text" name="product_code" id="product_code" class="form-control"
                                value="{{ $nextProductCode }}" data-default="{{ $nextProductCode }}" required>
                            <small class="text-muted">Bisa diubah, tidak boleh duplikat.</small>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">— Choose —</option>
                                @foreach ($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->category_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Product Type</label>
                            <select name="product_type" id="product_type" class="form-select" required>
                                <option value="normal">Normal</option>
                                <option value="material">Material</option>
                                <option value="BOM">BOM</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input"
                                    value="1" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Package / Satuan</label>
                            <select name="package_id" id="package_id" class="form-select" required>
                                <option value="">— None —</option>
                                @foreach ($packages as $p)
                                    <option value="{{ $p->id }}">{{ $p->package_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="form-select" required>
                                <option value="">— None —</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="description" rows="3" class="form-control" required placeholder="Optional"></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Purchasing Price</label>
                            <input type="number" name="purchasing_price" id="purchasing_price" class="form-control"
                                value="0" min="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Standard Cost (HPP)</label>
                            <input type="number" name="standard_cost" id="standard_cost" class="form-control"
                                min="0" step="0.01">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Selling Price</label>
                            <input type="number" name="selling_price" id="selling_price" class="form-control"
                                value="0" min="0">
                            <small class="text-muted small d-none" id="priceEditNote">
                                Harga beli &amp; harga jual tidak bisa diubah di sini.
                                Gunakan menu <strong>Adjustment</strong> untuk mengubah harga.
                            </small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Min Stock</label>
                            <input type="number" name="stock_minimum" id="stock_minimum" class="form-control" required
                                min="0">
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnSubmit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(function() {
            const baseUrl = @json(url('products'));
            const dtUrl = @json(route('products.datatable'));
            const nextCodeUrl = @json(route('products.next_code'));
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrf
                }
            });

            const table = $('#tblProducts').DataTable({
                processing: true,
                serverSide: true,
                lengthChange: false,
                dom: 'rt<"d-flex justify-content-between align-items-center p-2"ip>',
                ajax: {
                    url: dtUrl,
                    type: 'GET',
                    data: function(d) {
                        d.category = $('#filterCategory').val();
                        d.supplier = $('#filterSupplier').val();
                    }
                },
                order: [
                    [1, 'asc']
                ],
                pagingType: "simple_numbers",
                autoWidth: false,
                responsive: false,

                columns: [{
                        data: 'rownum',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'product_code'
                    },
                    {
                        data: 'name'
                    }, // kolom 3 (PRODUCT NAME) → ikut max-width CSS
                    {
                        data: 'category'
                    },
                    {
                        data: 'product_type'
                    },
                    {
                        data: 'package'
                    },
                    {
                        data: 'supplier'
                    },
                    {
                        data: 'description'
                    }, // kolom 7 (DESCRIPTION) → ikut max-width CSS
                    {
                        data: 'stock',
                        className: 'text-end'
                    },
                    {
                        data: 'min_stock',
                        className: 'text-end'
                    },
                    {
                        data: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'purchasing_price',
                        className: 'text-end'
                    },
                    {
                        data: 'standard_cost',
                        className: 'text-end'
                    },
                    {
                        data: 'selling_price',
                        className: 'text-end'
                    },
                    {
                        data: 'actions',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // page length
            $('#pageLength').on('change', function() {
                table.page.len(parseInt(this.value || 10, 10)).draw();
            });

            // DROPDOWN FILTER: reload datatable
            $('#filterCategory, #filterSupplier').on('change', function() {
                table.ajax.reload();
            });

            // GLOBAL NAVBAR SEARCH (input id="globalSearch" di navbar)
            const $globalSearch = $('#globalSearch');
            if ($globalSearch.length) {
                $globalSearch.off('.products').on('keyup.products change.products', function() {
                    table.search(this.value).draw();
                });
            }

            $('#product_code').on('input', function() {
                this.value = this.value.toUpperCase();
            });

            // ==== MODE ADD PRODUCT ====
            $('#btnShowAdd').on('click', function() {
                $('#modalTitle').text('Add Product');
                $('#formProduct').attr('action', baseUrl);
                $('#method').val('POST');
                $('#btnSubmit').text('Submit');
                $('#formProduct').trigger('reset');
                $('#category_id, #package_id, #supplier_id').val('');

                $('#purchasing_price, #selling_price').prop('readonly', false);
                $('#priceEditNote').addClass('d-none');

                $.get(nextCodeUrl, function(res) {
                    $('#product_code').val(res?.next_code || $('#product_code').data('default'));
                });
            });

            // SUBMIT FORM (add / update)
            $('#formProduct').on('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                fd.set('_method', $('#method').val());

                $.ajax({
                    url: $(this).attr('action') || baseUrl,
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        $('#mdlProduct').modal('hide');
                        table.ajax.reload(null, false);
                        Swal.fire({
                            title: res.success || 'Saved',
                            icon: 'success',
                            timer: 1300,
                            showConfirmButton: false
                        });
                    },
                    error: function(xhr) {
                        let msg = 'Something went wrong!';
                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        } else if (xhr.responseJSON?.error) {
                            msg = xhr.responseJSON.error;
                        }
                        Swal.fire({
                            title: 'Error!',
                            html: msg,
                            icon: 'error'
                        });
                    }
                });
            });

            // ==== MODE EDIT PRODUCT ====
            $(document).on('click', '.js-edit', function() {
                const d = $(this).data();

                $('#modalTitle').text('Edit Product');
                $('#formProduct').attr('action', baseUrl + '/' + d.id);
                $('#method').val('PUT');
                $('#btnSubmit').text('Update');

                $('#product_code').val(d.product_code);
                $('#name').val(d.name);
                $('#category_id').val(d.category_id || '');
                $('#package_id').val(d.package_id || '');
                $('#supplier_id').val(d.supplier_id || '');
                $('#description').val(d.description || '');
                $('#purchasing_price').val(d.purchasing_price);
                $('#standard_cost').val(d.standard_cost || '');
                $('#selling_price').val(d.selling_price);
                $('#stock_minimum').val(d.stock_minimum || '');

                $('#purchasing_price, #selling_price').prop('readonly', true);
                $('#priceEditNote').removeClass('d-none');

                $('#product_type').val(d.product_type || 'normal');
                $('#is_active').prop('checked', d.is_active == 1);

                $('#mdlProduct').modal('show');
            });

            // DELETE PRODUCT
            $(document).on('click', '.js-del', function() {
                const id = $(this).data('id');

                Swal.fire({
                    title: 'Delete product?',
                    text: 'Tindakan ini tidak bisa dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then((res) => {
                    if (!res.isConfirmed) return;

                    $.post(baseUrl + '/' + id, {
                        _method: 'DELETE'
                    }, function(r) {
                        table.ajax.reload(null, false);
                        Swal.fire('Deleted!', r.success || 'Product deleted.', 'success');
                    }).fail(function() {
                        Swal.fire('Error!', 'Could not delete product!', 'error');
                    });
                });
            });
        });
    </script>
@endpush
