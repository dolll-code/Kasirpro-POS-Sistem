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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->string('movement_type', 50);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->text('description')->nullable();
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_number', 100)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('cash_movements')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cash_account_id', 'occurred_at', 'id'], 'idx_cash_mov_acc_occurred');
            $table->index(['source_type', 'source_id'], 'idx_cash_mov_source');
            $table->index('movement_type', 'idx_cash_mov_type');
            $table->index('direction', 'idx_cash_mov_direction');

            // Source uniqueness guard: each source document creates at most one movement
            $table->unique(['source_type', 'source_id'], 'uq_cash_mov_source');
            // Reversal uniqueness guard: each movement can be reversed at most once
            $table->unique('reversal_of_id', 'uq_cash_mov_reversal');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_mov_amount_pos CHECK (amount > 0)');
            DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_mov_before_nonneg CHECK (balance_before >= 0)');
            DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_mov_after_nonneg CHECK (balance_after >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
