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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Product snapshots to isolate from future catalog changes
            $table->string('product_name_snapshot', 255);
            $table->string('sku_snapshot', 100);
            $table->string('barcode_snapshot', 100)->nullable();
            $table->string('unit_name_snapshot', 50);

            // Precision decimals (PRD §8.1)
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('gross_line_total', 15, 2);
            $table->decimal('line_discount', 15, 2)->default(0.00);
            $table->decimal('allocated_transaction_discount', 15, 2)->default(0.00);
            $table->decimal('net_line_total', 15, 2);

            // Historical Cost (COGS) snapshots (PRD §8.3)
            $table->decimal('cost_price_snapshot', 15, 2)->default(0.00);
            $table->decimal('cost_total_snapshot', 15, 2)->default(0.00);

            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
        });

        // Enforce quantity > 0 and nonnegative monetary values
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_qty CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_price CHECK (unit_price_snapshot >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_gross CHECK (gross_line_total >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_line_disc CHECK (line_discount >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_alloc_disc CHECK (allocated_transaction_discount >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_net CHECK (net_line_total >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_cost_price CHECK (cost_price_snapshot >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_cost_total CHECK (cost_total_snapshot >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
