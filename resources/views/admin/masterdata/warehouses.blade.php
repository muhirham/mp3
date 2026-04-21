    @extends('layouts.home')
    @section('title', 'Warehouses')

    @section('content')
        @push('styles')
            <style>
                .swal2-container {
                    z-index: 20000 !important;
                }
            </style>
            <meta name="csrf-token" content="{{ csrf_token() }}">
        @endpush

        <div class="container-xxl flex-grow-1 container-p-y">
            {{-- Header ala stock.blade --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="fw-bold mb-1">Warehouses</h4>
                    <p class="mb-0 text-muted small">Manage warehouse locations and depot details.</p>
                </div>
                <div class="d-flex gap-2">
                    @if(auth()->user()->hasPermission('warehouse.create'))
                        <a href="{{ route('warehouses.export.seeder') }}" class="btn btn-outline-secondary">
                            <i class="bx bx-export me-1"></i> Export Seeder
                        </a>
                        <button class="btn btn-primary px-3 shadow-none" id="btnShowAdd">
                            <i class="bx bx-plus me-1"></i> Add Warehouse
                        </button>
                    @endif
                </div>
            </div>

            {{-- Filters Bar --}}
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Show</label>
                            <select id="pageSize" class="form-select">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 table-bordered w-100"
                            id="tblWarehouses">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px">NO</th>
                                    <th style="width: 100px">CODE</th>
                                    <th>NAME</th>
                                    <th>ADDRESS</th>
                                    <th>NOTE</th>
                                    <th style="width: 120px">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <small id="pageInfo" class="text-muted"></small>
                        <nav>
                            <ul id="pager" class="pagination pagination-sm mb-0"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        {{-- deps --}}
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <style>
            /* Sync container agar tidak "Full" (pakai standard xxl) */

            /* Table Styling - Clean & Consistent */
            #tblWarehouses {
                width: 100% !important;
                border-collapse: collapse;
                background: #fff;
            }

            #tblWarehouses thead th {
                background-color: #f9fafb !important;
                color: #4b5563;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                padding: 12px 10px;
                border-bottom: 2px solid #f3f4f6;
                white-space: nowrap;
            }

            #tblWarehouses tbody td {
                padding: 10px;
                font-size: 13px;
                color: #334155;
                vertical-align: middle;
                border-bottom: 1px solid #f3f4f6;
                white-space: nowrap;
            }

            #tblWarehouses tbody tr:hover {
                background-color: #fbfbfc;
            }

            .swal2-container { z-index: 20000 !important; }
            .swal2-popup .form-control { background-color: #fff; color: #111827; }
        </style>

        <script>
            window.IS_WAREHOUSE_USER = @json(auth()->user()->hasRole('warehouse'));
            window.MY_WAREHOUSE_ID = @json(auth()->user()->warehouse_id);
        </script>

        <script>
            $(function() {
                const baseUrl = @json(url('warehouses'));
                const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const tbody = $('#tblWarehouses tbody');
                const pager = $('#pager');

                const Alert = Swal.mixin({
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-outline-secondary ms-2'
                    }
                });
                const confirmBx = (t, m, c = 'Yes') => Alert.fire({
                    icon: 'question',
                    title: t,
                    text: m,
                    showCancelButton: true,
                    confirmButtonText: c
                });
                const okBx = (t = 'Success', m = '') => Alert.fire({
                    icon: 'success',
                    title: t,
                    text: m
                });
                const errBx = (t = 'Error', m = '') => Alert.fire({
                    icon: 'error',
                    title: t,
                    html: m
                });
                const warnBx = (t = 'Validation', m = '') => Alert.fire({
                    icon: 'warning',
                    title: t,
                    html: m
                });

                // Data awal dari controller
                let warehouses = @json($warehouses instanceof \Illuminate\Pagination\AbstractPaginator ? $warehouses->items() : $warehouses) || [];

                // kode default dari controller (buat fallback)
                const initialNextCode = @json($nextCode ?? 'WH-001');

                let pageSize = 10;
                let currentPage = 1;
                let keyword = '';

                function filtered() {
                    const q = (keyword || '').toLowerCase().trim();
                    if (!q) return warehouses;
                    return warehouses.filter(w => [w.warehouse_code, w.warehouse_name, w.address, w.note]
                        .join(' ')
                        .toLowerCase()
                        .includes(q)
                    );
                }

                function totalPages() {
                    return Math.max(1, Math.ceil(filtered().length / pageSize));
                }

                function clamp(p) {
                    return Math.min(Math.max(1, p), totalPages());
                }

                function rowHTML(w, no) {
                    const canEdit =
                        @json(auth()->user()->hasPermission('warehouse.update')) &&
                        (
                            !window.IS_WAREHOUSE_USER ||
                            (window.IS_WAREHOUSE_USER && w.id === window.MY_WAREHOUSE_ID)
                        );
                    const canDel = (!window.IS_WAREHOUSE_USER && @json(auth()->user()->hasPermission('warehouse.delete')));

                    return `
                    <tr data-id="${w.id}">
                        <td class="text-center small" style="width: 50px;">${no}</td>
                        <td>${w.warehouse_code || '-'}</td>
                        <td class="fw-bold">${w.warehouse_name}</td>
                        <td><span class="small">${w.address || '-'}</span></td>
                        <td><span class="small">${w.note || '-'}</span></td>
                        <td class="text-center" style="width: 100px;">
                            <div class="d-flex gap-1 justify-content-center">
                            ${
                                canEdit
                                    ? `<button class="btn btn-outline-secondary btn-sm p-1 border-0 js-edit" data-item='${JSON.stringify(w)}'><i class="bx bx-edit-alt fs-5"></i></button>`
                                    : `<span class="badge bg-label-secondary">Read Only</span>`
                            }
                            ${
                                canDel
                                    ? `<button class="btn btn-outline-danger btn-sm p-1 border-0 js-del"><i class="bx bx-trash fs-5"></i></button>`
                                    : ''
                            }
                            </div>
                        </td>
                    </tr>`;
                }

                function render() {
                    tbody.empty();
                    const list = filtered();
                    const tp = totalPages();
                    currentPage = Math.min(currentPage, tp);

                    const startIdx = (currentPage - 1) * pageSize;
                    const slice = list.slice(startIdx, startIdx + pageSize);

                    slice.forEach((w, i) => tbody.append(rowHTML(w, startIdx + i + 1)));

                    if (slice.length === 0) {
                        tbody.append(
                            `<tr><td colspan="6" class="text-center py-4 text-muted">No warehouses found</td></tr>`);
                    }

                    // pager info
                    const from = list.length ? startIdx + 1 : 0;
                    const to = Math.min(startIdx + pageSize, list.length);
                    $('#pageInfo').text(`Showing ${from} to ${to} of ${list.length} results`);

                    // pager list
                    pager.empty();
                    pager.append(`<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" data-goto="${currentPage - 1}">«</a>
                    </li>`);

                    const start = Math.max(1, currentPage - 2);
                    const end = Math.min(tp, currentPage + 2);

                    for (let p = start; p <= end; p++) {
                        pager.append(`<li class="page-item ${p === currentPage ? 'active' : ''}">
                            <a class="page-link" data-goto="${p}">${p}</a>
                        </li>`);
                    }

                    pager.append(`<li class="page-item ${currentPage === tp ? 'disabled' : ''}">
                        <a class="page-link" data-goto="${currentPage + 1}">»</a>
                    </li>`);
                }

                function goto(p) {
                    currentPage = clamp(p);
                    render();
                }

                // hitung kode berikutnya dari data yg ada (fallback kalo user sudah tambah banyak)
                function suggestNextCode() {
                    if (!warehouses.length) return initialNextCode || 'WH-001';
                    const maxId = Math.max.apply(null, warehouses.map(w => w.id || 0));
                    const num = maxId + 1;
                    return 'WH-' + String(num).padStart(3, '0');
                }

                // init
                render();

                // Search & PageSize
                $('#globalSearch').on('input', function() {
                    keyword = this.value || '';
                    currentPage = 1;
                    render();
                });
                $('#pageSize').on('change', function() {
                    pageSize = parseInt(this.value, 10);
                    currentPage = 1;
                    render();
                });

                // Pager click
                pager.on('click', '.page-link', function(e) {
                    e.preventDefault();
                    const p = parseInt(this.dataset.goto, 10);
                    if (!isNaN(p)) {
                        currentPage = clamp(p);
                        render();
                    }
                });

                // DELETE
                tbody.on('click', '.js-del', async function(e) {
                    if (window.IS_WAREHOUSE_USER) return;
                    const row = $(this).closest('tr');
                    const id = row.data('id');
                    const wh = warehouses.find(x => x.id == id);

                    const ok = await confirmBx('Delete?', `Delete ${wh?.warehouse_name || 'warehouse'}?`,
                        'Delete');
                    if (!ok.isConfirmed) return;

                    try {
                        const res = await fetch(`${baseUrl}/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': CSRF
                            }
                        });
                        if (!res.ok) throw new Error('Failed: ' + res.status);
                        warehouses = warehouses.filter(x => x.id != id);
                        render();
                        await okBx('Deleted', 'Warehouse removed.');
                    } catch (err) {
                        errBx('Error', err.message);
                    }
                });

                // MODAL EDIT (Ganti flip card)
                tbody.on('click', '.js-edit', function() {
                    const d = $(this).data('item');

                    Alert.fire({
                        title: 'Edit Warehouse',
                        html: `
                        <div class="text-start">
                            <label class="form-label mb-1 small text-muted">Warehouse Code</label>
                            <input id="se_code" class="form-control mb-2" value="${d.warehouse_code || ''}">
                            <label class="form-label mb-1 small text-muted">Warehouse Name</label>
                            <input id="se_name" class="form-control mb-2" value="${d.warehouse_name || ''}">
                            <label class="form-label mb-1 small text-muted">Address</label>
                            <input id="se_addr" class="form-control mb-2" value="${d.address || ''}">
                            <label class="form-label mb-1 small text-muted">Note</label>
                            <input id="se_note" class="form-control" value="${d.note || ''}">
                        </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Update',
                        preConfirm: () => {
                            const name = $('#se_name').val().trim();
                            if (!name) return Alert.showValidationMessage('Name required');
                            return {
                                warehouse_code: $('#se_code').val().trim(),
                                warehouse_name: name,
                                address: $('#se_addr').val().trim(),
                                note: $('#se_note').val().trim(),
                            };
                        }
                    }).then(async r => {
                        if (!r.isConfirmed) return;
                        try {
                            const res = await fetch(`${baseUrl}/${d.id}`, {
                                method: 'PUT',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF
                                },
                                body: JSON.stringify(r.value)
                            });

                            if (res.status === 422) {
                                const j = await res.json();
                                return warnBx('Validation', Object.values(j.errors || {}).flat()
                                    .join('<br>'));
                            }
                            if (!res.ok) throw new Error('Failed: ' + res.status);

                            const j = await res.json();
                            warehouses = warehouses.map(x => x.id == d.id ? j.row : x);
                            render();
                            await okBx('Saved', 'Warehouse updated.');
                        } catch (err) {
                            errBx('Error', err.message);
                        }
                    });
                });

                // CREATE (Ganti Add Card)
                $('#btnShowAdd').on('click', function() {
                    if (window.IS_WAREHOUSE_USER) return;
                    const defaultCode = suggestNextCode();

                    Alert.fire({
                        title: 'Add Warehouse',
                        html: `
                        <div class="text-start">
                            <label class="form-label mb-1 small text-muted">Warehouse Code (auto / manual)</label>
                            <input id="sw_code" class="form-control mb-2" value="${defaultCode}">
                            <label class="form-label mb-1 small text-muted">Warehouse Name</label>
                            <input id="sw_name" class="form-control mb-2" placeholder="e.g. Depo Padang">
                            <label class="form-label mb-1 small text-muted">Address</label>
                            <input id="sw_addr" class="form-control mb-2" placeholder="Street, City...">
                            <label class="form-label mb-1 small text-muted">Note</label>
                            <input id="sw_note" class="form-control" placeholder="...">
                        </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Save',
                        preConfirm: () => {
                            const name = $('#sw_name').val().trim();
                            if (!name) return Alert.showValidationMessage('Name required');
                            return {
                                warehouse_code: $('#sw_code').val().trim() || null,
                                warehouse_name: name,
                                address: $('#sw_addr').val().trim(),
                                note: $('#sw_note').val().trim(),
                            };
                        }
                    }).then(async r => {
                        if (!r.isConfirmed) return;
                        try {
                            const res = await fetch(baseUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF
                                },
                                body: JSON.stringify(r.value)
                            });

                            if (res.status === 422) {
                                const j = await res.json();
                                return warnBx('Validation', Object.values(j.errors || {}).flat()
                                    .join('<br>'));
                            }
                            if (!res.ok) throw new Error('Failed: ' + res.status);

                            const j = await res.json();
                            warehouses.push(j.row);
                            keyword = '';
                            $('#globalSearch').val('');
                            currentPage = Math.ceil(warehouses.length / pageSize);
                            render();
                            await okBx('Added', 'New warehouse created.');
                        } catch (err) {
                            errBx('Error', err.message);
                        }
                    });
                });

                console.log('[Warehouses] Ready: client paging + CRUD via fetch (JSON).');
            });
        </script>
    @endsection
