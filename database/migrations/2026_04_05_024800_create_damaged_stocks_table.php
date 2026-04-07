<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('damaged_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            
            // Link to source (e.g. sales_return)
            $table->string('source_type')->default('manual'); 
            $table->unsignedBigInteger('source_id')->nullable();
            
            $table->integer('quantity');
            $table->enum('condition', ['damaged', 'expired']);
            
            // Action decided by Admin WH after quarantine
            $table->enum('action', ['repair', 'return_to_supplier', 'dispose', 'other'])->nullable();
            
            // Workflow status
            $table->enum('status', [
                'quarantine',       // Just arrived/discovered
                'pending_approval', // Admin WH requested an action
                'in_progress',      // Superadmin approved, action is being executed
                'resolved',         // Item is repaired/replaced/destroyed and stock updated
                'rejected'          // Superadmin rejected the action request
            ])->default('quarantine');
            
            $table->text('notes')->nullable();
            
            // Tracking
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['warehouse_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damaged_stocks');
    }
};
