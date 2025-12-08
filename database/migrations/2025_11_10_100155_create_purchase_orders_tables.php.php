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
        | purchase_orders (HEADER)
        |-----------------------------------------
        | - Satu baris = satu dokumen PO
        | - Dipakai baik untuk:
        |   * PO dari Request Restock
        |   * PO manual
        */
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();

            // Kode unik PO (misal: PO-2412-0001)
            $t->string('po_code', 50)->unique();

            // Supplier (optional, bisa null kalau belum ditentukan)
            $t->unsignedBigInteger('supplier_id')->nullable()->index();

            // Siapa yang bikin / mengajukan PO
            // (ini bisa sama dengan ordered_by lama, cuman kita pertegas maknanya)
            $t->unsignedBigInteger('ordered_by')->nullable()->index();

            // ========= FIELD APPROVAL 2 LAPIS =========

            // Disetujui oleh Procurement
            $t->unsignedBigInteger('approved_by_procurement')->nullable()->index();
            $t->timestamp('approved_at_procurement')->nullable();

            // Disetujui oleh CEO (lapis kedua)
            $t->unsignedBigInteger('approved_by_ceo')->nullable()->index();
            $t->timestamp('approved_at_ceo')->nullable();

            // Status flow approval PO
            // contoh nilai:
            // - waiting_procurement
            // - waiting_ceo
            // - approved
            // - rejected
            $t->string('approval_status', 30)
              ->default('draft')
              ->index();

            // ========= STATUS OPERASIONAL PO =========
            // Ini status yang berhubungan dengan lifecycle PO
            // dari sisi pembelian & penerimaan barang.
            $t->enum('status', [
                'draft',              // baru dibuat, masih bisa diedit
                'approved',           // (optional dipakai, kalau mau bedain dengan approval_status)
                'ordered',            // sudah dikirim ke supplier
                'partially_received', // beberapa item sudah diterima
                'completed',          // semua item sudah diterima
                'cancelled',          // PO dibatalkan
            ])->default('draft')->index();

            // ========= NILAI UANG =========
            $t->decimal('subtotal', 16, 2)->default(0);
            $t->decimal('discount_total', 16, 2)->default(0);
            $t->decimal('grand_total', 16, 2)->default(0);

            $t->text('notes')->nullable();

            // Kapan PO dianggap "ordered" (dikirim ke supplier)
            $t->timestamp('ordered_at')->nullable();

            $t->timestamps();
        });

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
        // Balikkan perubahan di restock_receipts
        if (Schema::hasTable('restock_receipts')) {
            Schema::table('restock_receipts', function (Blueprint $t) {
                if (Schema::hasColumn('restock_receipts', 'purchase_order_id')) {
                    $t->dropColumn('purchase_order_id');
                }
                // kolom 'code' kita biarkan apa adanya (nggak usah di-rollback detail)
            });
        }

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
