<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected $brand;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Test Cat', 'description' => 'Desc']);
        $this->brand = Brand::create(['name' => 'Test Brand', 'category_id' => $this->category->id]);
    }

    public function test_admin_can_create_product()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // $brand = Brand::first() ?? Brand::create(['name' => 'Test Brand', 'category_id' => 1]); // simplistic
        // $category = Category::first() ?? Category::create(['name' => 'Test Cat']);

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Admin Product',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'sku' => 'ADMIN-SKU-' . rand(100, 999),
            'unit_of_measurement' => 'pcs',
            'cost_price' => 10,
            'selling_price' => 20,
            'trackable' => true
        ]);

        $response->assertStatus(201);
    }

    public function test_staff_cannot_create_product()
    {
        $staff = User::factory()->create(['role' => 'staff']);
        // $brand = Brand::first();
        // $category = Category::first();

        $response = $this->actingAs($staff)->postJson('/api/products', [
            'name' => 'Staff Product',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'sku' => 'STAFF-SKU-' . rand(100, 999),
            'unit_of_measurement' => 'pcs',
            'cost_price' => 10,
            'selling_price' => 20,
            'trackable' => true
        ]);

        $response->assertStatus(403);
    }
}
