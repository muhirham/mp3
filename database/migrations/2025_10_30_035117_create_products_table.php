<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('product_code', 50)->unique();
            $t->string('name', 150);
            $t->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $t->text('description')->nullable();
            $t->integer('purchasing_price');
            $t->integer('selling_price');
            $t->integer('stock_minimum')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('products');
    }
};