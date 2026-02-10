@extends('layouts.home')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div class="container-xxl flex-grow-1 container-p-y">

        {{-- Header + Search + Button --}}
        <div class="d-flex align-items-center mb-3">
            <h4 class="mb-0 fw-bold">Company</h4>

            <div class="ms-auto d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 260px;">
                    <span class="input-group-text">
                        <i class="bx bx-search"></i>
                    </span>
                    <input type="text" id="company-search" class="form-control" placeholder="Search company...">
                </div>

                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#create-company-modal">
                    + New Company
                </button>
            </div>
        </div>

        {{-- Flash message hooks for SweetAlert2 --}}
        @if (session('success'))
            <div id="company-success-message" data-message="{{ session('success') }}"></div>
        @endif

        @if (session('error'))
            <div id="company-error-message" data-message="{{ session('error') }}"></div>
        @endif

        @if ($errors->any())
            <div id="company-error-messages" data-errors='@json($errors->all())'></div>
        @endif

        {{-- Tabel Company --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Daftar Company</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle" id="companies-table">
                        <thead>
                            <tr>
                                <th>Logo</th>
                                <th>Nama</th>
                                <th>Alamat</th>
                                <th>Kontak</th>
                                <th>Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($companies as $company)
                                <tr>
                                    <td>
                                        @php
                                            $logo = $company->logo_small_path ?: $company->logo_path;
                                        @endphp

                                        @if ($logo)
                                            @if (file_exists(storage_path('app/public/' . $logo)))
                                                <img src="{{ asset('storage/' . $logo) }}" alt="logo"
                                                    style="height:32px;">
                                            @elseif (file_exists(public_path($logo)))
                                                <img src="{{ asset($logo) }}" alt="logo" style="height:32px;">
                                            @endif
                                        @endif

                                    </td>
                                    <td>
                                        <strong>{{ $company->name }}</strong><br>
                                        @if ($company->short_name)
                                            <small class="text-muted">{{ $company->short_name }}</small><br>
                                        @endif
                                        @if ($company->code)
                                            <small class="text-muted">Code: {{ $company->code }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($company->address)
                                            <div>{{ $company->address }}</div>
                                        @endif
                                        @if ($company->city || $company->province)
                                            <small class="text-muted">
                                                {{ $company->city }}
                                                @if ($company->city && $company->province)
                                                    -
                                                @endif
                                                {{ $company->province }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($company->phone)
                                            <div>Tel: {{ $company->phone }}</div>
                                        @endif
                                        @if ($company->email)
                                            <div>{{ $company->email }}</div>
                                        @endif
                                        @if ($company->website)
                                            <small class="text-muted">{{ $company->website }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($company->is_default)
                                            <span class="badge bg-primary mb-1">Default</span><br>
                                        @endif

                                        @if ($company->is_active)
                                            <span class="badge bg-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#edit-company-{{ $company->id }}">
                                                Edit
                                            </button>

                                            <form action="{{ route('companies.destroy', $company) }}" method="POST"
                                                class="form-delete-company" data-company-name="{{ $company->name }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Del
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Modal Edit Company --}}
                                <div class="modal fade" id="edit-company-{{ $company->id }}" tabindex="-1"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Company: {{ $company->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="{{ route('companies.update', $company) }}" method="POST"
                                                enctype="multipart/form-data">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Nama Company</label>
                                                                <input type="text" name="name" class="form-control"
                                                                    required value="{{ old('name', $company->name) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Nama Legal</label>
                                                                <input type="text" name="legal_name" class="form-control"
                                                                    value="{{ old('legal_name', $company->legal_name) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Singkatan</label>
                                                                <input type="text" name="short_name" class="form-control"
                                                                    value="{{ old('short_name', $company->short_name) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Kode</label>
                                                                <input type="text" name="code" class="form-control"
                                                                    value="{{ old('code', $company->code) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Alamat</label>
                                                                <textarea name="address" class="form-control" rows="2">{{ old('address', $company->address) }}</textarea>
                                                            </div>

                                                            <div class="mb-3 row">
                                                                <div class="col-6">
                                                                    <label class="form-label">Kota</label>
                                                                    <input type="text" name="city"
                                                                        class="form-control"
                                                                        value="{{ old('city', $company->city) }}">
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label">Provinsi</label>
                                                                    <input type="text" name="province"
                                                                        class="form-control"
                                                                        value="{{ old('province', $company->province) }}">
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Telepon</label>
                                                                <input type="text" name="phone" class="form-control"
                                                                    value="{{ old('phone', $company->phone) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Email</label>
                                                                <input type="email" name="email" class="form-control"
                                                                    value="{{ old('email', $company->email) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Website</label>
                                                                <input type="text" name="website" class="form-control"
                                                                    value="{{ old('website', $company->website) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">NPWP / Tax Number</label>
                                                                <input type="text" name="tax_number"
                                                                    class="form-control"
                                                                    value="{{ old('tax_number', $company->tax_number) }}">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Logo Utama</label><br>
                                                                @php
                                                                    $logo = $company->logo_path;
                                                                @endphp
                                                                @if ($logo)
                                                                    @if (file_exists(storage_path('app/public/' . $logo)))
                                                                        <img src="{{ asset('storage/' . $logo) }}"
                                                                            style="height:32px;">
                                                                    @elseif (file_exists(public_path($logo)))
                                                                        <img src="{{ asset($logo) }}"
                                                                            style="height:32px;">
                                                                    @endif
                                                                @endif

                                                                <input type="file" name="logo"
                                                                    class="form-control">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Logo Kecil</label><br>
                                                                @if ($company->logo_small_path)
                                                                    <img src="{{ asset('storage/' . $company->logo_small_path) }}"
                                                                        alt="logo kecil"
                                                                        style="height: 24px; margin-bottom: 6px;">
                                                                    <br>
                                                                @endif
                                                                <input type="file" name="logo_small"
                                                                    class="form-control">
                                                            </div>

                                                            <div class="mb-2 form-check">
                                                                <input type="checkbox" name="is_default" value="1"
                                                                    class="form-check-input"
                                                                    id="company-default-{{ $company->id }}"
                                                                    {{ $company->is_default ? 'checked' : '' }}>
                                                                <label class="form-check-label"
                                                                    for="company-default-{{ $company->id }}">
                                                                    Set sebagai default
                                                                </label>
                                                            </div>

                                                            <div class="mb-3 form-check">
                                                                <input type="checkbox" name="is_active" value="1"
                                                                    class="form-check-input"
                                                                    id="company-active-{{ $company->id }}"
                                                                    {{ $company->is_active ? 'checked' : '' }}>
                                                                <label class="form-check-label"
                                                                    for="company-active-{{ $company->id }}">
                                                                    Aktif
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary"
                                                        data-bs-dismiss="modal">
                                                        Batal
                                                    </button>
                                                    <button type="submit" class="btn btn-primary">
                                                        Simpan Perubahan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-3">
                                        Belum ada data company.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- Modal Tambah Company --}}
    <div class="modal fade" id="create-company-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('companies.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Company</label>
                                    <input type="text" name="name" class="form-control" required
                                        value="{{ old('name') }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Nama Legal</label>
                                    <input type="text" name="legal_name" class="form-control"
                                        value="{{ old('legal_name') }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Singkatan (Short Name)</label>
                                    <input type="text" name="short_name" class="form-control"
                                        value="{{ old('short_name') }}" placeholder="NTT, MAND, dll">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Kode</label>
                                    <input type="text" name="code" class="form-control"
                                        value="{{ old('code') }}" placeholder="NTT01, HO, dll">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                                </div>

                                <div class="mb-3 row">
                                    <div class="col-6">
                                        <label class="form-label">Kota</label>
                                        <input type="text" name="city" class="form-control"
                                            value="{{ old('city') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Provinsi</label>
                                        <input type="text" name="province" class="form-control"
                                            value="{{ old('province') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telepon</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="{{ old('phone') }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        value="{{ old('email') }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Website</label>
                                    <input type="text" name="website" class="form-control"
                                        value="{{ old('website') }}" placeholder="https://...">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">NPWP / Tax Number</label>
                                    <input type="text" name="tax_number" class="form-control"
                                        value="{{ old('tax_number') }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Logo Utama (kop surat/PO)</label>
                                    <input type="file" name="logo" class="form-control">
                                    <small class="text-muted">Maks 2MB, format gambar.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Logo Kecil (sidebar/navbar)</label>
                                    <input type="file" name="logo_small" class="form-control">
                                    <small class="text-muted">Maks 1MB, format gambar.</small>
                                </div>

                                <div class="mb-2 form-check">
                                    <input type="checkbox" name="is_default" value="1" class="form-check-input"
                                        id="company-default-create">
                                    <label class="form-check-label" for="company-default-create">Set sebagai
                                        default</label>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                        id="company-active-create" checked>
                                    <label class="form-check-label" for="company-active-create">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Scripts: SweetAlert & Global Search --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // === SweetAlert: success ===
            const successEl = document.getElementById('company-success-message');
            if (successEl) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: successEl.dataset.message || 'Proses berhasil.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }

            // === SweetAlert: error message ===
            const errorMsgEl = document.getElementById('company-error-message');
            if (errorMsgEl) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: errorMsgEl.dataset.message || 'Terjadi kesalahan.',
                });
            }

            // === SweetAlert: validasi error list ===
            const errorListEl = document.getElementById('company-error-messages');
            if (errorListEl) {
                const errors = JSON.parse(errorListEl.dataset.errors || '[]');
                if (errors.length) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validasi gagal',
                        html: '<ul style="text-align:left;margin:0;padding-left:1.2rem;">' +
                            errors.map(e => `<li>${e}</li>`).join('') +
                            '</ul>'
                    }).then(() => {
                        // optional: buka modal create lagi kalau error dari create
                        const createModal = document.getElementById('create-company-modal');
                        if (createModal && '{{ old('name') }}') {
                            const modal = new bootstrap.Modal(createModal);
                            modal.show();
                        }
                    });
                }
            }

            // === SweetAlert: konfirmasi delete ===
            document.querySelectorAll('.form-delete-company').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const name = this.dataset.companyName || 'company';

                    Swal.fire({
                        title: 'Hapus company?',
                        text: `Data "${name}" akan dihapus.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, hapus',
                        cancelButtonText: 'Batal',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            // === Global search (client-side filter) ===
            const searchInput = document.getElementById('company-search');
            const table = document.getElementById('companies-table');

            if (searchInput && table) {
                searchInput.addEventListener('input', function() {
                    const keyword = this.value.toLowerCase();
                    const rows = table.querySelectorAll('tbody tr');

                    rows.forEach(function(row) {
                        const text = row.innerText.toLowerCase();
                        row.style.display = text.includes(keyword) ? '' : 'none';
                    });
                });
            }
        });
    </script>
@endsection
