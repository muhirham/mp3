<?php

namespace Database\Seeders\System;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;

class OperationAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['superadmin', 'admin']);
        })->first();

        if (!$admin) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@local'],
                [
                    'name'     => 'Admin Pusat',
                    'username' => 'admin',
                    'phone'    => '08120008000635',
                    'password' => bcrypt('password123'),
                    'status'   => 'active',
                ]
            );

            $role = Role::whereIn('slug', ['superadmin', 'admin'])
                ->orderByRaw("FIELD(slug, 'superadmin', 'admin')")
                ->first();

            if ($role && !$admin->roles()->where('roles.id', $role->id)->exists()) {
                $admin->roles()->attach($role->id);
            }
        }
    }
}