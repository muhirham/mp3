<?php

namespace Database\Seeders\Transfer;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\WarehouseTransfer;
use App\Models\Product;

class WarehouseTransferItemSeeder extends Seeder
{
    public function run(): void
    {
        $transfer = WarehouseTransfer::first();
        $product  = Product::first();

        if (!$transfer || !$product) return;

        DB::table('warehouse_transfer_items')->updateOrInsert(
            [
                'warehouse_transfer_id' => $transfer->id,
                'product_id'            => $product->id,
            ],
            [
                'qty_transfer'  => 5,
                'qty_good'      => 5,
                'qty_damaged'   => 0,
                'unit_cost'     => 10000,
                'subtotal_cost' => 50000,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );

        $this->command->info('WarehouseTransferItem FULL TEST created.');
    }
}