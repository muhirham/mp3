<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();      // ex: admin, warehouse, sales, auditor, dst.
            $t->string('home_route')->nullable(); // opsional: route mendarat per role
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('roles');
    }
};
