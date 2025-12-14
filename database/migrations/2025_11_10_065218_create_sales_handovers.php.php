<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * HEADER: sales_handovers
         * 1 record = 1x handover (pagi–sore) per sales per hari
         */
        Schema::create('sales_handovers', function (Blueprint $table) {
            $table->id();

            // Contoh: HDO-251208-0001
            $table->string('code', 50)->unique();

            // Relasi
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('sales_id')->constrained('users');

            $table->date('handover_date');

            // draft → waiting_morning_otp → on_sales → waiting_evening_otp → closed / cancelled
            $table->enum('status', [
                'draft',
                'waiting_morning_otp',
                'on_sales',
                'waiting_evening_otp',
                'closed',
                'cancelled',
            ])->default('draft');

            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');

            // ====== NOMINAL UANG (pakai unsignedBigInteger) ======
            // nilai total bawaan pagi (sum line_total_start semua item)
            $table->unsignedBigInteger('total_dispatched_amount')->default(0);

            // nilai total penjualan (sum line_total_sold semua item) setelah closing sore
            $table->unsignedBigInteger('total_sold_amount')->default(0);

            // FLAG: diisi oleh SALES sekali saja
            $table->boolean('evening_filled_by_sales')->default(false);
            $table->timestamp('evening_filled_at')->nullable();

            // setoran tunai & transfer dari sales (header-level recap)
            $table->unsignedBigInteger('cash_amount')->default(0);
            $table->unsignedBigInteger('transfer_amount')->default(0);

            // path file bukti transfer (kalau mau simpan bukti umum di header)
            $table->string('transfer_proof_path')->nullable();

            // ====== OTP PAGI ======
            $table->string('morning_otp_hash', 255)->nullable();
            $table->timestamp('morning_otp_sent_at')->nullable();
            $table->timestamp('morning_otp_verified_at')->nullable();

            // ====== OTP SORE (closing) ======
            $table->string('evening_otp_hash', 255)->nullable();
            $table->timestamp('evening_otp_sent_at')->nullable();
            $table->timestamp('evening_otp_verified_at')->nullable();

            $table->timestamps();
        });

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_handover_items');
        Schema::dropIfExists('sales_handovers');
    }
};
