<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_levels', function (Blueprint $t) {
            $t->id();
            $t->enum('owner_type', ['pusat','warehouse','sales']);
            $t->unsignedBigInteger('owner_id'); // warehouse.id atau users.id (role sales)
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->integer('quantity')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('stock_levels');
    }
};