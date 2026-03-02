<?php

namespace Database\Seeders;

use Database\Seeders\Bom\BomItemSeeder;
use Database\Seeders\Bom\BomSeeder;
use Database\Seeders\Bom\BomTransactionItemSeeder;
use Database\Seeders\Bom\BomTransactionSeeder;
use Database\Seeders\Core\CategorySeeder;
use Database\Seeders\Core\CompanySeeder;
use Database\Seeders\Core\PackageSeeder;
use Database\Seeders\Core\ProductSeeder;
use Database\Seeders\Core\RoleSeeder;
use Database\Seeders\Core\SupplierSeeder;
use Database\Seeders\Core\UserSeeder;
use Database\Seeders\Core\WarehouseSeeder;
use Database\Seeders\Inventory\RequestRestockSeeder;
use Database\Seeders\Inventory\StockAdjustmentSeeder;
use Database\Seeders\Inventory\StockLevelSeeder;
use Database\Seeders\Inventory\StockMovementSeeder;
use Database\Seeders\Inventory\StockRequestSeeder;
use Database\Seeders\Inventory\StockSnapshotSeeder;
use Database\Seeders\Purchase\PurchaseOrderSeeder;
use Database\Seeders\Purchase\RestockReceiptSeeder;
use Database\Seeders\Sales\SalesHandoverItemSeeder;
use Database\Seeders\Sales\SalesHandoverSeeder;
use Database\Seeders\Sales\SalesReportSeeder;
use Database\Seeders\Sales\SalesReturnSeeder;
use Database\Seeders\System\ActivityLogSeeder;
use Database\Seeders\System\OperationAdminSeeder;
use Database\Seeders\Transfer\WarehouseTransferItemSeeder;
use Database\Seeders\Transfer\WarehouseTransferLogSeeder;
use Database\Seeders\Transfer\WarehouseTransferSeeder;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

          // CORE
        CompanySeeder::class,
        RoleSeeder::class,
        WarehouseSeeder::class,
        UserSeeder::class,
        CategorySeeder::class,
        SupplierSeeder::class,
        PackageSeeder::class,
        ProductSeeder::class,

        // SYSTEM
        OperationAdminSeeder::class,
        ActivityLogSeeder::class,

        // INVENTORY
        StockLevelSeeder::class,
        StockSnapshotSeeder::class,
        StockMovementSeeder::class,
        StockRequestSeeder::class,
        RequestRestockSeeder::class,
        StockAdjustmentSeeder::class,

        // PURCHASE
        PurchaseOrderSeeder::class,
        RestockReceiptSeeder::class,

        // SALES
        SalesHandoverSeeder::class,
        SalesHandoverItemSeeder::class,
        SalesReportSeeder::class,
        SalesReturnSeeder::class,

        // TRANSFER
        WarehouseTransferSeeder::class,
        WarehouseTransferItemSeeder::class,
        WarehouseTransferLogSeeder::class,

        // BOM
        BomSeeder::class,
        BomItemSeeder::class,
        BomTransactionSeeder::class,
        BomTransactionItemSeeder::class,
        ]);
    }
}