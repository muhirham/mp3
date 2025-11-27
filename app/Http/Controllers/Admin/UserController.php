<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Role;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $me          = auth()->user();
        $isWarehouse = $me?->hasRole('warehouse') ?? false;

        $query = User::with(['warehouse','roles'])->orderBy('id');

        if ($isWarehouse) {
            $query->where(function ($q) use ($me) {
                $q->where('id', $me->id)
                  ->orWhere('warehouse_id', $me->warehouse_id);
            });
        }

        $users      = $query->get();
        $warehouses = Warehouse::select('id','warehouse_name')->orderBy('warehouse_name')->get();

        $allRoles = $isWarehouse
            ? Role::where('slug','sales')->get(['id','name','slug'])
            : Role::orderBy('name')->get(['id','name','slug']);

        return view('admin.users.indexUser', compact('users','warehouses','allRoles','me'));
    }

    public function store(Request $request)
    {
        $me          = auth()->user();
        $isWarehouse = $me?->hasRole('warehouse') ?? false;

        $data = $request->validate([
            'name'         => ['required','string','max:150'],
            'username'     => ['required','alpha_dash','max:150','unique:users,username'],
            'email'        => ['required','email','max:190','unique:users,email'],
            'phone'        => ['nullable','string','max:20','unique:users,phone'],
            'password'     => ['required','confirmed','min:6'],
            'roles'        => ['array','min:1'],
            'roles.*'      => ['string','exists:roles,slug'],
            'warehouse_id' => ['nullable','exists:warehouses,id'],
            'status'       => ['required', Rule::in(['active','inactive'])],
        ]);

        // slugs dari form
        $roleSlugs = collect($data['roles'] ?? []);

        // kalau user warehouse → force role sales + warehouse_id sama dengan milik dia
        if ($isWarehouse) {
            $roleSlugs = collect(['sales']);
            $data['warehouse_id'] = $me->warehouse_id;
        } else {
            $needsWarehouse = $roleSlugs->contains(fn ($s) => in_array($s, ['warehouse','sales'], true));
            if ($needsWarehouse) {
                $request->validate(['warehouse_id' => ['required','exists:warehouses,id']]);
            } else {
                $data['warehouse_id'] = null;
            }
        }

        // convert slug → role_id
        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();

        $payload = collect($data)->only(['name','username','email','phone','warehouse_id','status'])->toArray();
        $payload['password'] = Hash::make($data['password']);

        DB::transaction(function () use ($payload, $roleIds) {
            $user = User::create($payload);
            $user->roles()->sync($roleIds);
        });

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $me          = auth()->user();
        $isWarehouse = $me?->hasRole('warehouse') ?? false;

        $data = $request->validate([
            'name'         => ['required','string','max:150'],
            'username'     => ['required','alpha_dash','max:150', Rule::unique('users','username')->ignore($user->id)],
            'email'        => ['required','email','max:190', Rule::unique('users','email')->ignore($user->id)],
            'phone'        => ['nullable','string','max:20', Rule::unique('users','phone')->ignore($user->id)],
            'password'     => ['nullable','confirmed','min:6'],
            'roles'        => ['array','min:1'],
            'roles.*'      => ['string','exists:roles,slug'],
            'warehouse_id' => ['nullable','exists:warehouses,id'],
            'status'       => ['required', Rule::in(['active','inactive'])],
        ]);

        // kalau form nggak kirim roles, fallback ke roles existing user
        $roleSlugs = collect($data['roles'] ?? $user->roles->pluck('slug')->all());

        if ($isWarehouse) {
            $roleSlugs = collect(['sales']);
            $data['warehouse_id'] = $me->warehouse_id;
        } else {
            $needsWarehouse = $roleSlugs->contains(fn ($s) => in_array($s, ['warehouse','sales'], true));
            if ($needsWarehouse) {
                $request->validate(['warehouse_id' => ['required','exists:warehouses,id']]);
            } else {
                $data['warehouse_id'] = null;
            }
        }

        // slug → id
        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();

        $payload = collect($data)->only(['name','username','email','phone','warehouse_id','status'])->toArray();

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        DB::transaction(function () use ($user, $payload, $roleIds) {
            $user->update($payload);
            $user->roles()->sync($roleIds);
        });

        return back()->with('edit_success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $me = auth()->user();
        if ($me && $me->id === $user->id) {
            return response()->json(['error' => "You can't delete yourself."], 422);
        }
        $user->delete();
        return response()->json(['success' => 'User deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $me  = auth()->user();
        $ids = $request->validate([
            'ids'   => ['required','array','min:1'],
            'ids.*' => ['integer','distinct','exists:users,id'],
        ])['ids'];

        // jangan sampai ngehapus dirinya sendiri
        $ids = array_values(array_filter($ids, fn ($id) => $id !== ($me?->id)));

        DB::transaction(function () use ($ids) {
            User::whereIn('id', $ids)->delete();
        });

        return response()->json(['success' => 'Selected users deleted.']);
    }
}
