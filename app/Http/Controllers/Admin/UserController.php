<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected function ensureCanManageUsers()
    {
        $me = auth()->user();

        if (!$me || (!$me->hasRole('superadmin') && !$me->hasRole('warehouse'))) {
            abort(403);
        }

        return $me;
    }

    public function index()
    {
        /** @var \App\Models\User $me */
        $me          = $this->ensureCanManageUsers();
        $isWarehouse = $me->hasRole('warehouse');

        $query = User::with(['warehouse', 'roles'])->orderBy('id');

        // Admin warehouse: hanya lihat dirinya + sales di warehouse dia
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

        $users      = $query->get();
        $warehouses = Warehouse::select('id','warehouse_name')
                        ->orderBy('warehouse_name')
                        ->get();

        // Admin WH cuma boleh pilih role sales
        $allRoles = $isWarehouse
            ? Role::where('slug', 'sales')->get(['id','name','slug'])
            : Role::orderBy('name')->get(['id','name','slug']);

        return view('admin.users.indexUser', compact('users','warehouses','allRoles','me'));
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $me */
        $me          = $this->ensureCanManageUsers();
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

        // role slugs dari form
        $roleSlugs = collect($data['roles'] ?? []);

        if ($isWarehouse) {
            // Admin WH: FORCE sales + warehouse dia sendiri
            $roleSlugs           = collect(['sales']);
            $data['warehouse_id'] = $me->warehouse_id;
        } else {
            // Superadmin: kalau role butuh warehouse → wajib pilih
            $needsWarehouse = $roleSlugs->contains(fn ($s) => in_array($s, ['warehouse','sales'], true));
            if ($needsWarehouse) {
                $request->validate(['warehouse_id' => ['required','exists:warehouses,id']]);
            } else {
                $data['warehouse_id'] = null;
            }
        }

        // slug → id
        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();

        // upload signature
        $signaturePath = null;
        if ($request->hasFile('signature')) {
            $signaturePath = $request->file('signature')->store('signatures', 'public');
        }

        // payload users
        $payload = collect($data)->only([
            'name','username','email','phone','position','warehouse_id','status',
        ])->toArray();
        $payload['password'] = Hash::make($data['password']);

        if ($signaturePath) {
            $payload['signature_path'] = $signaturePath;
        }

        DB::transaction(function () use ($payload, $roleIds) {
            /** @var \App\Models\User $user */
            $user = User::create($payload);
            $user->roles()->sync($roleIds);
        });

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        /** @var \App\Models\User $me */
        $me          = $this->ensureCanManageUsers();
        $isWarehouse = $me->hasRole('warehouse');

        // Admin WH hanya boleh edit SALES di warehouse dia
        if ($isWarehouse) {
            $canManage = $user->warehouse_id === $me->warehouse_id && $user->hasRole('sales');
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

        // fallback roles kalau form nggak kirim
        $roleSlugs = collect($data['roles'] ?? $user->roles->pluck('slug')->all());

        if ($isWarehouse) {
            // Admin WH tetap dipaksa sales + warehouse dia
            $roleSlugs            = collect(['sales']);
            $data['warehouse_id'] = $me->warehouse_id;
        } else {
            $needsWarehouse = $roleSlugs->contains(fn ($s) => in_array($s, ['warehouse','sales'], true));
            if ($needsWarehouse) {
                $request->validate(['warehouse_id' => ['required','exists:warehouses,id']]);
            } else {
                $data['warehouse_id'] = null;
            }
        }

        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();

        // upload signature baru (kalau ada)
        $signaturePath = $user->signature_path;
        if ($request->hasFile('signature')) {
            if ($signaturePath) {
                Storage::disk('public')->delete($signaturePath);
            }
            $signaturePath = $request->file('signature')->store('signatures', 'public');
        }

        $payload = collect($data)->only([
            'name','username','email','phone','position','warehouse_id','status',
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
        /** @var \App\Models\User $me */
        $me          = $this->ensureCanManageUsers();
        $isWarehouse = $me->hasRole('warehouse');

        if ($me && $me->id === $user->id) {
            return response()->json(['error' => "You can't delete yourself."], 422);
        }

        if ($isWarehouse) {
            // Admin WH hanya boleh delete SALES di warehouse dia
            $canManage = $user->warehouse_id === $me->warehouse_id && $user->hasRole('sales');
            if (!$canManage) {
                return response()->json(['error' => "You are not allowed to delete this user."], 422);
            }
        }

        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }

        $user->delete();

        return response()->json(['success' => 'User deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        /** @var \App\Models\User $me */
        $me          = $this->ensureCanManageUsers();
        $isWarehouse = $me->hasRole('warehouse');

        $ids = $request->validate([
            'ids'   => ['required','array','min:1'],
            'ids.*' => ['integer','distinct','exists:users,id'],
        ])['ids'];

        // jangan sampai ngehapus dirinya sendiri
        $ids = array_values(array_filter($ids, fn ($id) => $id !== ($me?->id)));

        // Admin WH: hanya boleh hapus SALES di warehouse dia
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
                if ($user->signature_path) {
                    Storage::disk('public')->delete($user->signature_path);
                }
                $user->delete();
            }
        });

        return response()->json(['success' => 'Selected users deleted.']);
    }
}
