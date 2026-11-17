<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_code' => 'SUP-001',
                'name' => 'PT Sumber Pangan Sejahtera',
                'contact_person' => 'Budi Santoso',
                'phone' => '081234567890',
                'email' => 'budi@sumberpangan.com',
                'address' => 'Kawasan Industri Pulogadung Blok A5, Jakarta Timur',
                'notes' => 'Pemasok utama sembako dan minyak goreng',
            ],
            [
                'supplier_code' => 'SUP-002',
                'name' => 'CV Distribusi Makmur Sentosa',
                'contact_person' => 'Siti Rahma',
                'phone' => '082198765432',
                'email' => 'order@makmursentosa.co.id',
                'address' => 'Jl. Daan Mogot KM 12 No. 88, Jakarta Barat',
                'notes' => 'Pemasok makanan ringan dan minuman kemasan',
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::firstOrCreate(
                ['supplier_code' => $data['supplier_code']],
                $data + ['is_active' => true]
            );
        }
    }
}
