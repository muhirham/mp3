<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->enum('from_type', ['supplier','pusat','warehouse','sales'])->nullable();
            $t->unsignedBigInteger('from_id')->nullable(); // suppliers.id / warehouses.id / users.id (sales) / null utk pusat
            $t->enum('to_type', ['pusat','warehouse','sales'])->nullable();
            $t->unsignedBigInteger('to_id')->nullable();
            $t->integer('quantity');
            $t->enum('status', ['pending','approved','completed','rejected'])->default('pending');
            $t->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('stock_movements');
    }
};