<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('boms', function (Blueprint $t) {
            $t->id();

            $t->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->string('bom_code')->unique();
            $t->integer('version')->default(1);
            $t->integer('output_qty')->default(1);

            $t->boolean('is_active')->default(true);

            $t->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $t->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boms');
    }
};