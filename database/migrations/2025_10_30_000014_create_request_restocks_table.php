<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('request_restocks', function (Blueprint $t) {
            $t->id();

            // === NOMOR DOKUMEN RR (shared untuk banyak item) ===
            // contoh: RR-20251126-0001
            $t->string('code', 30)->index();

            // Sumber
            $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Gudang & user yang mengajukan
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            // Angka-angka
            $t->integer('quantity_requested');
            $t->integer('quantity_received')->default(0);
            $t->integer('cost_per_item')->default(0);
            $t->integer('total_cost')->default(0);

            // Status
            $t->enum('status', ['pending','approved','ordered','received','cancelled'])
            ->default('pending');

            // Approval
            $t->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('received_at')->nullable();

            // Catatan
            $t->text('note')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('request_restocks');
    }
};
