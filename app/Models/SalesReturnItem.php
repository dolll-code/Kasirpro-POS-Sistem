<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'string',
        'selling_price_snapshot' => 'string',
        'item_discount_snapshot' => 'string',
        'transaction_discount_snapshot' => 'string',
        'net_unit_price_snapshot' => 'string',
        'cost_price_snapshot' => 'string',
        'return_value' => 'string',
        'cost_value' => 'string',
        'damaged_loss' => 'string',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
