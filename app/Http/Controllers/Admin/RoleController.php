<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        // Semua role, menu_keys sudah otomatis dicast ke array dari model
        $roles = Role::orderBy('id')->get();

        // registry menu dari config
        $registry = collect(config('menu.items', []));

        // group untuk render checkbox di view
        $groups = [
            'admin'     => $registry->where('group', 'admin')->values(),
            'inventory'     => $registry->where('group', 'inventory')->values(),
            'procurement'     => $registry->where('group', 'procurement')->values(),
            'master'     => $registry->where('group', 'master')->values(),
            'warehouse' => $registry->where('group', 'warehouse')->values(),
            'sales'     => $registry->where('group', 'sales')->values(),
        ];

        $homeCandidates = config('menu.home_candidates', []);

        return view('admin.roles.indexRole', compact('roles', 'groups', 'homeCandidates', 'registry'));
    }

    public function store(Request $r)
    {
        // siapkan daftar key & route yang valid dari config
        $registry      = collect(config('menu.items', []))->keyBy('key');
        $validKeys     = $registry->keys()->all();
        $menuRoutes    = $registry->pluck('route')->all();
        $homeRoutes    = collect(config('menu.home_candidates', []))->pluck('route')->filter()->all();
        $allowedRoutes = array_values(array_unique(array_merge($menuRoutes, $homeRoutes)));

        $data = $r->validate([
            'slug'        => ['required','alpha_dash','max:60','unique:roles,slug'],
            'name'        => ['required','string','max:120'],
            'home_route'  => ['nullable','string', Rule::in($allowedRoutes)],
            'menu_keys'   => ['required','array','min:1'],
            'menu_keys.*' => ['string', Rule::in($validKeys)],
        ]);

        // fallback home_route = route dari menu pertama yang dicentang
        if (empty($data['home_route'])) {
            $firstKey = $data['menu_keys'][0];
            $data['home_route'] = $registry[$firstKey]['route'] ?? null;
        }

        // simpan langsung ke kolom menu_keys (JSON)
        Role::create([
            'slug'       => $data['slug'],
            'name'       => $data['name'],
            'home_route' => $data['home_route'],
            'menu_keys'  => $data['menu_keys'],
        ]);

        return back()->with('success', 'Role berhasil dibuat.');
    }

    public function update(Request $r, Role $role)
    {
        $registry      = collect(config('menu.items', []))->keyBy('key');
        $validKeys     = $registry->keys()->all();
        $menuRoutes    = $registry->pluck('route')->all();
        $homeRoutes    = collect(config('menu.home_candidates', []))->pluck('route')->filter()->all();
        $allowedRoutes = array_values(array_unique(array_merge($menuRoutes, $homeRoutes)));

        $data = $r->validate([
            'slug'        => ['required','alpha_dash','max:60', Rule::unique('roles','slug')->ignore($role->id)],
            'name'        => ['required','string','max:120'],
            'home_route'  => ['nullable','string', Rule::in($allowedRoutes)],
            'menu_keys'   => ['required','array','min:1'],
            'menu_keys.*' => ['string', Rule::in($validKeys)],
        ]);

        if (empty($data['home_route'])) {
            $firstKey = $data['menu_keys'][0];
            $data['home_route'] = $registry[$firstKey]['route'] ?? null;
        }

        $role->update([
            'slug'       => $data['slug'],
            'name'       => $data['name'],
            'home_route' => $data['home_route'],
            'menu_keys'  => $data['menu_keys'],
        ]);

        return back()->with('success', 'Role berhasil diupdate.');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(['success' => true]);
    }
}
