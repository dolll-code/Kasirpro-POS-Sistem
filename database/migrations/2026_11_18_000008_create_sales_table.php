<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('sale_status', 30)->default('completed');
            $table->string('payment_status', 30)->default('paid');

            // Decimal money fields (PRD §8.1, §10.2)
            $table->decimal('gross_total', 15, 2)->default(0.00);
            $table->decimal('item_discount_total', 15, 2)->default(0.00);
            $table->decimal('transaction_discount_total', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2)->default(0.00);

            // Store profile snapshot at time of checkout (PRD §5.2, §10.2)
            $table->json('store_snapshot');

            // Idempotency controls (PRD §5.5, §6.4, §10.2)
            $table->string('idempotency_key', 64)->unique();
            $table->string('payload_hash', 64);

            // Discount approval & maker-checker tracking (PRD §4.2, §5.6)
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('discount_override_reason')->nullable();

            // Status timestamps & audit
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Performance indexes (PRD §10.4)
            $table->index(['sale_status', 'completed_at']);
            $table->index(['cashier_id', 'completed_at']);
            $table->index('completed_at');
        });

        // Add CHECK constraints in MySQL 8.0
        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_gross_total CHECK (gross_total >= 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_item_disc CHECK (item_discount_total >= 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_tx_disc CHECK (transaction_discount_total >= 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_grand_total CHECK (grand_total >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
