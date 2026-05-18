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
            $table->text('transfer_proof_path')->nullable()->change();
        });

        Schema::table('warehouse_settlements', function (Blueprint $table) {
            $table->text('proof_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_handovers', function (Blueprint $table) {
            $table->string('transfer_proof_path', 255)->nullable()->change();
        });

        Schema::table('warehouse_settlements', function (Blueprint $table) {
            $table->string('proof_path', 255)->nullable()->change();
        });
    }
};
