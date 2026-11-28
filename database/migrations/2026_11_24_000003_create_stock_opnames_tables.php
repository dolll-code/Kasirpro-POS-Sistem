<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number', 50)->unique();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->text('admin_override_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('expected_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('variance_quantity', 15, 3)->nullable();
            $table->decimal('average_cost_snapshot', 15, 2);
            $table->decimal('active_cost_snapshot', 15, 2)->nullable();
            $table->decimal('positive_unit_cost', 15, 2)->nullable();
            $table->text('positive_cost_reason')->nullable();
            $table->decimal('variance_value', 15, 2)->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['stock_opname_id', 'product_id'], 'uq_stock_opname_product');
            $table->index(['product_id', 'stock_opname_id']);
        });

        Schema::create('stock_opname_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Set NULL on release while retaining the historical product_id. Unique nullable key
            // provides a database-enforced single active opname lock per product.
            $table->unsignedBigInteger('active_product_id')->nullable();
            $table->foreignId('locked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('locked_at');
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 50)->nullable();
            $table->timestamps();
            $table->unique('active_product_id', 'uq_stock_opname_active_product');
            $table->index(['stock_opname_id', 'released_at']);
        });

        DB::statement('ALTER TABLE stock_opname_items ADD CONSTRAINT chk_stock_opname_expected CHECK (expected_quantity >= 0)');
        DB::statement('ALTER TABLE stock_opname_items ADD CONSTRAINT chk_stock_opname_counted CHECK (counted_quantity IS NULL OR counted_quantity >= 0)');
        DB::statement('ALTER TABLE stock_opname_items ADD CONSTRAINT chk_stock_opname_cost CHECK (average_cost_snapshot >= 0 AND (active_cost_snapshot IS NULL OR active_cost_snapshot >= 0) AND (positive_unit_cost IS NULL OR positive_unit_cost >= 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_locks');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
