<?php

use App\Models\Product;
use App\Models\Stock;
use App\Models\Inventory;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SalesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// This script is intended to be run via 'php artisan tinker verify_sets.php' 
// or by pasting the logic into tinker.

function verifySetLogic() {
    echo "Starting verification...\n";

    // 1. Create a test product with 12 items per set
    $brandId = \App\Models\Brand::first()?->id ?? 1;
    $product = Product::create([
        'name' => 'Verification Soap',
        'brand_id' => $brandId,
        'sku' => 'VERIFY-' . time(),
        'product_type' => 'set',
        'items_per_set' => 12,
        'unit_of_measurement' => 'bottles',
        'cost_price' => 100,
        'selling_price' => 150,
        'trackable' => true
    ]);
    echo "Product created: {$product->name} (12 per set)\n";

    // 2. Add 1 set and 2 bottles to central stock
    // Mimicking the controller logic
    $totalToAdd = (1 * 12) + 2; // 1 set + 2 bottles = 14 bottles
    $stock = Stock::create([
        'product_id' => $product->id,
        'quantity' => $totalToAdd,
        'batch_number' => 'V-BATCH-001'
    ]);
    echo "Stock added: {$stock->quantity} bottles total. Expected: 14.\n";
    echo "Human readable: {$product->formatQuantity($stock->quantity)}\n";
    if ($product->formatQuantity($stock->quantity) !== "1 set, 2 bottles") {
        echo "FAILED: Format should be '1 set, 2 bottles'\n";
    }

    // 3. Move to unit inventory (mimicking StockRequest approval or manual store)
    $unitId = \App\Models\Unit::first()?->id ?? 1;
    $inventory = Inventory::create([
        'unit_id' => $unitId, 
        'product_id' => $product->id,
        'quantity' => 14
    ]);
    echo "Inventory created for unit 1: {$inventory->quantity} bottles.\n";

    // 4. Sell 1 bottle
    // Mimicking SalesController decrement
    $inventory->decrement('quantity', 1);
    $inventory->refresh();
    echo "After selling 1 bottle: {$inventory->quantity} bottles remaining. Expected: 13.\n";
    echo "Human readable: {$inventory->formatted_quantity}\n";
    if ($inventory->formatted_quantity !== "1 set, 1 bottle") {
         echo "FAILED: Format should be '1 set, 1 bottle'\n";
    }

    // 5. Sell 2 more bottles (crosses set boundary)
    $inventory->decrement('quantity', 2);
    $inventory->refresh();
    echo "After selling 2 more bottles: {$inventory->quantity} bottles remaining. Expected: 11.\n";
    echo "Human readable: {$inventory->formatted_quantity}\n";
    if ($inventory->formatted_quantity !== "11 bottles") {
         echo "FAILED: Format should be '11 bottles' (no sets)\n";
    }

    // Cleanup
    $inventory->delete();
    $stock->delete();
    $product->delete();
    echo "Verification complete and cleaned up.\n";
}

verifySetLogic();
