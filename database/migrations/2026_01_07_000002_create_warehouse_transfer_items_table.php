<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
        {
            Schema::create('warehouse_transfer_items', function (Blueprint $t) {
            $t->id();

            $t->foreignId('warehouse_transfer_id')
                ->constrained()
                ->cascadeOnDelete();

            $t->foreignId('product_id')->constrained('products');

            // qty dokumen
            $t->integer('qty_transfer');

            // qty real (hasil GR)
            $t->integer('qty_good')->nullable();
            $t->integer('qty_damaged')->nullable();

            // snapshot harga
            $t->decimal('unit_cost', 15, 2);
            $t->decimal('subtotal_cost', 15, 2);

            // bukti GR (path file)
            $t->string('photo_good')->nullable();
            $t->string('photo_damaged')->nullable();

            $t->text('note')->nullable();

            $t->timestamps();
        });
        }

        public function down(): void
        {
            Schema::dropIfExists('warehouse_transfer_items');
        }
};