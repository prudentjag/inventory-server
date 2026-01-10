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
        'cost_price',
        'selling_price',
        'expiry_date',
        'trackable'
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
     * Get items_per_set from the associated brand.
     * Defaults to 1 if not set (treat as individual items).
     */
    public function getItemsPerSetAttribute(): int
    {
        return $this->brand->items_per_set ?? 1;
    }

    /**
     * Calculate total individual items available in central stock.
     */
    public function getTotalItemsInStockAttribute(): int
    {
        $totalSets = $this->stocks()->sum('quantity');
        return $totalSets * $this->items_per_set;
    }

    /**
     * Calculate total individual items available in a specific unit's inventory.
     */
    public function getTotalItemsInInventory(int $unitId): int
    {
        $totalSets = $this->inventories()->where('unit_id', $unitId)->sum('quantity');
        return $totalSets * $this->items_per_set;
    }
}
