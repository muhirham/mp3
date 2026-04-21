<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Upgrade stock_adjustment_items
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->bigInteger('qty_before')->change();
            $table->bigInteger('qty_after')->change();
            $table->bigInteger('qty_diff')->change();
            $table->bigInteger('purchase_price_before')->nullable()->change();
            $table->bigInteger('purchase_price_after')->nullable()->change();
            $table->bigInteger('selling_price_before')->nullable()->change();
            $table->bigInteger('selling_price_after')->nullable()->change();
        });

        // 2. Upgrade products (Prices)
        Schema::table('products', function (Blueprint $table) {
            $table->bigInteger('purchasing_price')->nullable()->change();
            $table->bigInteger('selling_price')->nullable()->change();
        });

        // 3. Upgrade stock_movements (Qty)
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (Schema::hasColumn('stock_movements', 'qty')) {
                    $table->bigInteger('qty')->change();
                }
                if (Schema::hasColumn('stock_movements', 'quantity')) {
                    $table->bigInteger('quantity')->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Not really safe to downgrade if data already exceeds INT range,
        // but for rollback completeness:
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->integer('qty_before')->change();
            $table->integer('qty_after')->change();
            $table->integer('qty_diff')->change();
        });
    }
};
