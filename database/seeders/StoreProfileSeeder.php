<?php

namespace Database\Seeders;

use App\Models\StoreProfile;
use Illuminate\Database\Seeder;

class StoreProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StoreProfile::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'KasirPro Mart',
                'address' => 'Jl. Jenderal Sudirman No. 1, Jakarta Pusat',
                'phone' => '021-5551234',
                'email' => 'toko@kasirpro.local',
                'tax_id' => '01.234.567.8-012.000',
                'receipt_header' => 'Selamat Datang di KasirPro Mart',
                'receipt_footer' => 'Barang yang sudah dibeli tidak dapat ditukar kecuali ada perjanjian. Terima kasih atas kunjungan Anda!',
                'receipt_paper_width' => '58',
                'is_active' => true,
            ]
        );
    }
}
