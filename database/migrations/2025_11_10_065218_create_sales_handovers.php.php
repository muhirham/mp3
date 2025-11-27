<?php
    // database/migrations/2025_11_10_000100_create_sales_handovers.php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
    public function up(): void {
        Schema::create('sales_handovers', function (Blueprint $t) {
        $t->id();
        $t->string('code', 50)->unique();                 // HDO-251110-0001
        $t->foreignId('warehouse_id')->constrained('warehouses');
        $t->foreignId('sales_id')->constrained('users');  // user role=sales
        $t->date('handover_date');
        $t->enum('status', ['issued','waiting_otp','reconciled','cancelled'])->default('issued');
        $t->foreignId('issued_by')->constrained('users');
        $t->foreignId('reconciled_by')->nullable()->constrained('users');
        // OTP
        $t->string('otp_hash', 255)->nullable();
        $t->timestamp('otp_expires_at')->nullable();
        $t->timestamps();
        });

        Schema::create('sales_handover_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('handover_id')->constrained('sales_handovers')->cascadeOnDelete();
        $t->foreignId('product_id')->constrained('products');
        $t->integer('qty_dispatched');             // yang dibawa pagi
        $t->integer('qty_returned_good')->default(0);
        $t->integer('qty_returned_damaged')->default(0);
        $t->integer('qty_sold')->default(0);       // dihitung saat rekonsiliasi
        $t->timestamps();
        $t->unique(['handover_id','product_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('sales_handover_items');
        Schema::dropIfExists('sales_handovers');
    }
    };

