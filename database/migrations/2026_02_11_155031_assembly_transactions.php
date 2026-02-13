<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('assembly_transactions', function (Blueprint $t) {
        $t->id();

        $t->foreignId('saldo_id')
            ->constrained('products')
            ->restrictOnDelete();

        $t->foreignId('kpk_id')
            ->constrained('products')
            ->restrictOnDelete();

        $t->integer('qty');

        $t->integer('saldo_per_unit')->default(1000); // isi 1000 per kartu

        $t->bigInteger('saldo_used'); // total saldo dipakai

        $t->bigInteger('saldo_before'); // saldo sebelum assembly

        $t->bigInteger('saldo_after'); // saldo setelah assembly

        $t->foreignId('created_by')
            ->constrained('users')
            ->restrictOnDelete();

        $t->timestamps();
    });

    }

    public function down(): void {
        Schema::dropIfExists('assembly_transactions');
    }
};
