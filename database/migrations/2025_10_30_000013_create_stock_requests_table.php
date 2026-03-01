<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_requests', function (Blueprint $t) {
            $t->id();
            $t->enum('requester_type', ['warehouse','sales']);
            $t->unsignedBigInteger('requester_id'); // warehouse.id atau users.id (sales)
            $t->enum('approver_type', ['admin','warehouse']);
            $t->unsignedBigInteger('approver_id')->nullable(); // users.id (admin/warehouse)
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->integer('quantity_requested');
            $t->integer('quantity_approved')->nullable();
            $t->enum('status', ['pending','approved','rejected','fulfilled'])->default('pending');
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('stock_requests');
    }
};