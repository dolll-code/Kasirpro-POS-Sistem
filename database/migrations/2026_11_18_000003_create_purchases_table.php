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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 100)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('item_discount_total', 15, 2)->default(0);
            $table->decimal('document_discount_total', 15, 2)->default(0);
            $table->decimal('additional_cost_estimate', 15, 2)->default(0);
            $table->decimal('payable_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 50)->default('draft')->index();
            $table->string('payment_status', 50)->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'order_date', 'status'], 'idx_purchases_supp_date_status');
        });

        // Non-negative CHECK constraints (MySQL 8.0+)
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT chk_purchases_subtotal_nonneg CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT chk_purchases_item_disc_nonneg CHECK (item_discount_total >= 0)');
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT chk_purchases_doc_disc_nonneg CHECK (document_discount_total >= 0)');
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT chk_purchases_payable_nonneg CHECK (payable_total >= 0)');
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT chk_purchases_paid_nonneg CHECK (paid_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
