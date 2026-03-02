<?php

namespace Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;

class RequestRestockSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('request_restocks')) {
            $this->command->warn('request_restocks table not found.');
            return;
        }

        $supplier  = Supplier::first();
        $product   = Product::first();
        $warehouse = Warehouse::first();
        $user      = User::first();

        if (!$supplier || !$product || !$warehouse || !$user) {
            throw new \Exception('Supplier/Product/Warehouse/User belum ada.');
        }

        $code = 'RR-' . Carbon::now()->format('Ymd') . '-0001';

        DB::table('request_restocks')->updateOrInsert(
            ['code' => $code],
            [
                'supplier_id'        => $supplier->id,
                'product_id'         => $product->id,
                'warehouse_id'       => $warehouse->id,
                'requested_by'       => $user->id,

                'quantity_requested' => 5,
                'quantity_received'  => 5,
                'cost_per_item'      => 10000,
                'total_cost'         => 50000,

                'status'             => 'received',

                'approved_by'        => $user->id,
                'approved_at'        => now(),
                'received_at'        => now(),

                'note'               => 'Seeder restock test sesuai migration.',

                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );

        $this->command->info('RequestRestock FULL TEST created.');
    }
}