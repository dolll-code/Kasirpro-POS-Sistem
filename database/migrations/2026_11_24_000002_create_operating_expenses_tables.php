<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 50)->unique();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->date('expense_date');
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('landed_cost_reference', 100)->nullable();
            $table->boolean('is_landed_cost')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->decimal('void_cash_refund_amount', 15, 2)->default(0);
            $table->decimal('void_cash_unrecovered_amount', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['status', 'expense_date']);
            $table->index(['payment_status', 'status']);
            $table->index('posted_at');
            $table->index('voided_at');
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->string('payload_hash', 64);
            $table->foreignId('expense_id')->constrained('expenses')->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->unique()->constrained('cash_movements')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference_number', 100)->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['expense_id', 'paid_at']);
        });

        Schema::create('expense_void_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->restrictOnDelete();
            $table->foreignId('expense_payment_id')->unique()->constrained('expense_payments')->restrictOnDelete();
            $table->foreignId('original_cash_movement_id')->constrained('cash_movements')->restrictOnDelete();
            $table->foreignId('reversal_cash_movement_id')->unique()->constrained('cash_movements')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expenses_amount CHECK (amount > 0)');
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expenses_void_cash CHECK (void_cash_refund_amount >= 0 AND void_cash_unrecovered_amount >= 0)');
        DB::statement('ALTER TABLE expense_payments ADD CONSTRAINT chk_expense_payments_amount CHECK (amount > 0)');
        DB::statement('ALTER TABLE expense_void_reversals ADD CONSTRAINT chk_expense_void_reversals_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_void_reversals');
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expenses');
    }
};
