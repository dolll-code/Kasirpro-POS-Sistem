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
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->string('payment_method', 30)->default('cash');
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();

            // Monetary values
            $table->decimal('amount', 15, 2); // Net paid amount = grand_total
            $table->decimal('cash_tendered', 15, 2); // Nominal physical cash handed over
            $table->decimal('change_amount', 15, 2)->default(0.00); // Kembalian

            $table->string('reference_number', 100)->nullable();
            $table->string('verification_status', 30)->default('verified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Link to append-only Cash Movement (PRD §5.5, §10.2)
            $table->foreignId('cash_movement_id')->nullable()->unique()->constrained('cash_movements')->nullOnDelete();

            $table->timestamps();

            $table->index(['verification_status', 'paid_at']);
        });

        // Enforce nonnegative values
        DB::statement('ALTER TABLE sale_payments ADD CONSTRAINT chk_sale_pay_amount CHECK (amount >= 0)');
        DB::statement('ALTER TABLE sale_payments ADD CONSTRAINT chk_sale_pay_tender CHECK (cash_tendered >= 0)');
        DB::statement('ALTER TABLE sale_payments ADD CONSTRAINT chk_sale_pay_change CHECK (change_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
