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
        if (($this->product_type ?? 'set') === 'individual') {
            return $this->stocks()->sum('quantity');
        }
        $totalSets = $this->stocks()->sum('quantity');
        return $totalSets * ($this->items_per_set ?? 1);
    }

    /**
     * Calculate total individual items available in a specific unit's inventory.
     */
    public function getTotalItemsInInventory(int $unitId): int
    {
        if (($this->product_type ?? 'set') === 'individual') {
            return (int) $this->inventories()->where('unit_id', $unitId)->sum('quantity');
        }
        $totalSets = $this->inventories()->where('unit_id', $unitId)->sum('quantity');
        return (int) ($totalSets * ($this->items_per_set ?? 1));
    }
}
