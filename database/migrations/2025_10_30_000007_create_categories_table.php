<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('category_code', 50)->unique();
            $t->string('category_name', 150);
            $t->text('description')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('categories');
    }
};