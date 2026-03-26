@extends('layouts.home')

<style>
    .swal-front {
        z-index: 20000 !important;
    }
</style>

@section('content')
    <div class="container-xxl container-p-y">

        <h4 class="fw-bold py-2 mb-4">Sales Stock Request Approval</h4>

        <div class="card">
            <div class="card-body">

                <div class="row mb-3 g-2">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" id="dateFrom" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" id="dateTo" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Sales</th>
                                <th>Warehouse</th>
                                <th>Items</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="requestTable">
                            @foreach ($requests as $group)
                                @php $first = $group->first(); @endphp

                                <tr>
                                    <td>{{ $first->created_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $first->user->name }}</td>
                                    <td>{{ $first->warehouse->warehouse_name }}</td>
                                    <td>{{ $group->count() }} item</td>

                                    <td>
                                        <button class="btn btn-primary btn-sm detailBtn"
                                            data-group="{{ $first->user_id }}_{{ $first->warehouse_id }}_{{ $first->created_at->format('Y-m-d H:i') }}">
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>

            </div>
        </div>

        <div class="modal fade" id="detailModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Request Detail</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <div id="detailBody"></div>

                        <hr>

                        <div class="text-end mt-3" id="approveWrapper" style="display:none;">
    <button class="btn btn-success" id="approveAllBtn">
        Approve All Remaining
    </button>
</div>
                    </div>

                </div>
            </div>
        </div>

        <form id="approveForm" method="POST" style="display:none;">
            @csrf
        </form>

        <form id="rejectForm" method="POST" style="display:none;">
            @csrf
        </form>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let selectedItems = [];
        let detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        const csrfToken = '{{ csrf_token() }}';

        document.querySelectorAll('.detailBtn').forEach(btn => {
            btn.onclick = function() {

                const group = this.dataset.group;

                fetch(`/warehouse/stock-requests/detail?group=${encodeURIComponent(group)}`)
                    .then(async res => {
                        const data = await res.json();

                        if (!res.ok) {
                            throw data;
                        }

                        return data;
                    })
                    .then(data => {

                        selectedItems = data;

                        renderItems();
                        detailModal.show();

                    })
                    .catch(err => {
                        Swal.fire({
                            icon:'error',
                            title:'Detail gagal dibuka',
                            text: err.message || 'Terjadi kesalahan'
                        });
                    });
            };
        });

        function renderItems() {

                let html = '';

                selectedItems.forEach(item => {

                    const rejected = item.status === 'rejected';
                    const approved = item.status === 'approved';

                    html += `
                <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${item.product.name}</strong><br>
                        Qty: ${item.quantity_requested}<br>
                        Note: ${item.note ?? '-'}
                    </div>

                    <div>
                        ${
                                approved
                                ? `<span class="badge bg-success">Approved</span>`
                                : rejected
                                ? `<span class="badge bg-danger">Rejected</span>`
                                : `<button class="btn btn-danger btn-sm rejectItemBtn"
                                                                data-id="${item.id}">
                                                                Reject
                                                        </button>`
                            }
                                        </div>
                                    </div>
                                    `;
                                        });

                document.getElementById('detailBody').innerHTML = html;

                const hasPending = selectedItems.some(item => item.status === 'pending');

                document.getElementById('approveWrapper').style.display =
                    hasPending ? 'block' : 'none';
                
                    bindRejectButtons();
        }

        function bindRejectButtons() {

            document.querySelectorAll('.rejectItemBtn').forEach(btn => {

                btn.onclick = function() {

                    const id = this.dataset.id;

                    detailModal.hide();

                    setTimeout(() => {

                        Swal.fire({
                            title: 'Alasan reject',
                            input: 'text',
                            inputPlaceholder: 'Masukkan alasan reject',
                            allowOutsideClick: false,
                            showCancelButton: true,
                            customClass: {
                                popup: 'swal-front'
                            },
                            didOpen: () => {
                                document.querySelector('.swal2-container').style.zIndex =
                                    '30000';
                            },
                            inputValidator: (value) => {
                                if (!value) return 'Note wajib diisi';
                            }

                        }).then((result) => {

                            if (result.isConfirmed) {

                                fetch(`/warehouse/stock-requests/${id}/reject`, {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            approval_note: result.value
                                        })
                                    })
                                    .then(async res => {
                                        const data = await res.json();

                                        if (!res.ok) {
                                            throw data;
                                        }

                                        return data;
                                    })
                                    .then(() => {

                                        const target = selectedItems.find(x => x.id == id);

                                        if (target) {
                                            target.status = 'rejected';
                                        }

                                        renderItems();

                                        detailModal.show();

                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Rejected',
                                            timer: 1200,
                                            showConfirmButton: false
                                        });

                                    });

                            } else {
                                detailModal.show();
                            }

                        });

                    }, 200);
                };

            });
        }

        document.getElementById('approveAllBtn').onclick = function() {

            const remain = selectedItems.filter(x => x.status !== 'rejected' && x.status !== 'approved');

            if (remain.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak ada item approve'
                });
                return;
            }

            detailModal.hide();

            setTimeout(() => {

                Swal.fire({
                    title: 'Approve semua item?',
                    icon: 'warning',
                    showCancelButton: true,
                    customClass: {
                        popup: 'swal-front'
                    },
                    didOpen: () => {
                        document.querySelector('.swal2-container').style.zIndex = '30000';
                    }

                }).then(res => {

                    if (!res.isConfirmed) {
                        detailModal.show();
                        return;
                    }

                    Promise.all(
                            remain.map(item =>
                                fetch(`/warehouse/stock-requests/${item.id}/approve`, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken,
                                        'Accept': 'application/json'
                                    }
                                })
                                .then(async response => {

                                    const data = await response.json();

                                    if (!response.ok) {
                                        throw data;
                                    }

                                    const target = selectedItems.find(x => x.id == item.id);

                                    if (target) {
                                        target.status = 'approved';
                                    }

                                    if (data.handover_id) {
                                        window.open(
                                            `/sales/handover/morning?handover_id=${data.handover_id}`,
                                            '_blank'
                                        );
                                    }

                                })
                            )
                        )
                        .then(() => {

                            renderItems();

                            detailModal.show();

                            Swal.fire({
                                icon: 'success',
                                title: 'Approve selesai',
                                timer: 1200,
                                showConfirmButton: false
                            });

                        })
                        .catch(err => {

                            detailModal.show();

                            Swal.fire({
                                icon: 'warning',
                                title: 'Tidak bisa approve',
                                text: err.message || 'Sales sudah punya 3 HDO aktif'
                            });

                        });

                });

            }, 200);
        };
    </script>
@endpush
