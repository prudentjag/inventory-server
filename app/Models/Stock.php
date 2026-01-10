<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = ['product_id', 'quantity', 'low_stock_threshold', 'batch_number'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($stock) {
            if (empty($stock->batch_number)) {
                // Format: BATCH-YYYYMMDD-XXXX (e.g., BATCH-20251227-0001)
                $date = now()->format('Ymd');
                $count = self::whereDate('created_at', today())->count() + 1;
                $stock->batch_number = sprintf('BATCH-%s-%04d', $date, $count);
            }
        });
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
        $itemsPerSet = $this->product->items_per_set ?? 1;
        return $this->quantity * $itemsPerSet;
    }
}
