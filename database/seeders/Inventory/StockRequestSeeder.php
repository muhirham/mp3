<?php

namespace Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;
use App\Models\StockRequest;

class StockRequestSeeder extends Seeder
{
    public function run(): void
    {
        if (!\Schema::hasTable((new StockRequest)->getTable())) return;

        $product = Product::first();
        $sales   = User::first();
        $wh      = User::first();

        if (!$product || !$sales || !$wh) return;

        StockRequest::updateOrCreate(
            [
                'requester_type' => 'sales',
                'requester_id'   => $sales->id,
                'product_id'     => $product->id,
            ],
            [
                'status'             => 'approved',
                'approver_type'      => 'warehouse',
                'approver_id'        => $wh->id,
                'quantity_requested' => 20,
                'quantity_approved'  => 20,
                'note'               => 'Permintaan stok voucher untuk operasional penjualan harian.',
            ]
        );
    }
}