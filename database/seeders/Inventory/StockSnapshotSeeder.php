<?php

namespace Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\StockSnapshot;

class StockSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        if (!\Schema::hasTable((new StockSnapshot)->getTable())) return;

        $product = Product::first();
        if (!$product) return;

        $today = Carbon::now()->toDateString();

        StockSnapshot::updateOrCreate(
            [
                'owner_type'  => 'pusat',
                'owner_id'    => 0,
                'product_id'  => $product->id,
                'recorded_at' => $today,
            ],
            ['quantity' => 100]
        );

        $warehouse = Warehouse::first();
        if ($warehouse) {
            StockSnapshot::updateOrCreate(
                [
                    'owner_type'  => 'warehouse',
                    'owner_id'    => $warehouse->id,
                    'product_id'  => $product->id,
                    'recorded_at' => $today,
                ],
                ['quantity' => 50]
            );
        }

        $sales = User::first();
        if ($sales) {
            StockSnapshot::updateOrCreate(
                [
                    'owner_type'  => 'sales',
                    'owner_id'    => $sales->id,
                    'product_id'  => $product->id,
                    'recorded_at' => $today,
                ],
                ['quantity' => 20]
            );
        }
    }
}