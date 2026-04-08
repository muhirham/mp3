<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restock_receipts', function (Blueprint $table) {
            $table->enum('gr_type', ['po', 'request_stock', 'gr_transfer', 'gr_return'])
                ->default('po')
                ->after('id')
                ->index();

            $table->dropUnique('restock_receipts_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('restock_receipts', function (Blueprint $table) {
            $table->dropColumn('gr_type');
            $table->unique('code');
        });
    }
};
