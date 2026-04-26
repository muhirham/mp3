<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_handovers', function (Blueprint $table) {
            // Penanda transaksi bypass gudang
            $table->boolean('is_direct_sale')->default(false)->after('status');
            
            // Tipe pembeli: sales, pareto, umum
            $table->string('buyer_type', 20)->default('sales')->after('is_direct_sale');
            
            // Nama pembeli (untuk pareto/umum)
            $table->string('customer_name')->nullable()->after('buyer_type');
            
            // Optional: ID Pareto jika nanti ada master datanya
            $table->unsignedBigInteger('pareto_id')->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('sales_handovers', function (Blueprint $table) {
            $table->dropColumn(['is_direct_sale', 'buyer_type', 'customer_name', 'pareto_id']);
        });
    }
};
