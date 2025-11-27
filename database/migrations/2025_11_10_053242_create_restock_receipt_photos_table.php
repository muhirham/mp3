<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_receipt_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');   // relasi ke restock_receipts.id
            $table->string('path', 255);                // path file di storage
            $table->enum('type', ['good', 'damaged']);  // jenis foto
            $table->string('caption', 255)->nullable(); // keterangan (opsional)
            $table->timestamps();

            $table->foreign('receipt_id')
                  ->references('id')->on('restock_receipts')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_receipt_photos');
    }
};
