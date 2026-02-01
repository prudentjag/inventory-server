<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = ['product_id', 'quantity', 'low_stock_threshold', 'batch_number'];

    protected $appends = ['total_items', 'formatted_quantity'];

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
