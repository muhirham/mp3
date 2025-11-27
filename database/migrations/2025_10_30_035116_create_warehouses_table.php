<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->string('warehouse_code', 50)->unique();
            $t->string('warehouse_name', 150);
            $t->string('address')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('warehouses');
    }
};