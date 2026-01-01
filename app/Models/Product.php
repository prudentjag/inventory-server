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
}
