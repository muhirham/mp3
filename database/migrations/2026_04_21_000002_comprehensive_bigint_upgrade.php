<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Modul Purchase Order
        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                // qty_ordered dan qty_received masih INT(11)
                $table->bigInteger('qty_ordered')->change();
                $table->bigInteger('qty_received')->default(0)->change();
            });
        }
        // Note: purchase_orders table uses decimal(16,2) for totals which is already SAFE (up to 9 Trillion).

        // 2. Modul Sales (Penjualan)
        if (Schema::hasTable('sales_handover_items')) {
            Schema::table('sales_handover_items', function (Blueprint $table) {
                $table->bigInteger('qty_start')->change();
                $table->bigInteger('qty_returned')->default(0)->change();
                $table->bigInteger('qty_sold')->default(0)->change();
                $table->bigInteger('payment_qty')->default(0)->change();
                $table->bigInteger('payment_cash_qty')->default(0)->change();
                $table->bigInteger('payment_transfer_qty')->default(0)->change();
            });
        }

        if (Schema::hasTable('sales_reports')) {
            Schema::table('sales_reports', function (Blueprint $table) {
                $table->bigInteger('total_sold')->default(0)->change();
                $table->bigInteger('total_revenue')->default(0)->change();
                $table->bigInteger('stock_remaining')->default(0)->change();
                $table->bigInteger('damaged_goods')->default(0)->change();
                $table->bigInteger('goods_returned')->default(0)->change();
            });
        }

        if (Schema::hasTable('sales_returns')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->bigInteger('quantity')->change();
            });
        }

        // 3. Modul Inventory & Gudang
        if (Schema::hasTable('stock_requests')) {
            Schema::table('stock_requests', function (Blueprint $table) {
                $table->bigInteger('quantity_requested')->change();
                $table->bigInteger('quantity_approved')->nullable()->change();
            });
        }

        if (Schema::hasTable('warehouse_transfer_items')) {
            Schema::table('warehouse_transfer_items', function (Blueprint $table) {
                $table->bigInteger('qty_transfer')->change();
                $table->bigInteger('qty_good')->nullable()->change();
                $table->bigInteger('qty_damaged')->nullable()->change();
            });
        }

        if (Schema::hasTable('damaged_stocks')) {
            Schema::table('damaged_stocks', function (Blueprint $table) {
                $table->bigInteger('quantity')->change();
            });
        }

        // 4. Modul Produksi (BOM)
        if (Schema::hasTable('bom_transactions')) {
            Schema::table('bom_transactions', function (Blueprint $table) {
                $table->bigInteger('production_qty')->change();
            });
        }

        if (Schema::hasTable('boms')) {
            Schema::table('boms', function (Blueprint $table) {
                $table->bigInteger('output_qty')->default(1)->change();
            });
        }
    }

    public function down(): void
    {
        // Rollback is complex for BIGINT to INT if data exists.
    }
};
