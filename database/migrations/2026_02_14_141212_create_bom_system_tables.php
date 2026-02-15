<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | BOM HEADER
        |--------------------------------------------------------------------------
        */
        Schema::create('boms', function (Blueprint $t) {
            $t->id();

            // Finished product (hasil produksi)
            $t->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->string('bom_code')->unique(); // ex: BOM-0001
            $t->integer('version')->default(1);
            $t->integer('output_qty')->default(1); 
            // 1 recipe menghasilkan berapa unit

            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $t->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            $t->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | BOM ITEMS (DETAIL MATERIAL)
        |--------------------------------------------------------------------------
        */
        Schema::create('bom_items', function (Blueprint $t) {
            $t->id();

            $t->foreignId('bom_id')
                ->constrained('boms')
                ->cascadeOnDelete();

            // bahan baku
            $t->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->decimal('quantity', 15, 2);

            $t->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | PRODUCTION TRANSACTIONS (EKSEKUSI PRODUKSI)
        |--------------------------------------------------------------------------
        */
        Schema::create('bom_transactions', function (Blueprint $t) {
            $t->id();

            $t->foreignId('bom_id')
                ->constrained('boms')
                ->cascadeOnDelete();

            // finished product
            $t->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->integer('production_qty'); // berapa batch dijalankan

            $t->decimal('total_cost', 15, 2)->default(0);

            $t->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $t->timestamps();
        });

        Schema::create('bom_transaction_items', function (Blueprint $t) {
            $t->id();

            $t->foreignId('bom_transaction_id')
                ->constrained('bom_transactions')
                ->cascadeOnDelete();

            $t->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $t->decimal('qty_used', 15, 2);

            $t->decimal('cost_per_unit', 15, 2)->default(0);
            $t->decimal('total_cost', 15, 2)->default(0);

            $t->timestamps();
        });

    }

    public function down(): void
    {
        
        Schema::dropIfExists('bom_transaction_items');
        Schema::dropIfExists('bom_transactions');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('boms');
    }
};
