<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Makanan & Minuman',
                'description' => 'Produk makanan ringan, instan, dan minuman kemasan',
            ],
            [
                'name' => 'Sembako',
                'description' => 'Kebutuhan pokok seperti beras, gula, minyak goreng, dan telur',
            ],
            [
                'name' => 'Kebutuhan Rumah Tangga',
                'description' => 'Peralatan kebersihan, sabun cuci, detergen, dan perlengkapan rumah',
            ],
            [
                'name' => 'Perawatan Diri',
                'description' => 'Sabun mandi, sampo, pasta gigi, dan kosmetik dasar',
            ],
        ];

        foreach ($categories as $data) {
            Category::firstOrCreate(
                ['name' => $data['name']],
                [
                    'slug' => Str::slug($data['name']),
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
