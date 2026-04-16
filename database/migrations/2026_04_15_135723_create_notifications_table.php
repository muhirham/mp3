<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Penerima notif
            $table->unsignedBigInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Tipe & konten
            $table->string('type', 50);           // new_return, return_approved, return_rejected, payment_submitted
            $table->string('title', 255);          // Judul singkat notif
            $table->text('body')->nullable();      // Deskripsi detail
            $table->string('url', 255)->nullable(); // Link tujuan saat diklik

            // Referensi ke data sumber (polymorphic-like)
            $table->string('reference_type', 50)->nullable(); // sales_return, handover
            $table->unsignedBigInteger('reference_id')->nullable();

            // Status baca
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
