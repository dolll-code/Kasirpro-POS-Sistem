<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('cash_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->string('direction', 10);
            $table->string('category', 50);
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('status', 20)->default('pending_approval');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('approval_note')->nullable();
            $table->text('admin_override_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('cash_movement_id')->nullable()->unique()->constrained('cash_movements')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'requested_at']);
            $table->index(['cash_account_id', 'requested_at']);
        });

        DB::statement('ALTER TABLE cash_adjustments ADD CONSTRAINT chk_cash_adjustments_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_adjustments');
    }
};
