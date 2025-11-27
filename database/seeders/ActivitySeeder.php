<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use App\Models\StockSnapshot;
use App\Models\Product;
use App\Models\User;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        // Ambil user yang punya role 'admin' via pivot
        $admin = User::whereHas('roles', function ($q) {
            $q->where('slug', 'admin');
        })->first();

        // Fallback: kalau belum ada (mis. seeder Core gagal), bikin minimal 1 admin
        if (!$admin) {
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
            // kasih role admin (helper dari model User)
            $admin->assignRole('admin');
        }

        // Ambil product 0097-P (VO 5GB-3D), kalau kosong pakai product pertama
        $product = Product::firstWhere('product_code', '0097-P') ?? Product::first();

        // ===== Activity Logs =====
        ActivityLog::create([
            'user_id'     => $admin->id,
            'action'      => 'Seeder Init',
            'entity_type' => 'System',
            'entity_id'   => null,
            'description' => 'Seeder initial setup data dummy MP3 berhasil dibuat.',
        ]);

        if ($product) {
            ActivityLog::create([
                'user_id'     => $admin->id,
                'action'      => 'Stock Update',
                'entity_type' => 'Product',
                'entity_id'   => $product->id,
                'description' => 'Perubahan stok produk '.$product->product_code.' ('.$product->name.') oleh admin pusat.',
            ]);

            // ===== Stock Snapshots =====
            StockSnapshot::create([
                'owner_type'  => 'pusat',
                'owner_id'    => 0,
                'product_id'  => $product->id,
                'quantity'    => 100,
                'recorded_at' => now()->toDateString(),
            ]);
        }
    }
}
