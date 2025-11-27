<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('roles', function (Blueprint $t) {
            if (!Schema::hasColumn('roles','menu_keys')) {
                $t->json('menu_keys')->nullable()->after('home_route');
            }
        });
    }

    public function down(): void {
        Schema::table('roles', function (Blueprint $t) {
            if (Schema::hasColumn('roles','menu_keys')) {
                $t->dropColumn('menu_keys');
            }
        });
    }
};
