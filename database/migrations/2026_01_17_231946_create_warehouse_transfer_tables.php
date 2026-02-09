<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * ===============================
         * TABLE: warehouse_transfers
         * ===============================
         */
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


        /**
         * ===============================
         * TABLE: warehouse_transfer_items
         * ===============================
         */
        Schema::create('warehouse_transfer_items', function (Blueprint $t) {
          $t->id();

          $t->foreignId('warehouse_transfer_id')
              ->constrained()
              ->cascadeOnDelete();

          $t->foreignId('product_id')->constrained('products');

          // qty dokumen
          $t->integer('qty_transfer');

          // qty real (hasil GR)
          $t->integer('qty_good')->nullable();
          $t->integer('qty_damaged')->nullable();

          // snapshot harga
          $t->decimal('unit_cost', 15, 2);
          $t->decimal('subtotal_cost', 15, 2);

          // bukti GR (path file)
          $t->string('photo_good')->nullable();
          $t->string('photo_damaged')->nullable();

          $t->text('note')->nullable();

          $t->timestamps();
      });


        /**
         * ===============================
         * TABLE: warehouse_transfer_logs
         * ===============================
         */
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
        Schema::dropIfExists('warehouse_transfer_items');
        Schema::dropIfExists('warehouse_transfers');
    }
};
