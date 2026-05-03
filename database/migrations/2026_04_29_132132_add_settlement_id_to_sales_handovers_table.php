<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_handovers', function (Blueprint $table) {
            $table->unsignedBigInteger('settlement_id')->nullable()->after('transfer_proof_path');
            
            $table->foreign('settlement_id')->references('id')->on('warehouse_settlements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_handovers', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn('settlement_id');
        });
    }
};
