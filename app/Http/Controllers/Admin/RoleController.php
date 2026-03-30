<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    protected function ensureRolePermission(string $permission = 'roles.view')
    {
        $me = auth()->user();

        if (!$me || !$me->hasPermission($permission)) {
            abort(403);
        }

        return $me;
    }

    public function index()
    {
        $this->ensureRolePermission('roles.view');

        $roles = Role::orderBy('id')->get();

        $registry = collect(config('menu.items', []));

        $groups = [
            'admin'       => $registry->where('group', 'admin')->values(),
            'inventory'   => $registry->where('group', 'inventory')->values(),
            'procurement' => $registry->where('group', 'procurement')->values(),
            'master'      => $registry->where('group', 'master')->values(),
            'warehouse'   => $registry->where('group', 'warehouse')->values(),
            'sales'       => $registry->where('group', 'sales')->values(),
            'finance'     => $registry->where('group', 'finance')->values(),
        ];

        $homeCandidates = config('menu.home_candidates', []);

        return view('admin.roles.indexRole', compact('roles', 'groups', 'homeCandidates', 'registry'));
    }

    public function store(Request $r)
    {
        $this->ensureRolePermission('roles.create');

        $registry      = collect(config('menu.items', []))->keyBy('key');
        $validKeys     = $registry->keys()->all();
        $menuRoutes    = $registry->pluck('route')->all();
        $homeRoutes    = collect(config('menu.home_candidates', []))->pluck('route')->filter()->all();
        $allowedRoutes = array_values(array_unique(array_merge($menuRoutes, $homeRoutes)));

        $data = $r->validate([
            'slug'          => ['required','alpha_dash','max:60','unique:roles,slug'],
            'name'          => ['required','string','max:120'],
            'home_route'    => ['nullable','string', Rule::in($allowedRoutes)],
            'menu_keys'     => ['required','array','min:1'],
            'menu_keys.*'   => ['string', Rule::in($validKeys)],
            'permissions'   => ['nullable','array'],
            'permissions.*' => ['string'],
        ]);

        if (empty($data['home_route'])) {
            $firstKey = $data['menu_keys'][0];
            $data['home_route'] = $registry[$firstKey]['route'] ?? null;
        }

        Role::create([
            'slug'        => $data['slug'],
            'name'        => $data['name'],
            'home_route'  => $data['home_route'],
            'menu_keys'   => $data['menu_keys'],
            'permissions' => $data['permissions'] ?? [],
        ]);

        return back()->with('success', 'Role berhasil dibuat.');
    }

    public function update(Request $r, Role $role)
    {
        $this->ensureRolePermission('roles.update');

        $registry      = collect(config('menu.items', []))->keyBy('key');
        $validKeys     = $registry->keys()->all();
        $menuRoutes    = $registry->pluck('route')->all();
        $homeRoutes    = collect(config('menu.home_candidates', []))->pluck('route')->filter()->all();
        $allowedRoutes = array_values(array_unique(array_merge($menuRoutes, $homeRoutes)));

        $data = $r->validate([
            'slug'          => ['required','alpha_dash','max:60', Rule::unique('roles','slug')->ignore($role->id)],
            'name'          => ['required','string','max:120'],
            'home_route'    => ['nullable','string', Rule::in($allowedRoutes)],
            'menu_keys'     => ['required','array','min:1'],
            'menu_keys.*'   => ['string', Rule::in($validKeys)],
            'permissions'   => ['nullable','array'],
            'permissions.*' => ['string'],
        ]);

        if (empty($data['home_route'])) {
            $firstKey = $data['menu_keys'][0];
            $data['home_route'] = $registry[$firstKey]['route'] ?? null;
        }

        $role->update([
            'slug'        => $data['slug'],
            'name'        => $data['name'],
            'home_route'  => $data['home_route'],
            'menu_keys'   => $data['menu_keys'],
            'permissions' => $data['permissions'] ?? [],
        ]);

        return back()->with('success', 'Role berhasil diupdate.');
    }

    public function destroy(Role $role)
    {
        $this->ensureRolePermission('roles.delete');

        $role->delete();

        return response()->json(['success' => true]);
    }
}