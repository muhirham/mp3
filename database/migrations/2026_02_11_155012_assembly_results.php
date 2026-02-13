<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('assembly_results', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150); // contoh: Kartu Isi 1000
            $t->integer('qty')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('assembly_results');
    }
};
