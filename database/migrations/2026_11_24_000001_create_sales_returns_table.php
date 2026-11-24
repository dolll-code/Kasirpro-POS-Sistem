<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('pending_approval');
            $table->string('refund_status', 20)->default('due');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->text('approval_note')->nullable();
            $table->text('admin_override_reason')->nullable();
            $table->decimal('return_total', 15, 2)->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['sale_id', 'status']);
            $table->index(['status', 'requested_at']);
            $table->index('approved_at');
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->string('condition', 20);
            $table->decimal('quantity', 15, 3);
            $table->decimal('selling_price_snapshot', 15, 2);
            $table->decimal('item_discount_snapshot', 15, 2);
            $table->decimal('transaction_discount_snapshot', 15, 2);
            $table->decimal('net_unit_price_snapshot', 15, 2);
            $table->decimal('cost_price_snapshot', 15, 2);
            $table->decimal('return_value', 15, 2);
            $table->decimal('cost_value', 15, 2);
            $table->decimal('damaged_loss', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['sale_item_id', 'sales_return_id']);
        });

        Schema::create('return_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->unique()->constrained('sales_returns')->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('cash_movement_id')->unique()->constrained('cash_movements')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference_number', 100)->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['cash_account_id', 'paid_at']);
        });

        DB::statement('ALTER TABLE sales_returns ADD CONSTRAINT chk_sales_returns_total CHECK (return_total >= 0)');
        DB::statement('ALTER TABLE sales_return_items ADD CONSTRAINT chk_return_items_qty CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales_return_items ADD CONSTRAINT chk_return_items_value CHECK (return_value >= 0 AND cost_value >= 0 AND damaged_loss >= 0)');
        DB::statement('ALTER TABLE return_payments ADD CONSTRAINT chk_return_payments_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_payments');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
