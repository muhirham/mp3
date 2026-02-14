<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('products', function (Blueprint $t) {

            $t->id();

            // 🔹 Identity
            $t->string('product_code', 50)->unique();
            $t->string('name', 150);

            // 🔹 Category (grouping UI)
            $t->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            // 🔹 Behavior (logic system)
            $t->enum('product_type', [
                'material',   // bahan baku
                'finished',   // hasil produksi
                'normal'      // barang biasa
            ])->default('normal');

            // 🔹 Description
            $t->text('description')->nullable();

            // 🔹 Pricing
            $t->decimal('purchasing_price', 15, 2)->default(0);
            $t->decimal('standard_cost', 15, 2)->nullable(); // HPP produksi
            $t->decimal('selling_price', 15, 2)->default(0);

            // 🔹 Stock control
            $t->bigInteger('stock_minimum')->nullable();

            // 🔹 Optional relations
            $t->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            $t->foreignId('package_id')
                ->nullable()
                ->constrained('packages')
                ->nullOnDelete();

                $t->boolean('is_active')->default(true);

            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};
