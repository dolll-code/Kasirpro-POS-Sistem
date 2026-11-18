<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 100)->unique();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->string('method', 50);
            $table->decimal('amount', 15, 2);
            $table->string('reference_number', 100)->nullable();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->foreignId('cash_movement_id')->nullable()->unique()->constrained('cash_movements')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reversal_reason', 255)->nullable();
            $table->timestamps();

            $table->index('purchase_id', 'idx_supp_pay_purchase');
            $table->index('cash_account_id', 'idx_supp_pay_cash_acc');
            $table->index('paid_at', 'idx_supp_pay_paid_at');
        });

        // Amount CHECK constraint (MySQL 8.0+)
        DB::statement('ALTER TABLE supplier_payments ADD CONSTRAINT chk_supp_pay_amount_pos CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
