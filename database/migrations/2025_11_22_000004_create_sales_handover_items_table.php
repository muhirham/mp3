<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        
        /*
         * DETAIL: sales_handover_items
         * Tiap baris = 1 produk yang dibawa sales
         */
        Schema::create('sales_handover_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('handover_id')
                ->constrained('sales_handovers')
                ->cascadeOnDelete();

            $table->foreignId('product_id')->constrained('products');

            // Qty pagi & sore
            $table->integer('qty_start');                 // qty dibawa pagi
            $table->integer('qty_returned')->default(0);  // qty sisa (good+rusak) sore
            $table->integer('qty_sold')->default(0);      // qty_start - qty_returned

            // Harga & nilai (pakai bigInteger juga, isi rupiah utuh)
            $table->unsignedBigInteger('unit_price')->default(0);        // harga satuan
            $table->unsignedBigInteger('line_total_start')->default(0);  // qty_start * unit_price
            $table->unsignedBigInteger('line_total_sold')->default(0);   // qty_sold * unit_price

            // ====== PAYMENT PER ITEM ======
            $table->integer('payment_qty')->default(0); // qty yang dibayar
            $table->enum('payment_method', ['cash', 'transfer'])->nullable();
            $table->unsignedBigInteger('payment_amount')->default(0);
            $table->string('payment_transfer_proof_path')->nullable();
            $table->enum('payment_status', ['pending', 'approved', 'rejected'])
                ->nullable();
            $table->text('payment_reject_reason')->nullable();
            // ====== END PAYMENT ======

            $table->timestamps();

            // 1 produk cuma boleh muncul sekali per handover
            $table->unique(['handover_id', 'product_id']);

            // ===== DISCOUNT PER ITEM (SET PAGI) =====
            $table->unsignedBigInteger('discount_per_unit')->default(0);
            // diskon per pcs

            $table->unsignedBigInteger('discount_total')->default(0);
            // discount_per_unit * qty_sold

            $table->unsignedBigInteger('unit_price_after_discount')->default(0);
            // unit_price - discount_per_unit

            $table->unsignedBigInteger('line_total_after_discount')->default(0);
            // qty_sold * unit_price_after_discount

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_handover_items');
    }
};