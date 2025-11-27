<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------- purchase_orders (HEADER) ----------
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->string('po_code', 50)->unique();
            $t->unsignedBigInteger('supplier_id')->nullable()->index();
            $t->unsignedBigInteger('ordered_by')->nullable()->index();
            $t->enum('status', [
                'draft',
                'approved',
                'ordered',
                'partially_received',
                'completed',
                'cancelled'
            ])->default('draft')->index();
            $t->decimal('subtotal', 16, 2)->default(0);
            $t->decimal('discount_total', 16, 2)->default(0);
            $t->decimal('grand_total', 16, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamp('ordered_at')->nullable();
            $t->timestamps();
        });

        // -------- purchase_order_items (DETAIL) ----------
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('purchase_order_id')->index();

            // LINK BALIK KE REQUEST RESTOCK (penting utk sync qty & harga)
            $t->unsignedBigInteger('request_id')->nullable()->index();

            $t->unsignedBigInteger('product_id')->index();
            $t->unsignedBigInteger('warehouse_id')->index()->nullable();

            $t->integer('qty_ordered');
            $t->integer('qty_received')->default(0);
            $t->decimal('unit_price', 16, 2)->default(0);
            $t->enum('discount_type', ['percent','amount'])->nullable();
            $t->decimal('discount_value', 16, 2)->nullable();
            $t->decimal('line_total', 16, 2)->default(0);

            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // -------- ALTER restock_receipts: tambah purchase_order_id ----------
        if (Schema::hasTable('restock_receipts')) {
            Schema::table('restock_receipts', function (Blueprint $t) {
                if (! Schema::hasColumn('restock_receipts', 'purchase_order_id')) {
                    $t->unsignedBigInteger('purchase_order_id')
                        ->nullable()
                        ->after('id')
                        ->index();
                }

                // pastikan code nullable (biar nggak 1364 lagi)
                if (Schema::hasColumn('restock_receipts', 'code')) {
                    $t->string('code', 50)->nullable()->change();
                } else {
                    $t->string('code', 50)->nullable()->after('product_id');
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
        Schema::dropIfExists('purchase_orders');
    }
};
