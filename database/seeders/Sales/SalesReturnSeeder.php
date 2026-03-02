<?php

namespace Database\Seeders\Sales;

use Illuminate\Database\Seeder;
use App\Models\SalesReturn;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\SalesHandover;

class SalesReturnSeeder extends Seeder
{
    public function run(): void
    {
        if (!\Schema::hasTable((new SalesReturn)->getTable())) return;

        $handover = SalesHandover::first();
        $warehouse = Warehouse::first();
        $product = Product::first();
        $user = User::first();

        if (!$handover || !$warehouse || !$product || !$user) return;

        SalesReturn::updateOrCreate(
            [
                'sales_id'     => $user->id,
                'warehouse_id' => $warehouse->id,
                'handover_id'  => $handover->id,
                'product_id'   => $product->id,
                'quantity'     => 1,
            ],
            [
                'condition'   => 'damaged',
                'reason'      => 'Produk rusak / tidak layak jual (real testing).',
                'status'      => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]
        );
    }
}