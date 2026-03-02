<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('restock_receipts', function (Blueprint $t) {
            $t->id();
            // hubungan ke request_restocks / restocks (tanpa FK biar fleksibel)
            $t->unsignedBigInteger('request_id')->nullable()->index();       // id dari request restock
            $t->unsignedBigInteger('warehouse_id')->nullable()->index();
            $t->unsignedBigInteger('supplier_id')->nullable()->index();
            $t->unsignedBigInteger('product_id')->index();

            $t->string('code', 30)->unique();                  // GRN code: GR-202511-0001
            $t->unsignedInteger('qty_requested')->default(0);
            $t->unsignedInteger('qty_good')->default(0);       // yang layak masuk stok
            $t->unsignedInteger('qty_damaged')->default(0);    // yang rusak/tidak layak  // opsional
            $t->text('notes')->nullable();

            $t->unsignedBigInteger('received_by')->nullable(); // user penerima
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('restock_receipts');
    }
};
