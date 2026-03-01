<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $t) {
                $t->id();
                $t->string('package_name', 150)->unique();
                $t->timestamps();
            });
        } else {
            // Kalau tabel sudah ada (dari versi sebelumnya), pastikan kolom package_name ada
            Schema::table('packages', function (Blueprint $t) {
                if (!Schema::hasColumn('packages', 'package_name')) {
                    $t->string('package_name', 150)->after('id');
                }
            });
        }

        // Pastikan products punya package_id (nullable)
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'package_id')) {
            Schema::table('products', function (Blueprint $t) {
                $t->foreignId('package_id')->nullable()->after('category_id')->constrained('packages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // optional: tidak drop supaya data satuan aman
        // Schema::dropIfExists('packages');
    }
};
