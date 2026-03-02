<?php

namespace Database\Seeders\Bom;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\BomTransaction;
use App\Models\BomItem;

class BomTransactionItemSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('bom_transaction_items')) return;

        $transaction = BomTransaction::first();
        if (!$transaction) return;

        $bomItems = BomItem::where('bom_id', $transaction->bom_id)->get();
        if ($bomItems->isEmpty()) return;

        $productionQty = $transaction->production_qty;
        $grandTotal = 0;

        foreach ($bomItems as $item) {

            $costPerUnit = 10000; // simulasi HPP bahan
            $qtyUsed = $item->quantity * $productionQty;
            $totalCost = $qtyUsed * $costPerUnit;

            $grandTotal += $totalCost;

            DB::table('bom_transaction_items')->updateOrInsert(
                [
                    'bom_transaction_id' => $transaction->id,
                    'material_id'        => $item->material_id,
                ],
                [
                    'qty_used'      => $qtyUsed,
                    'cost_per_unit' => $costPerUnit,
                    'total_cost'    => $totalCost,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]
            );
        }

        // update total_cost header
        $transaction->update([
            'total_cost' => $grandTotal
        ]);
    }
}