<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Inventory extends Model
{
    protected $fillable = ['unit_id', 'product_id', 'quantity', 'low_stock_threshold'];

    protected $appends = ['total_items', 'formatted_quantity'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Return total items (now same as quantity column).
     */
    public function getTotalItemsAttribute(): int
    {
        return (int) $this->quantity;
    }

    /**
     * Return human-readable quantity.
     */
    public function getFormattedQuantityAttribute(): string
    {
        return $this->product->formatQuantity($this->quantity);
    }
}
