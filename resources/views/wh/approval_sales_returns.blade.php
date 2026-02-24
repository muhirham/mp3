@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="container-xxl flex-grow-1 container-p-y">

        <div class="mb-3">
            <h4 class="mb-0">Approval Sales Return</h4>
            <small class="text-muted">Daftar retur berdasarkan HDO</small>
        </div>

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body">
                <form id="filterForm" class="row g-3">

                    <div class="col-md-3">
                        <label>Dari Tanggal</label>
                        <input type="date" name="from" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="to" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    @if(!empty($canSwitchWarehouse) && $canSwitchWarehouse)
                        <div class="col-md-3">
                            <label>Warehouse</label>
                            <select name="warehouse_id" class="form-select">
                                <option value="">Semua Warehouse</option>
                                    @foreach ($warehouses as $wh)
                                        <option value="{{ $wh->id }}">
                                            {{ $wh->warehouse_name }}
                                        </option>
                                    @endforeach
                            </select>
                        </div>
                    @endif

                </form>
            </div>
        </div>
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th style="width:140px;">HDO</th>
                                <th>Sales</th>
                                <th style="width:120px;">Total Item</th>
                                <th style="width:120px;">Status</th>
                                <th style="width:180px;">Dibuat</th>
                                <th style="width:110px;" class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>

                        <tbody id="returnTableBody">
                            @forelse($returns as $handoverId => $data)
                                <tr>
                                    <td class="fw-semibold text-nowrap">
                                        {{ $data['handover']->code ?? '-' }}
                                    </td>

                                    <td>{{ $data['sales']->name }}</td>

                                    <td>
                                        <span class="badge bg-label-primary">
                                            {{ $data['items']->count() }} item
                                        </span>
                                    </td>

                                    <td>
                                        @if ($data['status'] == 'pending')
                                            <span class="badge bg-label-warning">Pending</span>
                                        @elseif($data['status'] == 'rejected')
                                            <span class="badge bg-label-danger">Rejected</span>
                                        @else
                                            <span class="badge bg-label-success">Approved</span>
                                        @endif
                                    </td>

                                    <td class="text-muted text-nowrap">
                                        {{ $data['date']->format('d M Y H:i') }}
                                    </td>

                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary"
                                            onclick="openDetailModal('{{ $handoverId }}')">
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Tidak ada data return
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= MODAL DETAIL ================= --}}
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Return HDO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailBody"></div>
            </div>
        </div>
    </div>

    {{-- ================= MODAL REJECT ================= --}}
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="rejectForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Alasan Reject</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="reject_reason" class="form-control" required placeholder="Masukkan alasan reject"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">
                            Confirm Reject
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        function openDetailModal(handoverId) {


            fetch("{{ route('warehouse.returns.hdo.details', ':id') }}"
                    .replace(':id', handoverId))
                .then(res => res.json())
                .then(data => {

                    let body = document.getElementById('detailBody');
                    body.innerHTML = '';

                    if (data.length === 0) {
                        body.innerHTML = '<div class="text-center text-muted py-4">Tidak ada detail</div>';
                        new bootstrap.Modal(document.getElementById('detailModal')).show();
                        return;
                    }

                    let html = `
                    <button class="btn btn-success mb-3"
                        onclick="approveAll(${handoverId})">
                        Approve All
                    </button>
                    <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Kondisi</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Tanggal</th>
                                <th width="130">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                    `;

                    data.forEach(item => {

                        let badge = '';

                        if (item.status === 'pending') {
                            badge = '<span class="badge bg-warning">Pending</span>';
                        } else if (item.status === 'rejected') {
                            badge = '<span class="badge bg-danger">Rejected</span>';
                        } else {
                            badge = '<span class="badge bg-success">Received</span>';
                        }

                        html += `
            <tr>
                <td>${item.product.name}</td>
                <td>${item.quantity}</td>
                <td>${item.condition}</td>
                <td>${item.reason ?? '-'}</td>
                <td>${badge}</td>
                <td>${item.approved_by_user?.name ?? '-'}</td>
                <td>${new Date(item.created_at).toLocaleString()}</td>
                <td>
                    ${item.status==='pending' ? `
                                                            <button class="btn btn-danger btn-sm"
                                                                onclick="openRejectModal(${item.id})">
                                                                Reject
                                                            </button>
                                                        ` : '-'}
                </td>
            </tr>
            `;
                    });

                    html += `</tbody></table></div>`;

                    body.innerHTML = html;

                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                });
        }

        function openRejectModal(id) {
            let form = document.getElementById('rejectForm');
            form.action = '/warehouse/returns/' + id + '/reject';
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        function approveAll(handoverId) {

            // 🔥 tutup modal dulu
            const modalEl = document.getElementById('detailModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            modalInstance.hide();

            Swal.fire({
                title: 'Approve semua item?',
                text: "Semua item pending akan disetujui.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, approve!',
                cancelButtonText: 'Batal'
            }).then((result) => {

                if (result.isConfirmed) {

                    Swal.fire({
                        title: 'Memproses...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(`/warehouse/returns/${handoverId}/approve-all`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        })
                        .then(res => {
                            if (!res.ok) throw new Error();
                            return res.text();
                        })
                        .then(() => {

                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: 'Semua item berhasil di-approve.',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });

                        })
                        .catch(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Terjadi kesalahan.'
                            });
                        });
                }
            });
        }
    </script>

    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: '{{ session('success') }}',
                timer: 2000,
                showConfirmButton: false
            });
        </script>
    @endif
    <script>
        const filterForm = document.getElementById('filterForm');
        const inputs = filterForm.querySelectorAll('input, select');

        inputs.forEach(input => {
            input.addEventListener('change', loadFilteredData);
        });

        function loadFilteredData() {

            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData).toString();

            fetch(`/warehouse/returns/filter?${params}`)
                .then(res => res.json())
                .then(data => {

                    const tbody = document.getElementById('returnTableBody');
                    tbody.innerHTML = '';

                    if (!data.length) {
                        tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Tidak ada data return
                        </td>
                    </tr>
                `;
                        return;
                    }

                    data.forEach(item => {

                        let badge = '';

                        if (item.status === 'pending') {
                            badge = '<span class="badge bg-warning text-dark">Pending</span>';
                        } else if (item.status === 'rejected') {
                            badge = '<span class="badge bg-danger">Rejected</span>';
                        } else {
                            badge = '<span class="badge bg-success">Approved</span>';
                        }

                        tbody.innerHTML += `
                    <tr>
                        <td><strong>${item.handover_code}</strong></td>
                        <td>${item.sales_name}</td>
                        <td>${item.total_items} items</td>
                        <td>${badge}</td>
                        <td>${item.date}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary w-100"
                                onclick="openDetailModal(${item.handover_id})">
                                Detail
                            </button>
                        </td>
                    </tr>
                `;
                    });

                });
        }
    </script>
@endpush
