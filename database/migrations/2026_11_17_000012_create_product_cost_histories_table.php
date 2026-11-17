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
        Schema::create('product_cost_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('qty_before', 15, 3);
            $table->decimal('cost_before', 15, 2);
            $table->decimal('qty_after', 15, 3);
            $table->decimal('cost_after', 15, 2);
            $table->string('reason', 255)->nullable();
            $table->timestamp('effective_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'effective_at'], 'idx_cost_hist_prod_effective');
            $table->index(['reference_type', 'reference_id'], 'idx_cost_hist_reference');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE product_cost_histories ADD CONSTRAINT chk_cost_hist_cost_before CHECK (cost_before >= 0)');
            DB::statement('ALTER TABLE product_cost_histories ADD CONSTRAINT chk_cost_hist_cost_after CHECK (cost_after >= 0)');
            DB::statement('ALTER TABLE product_cost_histories ADD CONSTRAINT chk_cost_hist_qty_before CHECK (qty_before >= 0)');
            DB::statement('ALTER TABLE product_cost_histories ADD CONSTRAINT chk_cost_hist_qty_after CHECK (qty_after >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_cost_histories');
    }
};
