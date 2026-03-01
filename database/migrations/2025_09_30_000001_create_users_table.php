<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $t) {
            $t->id();

            // Identitas dasar
            $t->string('name');
            $t->string('username')->unique();
            $t->string('email')->unique();
            $t->string('phone', 20)->nullable()->unique(); // no HP (optional)

            // Jabatan & tanda tangan (buat approval di PO / dokumen lain)
            $t->string('position', 100)->nullable();        // misal: CEO, Procurement, Warehouse Staff
            $t->string('signature_path')->nullable();       // path file tanda tangan (storage)

            $t->string('password');

            // Relasi ke master gudang (kalau dipakai)
            $t->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $t->enum('status', ['active','inactive'])->default('active');

            $t->rememberToken();
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
