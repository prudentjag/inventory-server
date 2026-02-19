<?php

use App\Models\User;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function testCreateProduct($sourceType, $quantity = null) {
    echo "Testing creation for source_type: {$sourceType}...\n";
    
    $admin = User::firstOrCreate(['email' => 'admin@test.com'], [
        'name' => 'Admin Test',
        'password' => bcrypt('password'),
        'role' => 'admin'
    ]);

    $category = Category::firstOrCreate(['name' => 'Test Category']);
    $brand = Brand::firstOrCreate(['name' => 'Test Brand'], ['category_id' => $category->id]);

    $data = [
        'name' => 'Test Product ' . $sourceType . ' ' . time(),
        'brand_id' => $brand->id,
        'sku' => 'SKU-' . $sourceType . '-' . time(),
        'unit_of_measurement' => 'item',
        'cost_price' => 100,
        'selling_price' => 200,
        'source_type' => $sourceType,
    ];

    if ($quantity !== null) {
        $data['quantity'] = $quantity;
    }

    $request = Request::create('/api/products', 'POST', $data);
    $request->setUserResolver(fn() => $admin);

    try {
        $controller = new ProductController();
        $response = $controller->store(new \App\Http\Requests\StoreProductRequest($data));
        
        $statusCode = $response->getStatusCode();
        echo "Response Code: {$statusCode}\n";
        
        if ($statusCode === 201) {
            $productData = json_decode($response->getContent(), true)['data'];
            $productId = $productData['id'];
            echo "Product Created ID: {$productId}\n";
            
            // Check if stock entry exists
            $stockExists = Stock::where('product_id', $productId)->exists();
            echo "Stock Entry Created: " . ($stockExists ? "YES (Incorrect for unit_produced)" : "NO (Correct for unit_produced)") . "\n";
        } else {
            echo "Error: " . $response->getContent() . "\n";
        }
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    echo "-----------------------------------\n";
}

// Case 1: Unit Produced (No quantity) - Should SUCCEED
testCreateProduct('unit_produced');

// Case 2: Central Stock (No quantity) - Should FAIL validation
testCreateProduct('central_stock');

// Case 3: Central Stock (With quantity) - Should SUCCEED
testCreateProduct('central_stock', 50);
