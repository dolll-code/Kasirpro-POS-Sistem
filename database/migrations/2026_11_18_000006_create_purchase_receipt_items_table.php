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
        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('physically_received_qty', 15, 3);
            $table->decimal('accepted_qty', 15, 3);
            $table->decimal('rejected_qty', 15, 3)->default(0);
            $table->string('rejection_reason', 255)->nullable();
            $table->decimal('landed_unit_cost', 15, 2);
            $table->decimal('landed_total_cost', 15, 2);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['receipt_id', 'product_id'], 'idx_rcv_items_receipt_prod');
            $table->index('purchase_item_id', 'idx_rcv_items_purch_item');
        });

        // Sum constraint: physically_received = accepted + rejected
        DB::statement('ALTER TABLE purchase_receipt_items ADD CONSTRAINT chk_rcv_item_phys_sum CHECK (physically_received_qty = (accepted_qty + rejected_qty))');
        DB::statement('ALTER TABLE purchase_receipt_items ADD CONSTRAINT chk_rcv_item_accepted_nonneg CHECK (accepted_qty >= 0)');
        DB::statement('ALTER TABLE purchase_receipt_items ADD CONSTRAINT chk_rcv_item_rejected_nonneg CHECK (rejected_qty >= 0)');
        DB::statement('ALTER TABLE purchase_receipt_items ADD CONSTRAINT chk_rcv_item_cost_nonneg CHECK (landed_unit_cost >= 0)');
        DB::statement('ALTER TABLE purchase_receipt_items ADD CONSTRAINT chk_rcv_item_total_nonneg CHECK (landed_total_cost >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
    }
};
