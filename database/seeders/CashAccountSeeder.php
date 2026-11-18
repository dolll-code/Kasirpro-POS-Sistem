<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Services\CashMovementService;
use Illuminate\Database\Seeder;

class CashAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cashMovementService = app(CashMovementService::class);

        $accounts = [
            [
                'code' => 'KAS-01',
                'name' => 'Kas Laci Kasir Utama',
                'account_type' => 'cash',
                'opening_balance' => 500000.00,
                'opening_balance_at' => now(),
            ],
            [
                'code' => 'BNK-BCA',
                'name' => 'Bank BCA Operasional',
                'account_type' => 'bank',
                'opening_balance' => 5000000.00,
                'opening_balance_at' => now(),
            ],
        ];

        foreach ($accounts as $data) {
            $account = CashAccount::firstOrCreate(
                ['code' => $data['code']],
                $data + ['is_active' => true]
            );

            // Record opening balance movement if not exists
            $hasOpening = CashMovement::where('cash_account_id', $account->id)
                ->where('movement_type', 'OPENING_BALANCE')
                ->exists();

            if (! $hasOpening) {
                $cashMovementService->recordOpeningBalance($account);
            }
        }
    }
}
