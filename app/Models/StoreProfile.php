<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'tax_id',
        'receipt_header',
        'receipt_footer',
        'receipt_paper_width',
        'logo_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the single active store profile or return a default instance.
     */
    public static function current(): self
    {
        return static::where('is_active', true)->first() ?? static::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'KasirPro Store',
                'address' => 'Jl. Jenderal Sudirman No. 1, Jakarta Pusat',
                'phone' => '021-5551234',
                'email' => 'toko@kasirpro.local',
                'receipt_header' => 'Selamat Datang di KasirPro Store',
                'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
                'receipt_paper_width' => '58',
                'is_active' => true,
            ]
        );
    }
}
