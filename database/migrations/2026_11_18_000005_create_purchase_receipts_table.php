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
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 100)->unique();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->decimal('capitalizable_additional_cost', 15, 2)->default(0);
            $table->string('status', 50)->default('posted')->index();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'status', 'received_at'], 'idx_receipts_purch_status_date');
        });

        // Additional cost CHECK constraint (MySQL 8.0+)
        DB::statement('ALTER TABLE purchase_receipts ADD CONSTRAINT chk_rcv_cap_cost_nonneg CHECK (capitalizable_additional_cost >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
    }
};
