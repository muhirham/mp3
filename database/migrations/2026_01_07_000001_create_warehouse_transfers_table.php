<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
        {

            Schema::create('warehouse_transfers', function (Blueprint $t) {
            $t->id();

            $t->string('transfer_code')->unique();

            $t->foreignId('source_warehouse_id')->constrained('warehouses');
            $t->foreignId('destination_warehouse_id')->constrained('warehouses');

            $t->enum('status', [
                'draft',
                'pending_destination',
                'approved',
                'gr_source',
                'completed',
                'rejected',
                'canceled',
            ])->default('draft');

            $t->decimal('total_cost', 15, 2)->default(0);

            $t->foreignId('created_by')->constrained('users');

            $t->foreignId('approved_destination_by')->nullable()->constrained('users');
            $t->timestamp('approved_destination_at')->nullable();

            $t->foreignId('gr_source_by')->nullable()->constrained('users');
            $t->timestamp('gr_source_at')->nullable();

            $t->text('note')->nullable();

            $t->timestamps();
        });
        }

        public function down(): void
        {
            Schema::dropIfExists('warehouse_transfers');
        }
};