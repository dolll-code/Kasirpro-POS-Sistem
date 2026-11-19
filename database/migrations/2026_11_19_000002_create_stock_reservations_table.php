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
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->string('status', 30)->default('active'); // active, released, consumed, expired
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
