<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_handover_items', function (Blueprint $table) {
            $table->integer('payment_cash_qty')->default(0)->after('payment_qty');
            $table->unsignedBigInteger('payment_cash_amount')->default(0)->after('payment_cash_qty');
            $table->integer('payment_transfer_qty')->default(0)->after('payment_cash_amount');
            $table->unsignedBigInteger('payment_transfer_amount')->default(0)->after('payment_transfer_qty');
        });
    }

    public function down(): void
    {
        Schema::table('sales_handover_items', function (Blueprint $table) {
            $table->dropColumn([
                'payment_cash_qty',
                'payment_cash_amount',
                'payment_transfer_qty',
                'payment_transfer_amount',
            ]);
        });
    }
};
