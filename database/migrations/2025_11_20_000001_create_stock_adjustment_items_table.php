<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_adjustment_id')
                  ->constrained('stock_adjustments')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->foreignId('product_id')
                  ->constrained()
                  ->cascadeOnUpdate();

            $table->integer('qty_before'); // stok sistem sebelum adjustment
            $table->integer('qty_after');  // stok fisik
            $table->integer('qty_diff');   // qty_after - qty_before (bisa -/+)

            $table->string('notes')->nullable(); // notes per item kalau perlu

            $table->timestamps();

            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
