<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('role_user', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['user_id','role_id']);

            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('role_user');
    }
};
