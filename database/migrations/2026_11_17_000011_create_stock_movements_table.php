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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('movement_type', 50);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_number', 100)->nullable();
            $table->decimal('quantity_in', 15, 3)->default(0.000);
            $table->decimal('quantity_out', 15, 3)->default(0.000);
            $table->decimal('balance_before', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0.00);
            $table->decimal('total_cost', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'occurred_at', 'id'], 'idx_stock_mov_prod_occurred');
            $table->index(['source_type', 'source_id'], 'idx_stock_mov_source');
            $table->index('movement_type', 'idx_stock_mov_type');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_mov_qty_in_nonneg CHECK (quantity_in >= 0)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_mov_qty_out_nonneg CHECK (quantity_out >= 0)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_mov_unit_cost_nonneg CHECK (unit_cost >= 0)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_mov_total_cost_nonneg CHECK (total_cost >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
