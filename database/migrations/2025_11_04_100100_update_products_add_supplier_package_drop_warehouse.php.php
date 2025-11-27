<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            // description (kalau belum ada, tambahkan)
            if (!Schema::hasColumn('products','description')) {
                $t->text('description')->nullable()->after('name');
            }

            // package_id (nullable FK ke packages)
            if (!Schema::hasColumn('products','package_id')) {
                $t->foreignId('package_id')->nullable()
                    ->after('category_id')
                    ->constrained('packages')->nullOnDelete();
            }

            // supplier_id (nullable FK ke suppliers)
            if (!Schema::hasColumn('products','supplier_id')) {
                $t->foreignId('supplier_id')->nullable()
                    ->after('package_id')
                    ->constrained('suppliers')->nullOnDelete();
            }

            // HAPUS warehouse_id kalau ada
            if (Schema::hasColumn('products','warehouse_id')) {
                try { $t->dropForeign(['warehouse_id']); } catch (\Throwable $e) {}
                $t->dropColumn('warehouse_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            if (Schema::hasColumn('products','supplier_id')) {
                $t->dropForeign(['supplier_id']); $t->dropColumn('supplier_id');
            }
            if (Schema::hasColumn('products','package_id')) {
                $t->dropForeign(['package_id']);  $t->dropColumn('package_id');
            }
            // (warehouse_id tidak dikembalikan)
        });
    }
};