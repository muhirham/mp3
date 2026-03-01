<?php

namespace Database\Seeders\Bom;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;
use App\Models\User;
use App\Models\Bom;

class BomSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('boms')) return;

        $finishedProduct = Product::first();
        $user = User::first();

        if (!$finishedProduct) return;

        Bom::updateOrCreate(
            [
                'bom_code' => 'BOM-0001',
            ],
            [
                'product_id' => $finishedProduct->id,
                'version'    => 1,
                'output_qty' => 1,
                'is_active'  => true,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]
        );
    }
}
