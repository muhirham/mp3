<?php

namespace Database\Seeders\Sales;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SalesHandover;
use App\Models\Product;

class SalesHandoverItemSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('sales_handover_items')) return;

        $handover = SalesHandover::first();
        if (!$handover) {
            $this->command->warn('No Sales Handover found. Skipping items.');
            return;
        }

        $products = Product::take(2)->get();
        if ($products->isEmpty()) {
            $this->command->warn('No products found. Skipping handover items.');
            return;
        }

        foreach ($products as $index => $product) {

            $qtyStart     = 10 + $index;
            $qtyReturned  = 2;
            $qtySold      = $qtyStart - $qtyReturned;

            $unitPrice    = 10000 + ($index * 5000);

            $lineStart    = $qtyStart * $unitPrice;
            $lineSold     = $qtySold * $unitPrice;

            $discountPerUnit = 1000;
            $unitAfterDisc   = $unitPrice - $discountPerUnit;
            $discountTotal   = $discountPerUnit * $qtySold;
            $lineAfterDisc   = $qtySold * $unitAfterDisc;

            DB::table('sales_handover_items')->updateOrInsert(
                [
                    'handover_id' => $handover->id,
                    'product_id'  => $product->id,
                ],
                [
                    'qty_start'                    => $qtyStart,
                    'qty_returned'                 => $qtyReturned,
                    'qty_sold'                     => $qtySold,

                    'unit_price'                   => $unitPrice,
                    'line_total_start'             => $lineStart,
                    'line_total_sold'              => $lineSold,

                    'payment_qty'                  => $qtySold,
                    'payment_method'               => 'cash',
                    'payment_amount'               => $lineAfterDisc,
                    'payment_transfer_proof_path'  => null,
                    'payment_status'               => 'approved',
                    'payment_reject_reason'        => null,

                    'discount_per_unit'            => $discountPerUnit,
                    'discount_total'               => $discountTotal,
                    'unit_price_after_discount'    => $unitAfterDisc,
                    'line_total_after_discount'    => $lineAfterDisc,

                    'created_at'                   => now(),
                    'updated_at'                   => now(),
                ]
            );
        }

        $this->command->info('Sales handover items seeded successfully.');
    }
}