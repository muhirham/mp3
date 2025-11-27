<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->integer('quantity');
            $t->enum('condition', ['good','damaged','expired'])->default('good');
            $t->text('reason')->nullable();
            $t->enum('status', ['pending','approved','rejected','received'])->default('pending');
            $t->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sales_returns');
    }
};