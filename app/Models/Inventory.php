<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Inventory extends Model
{
    protected $fillable = ['unit_id', 'product_id', 'quantity', 'low_stock_threshold'];

    protected $appends = ['total_items'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate total individual items from sets.
     */
    public function getTotalItemsAttribute(): int
    {
        if ($this->product->product_type === 'individual') {
            return $this->quantity;
        }
        $itemsPerSet = $this->product->items_per_set ?? 1;
        return $this->quantity * $itemsPerSet;
    }
}
