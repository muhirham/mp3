<?php

namespace Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;

class StockLevelSeeder extends Seeder
{
    public function run(): void
    {
        $wh1 = Warehouse::where('warehouse_code', 'DEPO-BUKITTINGGI')->first();
        $wh2 = Warehouse::where('warehouse_code', 'DEPO-PADANG')->first();

        $sales_bkt = User::where('username', 'sales_bukittinggi')->first();
        $sales_pdg = User::where('username', 'sales_padang')->first();

        $products = Product::all();

        foreach ($products as $product) {

            StockLevel::updateOrCreate(
                ['owner_type' => 'pusat', 'owner_id' => 0, 'product_id' => $product->id],
                ['quantity' => 100]
            );

            if ($wh1)
                StockLevel::updateOrCreate(
                    ['owner_type' => 'warehouse', 'owner_id' => $wh1->id, 'product_id' => $product->id],
                    ['quantity' => 20]
                );

            if ($wh2)
                StockLevel::updateOrCreate(
                    ['owner_type' => 'warehouse', 'owner_id' => $wh2->id, 'product_id' => $product->id],
                    ['quantity' => 25]
                );

            if ($sales_bkt)
                StockLevel::updateOrCreate(
                    ['owner_type' => 'sales', 'owner_id' => $sales_bkt->id, 'product_id' => $product->id],
                    ['quantity' => 10]
                );

            if ($sales_pdg)
                StockLevel::updateOrCreate(
                    ['owner_type' => 'sales', 'owner_id' => $sales_pdg->id, 'product_id' => $product->id],
                    ['quantity' => 12]
                );
        }
    }
}