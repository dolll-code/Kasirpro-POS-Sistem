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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('sku_snapshot', 100);
            $table->string('unit_snapshot', 50);
            $table->decimal('ordered_qty', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_discount', 15, 2)->default(0);
            $table->decimal('allocated_document_discount', 15, 2)->default(0);
            $table->decimal('accepted_qty_total', 15, 3)->default(0);
            $table->decimal('rejected_qty_total', 15, 3)->default(0);
            $table->decimal('cancelled_qty', 15, 3)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->index(['purchase_id', 'product_id'], 'idx_purchase_items_purch_prod');
        });

        // Numeric CHECK constraints (MySQL 8.0+)
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_ordered_pos CHECK (ordered_qty > 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_cost_nonneg CHECK (unit_cost >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_line_disc_nonneg CHECK (line_discount >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_doc_disc_nonneg CHECK (allocated_document_discount >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_accepted_nonneg CHECK (accepted_qty_total >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_rejected_nonneg CHECK (rejected_qty_total >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_cancelled_nonneg CHECK (cancelled_qty >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purch_item_total_nonneg CHECK (line_total >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
