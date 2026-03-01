<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('bom_transaction_items', function (Blueprint $t) {
            $t->id();

            $t->foreignId('bom_transaction_id')
                ->constrained('bom_transactions')
                ->cascadeOnDelete();

            $t->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->decimal('qty_used', 15, 2);

            $t->decimal('cost_per_unit', 15, 2)->default(0);
            $t->decimal('total_cost', 15, 2)->default(0);

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_transaction_items');
    }
};