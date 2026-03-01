<?php

namespace Database\Seeders\Inventory;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;

class StockAdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('stock_adjustments')) {
            $this->command->warn('Table stock_adjustments not found.');
            return;
        }

        $user = User::first();
        $product = Product::first();

        if (!$user || !$product) {
            $this->command->warn('User or Product not found. Seeder skipped.');
            return;
        }

        $warehouse = Warehouse::first(); // boleh null

        DB::transaction(function () use ($user, $warehouse, $product) {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ CREATE HEADER (PASTI MASUK)
            |--------------------------------------------------------------------------
            */

            $adjCode = 'ADJ-' . Carbon::now()->format('Ymd') . '-0001';

            DB::table('stock_adjustments')->updateOrInsert(
                ['adj_code' => $adjCode],
                [
                    'stock_scope_mode'  => 'single',
                    'price_update_mode' => 'update_both',
                    'warehouse_id'      => $warehouse?->id,
                    'adj_date'          => now()->toDateString(),
                    'notes'             => 'Adjustment stok & harga (real testing).',
                    'created_by'        => $user->id,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );

            // Ambil ID header
            $adjustment = DB::table('stock_adjustments')
                ->where('adj_code', $adjCode)
                ->first();

            if (!$adjustment) {
                throw new \Exception('Stock Adjustment header gagal dibuat.');
            }

            /*
            |--------------------------------------------------------------------------
            | 2️⃣ CREATE ITEM
            |--------------------------------------------------------------------------
            */

            if (!Schema::hasTable('stock_adjustment_items')) return;

            $qtyBefore = 50;
            $qtyAfter  = 45;
            $qtyDiff   = $qtyAfter - $qtyBefore;

            $purchaseBefore = $product->purchasing_price ?? 10000;
            $purchaseAfter  = $purchaseBefore + 500;

            $sellingBefore  = $product->selling_price ?? 12000;
            $sellingAfter   = $sellingBefore + 1000;

            DB::table('stock_adjustment_items')->updateOrInsert(
                [
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id'          => $product->id,
                ],
                [
                    'qty_before' => $qtyBefore,
                    'qty_after'  => $qtyAfter,
                    'qty_diff'   => $qtyDiff,

                    'purchase_price_before' => $purchaseBefore,
                    'purchase_price_after'  => $purchaseAfter,
                    'selling_price_before'  => $sellingBefore,
                    'selling_price_after'   => $sellingAfter,

                    'notes'      => 'Penyesuaian stok karena selisih fisik & update harga.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        $this->command->info('StockAdjustment FULL TEST created successfully.');
    }
}