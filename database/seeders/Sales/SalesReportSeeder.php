<?php

namespace Database\Seeders\Sales;

use Illuminate\Database\Seeder;
use App\Models\SalesReport;
use App\Models\User;
use App\Models\Warehouse;

class SalesReportSeeder extends Seeder
{
    public function run(): void
    {
        $sales     = User::first();
        $warehouse = Warehouse::first();

        if (!$sales || !$warehouse) return;

        SalesReport::updateOrCreate(
            [
                'sales_id'     => $sales->id,
                'warehouse_id' => $warehouse->id,
                'date'         => now()->toDateString(),
            ],
            [
                'total_sold'      => 20,
                'total_revenue'   => 200000,
                'stock_remaining' => 5,
                'damaged_goods'   => 1,
                'goods_returned'  => 1,
                'status'          => 'approved',
                'approved_by'     => $sales->id,
                'approved_at'     => now(),
            ]
        );

        $this->command->info('SalesReport FULL TEST created.');
    }
}