<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // Identitas utama
            $table->string('name');                           // Nama utama: PT NTT Indonesia
            $table->string('legal_name')->nullable();         // Kalau mau bedain nama legal
            $table->string('short_name', 50)->nullable();     // Singkatan: NTT, MAND, dll
            $table->string('code', 20)->nullable()->index();  // Kode internal: NTT01, HO, dll

            // Alamat & kontak (simpel tapi cukup)
            $table->text('address')->nullable();              // Alamat lengkap 1 kolom
            $table->string('city', 100)->nullable();          // Kota (opsional, buat di kop)
            $table->string('province', 100)->nullable();      // Provinsi (opsional)
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Info pajak (kalau suatu saat mau dimunculin di dokumen)
            $table->string('tax_number', 100)->nullable();    // NPWP / VAT

            // Asset visual
            $table->string('logo_path')->nullable();          // Logo utama (PO, kop surat)
            $table->string('logo_small_path')->nullable();    // Logo versi kecil (sidebar, navbar)

            // Status & default
            $table->boolean('is_default')->default(false);    // Company default
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();                            // Jaga-jaga kalau pernah dipakai di PO
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
