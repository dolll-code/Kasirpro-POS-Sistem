<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Listrik, Air & Internet',
                'description' => 'Tagihan utilitas bulanan toko',
            ],
            [
                'name' => 'Perlengkapan Kasir & Struk',
                'description' => 'Kertas thermal struk, tinta, plastik belanja, dan lakban',
            ],
            [
                'name' => 'Kebersihan & Pemeliharaan',
                'description' => 'Cairan pembersih lantai, kantong sampah, dan servis AC/alat',
            ],
            [
                'name' => 'Konsumsi & Lembur Karyawan',
                'description' => 'Air minum galon dan konsumsi lembur kasir/staf',
            ],
        ];

        foreach ($categories as $data) {
            ExpenseCategory::firstOrCreate(
                ['name' => $data['name']],
                $data + ['is_active' => true]
            );
        }
    }
}
