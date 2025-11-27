    @extends('layouts.home')

    @section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0">Roles & Sidebar</h4>
        <div class="ms-auto">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlAddRole">
            <i class="bx bx-plus"></i> New Role
        </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th style="width:60px">ID</th>
                <th>Slug</th>
                <th>Name</th>
                <th>Menus</th>
                <th>Home Route</th>
                <th style="width:120px">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($roles as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->slug }}</td>
                <td>{{ $r->name }}</td>
                {{-- pakai menu_keys (array) bukan relasi menus --}}
                <td>{{ is_array($r->menu_keys) ? count($r->menu_keys) : 0 }} item</td>
                <td><code>{{ $r->home_route ?? '-' }}</code></td>
                <td>
                <div class="btn-group">
                    <a href="#" class="btn btn-sm btn-outline-secondary js-edit"
                    data-id="{{ $r->id }}"
                    data-slug="{{ $r->slug }}"
                    data-name="{{ $r->name }}"
                    data-home_route="{{ $r->home_route }}"
                    data-menu_keys='@json($r->menu_keys)'>
                    Edit
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-danger js-del" data-id="{{ $r->id }}">Delete</a>
                </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted">No data</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    </div>

    {{-- ====== ADD ROLE ====== --}}
    <div class="modal fade" id="mdlAddRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">New Role</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formAddRole" method="POST" action="{{ route('roles.store') }}">
            @csrf
            <div class="modal-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                <label class="form-label">Slug</label>
                <input name="slug" class="form-control" placeholder="ex: auditor" required>
                </div>
                <div class="col-md-4">
                <label class="form-label">Name</label>
                <input name="name" class="form-control" placeholder="Auditor" required>
                </div>
                <div class="col-md-4">
                <label class="form-label">Home Route (optional)</label>
                <select name="home_route" id="add_home_route" class="form-select">
                    <option value="">(Auto from first checked)</option>
                    @foreach($homeCandidates as $c)
                    <option value="{{ $c['route'] }}">{{ $c['label'] }} ({{ $c['route'] }})</option>
                    @endforeach
                </select>
                </div>
            </div>

            <div class="row g-4">
                {{-- ADMIN --}}
                <div class="col-md-4">
                <div class="fw-semibold mb-2">inventory</div>
                @foreach($groups['inventory'] as $it)
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="add_{{ $it['key'] }}">
                    <label for="add_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>
                <div class="col-md-4">
                <div class="fw-semibold mb-2">procurement</div>
                @foreach($groups['procurement'] as $it)
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="add_{{ $it['key'] }}">
                    <label for="add_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>
                <div class="col-md-4">
                <div class="fw-semibold mb-2">master</div>
                @foreach($groups['master'] as $it)
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="add_{{ $it['key'] }}">
                    <label for="add_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>

                {{-- WAREHOUSE --}}
                <div class="col-md-4">
                <div class="fw-semibold mb-2">Warehouse</div>
                @foreach($groups['warehouse'] as $it)
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="add_{{ $it['key'] }}">
                    <label for="add_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>

                {{-- SALES --}}
                <div class="col-md-4">
                <div class="fw-semibold mb-2">Sales</div>
                @foreach($groups['sales'] as $it)
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="add_{{ $it['key'] }}">
                    <label for="add_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>
            </div>
            </div>

            <div class="modal-footer">
            <button class="btn btn-primary">Save</button>
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
            </div>
        </form>
        </div>
    </div>
    </div>

    {{-- ====== EDIT ROLE ====== --}}
    <div class="modal fade" id="mdlEditRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Role</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formEditRole" method="POST">
            @csrf @method('PUT')
            <div class="modal-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                <label class="form-label">Slug</label>
                <input id="edit_slug" name="slug" class="form-control" required>
                </div>
                <div class="col-md-4">
                <label class="form-label">Name</label>
                <input id="edit_name" name="name" class="form-control" required>
                </div>
                <div class="col-md-4">
                <label class="form-label">Home Route (optional)</label>
                <select name="home_route" id="edit_home_route" class="form-select">
                    <option value="">(Auto from first checked)</option>
                    @foreach($homeCandidates as $c)
                    <option value="{{ $c['route'] }}">{{ $c['label'] }} ({{ $c['route'] }})</option>
                    @endforeach
                </select>
                </div>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                <div class="fw-semibold mb-2">inventory</div>
                @foreach($groups['inventory'] as $it)
                    <div class="form-check">
                    <input class="form-check-input edit-check" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="edit_{{ $it['key'] }}">
                    <label for="edit_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach

                </div>
                <div class="col-md-4">
                <div class="fw-semibold mb-2">procurement</div>
                @foreach($groups['procurement'] as $it)
                    <div class="form-check">
                    <input class="form-check-input edit-check" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="edit_{{ $it['key'] }}">
                    <label for="edit_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach

                </div>
                <div class="col-md-4">
                <div class="fw-semibold mb-2">master</div>
                @foreach($groups['master'] as $it)
                    <div class="form-check">
                    <input class="form-check-input edit-check" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="edit_{{ $it['key'] }}">
                    <label for="edit_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>

                <div class="col-md-4">
                <div class="fw-semibold mb-2">Warehouse</div>
                @foreach($groups['warehouse'] as $it)
                    <div class="form-check">
                    <input class="form-check-input edit-check" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="edit_{{ $it['key'] }}">
                    <label for="edit_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>

                <div class="col-md-4">
                <div class="fw-semibold mb-2">Sales</div>
                @foreach($groups['sales'] as $it)
                    <div class="form-check">
                    <input class="form-check-input edit-check" type="checkbox" name="menu_keys[]"
                            value="{{ $it['key'] }}" id="edit_{{ $it['key'] }}">
                    <label for="edit_{{ $it['key'] }}" class="form-check-label">{{ $it['label'] }}</label>
                    </div>
                @endforeach
                </div>
            </div>
            </div>

            <div class="modal-footer">
            <button class="btn btn-primary">Save</button>
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
            </div>
        </form>
        </div>
    </div>
    </div>
    @endsection

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
    // Edit
    const editModalEl = document.getElementById('mdlEditRole');
    const editModal   = new bootstrap.Modal(editModalEl);
    const editForm    = document.getElementById('formEditRole');

    document.querySelectorAll('.js-edit').forEach(btn => {
        btn.addEventListener('click', e => {
        e.preventDefault();
        const id   = btn.dataset.id;
        const slug = btn.dataset.slug || '';
        const name = btn.dataset.name || '';
        const home = btn.dataset.home_route || '';
        let keys   = [];
        try { keys = JSON.parse(btn.dataset.menu_keys || '[]'); } catch(e){}

        // set form action
        editForm.setAttribute('action', `{{ url('roles') }}/${id}`);

        // isi field
        document.getElementById('edit_slug').value = slug;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_home_route').value = home || '';

        // reset centang
        document.querySelectorAll('#mdlEditRole .edit-check').forEach(ch => {
            ch.checked = keys.includes(ch.value);
        });

        editModal.show();
        });
    });

    // Delete
    document.querySelectorAll('.js-del').forEach(btn => {
        btn.addEventListener('click', e => {
        e.preventDefault();
        if (!confirm('Delete this role?')) return;
        fetch(`{{ url('roles') }}/${btn.dataset.id}`, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept':'application/json'}
        }).then(r => location.reload());
        });
    });
    });
    </script>
    @endpush
