<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTypeStockTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $unit;
    protected $brand;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->unit = Unit::create(['name' => 'General Store']);
        $this->category = Category::create(['name' => 'Food']);
        $this->brand = Brand::create(['name' => 'Meat Vendor', 'category_id' => $this->category->id]);
    }

    public function test_individual_product_stock_calculation()
    {
        // 1. Create individual product (e.g., Meat by kg)
        $response = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'Beef',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'sku' => 'BEEF-001',
            'unit_of_measurement' => 'kg',
            'cost_price' => 5000,
            'selling_price' => 6000,
            'product_type' => 'individual',
            'quantity' => 10 // 10kg
        ]);

        $response->assertStatus(201);
        $productId = $response->json('data.id');
        $product = Product::find($productId);

        $this->assertEquals('individual', $product->product_type);
        $this->assertEquals(10, $product->total_items_in_stock);
    }

    public function test_set_product_stock_calculation()
    {
        // 1. Create set product (e.g., Water crates)
        $response = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'Water',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'sku' => 'WATER-001',
            'unit_of_measurement' => 'crate',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'product_type' => 'set',
            'items_per_set' => 12,
            'quantity' => 5 // 5 crates
        ]);

        $response->assertStatus(201);
        $productId = $response->json('data.id');
        $product = Product::find($productId);

        $this->assertEquals('set', $product->product_type);
        $this->assertEquals(60, $product->total_items_in_stock); // 5 * 12
    }

    public function test_individual_product_sale_decrement()
    {
        // Create individual product
        $product = Product::create([
            'name' => 'Beef',
            'brand_id' => $this->brand->id,
            'sku' => 'BEEF-002',
            'unit_of_measurement' => 'kg',
            'cost_price' => 5000,
            'selling_price' => 6000,
            'product_type' => 'individual',
        ]);

        // Add to unit inventory
        Inventory::create([
            'unit_id' => $this->unit->id,
            'product_id' => $product->id,
            'quantity' => 10, // 10kg
        ]);

        // Sell 3kg
        $response = $this->actingAs($this->admin)->postJson('/api/sales', [
            'unit_id' => $this->unit->id,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 6000
                ]
            ]
        ]);

        $response->assertStatus(201);
        
        $inventory = Inventory::where('unit_id', $this->unit->id)
            ->where('product_id', $product->id)
            ->first();

        // Should be exactly 7 (10 - 3)
        $this->assertEquals(7, $inventory->quantity);
    }

    public function test_set_product_sale_decrement_rounds_up()
    {
        // Create set product
        $product = Product::create([
            'name' => 'Water',
            'brand_id' => $this->brand->id,
            'sku' => 'WATER-002',
            'unit_of_measurement' => 'crate',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'product_type' => 'set',
            'items_per_set' => 12,
        ]);

        // Add to unit inventory
        Inventory::create([
            'unit_id' => $this->unit->id,
            'product_id' => $product->id,
            'quantity' => 2, // 2 crates = 24 bottles
        ]);

        // Sell 5 bottles
        $response = $this->actingAs($this->admin)->postJson('/api/sales', [
            'unit_id' => $this->unit->id,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 200
                ]
            ]
        ]);

        $response->assertStatus(201);
        
        $inventory = Inventory::where('unit_id', $this->unit->id)
            ->where('product_id', $product->id)
            ->first();

        // Should deduct 1 full crate, leaving 1 crate (ceil(5/12) = 1)
        $this->assertEquals(1, $inventory->quantity);
    }
}
