<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $brand;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Category::create(['name' => 'Food']);
        $this->brand = Brand::create([
            'name' => 'Brand A',
            'category_id' => $this->category->id,
            'image_path' => 'brands/brand_a.png'
        ]);
    }

    public function test_product_image_upload()
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('product.jpg');

        $response = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'Product with Image',
            'brand_id' => $this->brand->id,
            'sku' => 'IMG-001',
            'unit_of_measurement' => 'item',
            'cost_price' => 100,
            'selling_price' => 150,
            'product_type' => 'individual',
            'source_type' => 'unit_produced',
            'image' => $image
        ]);

        $response->assertStatus(201);
        $productId = $response->json('data.id');
        $product = Product::find($productId);

        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);

        $this->assertEquals(asset('storage/' . $product->image_path), $product->image_url);
    }

    public function test_product_fallback_to_brand_image()
    {
        $product = Product::create([
            'name' => 'Product without Image',
            'brand_id' => $this->brand->id,
            'sku' => 'NOIMG-001',
            'unit_of_measurement' => 'item',
            'cost_price' => 100,
            'selling_price' => 150,
            'product_type' => 'individual',
            'source_type' => 'unit_produced',
            'image_path' => null
        ]);

        $this->assertNull($product->image_path);
        $this->assertEquals($this->brand->image_url, $product->image_url);
        $this->assertNotNull($product->image_url);
    }

    public function test_product_image_update()
    {
        Storage::fake('public');

        $product = Product::create([
            'name' => 'Product for Update',
            'brand_id' => $this->brand->id,
            'sku' => 'UPDATE-001',
            'unit_of_measurement' => 'item',
            'cost_price' => 100,
            'selling_price' => 150,
            'product_type' => 'individual',
            'source_type' => 'unit_produced',
        ]);

        $image = UploadedFile::fake()->image('updated_product.jpg');

        $response = $this->actingAs($this->admin)->putJson("/api/products/{$product->id}", [
            'image' => $image
        ]);

        $response->assertStatus(200);
        $product->refresh();

        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }
}
