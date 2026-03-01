<?php

namespace Database\Seeders\Purchase;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            $this->command->warn('purchase_orders table not found.');
            return;
        }

        $supplier  = Supplier::first();
        $product   = Product::first();
        $warehouse = Warehouse::first();
        $user      = User::first();

        if (!$supplier || !$product || !$warehouse || !$user) {
            throw new \Exception('Supplier/Product/Warehouse/User belum ada.');
        }

        $poCode = 'PO-SEED-001';

        DB::table('purchase_orders')->updateOrInsert(
            ['po_code' => $poCode],
            [
                'supplier_id'     => $supplier->id,
                'ordered_by'      => $user->id,
                'status'          => 'ordered',
                'approval_status' => 'approved',
                'subtotal'        => 100000,
                'discount_total'  => 0,
                'grand_total'     => 100000,
                'notes'           => 'PO operasional rutin (real testing).',
                'ordered_at'      => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        $po = DB::table('purchase_orders')
            ->where('po_code', $poCode)
            ->first();

        DB::table('purchase_order_items')->updateOrInsert(
            [
                'purchase_order_id' => $po->id,
                'product_id'        => $product->id,
            ],
            [
                'warehouse_id' => $warehouse->id,
                'qty_ordered'  => 10,
                'qty_received' => 0,
                'unit_price'   => 10000,
                'line_total'   => 100000,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        $this->command->info('PurchaseOrder FULL TEST created.');
    }
}