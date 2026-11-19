<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('pending_expires_at')->nullable()->after('completed_at');
            $table->index('pending_expires_at');
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->decimal('cash_tendered', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropColumn('verification_note');
            $table->decimal('cash_tendered', 15, 2)->nullable(false)->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['pending_expires_at']);
            $table->dropColumn('pending_expires_at');
        });
    }
};
