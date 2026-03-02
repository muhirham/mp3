<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();

            // Kode dokumen, contoh: ADJ-20251124-0001
            $table->string('adj_code')->unique();

            // DROPDOWN 1: Mode stok (per item / semua produk)
            // single = input manual (per item), all = semua produk di lokasi tsb
            $table->string('stock_scope_mode', 20)->default('single');

            // DROPDOWN 2: Mode update harga
            // none | update_purchase | update_selling | update_both
            $table->string('price_update_mode', 30)->default('none');

            // Boleh NULL kalau adjustment ke Stock Central (pusat)
            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            // Tanggal dokumen adjustment
            $table->date('adj_date');

            // Catatan / alasan umum
            $table->text('notes')->nullable();

            // Siapa yang bikin dokumen
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnUpdate();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
