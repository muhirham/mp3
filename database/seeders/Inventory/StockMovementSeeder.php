<?php

namespace Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\StockMovement;

class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        if (!\Schema::hasTable((new StockMovement)->getTable())) return;

        $product = Product::first();
        $warehouse = Warehouse::first();
        $user = User::first();

        if (!$product || !$warehouse || !$user) return;

        // Pusat -> Warehouse
        StockMovement::firstOrCreate(
            [
                'product_id' => $product->id,
                'from_type'  => 'pusat',
                'to_type'    => 'warehouse',
                'to_id'      => $warehouse->id,
            ],
            [
                'from_id'     => 0,
                'quantity'    => 200,
                'status'      => 'completed',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'note'        => 'Distribusi stok awal voucher ke depo (real testing).',
            ]
        );

        // Warehouse -> Sales
        StockMovement::firstOrCreate(
            [
                'product_id' => $product->id,
                'from_type'  => 'warehouse',
                'to_type'    => 'sales',
                'from_id'    => $warehouse->id,
                'to_id'      => $user->id,
            ],
            [
                'quantity'    => 50,
                'status'      => 'completed',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'note'        => 'Handover pagi: stok dibawa sales untuk jualan.',
            ]
        );
    }
}