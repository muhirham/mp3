<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->date('date');
            $t->integer('total_sold')->default(0);
            $t->integer('total_revenue')->default(0);
            $t->integer('stock_remaining')->default(0);
            $t->integer('damaged_goods')->default(0);
            $t->integer('goods_returned')->default(0);
            $t->text('notes')->nullable();
            $t->enum('status', ['pending','approved','verified'])->default('pending');
            $t->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sales_reports');
    }
};