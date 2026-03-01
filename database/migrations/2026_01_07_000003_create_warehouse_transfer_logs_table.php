<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
        {
            Schema::create('warehouse_transfer_logs', function (Blueprint $t) {
                $t->id();

                $t->foreignId('warehouse_transfer_id')
                ->constrained('warehouse_transfers')
                ->cascadeOnDelete();

                $t->string('action');
                // CREATED, SUBMITTED, SOURCE_APPROVED,
                // DEST_APPROVED, REJECTED, CANCELED, RESUBMITTED

                $t->foreignId('performed_by')
                ->constrained('users');

                $t->text('note')->nullable();
                $t->timestamps();
            });
        }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_logs');
    }
};