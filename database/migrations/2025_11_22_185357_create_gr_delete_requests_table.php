<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gr_delete_requests', function (Blueprint $table) {
            $table->id();

            // dibuat nullable, karena nanti GR bisa dihapus
            $table->unsignedBigInteger('restock_receipt_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();

            // pending, approved, rejected, cancelled
            $table->string('status', 20)->default('pending');

            $table->text('reason')->nullable();        // alasan dari user gudang
            $table->text('approval_note')->nullable(); // catatan approver

            $table->timestamps();

            // === FOREIGN KEY ===

            // JANGAN cascade ke restock_receipts, biar request nggak ikut kehapus.
            // Kalau GR dihapus, kolom ini otomatis jadi NULL.
            $table->foreign('restock_receipt_id')
                ->references('id')->on('restock_receipts')
                ->onDelete('set null');

            // Kalau PO dihapus, wajar riwayat request GR ikut hilang ⇒ boleh cascade
            $table->foreign('purchase_order_id')
                ->references('id')->on('purchase_orders')
                ->onDelete('cascade');

            // Kalau user pengaju dihapus, request ikut hilang ⇒ masih ok kalau mau cascade
            $table->foreign('requested_by')
                ->references('id')->on('users')
                ->onDelete('cascade');

            // Approver boleh di-hapus, request tetap ada, cuma approved_by jadi NULL
            $table->foreign('approved_by')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gr_delete_requests');
    }
};
