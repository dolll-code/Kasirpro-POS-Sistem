<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'name' => 'Pieces',
                'symbol' => 'pcs',
                'decimal_precision' => 0,
            ],
            [
                'name' => 'Pack',
                'symbol' => 'pack',
                'decimal_precision' => 0,
            ],
            [
                'name' => 'Kilogram',
                'symbol' => 'kg',
                'decimal_precision' => 3,
            ],
            [
                'name' => 'Gram',
                'symbol' => 'gr',
                'decimal_precision' => 1,
            ],
            [
                'name' => 'Liter',
                'symbol' => 'ltr',
                'decimal_precision' => 2,
            ],
        ];

        foreach ($units as $data) {
            Unit::firstOrCreate(
                ['symbol' => $data['symbol']],
                [
                    'name' => $data['name'],
                    'decimal_precision' => $data['decimal_precision'],
                    'is_active' => true,
                ]
            );
        }
    }
}
