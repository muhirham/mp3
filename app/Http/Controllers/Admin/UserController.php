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
    protected function ensureCanManageUsers(string $permission = 'users.view')
    {
        $me = auth()->user();

        if (!$me || !$me->hasPermission($permission)) {
            abort(403);
        }

        return $me;
    }

    public function index()
    {
        $me = $this->ensureCanManageUsers('users.view');
        $isWarehouse = $me->hasRole('warehouse');

        $query = User::with(['warehouse', 'roles'])->orderBy('id');

        if ($isWarehouse) {
            $query->where(function ($q) use ($me) {
                $q->where('id', $me->id)
                  ->orWhere(function ($qq) use ($me) {
                      $qq->where('warehouse_id', $me->warehouse_id)
                         ->whereHas('roles', function ($r) {
                             $r->where('slug', 'sales');
                         });
                  });
            });
        }

        $users = $query->get();

        $warehouses = Warehouse::select('id', 'warehouse_name')
            ->orderBy('warehouse_name')
            ->get();

        $allRoles = $isWarehouse
            ? Role::where('slug', 'sales')->get(['id','name','slug'])
            : Role::orderBy('name')->get(['id','name','slug']);

        return view('admin.users.indexUser', compact('users','warehouses','allRoles','me'));
    }

    public function store(Request $request)
    {
        $me = $this->ensureCanManageUsers('users.create');
        $isWarehouse = $me->hasRole('warehouse');

        $data = $request->validate([
            'name'         => ['required','string','max:150'],
            'username'     => ['required','alpha_dash','max:150','unique:users,username'],
            'email'        => ['required','email','max:190','unique:users,email'],
            'phone'        => ['nullable','string','max:20','unique:users,phone'],
            'position'     => ['nullable','string','max:100'],
            'signature'    => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'password'     => ['required','confirmed','min:6'],
            'roles'        => ['array','min:1'],
            'roles.*'      => ['string','exists:roles,slug'],
            'warehouse_id' => ['nullable','exists:warehouses,id'],
            'status'       => ['required', Rule::in(['active','inactive'])],
        ]);

        $roleSlugs = collect($data['roles'] ?? []);

        if ($isWarehouse) {
            $roleSlugs = collect(['sales']);
            $data['warehouse_id'] = $me->warehouse_id;
        } else {
            $needsWarehouse = $roleSlugs->contains(fn ($s) => in_array($s, ['warehouse','sales'], true));

            if ($needsWarehouse) {
                $request->validate([
                    'warehouse_id' => ['required','exists:warehouses,id']
                ]);
            } else {
                $data['warehouse_id'] = null;
            }
        }

        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();

        $signaturePath = replace_uploaded_file(
            null,
            $request->file('signature') ?? null,
            'signatures',
            'public'
        );

        $payload = collect($data)->only([
            'name','username','email','phone','position','warehouse_id','status'
        ])->toArray();

        $payload['password'] = Hash::make($data['password']);

        if ($signaturePath) {
            $payload['signature_path'] = $signaturePath;
        }

        DB::transaction(function () use ($payload, $roleIds) {
            $user = User::create($payload);
            $user->roles()->sync($roleIds);
        });

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $me = $this->ensureCanManageUsers('users.update');
        $isWarehouse = $me->hasRole('warehouse');

        if ($isWarehouse) {
            $canManage = ($user->id === $me->id) || ($user->warehouse_id === $me->warehouse_id && $user->hasRole('sales'));

            if (!$canManage) {
                abort(403);
            }
        }

        $data = $request->validate([
            'name'         => ['required','string','max:150'],
            'username'     => ['required','alpha_dash','max:150', Rule::unique('users','username')->ignore($user->id)],
            'email'        => ['required','email','max:190', Rule::unique('users','email')->ignore($user->id)],
            'phone'        => ['nullable','string','max:20', Rule::unique('users','phone')->ignore($user->id)],
            'position'     => ['nullable','string','max:100'],
            'signature'    => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'password'     => ['nullable','confirmed','min:6'],
            'roles'        => ['array','min:1'],
            'roles.*'      => ['string','exists:roles,slug'],
            'warehouse_id' => ['nullable','exists:warehouses,id'],
            'status'       => ['required', Rule::in(['active','inactive'])],
        ]);

        $roleSlugs = collect($data['roles'] ?? $user->roles->pluck('slug')->all());

        if ($isWarehouse) {
            // Admin WH can only assign 'sales' role to others, but keep their own roles if updating self
            if ($user->id === $me->id) {
                $roleSlugs = collect($user->roles->pluck('slug')->all());
            } else {
                $roleSlugs = collect(['sales']);
            }
            $data['warehouse_id'] = $me->warehouse_id;
        } else {
            $needsWarehouse = $roleSlugs->contains(fn ($s) => in_array($s, ['warehouse','sales'], true));

            if ($needsWarehouse) {
                $request->validate([
                    'warehouse_id' => ['required','exists:warehouses,id']
                ]);
            } else {
                $data['warehouse_id'] = null;
            }
        }

        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();

        $signaturePath = replace_uploaded_file(
            $user->signature_path,
            $request->file('signature') ?? null,
            'signatures'
        );

        $payload = collect($data)->only([
            'name','username','email','phone','position','warehouse_id','status'
        ])->toArray();

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $payload['signature_path'] = $signaturePath;

        DB::transaction(function () use ($user, $payload, $roleIds) {
            $user->update($payload);
            $user->roles()->sync($roleIds);
        });

        return back()->with('edit_success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $me = $this->ensureCanManageUsers('users.delete');
        $isWarehouse = $me->hasRole('warehouse');

        if ($me->id === $user->id) {
            return response()->json(['error' => "You can't delete yourself."], 422);
        }

        if ($isWarehouse) {
            $canManage = $user->warehouse_id === $me->warehouse_id && $user->hasRole('sales');

            if (!$canManage) {
                return response()->json(['error' => "You are not allowed to delete this user."], 422);
            }
        }

        delete_file_if_exists($user->signature_path);

        $user->delete();

        return response()->json(['success' => 'User deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $me = $this->ensureCanManageUsers('users.bulk_delete');
        $isWarehouse = $me->hasRole('warehouse');

        $ids = $request->validate([
            'ids'   => ['required','array','min:1'],
            'ids.*' => ['integer','distinct','exists:users,id'],
        ])['ids'];

        $ids = array_values(array_filter($ids, fn ($id) => $id !== $me->id));

        if ($isWarehouse) {
            $ids = User::whereIn('id', $ids)
                ->where('warehouse_id', $me->warehouse_id)
                ->whereHas('roles', function ($q) {
                    $q->where('slug', 'sales');
                })
                ->pluck('id')
                ->all();
        }

        if (empty($ids)) {
            return response()->json(['error' => 'No user can be deleted.'], 422);
        }

        DB::transaction(function () use ($ids) {
            $users = User::whereIn('id', $ids)->get();

            foreach ($users as $user) {
                delete_file_if_exists($user->signature_path);
                $user->delete();
            }
        });

        return response()->json(['success' => 'Selected users deleted.']);
    }

    public function toggleStatus(Request $request, User $user)
    {
        $me = $this->ensureCanManageUsers('users.update');
        $isWarehouse = $me->hasRole('warehouse');

        if ($me->id === $user->id) {
            return response()->json(['error' => "You can't change your own status."], 422);
        }

        if ($isWarehouse) {
            $canManage = $user->warehouse_id === $me->warehouse_id && $user->hasRole('sales');

            if (!$canManage) {
                return response()->json(['error' => "You are not allowed to change this user's status."], 422);
            }
        }

        $user->status = ($user->status === 'active') ? 'inactive' : 'active';
        $user->save();

        return response()->json([
            'success' => 'Status updated.',
            'status' => $user->status
        ]);
    }
}