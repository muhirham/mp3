<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_handover_items', function (Blueprint $table) {
            // mode diskon: unit (per pcs) atau fixed (total baris/bundle)
            $table->string('discount_mode', 20)->default('unit')->after('discount_per_unit');
            
            // kolom buat nyimpen nominal diskon utuh (fixed)
            $table->unsignedBigInteger('discount_fixed_amount')->default(0)->after('discount_mode');
        });
    }

    public function down(): void
    {
        Schema::table('sales_handover_items', function (Blueprint $table) {
            $table->dropColumn(['discount_mode', 'discount_fixed_amount']);
        });
    }
};
