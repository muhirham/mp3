@extends('layouts.home')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0">Roles & Sidebar</h4>
        <div class="ms-auto">
            @if(auth()->user()->hasPermission('roles.create'))
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlAddRole">
                    <i class="bx bx-plus"></i> New Role
                </button>
            @endif
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
                        <td>{{ is_array($r->menu_keys) ? count($r->menu_keys) : 0 }} item</td>
                        <td><code>{{ $r->home_route ?? '-' }}</code></td>
                        <td>
                            <div class="btn-group">

                                @if(auth()->user()->hasPermission('roles.update'))
                                <a href="#" class="btn btn-sm btn-outline-secondary js-edit"
                                data-id="{{ $r->id }}"
                                data-slug="{{ $r->slug }}"
                                data-name="{{ $r->name }}"
                                data-home_route="{{ $r->home_route }}"
                                data-menu_keys='@json($r->menu_keys)'
                                data-permissions='@json($r->permissions)'>
                                    Edit
                                </a>
                                @endif

                                @if(auth()->user()->hasPermission('roles.delete'))
                                <a href="#" class="btn btn-sm btn-outline-danger js-del" data-id="{{ $r->id }}">
                                    Delete
                                </a>
                                @endif

                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">No data</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
{{-- ====== ADD ROLE ====== --}}
@if(auth()->user()->hasPermission('roles.create'))
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
                            <input name="slug" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input name="name" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Home Route</label>
                            <select name="home_route" class="form-select">
                                <option value="">(Auto)</option>
                                @foreach($homeCandidates as $c)
                                    <option value="{{ $c['route'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-4">

                        {{-- INVENTORY --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Inventory</div>

                            @foreach($groups['inventory'] as $it)

                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}"
                                           id="add_{{ $it['key'] }}">
                                    <label class="form-check-label">{{ $it['label'] }}</label>
                                </div>

                                @if($it['key'] === 'products')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="products.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="products.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="products.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="products.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'packages')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="uom.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="uom.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="uom.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="uom.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'categories')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="category.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="category.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="category.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="category.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'suppliers')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="supplier.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="supplier.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="supplier.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="supplier.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'stock_adjustments')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="stock_adjustments.view"
                                                id="add_stock_adjustments_view">
                                            <label class="form-check-label" for="add_stock_adjustments_view">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="stock_adjustments.create"
                                                id="add_stock_adjustments_create">
                                            <label class="form-check-label" for="add_stock_adjustments_create">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="stock_adjustments.export"
                                                id="add_stock_adjustments_export">
                                            <label class="form-check-label" for="add_stock_adjustments_export">Export Excel</label>
                                        </div>

                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- PROCUREMENT --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Procurement</div>
                            @foreach($groups['procurement'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}">
                                    <label class="form-check-label">{{ $it['label'] }}</label>
                                </div>
                            @endforeach
                        </div>

                        {{-- MASTER --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Master</div>
                            @foreach($groups['master'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}">
                                    <label class="form-check-label">{{ $it['label'] }}</label>
                                </div>
                                {{-- ADD MASTER PERMISSION --}}
                                @if($it['key'] === 'company')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'users')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.bulk_delete">
                                            <label class="form-check-label">Bulk Delete</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.export">
                                            <label class="form-check-label">Export</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'roles')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'bom')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input add-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.produce">
                                            <label class="form-check-label">Produce</label>
                                        </div>

                                    </div>
                                @endif
                            @endforeach
                        </div>
                        {{-- WAREHOUSE --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Warehouse</div>
                            @foreach($groups['warehouse'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input"
                                        type="checkbox"
                                        name="menu_keys[]"
                                        value="{{ $it['key'] }}"
                                        id="add_{{ $it['key'] }}">
                                    <label for="add_{{ $it['key'] }}" class="form-check-label">
                                        {{ $it['label'] }}
                                    </label>
                                </div>
                                
                                @if($it['key'] === 'warehouses')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="warehouse.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="warehouse.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="warehouse.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[]" value="warehouse.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- SALES --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Sales</div>
                            @foreach($groups['sales'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input"
                                        type="checkbox"
                                        name="menu_keys[]"
                                        value="{{ $it['key'] }}"
                                        id="add_{{ $it['key'] }}">
                                    <label for="add_{{ $it['key'] }}" class="form-check-label">
                                        {{ $it['label'] }}
                                    </label>
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
@endif

{{-- ====== EDIT ROLE ====== --}}
@if(auth()->user()->hasPermission('roles.update'))
<div class="modal fade" id="mdlEditRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Edit Role</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="formEditRole" method="POST">
                @csrf
                @method('PUT')

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

                        {{-- INVENTORY --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Inventory</div>

                            @foreach($groups['inventory'] as $it)

                                <div class="form-check">
                                    <input class="form-check-input edit-check" type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}"
                                           id="edit_{{ $it['key'] }}">

                                    <label for="edit_{{ $it['key'] }}" class="form-check-label">
                                        {{ $it['label'] }}
                                    </label>
                                </div>

                                @if($it['key'] === 'products')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                   name="permissions[]"
                                                   value="products.view"
                                                   id="edit_products_view">
                                            <label class="form-check-label" for="edit_products_view">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                   name="permissions[]"
                                                   value="products.create"
                                                   id="edit_products_create">
                                            <label class="form-check-label" for="edit_products_create">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                   name="permissions[]"
                                                   value="products.update"
                                                   id="edit_products_update">
                                            <label class="form-check-label" for="edit_products_update">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                   name="permissions[]"
                                                   value="products.delete"
                                                   id="edit_products_delete">
                                            <label class="form-check-label" for="edit_products_delete">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'packages')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="uom.view"
                                                id="edit_uom_view">
                                            <label class="form-check-label" for="edit_uom_view">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="uom.create"
                                                id="edit_uom_create">
                                            <label class="form-check-label" for="edit_uom_create">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="uom.update"
                                                id="edit_uom_update">
                                            <label class="form-check-label" for="edit_uom_update">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="uom.delete"
                                                id="edit_uom_delete">
                                            <label class="form-check-label" for="edit_uom_delete">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'categories')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="category.view"
                                                id="edit_category_view">
                                            <label class="form-check-label" for="edit_category_view">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="category.create"
                                                id="edit_category_create">
                                            <label class="form-check-label" for="edit_category_create">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="category.update"
                                                id="edit_category_update">
                                            <label class="form-check-label" for="edit_category_update">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="category.delete"
                                                id="edit_category_delete">
                                            <label class="form-check-label" for="edit_category_delete">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'suppliers')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="supplier.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="supplier.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="supplier.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="supplier.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'stock_adjustments')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="stock_adjustments.view"
                                                id="edit_stock_adjustments_view">
                                            <label class="form-check-label" for="edit_stock_adjustments_view">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="stock_adjustments.create"
                                                id="edit_stock_adjustments_create">
                                            <label class="form-check-label" for="edit_stock_adjustments_create">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="stock_adjustments.export"
                                                id="edit_stock_adjustments_export">
                                            <label class="form-check-label" for="edit_stock_adjustments_export">Export Excel</label>
                                        </div>

                                    </div>
                                @endif

                            @endforeach
                        </div>

                        {{-- PROCUREMENT --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Procurement</div>
                            @foreach($groups['procurement'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input edit-check" type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}"
                                           id="edit_{{ $it['key'] }}">
                                    <label for="edit_{{ $it['key'] }}" class="form-check-label">
                                        {{ $it['label'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        {{-- MASTER --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Master</div>
                            @foreach($groups['master'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input edit-check" type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}"
                                           id="edit_{{ $it['key'] }}">
                                    <label for="edit_{{ $it['key'] }}" class="form-check-label">
                                        {{ $it['label'] }}
                                    </label>
                                </div>

                                {{-- EDIT MASTER PERMISSION --}}
                                @if($it['key'] === 'company')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="company.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'users')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.bulk_delete">
                                            <label class="form-check-label">Bulk Delete</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="users.export">
                                            <label class="form-check-label">Export</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'roles')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="roles.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                                @if($it['key'] === 'bom')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]"
                                                value="bom.produce">
                                            <label class="form-check-label">Produce</label>
                                        </div>

                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- WAREHOUSE --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Warehouse</div>
                            @foreach($groups['warehouse'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input edit-check" type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}"
                                           id="edit_{{ $it['key'] }}">
                                    <label for="edit_{{ $it['key'] }}" class="form-check-label">
                                        {{ $it['label'] }}
                                    </label>
                                </div>

                                {{-- EDIT WAREHOUSE PERMISSION --}}
                                @if($it['key'] === 'warehouses')
                                    <div class="ms-4 mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="warehouse.view">
                                            <label class="form-check-label">View</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="warehouse.create">
                                            <label class="form-check-label">Create</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="warehouse.update">
                                            <label class="form-check-label">Update</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input edit-permission" type="checkbox"
                                                name="permissions[]" value="warehouse.delete">
                                            <label class="form-check-label">Delete</label>
                                        </div>

                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- SALES --}}
                        <div class="col-md-4">
                            <div class="fw-semibold mb-2">Sales</div>
                            @foreach($groups['sales'] as $it)
                                <div class="form-check">
                                    <input class="form-check-input edit-check" type="checkbox"
                                           name="menu_keys[]"
                                           value="{{ $it['key'] }}"
                                           id="edit_{{ $it['key'] }}">
                                    <label for="edit_{{ $it['key'] }}" class="form-check-label">
                                        {{ $it['label'] }}
                                    </label>
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
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const permissionMenuMap = {
        products: 'products',
        uom: 'packages',
        category: 'categories',
        supplier: 'suppliers',
        stock_adjustments: 'stock_adjustments',
        company: 'company',
        users: 'users',
        roles: 'roles',
        bom: 'bom',
        warehouse: 'warehouses',
    };

    function getPermissionParts(value) {
        const [module, action] = String(value || '').split('.');
        return { module, action };
    }

    function getMenuCheckbox(modalEl, module) {
        const menuKey = permissionMenuMap[module];
        if (!menuKey) {
            return null;
        }

        return modalEl.querySelector(`input[name="menu_keys[]"][value="${menuKey}"]`);
    }

    function getPermissionCheckbox(modalEl, module, action) {
        return modalEl.querySelector(`input[name="permissions[]"][value="${module}.${action}"]`);
    }

    function getPermissionCheckboxes(modalEl, module) {
        return Array.from(modalEl.querySelectorAll(`input[name="permissions[]"][value^="${module}."]`));
    }

    function syncFromMenuCheckbox(modalEl, menuCheckbox) {
        const menuKey = menuCheckbox.value;
        const module = Object.keys(permissionMenuMap).find((key) => permissionMenuMap[key] === menuKey);

        if (!module) {
            return;
        }

        const permissionCheckboxes = getPermissionCheckboxes(modalEl, module);
        const viewCheckbox = getPermissionCheckbox(modalEl, module, 'view');

        if (!permissionCheckboxes.length) {
            return;
        }

        if (menuCheckbox.checked) {
            if (viewCheckbox) {
                viewCheckbox.checked = true;
            }
            return;
        }

        permissionCheckboxes.forEach((checkbox) => {
            checkbox.checked = false;
        });
    }

    function syncFromPermissionCheckbox(modalEl, permissionCheckbox) {
        const { module, action } = getPermissionParts(permissionCheckbox.value);
        const menuCheckbox = getMenuCheckbox(modalEl, module);
        const viewCheckbox = getPermissionCheckbox(modalEl, module, 'view');

        if (!menuCheckbox) {
            return;
        }

        if (permissionCheckbox.checked) {
            menuCheckbox.checked = true;

            if (action !== 'view' && viewCheckbox) {
                viewCheckbox.checked = true;
            }

            if (action === 'view' && viewCheckbox) {
                viewCheckbox.checked = true;
            }

            return;
        }

        const hasOtherChecked = getPermissionCheckboxes(modalEl, module)
            .some((checkbox) => checkbox.checked);

        if (!hasOtherChecked) {
            menuCheckbox.checked = false;
        }
    }

    function bindPermissionSync(modalSelector) {
        const modalEl = document.querySelector(modalSelector);
        if (!modalEl) {
            return;
        }

        modalEl.querySelectorAll('input[name="menu_keys[]"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => syncFromMenuCheckbox(modalEl, checkbox));
        });

        modalEl.querySelectorAll('input[name="permissions[]"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => syncFromPermissionCheckbox(modalEl, checkbox));
        });
    }

    bindPermissionSync('#mdlAddRole');
    bindPermissionSync('#mdlEditRole');

    const editModalEl = document.getElementById('mdlEditRole');
    const editModal   = new bootstrap.Modal(editModalEl);
    const editForm    = document.getElementById('formEditRole');

    document.querySelectorAll('.js-edit').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();

            let keys = [];
            let permissions = [];

            try { keys = JSON.parse(btn.dataset.menu_keys || '[]'); } catch(e){}
            try { permissions = JSON.parse(btn.dataset.permissions || '[]'); } catch(e){}

            editForm.action = `{{ url('roles') }}/${btn.dataset.id}`;

            document.getElementById('edit_slug').value = btn.dataset.slug;
            document.getElementById('edit_name').value = btn.dataset.name;
            document.getElementById('edit_home_route').value = btn.dataset.home_route || '';

            document.querySelectorAll('#mdlEditRole .edit-check').forEach(ch => {
                ch.checked = keys.includes(ch.value);
            });

            document.querySelectorAll('#mdlEditRole .edit-permission').forEach(ch => {
                ch.checked = permissions.includes(ch.value);
            });

            editModal.show();
        });
    });

    document.querySelectorAll('.js-del').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();

            const id = btn.dataset.id;

            Swal.fire({
                title: 'Delete role?',
                text: 'Role will be permanently deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then(result => {

                if (result.isConfirmed) {

                    fetch(`{{ url('roles') }}/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(async res => {
                        const data = await res.json();

                        if (!res.ok) {
                            throw new Error(data.message || 'Delete failed');
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted',
                            timer: 1000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', err.message, 'error');
                    });

                }

            });
        });
    });
});
</script>
@endpush
