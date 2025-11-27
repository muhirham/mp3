<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('supplier_code', 50)->unique();
            $t->string('name', 150);
            $t->string('address')->nullable();
            $t->string('phone', 50)->nullable();
            $t->text('note')->nullable();
            $t->string('bank_name', 100)->nullable(); // Menambahkan kolom nama bank
            $t->string('bank_account', 100)->nullable(); // Menambahkan kolom nomor rekening
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('suppliers');
    }
};