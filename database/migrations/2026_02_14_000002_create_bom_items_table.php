<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('bom_items', function (Blueprint $t) {
            $t->id();

            $t->foreignId('bom_id')
                ->constrained('boms')
                ->cascadeOnDelete();

            $t->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->decimal('quantity', 15, 2);

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
    }
};