<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expected_quantity' => 'string',
        'counted_quantity' => 'string',
        'variance_quantity' => 'string',
        'average_cost_snapshot' => 'string',
        'active_cost_snapshot' => 'string',
        'positive_unit_cost' => 'string',
        'variance_value' => 'string',
        'counted_at' => 'datetime',
    ];

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
