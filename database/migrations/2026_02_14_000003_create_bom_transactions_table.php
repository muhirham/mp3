<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('bom_transactions', function (Blueprint $t) {
            $t->id();

            $t->foreignId('bom_id')
                ->constrained('boms')
                ->cascadeOnDelete();

            $t->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->integer('production_qty');

            $t->decimal('total_cost', 15, 2)->default(0);

            $t->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_transactions');
    }
};