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
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->decimal('current_balance', 15, 2)->default(0.00)->after('opening_balance_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cash_accounts ADD CONSTRAINT chk_cash_acc_balance_nonneg CHECK (current_balance >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cash_accounts DROP CHECK chk_cash_acc_balance_nonneg');
        }

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropColumn('current_balance');
        });
    }
};
