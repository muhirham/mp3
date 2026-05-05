<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Modul Request Restocks (Target Utama)
        if (Schema::hasTable('request_restocks')) {
            Schema::table('request_restocks', function (Blueprint $table) {
                $table->bigInteger('quantity_requested')->change();
                $table->bigInteger('quantity_received')->default(0)->change();
                $table->bigInteger('cost_per_item')->default(0)->change();
                $table->bigInteger('total_cost')->default(0)->change();
            });
        }

        // 2. Modul Restock Receipts (Penerimaan Barang)
        if (Schema::hasTable('restock_receipts')) {
            Schema::table('restock_receipts', function (Blueprint $table) {
                $table->bigInteger('qty_requested')->default(0)->change();
                $table->bigInteger('qty_good')->default(0)->change();
                $table->bigInteger('qty_damaged')->default(0)->change();
            });
        }

        // 3. Modul Stock Adjustment Items (Harga Modal & Jual)
        if (Schema::hasTable('stock_adjustment_items')) {
            Schema::table('stock_adjustment_items', function (Blueprint $table) {
                $table->bigInteger('qty_before')->change();
                $table->bigInteger('qty_after')->change();
                $table->bigInteger('qty_diff')->change();
                $table->bigInteger('purchase_price_before')->nullable()->change();
                $table->bigInteger('purchase_price_after')->nullable()->change();
                $table->bigInteger('selling_price_before')->nullable()->change();
                $table->bigInteger('selling_price_after')->nullable()->change();
            });
        }

        // 4. Modul Stock Movements & Snapshots
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->bigInteger('quantity')->change();
            });
        }
        if (Schema::hasTable('stock_snapshots')) {
            Schema::table('stock_snapshots', function (Blueprint $table) {
                $table->bigInteger('quantity')->default(0)->change();
            });
        }

        // 5. Modul Purchase Order Items
        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->bigInteger('qty_ordered')->change();
                $table->bigInteger('qty_received')->default(0)->change();
                // unit_price dan line_total biasanya sudah bigInteger/decimal, 
                // tapi kita pastikan ke bigInteger jika masih integer.
                $table->bigInteger('unit_price')->default(0)->change();
                $table->bigInteger('line_total')->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        // Rollback ke integer berisiko jika data sudah melebihi 2.1M.
        // Jadi kita kosongkan atau tetap di bigint untuk keamanan data.
    }
};
