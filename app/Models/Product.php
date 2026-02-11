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
        'product_type',
        'source_type'
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
        $itemsPerSet = $this->items_per_set ?? 1;

        if (($this->product_type ?? 'set') === 'individual' || $itemsPerSet <= 1) {
            return "{$totalItems} " . ($totalItems == 1 ? ($this->unit_of_measurement ?? 'item') : ($this->unit_of_measurement ?? 'items'));
        }

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

    /**
     * Scope to filter products from central stock.
     */
    public function scopeCentralStock($query)
    {
        return $query->where('source_type', 'central_stock');
    }

    /**
     * Scope to filter unit-produced products.
     */
    public function scopeUnitProduced($query)
    {
        return $query->where('source_type', 'unit_produced');
    }

    /**
     * Check if product is from central stock.
     */
    public function isCentralStockProduct(): bool
    {
        return $this->source_type === 'central_stock';
    }
}
