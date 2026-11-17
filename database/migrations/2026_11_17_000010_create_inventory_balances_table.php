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
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 15, 3)->default(0.000);
            $table->decimal('quantity_reserved', 15, 3)->default(0.000);
            $table->decimal('average_cost', 15, 2)->default(0.00);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });

        // Add CHECK constraints for MySQL 8.0+ / InnoDB
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inv_on_hand_nonneg CHECK (quantity_on_hand >= 0)');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inv_reserved_nonneg CHECK (quantity_reserved >= 0)');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inv_reserved_lte_on_hand CHECK (quantity_reserved <= quantity_on_hand)');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inv_avg_cost_nonneg CHECK (average_cost >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
