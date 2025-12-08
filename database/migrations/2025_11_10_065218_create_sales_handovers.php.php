<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_handovers', function (Blueprint $table) {
            $table->id();

            // contoh: HDO-251207-0001
            $table->string('code', 50)->unique();

            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('sales_id')->constrained('users');
            $table->date('handover_date');

            // issued      : sudah serah terima pagi, belum rekonsiliasi
            // reconciled  : sudah rekonsiliasi sore, laporan close
            // cancelled   : dibatalkan
            $table->enum('status', ['issued', 'reconciled', 'cancelled'])->default('issued');

            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('reconciled_by')->nullable()->constrained('users');
            $table->timestamp('reconciled_at')->nullable();

            // total nilai barang keluar & terjual (berdasarkan selling_price)
            $table->decimal('total_dispatched_amount', 15, 2)->default(0);
            $table->decimal('total_sold_amount', 15, 2)->default(0);

            // OTP pagi (dipakai validasi sore)
            $table->string('morning_otp_hash', 255)->nullable();
            $table->timestamp('morning_otp_expires_at')->nullable();

            // OTP closing sore (hanya dikirim ke email sebagai bukti)
            $table->string('closing_otp_hash', 255)->nullable();
            $table->timestamp('closing_otp_expires_at')->nullable();

            // Setoran uang
            $table->decimal('cash_amount', 15, 2)->default(0);
            $table->decimal('transfer_amount', 15, 2)->default(0);
            $table->string('transfer_proof_path')->nullable(); // path gambar bukti tf

            $table->timestamps();
        });

        Schema::create('sales_handover_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('handover_id')
                ->constrained('sales_handovers')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products');

            // qty
            $table->integer('qty_dispatched');                    // dibawa pagi
            $table->integer('qty_returned_good')->default(0);     // balik bagus
            $table->integer('qty_returned_damaged')->default(0);  // balik rusak
            $table->integer('qty_sold')->default(0);              // dihitung sore

            // harga
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total_dispatched', 15, 2)->default(0); // qty_dispatched * price
            $table->decimal('line_total_sold', 15, 2)->default(0);       // qty_sold * price

            $table->timestamps();

            $table->unique(['handover_id', 'product_id']); // 1 produk sekali saja per handover
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_handover_items');
        Schema::dropIfExists('sales_handovers');
    }
};
