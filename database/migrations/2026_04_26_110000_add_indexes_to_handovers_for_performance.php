<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_handovers', function (Blueprint $table) {
            // Index untuk mempercepat filter laporan harian
            $table->index('handover_date');
            $table->index('status');
            $table->index('is_direct_sale');
            $table->index('buyer_type');
            $table->index('customer_name');
        });

        Schema::table('sales_handover_items', function (Blueprint $table) {
            // Index untuk mempercepat pengecekan status pembayaran/approval
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales_handovers', function (Blueprint $table) {
            $table->dropIndex(['handover_date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['is_direct_sale']);
            $table->dropIndex(['buyer_type']);
            $table->dropIndex(['customer_name']);
        });

        Schema::table('sales_handover_items', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
        });
    }
};
