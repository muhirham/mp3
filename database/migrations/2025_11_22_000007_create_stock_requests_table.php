<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('stock_requests', function (Blueprint $t) {
            $t->id();

            // siapa yang request (sales)
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // ambil dari warehouse mana
            $t->foreignId('warehouse_id')->constrained('warehouses');

            // product & qty
            $t->foreignId('product_id')->constrained('products');
            $t->integer('quantity_requested');
            $t->integer('quantity_approved')->nullable();

            // status flow
            $t->enum('status', ['pending','approved','rejected','completed'])
              ->default('pending');

            // siapa approve
            $t->foreignId('approved_by')
              ->nullable()
              ->constrained('users');

            // link ke HDO (setelah approve)
            $t->foreignId('sales_handover_id')
              ->nullable()
              ->constrained('sales_handovers')
              ->nullOnDelete();

            $t->text('note')->nullable();

            $t->timestamps();

            // index penting
            $t->index(['status']);
            $t->index(['user_id']);
            $t->index(['warehouse_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_requests');
    }
};