<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use App\Models\StockSnapshot;
use App\Models\Product;
use App\Models\User;
use App\Models\Role;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        // Ambil user pusat (superadmin / admin)
        $admin = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['superadmin', 'admin']);
        })->first();

        // Fallback: kalau belum ada, bikin minimal 1 user pusat
        if (! $admin) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@local'],
                [
                    'name'     => 'Admin Pusat',
                    'username' => 'admin',
                    'phone'    => '081200000000',
                    'password' => bcrypt('password123'),
                    'status'   => 'active',
                ]
            );

            $role = Role::whereIn('slug', ['superadmin', 'admin'])
                ->orderByRaw("FIELD(slug, 'superadmin', 'admin')")
                ->first();

            if ($role && ! $admin->roles()->where('roles.id', $role->id)->exists()) {
                $admin->roles()->attach($role->id);
            }
        }

        // Ambil produk 0097-P (VO 5GB-3D), kalau kosong pakai product pertama
        $product = Product::firstWhere('product_code', '0097-P') ?? Product::first();

        // ===== Activity Logs =====
        ActivityLog::firstOrCreate(
            [
                'user_id'     => $admin->id,
                'action'      => 'Seeder Init',
                'entity_type' => 'System',
            ],
            [
                'entity_id'   => null,
                'description' => 'Seeder initial setup data dummy MP3 berhasil dibuat.',
            ]
        );

        if ($product) {
            ActivityLog::firstOrCreate(
                [
                    'user_id'     => $admin->id,
                    'action'      => 'Stock Update',
                    'entity_type' => 'Product',
                    'entity_id'   => $product->id,
                ],
                [
                    'description' => 'Perubahan stok produk ' . $product->product_code . ' (' . $product->name . ') oleh admin pusat.',
                ]
            );

            // ===== Stock Snapshots =====
            StockSnapshot::firstOrCreate(
                [
                    'owner_type'  => 'pusat',
                    'owner_id'    => 0,
                    'product_id'  => $product->id,
                    'recorded_at' => now()->toDateString(),
                ],
                [
                    'quantity'    => 100,
                ]
            );
        }
    }
}
