<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('action', 100);
            $t->string('entity_type', 100);
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('activity_logs');
    }
};