<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //
    protected $fillable = [
        'name',
        'brand_id',
        'sku',
        'unit_of_measurement',
        'size',
        'items_per_set',
        'cost_price',
        'selling_price',
        'expiry_date',
        'trackable',
        'product_type'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    // Category is accessed through brand: $product->brand->category
    public function category()
    {
        return $this->hasOneThrough(Category::class, Brand::class, 'id', 'id', 'brand_id', 'category_id');
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }


    /**
     * Calculate total individual items available in central stock.
     */
    public function getTotalItemsInStockAttribute(): int
    {
        return (int) $this->stocks()->sum('quantity');
    }

    /**
     * Calculate total individual items available in a specific unit's inventory.
     */
    public function getTotalItemsInInventory(int $unitId): int
    {
        return (int) $this->inventories()->where('unit_id', $unitId)->sum('quantity');
    }

    /**
     * Format a quantity into sets and items.
     */
    public function formatQuantity(int $totalItems): string
    {
        if (($this->product_type ?? 'set') === 'individual') {
            return "{$totalItems} " . ($this->unit_of_measurement ?? 'items');
        }

        $itemsPerSet = $this->items_per_set ?? 1;
        $sets = floor($totalItems / $itemsPerSet);
        $remainder = $totalItems % $itemsPerSet;

        $result = [];
        if ($sets > 0) {
            $result[] = "{$sets} " . ($sets == 1 ? 'set' : 'sets');
        }
        if ($remainder > 0 || empty($result)) {
            $result[] = "{$remainder} " . ($remainder == 1 ? ($this->unit_of_measurement ?? 'item') : ($this->unit_of_measurement ?? 'items'));
        }

        return implode(', ', $result);
    }
}
