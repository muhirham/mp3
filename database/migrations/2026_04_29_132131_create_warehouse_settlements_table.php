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
        Schema::create('warehouse_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('admin_id');
            $table->date('settlement_date');
            $table->bigInteger('total_cash_amount')->default(0);
            $table->bigInteger('total_transfer_amount')->default(0);
            $table->string('proof_path')->nullable();
            $table->timestamps();
            
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_settlements');
    }
};
