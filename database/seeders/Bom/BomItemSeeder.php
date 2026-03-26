<?php

namespace Database\Seeders\Bom;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Bom;
use App\Models\Product;

class BomItemSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('bom_items')) return;

        $bom = Bom::first();
        if (!$bom) return;

        $materials = Product::where('id', '!=', $bom->product_id)
            ->take(2)
            ->get();

        if ($materials->count() < 1) return;

        foreach ($materials as $index => $material) {

            DB::table('bom_items')->updateOrInsert(
                [
                    'bom_id'      => $bom->id,
                    'material_id' => $material->id,
                ],
                [
                    'quantity'   => 2 + $index, // contoh variasi qty
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}