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
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};