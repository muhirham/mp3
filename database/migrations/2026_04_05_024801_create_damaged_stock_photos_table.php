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
        Schema::create('damaged_stock_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damaged_stock_id')->constrained('damaged_stocks')->cascadeOnDelete();
            $table->string('path');
            $table->enum('kind', ['initial', 'action_proof', 'resolved'])->default('initial');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damaged_stock_photos');
    }
};
