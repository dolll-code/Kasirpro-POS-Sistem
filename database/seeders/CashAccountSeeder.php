<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use Illuminate\Database\Seeder;

class CashAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            CashAccount::firstOrCreate(
                ['code' => $data['code']],
                $data + ['is_active' => true]
            );
        }
    }
}
