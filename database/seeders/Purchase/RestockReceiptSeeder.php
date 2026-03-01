<?php

namespace Database\Seeders\Purchase;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\User;

class RestockReceiptSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('restock_receipts')) {
            $this->command->warn('restock_receipts table not found.');
            return;
        }

        $product   = Product::first();
        $supplier  = Supplier::first();
        $warehouse = Warehouse::first();
        $user      = User::first();

        if (!$product || !$supplier || !$warehouse || !$user) {
            throw new \Exception('Data dasar belum lengkap untuk RestockReceipt.');
        }

        $code = 'GR-' . Carbon::now()->format('Ym') . '-0001';

        DB::table('restock_receipts')->updateOrInsert(
            ['code' => $code],
            [
                'request_id'    => null,
                'warehouse_id'  => $warehouse->id,
                'supplier_id'   => $supplier->id,
                'product_id'    => $product->id,
                'qty_requested' => 10,
                'qty_good'      => 9,
                'qty_damaged'   => 1,
                'notes'         => 'Penerimaan barang dari supplier (real testing).',
                'received_by'   => $user->id,
                'received_at'   => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );

        $this->command->info('RestockReceipt FULL TEST created.');
    }
}