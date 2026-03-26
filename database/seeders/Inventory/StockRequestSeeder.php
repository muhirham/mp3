<?php

namespace Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use App\Models\StockRequest;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Arr;

class StockRequestSeeder extends Seeder
{
    public function run(): void
    {
        $salesUsers = User::whereHas('roles', function ($q) {
            $q->where('name', 'sales');
        })->get();

        $warehouses = Warehouse::all();
        $products   = Product::all();

        if ($salesUsers->isEmpty() || $warehouses->isEmpty() || $products->isEmpty()) {
            $this->command->warn('Pastikan users (sales), warehouses, dan products sudah ada.');
            return;
        }

        $statuses = ['pending','approved','rejected','completed'];

        for ($i = 0; $i < 30; $i++) {

            $sales     = $salesUsers->random();
            $warehouse = $warehouses->random();
            $product   = $products->random();

            $status = Arr::random($statuses);

            $approvedQty = null;
            $approvedBy  = null;

            if (in_array($status, ['approved','completed'])) {
                $approvedQty = rand(1, 20);
                $approvedBy  = User::whereHas('roles', fn($q) =>
                    $q->whereIn('name',['warehouse','admin','superadmin'])
                )->inRandomOrder()->value('id');
            }

            StockRequest::create([
                'user_id'             => $sales->id,
                'warehouse_id'        => $warehouse->id,
                'product_id'          => $product->id,
                'quantity_requested'  => rand(1, 20),
                'quantity_approved'   => $approvedQty,
                'status'              => $status,
                'approved_by'         => $approvedBy,
                'sales_handover_id'   => null,
                'note'                => 'Seeder generated request',
            ]);
        }
    }
}