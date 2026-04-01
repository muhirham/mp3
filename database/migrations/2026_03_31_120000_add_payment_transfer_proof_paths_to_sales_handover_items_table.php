<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_handover_items', function (Blueprint $table) {
            $table->json('payment_transfer_proof_paths')->nullable()->after('payment_transfer_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('sales_handover_items', function (Blueprint $table) {
            $table->dropColumn('payment_transfer_proof_paths');
        });
    }
};
