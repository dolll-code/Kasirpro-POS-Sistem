<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CashAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'account_type',
        'opening_balance',
        'opening_balance_at',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Normalize account code: uppercase and trimmed.
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? strtoupper(trim($value)) : $value
        );
    }

    /**
     * Scope a query to only include active cash accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search accounts by name or code.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }

    /**
     * Check if this cash account has any financial movements or transactions recorded.
     * Prevents hard delete if transactions exist.
     */
    public function hasMovements(): bool
    {
        if (Schema::hasTable('cash_movements')) {
            if (DB::table('cash_movements')->where('cash_account_id', $this->id)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('sale_payments')) {
            if (DB::table('sale_payments')->where('cash_account_id', $this->id)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('supplier_payments')) {
            if (DB::table('supplier_payments')->where('cash_account_id', $this->id)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('expense_payments')) {
            if (DB::table('expense_payments')->where('cash_account_id', $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
