<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ProductCostHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'reference_type',
        'reference_id',
        'reference_number',
        'qty_before',
        'cost_before',
        'qty_after',
        'cost_after',
        'reason',
        'effective_at',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'qty_before' => 'string',
        'cost_before' => 'string',
        'qty_after' => 'string',
        'cost_after' => 'string',
        'effective_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Product cost histories are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Product cost histories are immutable and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
