<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Services\ResponseService;
use App\Services\AuditService;

class StockController extends Controller
{
    /**
     * List all central stock with product info
     */
    public function index()
    {
        $stock = Stock::with('product')->get();
        return ResponseService::success($stock, 'Central stock fetched successfully');
    }

    /**
     * Add stock to central warehouse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:0',
            'sets' => 'nullable|integer|min:0',
            'items' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'integer|min:0',
            'batch_number' => 'nullable|string'
        ]);

        $product = \App\Models\Product::findOrFail($validated['product_id']);
        $itemsPerSet = $product->items_per_set ?? 1;

        // Calculate total items to add
        $totalToAdd = $validated['quantity'] ?? 0;
        if (isset($validated['sets'])) {
            $totalToAdd += $validated['sets'] * $itemsPerSet;
        }
        if (isset($validated['items'])) {
            $totalToAdd += $validated['items'];
        }

        if ($totalToAdd <= 0) {
            return ResponseService::error('Total quantity to add must be greater than 0', 400);
        }

        // Check if stock entry for this product already exists
        $stock = Stock::where('product_id', $validated['product_id'])->first();

        if ($stock) {
            // Add to existing quantity
            $oldQuantity = $stock->quantity;
            $stock->quantity += $totalToAdd;
            if (isset($validated['low_stock_threshold'])) {
                $stock->low_stock_threshold = $validated['low_stock_threshold'];
            }
            if (isset($validated['batch_number'])) {
                $stock->batch_number = $validated['batch_number'];
            }
            $stock->save();

            AuditService::log('stock_updated', $stock->product_id, $stock, ['quantity' => $oldQuantity], ['quantity' => $stock->quantity], "Added {$totalToAdd} units to central stock.");
        } else {
            $stockData = $validated;
            $stockData['quantity'] = $totalToAdd;
            $stock = Stock::create($stockData);
            AuditService::log('stock_added', $stock->product_id, $stock, null, $stock->toArray(), "Initial stock added for product ID: {$validated['product_id']}.");
        }

        return ResponseService::success($stock->load('product'), 'Stock added successfully', 201);
    }

    /**
     * Show specific stock entry
     */
    public function show(Stock $stock)
    {
        return ResponseService::success($stock->load('product'), 'Stock fetched successfully');
    }

    /**
     * Update stock entry
     */
    public function update(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'quantity' => 'integer|min:0',
            'low_stock_threshold' => 'integer|min:0',
            'batch_number' => 'nullable|string'
        ]);

        $oldValues = $stock->toArray();
        $stock->update($validated);
        AuditService::log('stock_updated', $stock->product_id, $stock, $oldValues, $stock->toArray(), "Stock updated: " . json_encode($validated));
        return ResponseService::success($stock->load('product'), 'Stock updated successfully');
    }

    /**
     * Delete stock entry (rarely used)
     */
    public function destroy(Stock $stock)
    {
        $stock->delete();
        return ResponseService::success(null, 'Stock deleted successfully');
    }
}
