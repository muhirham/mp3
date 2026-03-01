<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |-----------------------------------------
        | purchase_order_items (DETAIL)
        |-----------------------------------------
        | - Item per produk di dalam PO
        */
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('purchase_order_id')->index();

            // LINK BALIK KE REQUEST RESTOCK (kalau PO berasal dari RR)
            $t->unsignedBigInteger('request_id')->nullable()->index();

            $t->unsignedBigInteger('product_id')->index();
            $t->unsignedBigInteger('warehouse_id')->nullable()->index();

            // Qty & harga
            $t->integer('qty_ordered');
            $t->integer('qty_received')->default(0);

            $t->decimal('unit_price', 16, 2)->default(0);

            $t->enum('discount_type', ['percent','amount'])->nullable();
            $t->decimal('discount_value', 16, 2)->nullable();

            $t->decimal('line_total', 16, 2)->default(0);

            $t->text('notes')->nullable();

            $t->timestamps();
        });

        /*
        |-----------------------------------------
        | ALTER restock_receipts: tambah purchase_order_id
        |-----------------------------------------
        | - Biar GR bisa di-link ke PO
        */
        if (Schema::hasTable('restock_receipts')) {
            Schema::table('restock_receipts', function (Blueprint $t) {
                if (! Schema::hasColumn('restock_receipts', 'purchase_order_id')) {
                    $t->unsignedBigInteger('purchase_order_id')
                        ->nullable()
                        ->after('id')
                        ->index();
                }

                // pastikan 'code' nullable supaya nggak error 1364
                if (Schema::hasColumn('restock_receipts', 'code')) {
                    $t->string('code', 50)->nullable()->change();
                } else {
                    $t->string('code', 50)
                        ->nullable()
                        ->after('product_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restock_receipts')) {
            Schema::table('restock_receipts', function (Blueprint $t) {
                if (Schema::hasColumn('restock_receipts', 'purchase_order_id')) {
                    $t->dropColumn('purchase_order_id');
                }
            });
        }

        Schema::dropIfExists('purchase_order_items');
    }
};