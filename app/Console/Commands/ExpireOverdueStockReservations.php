<?php

namespace App\Console\Commands;

use App\Services\SaleService;
use Illuminate\Console\Command;

class ExpireOverdueStockReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:expire-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire overdue stock reservations for pending bank transfers (PRD §6.4, §9.5)';

    /**
     * Execute the console command.
     */
    public function handle(SaleService $saleService): int
    {
        $count = $saleService->expireOverdueReservations();

        if ($count > 0) {
            $this->info("Berhasil merilis {$count} reservasi stok yang telah kedaluwarsa.");
        } else {
            $this->line('Tidak ada reservasi stok yang kedaluwarsa saat ini.');
        }

        return self::SUCCESS;
    }
}
