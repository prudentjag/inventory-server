<?php

namespace App\Http\Controllers;

use App\Http\Services\ResponseService;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\AuditService;

use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index()
    {
        return ResponseService::success(Product::with(['brand', 'category'])->get(), 'Products fetched successfully');
    }

    public function store(StoreProductRequest $request)
    {
        Gate::authorize('create', Product::class);

        $validated = $request->validated();
        $quantity = $validated['quantity'] ?? 0;
        unset($validated['quantity']);

        $product = Product::create($validated);

        // Only add to central stock if source_type is 'central_stock'
        if ($product->source_type === 'central_stock') {
            $stock = Stock::create([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);

            AuditService::log('product_created', $product->id, $product, null, $product->toArray(), "Product created: {$product->name}");
            AuditService::log('stock_added', $product->id, $stock, null, $stock->toArray(), "Initial stock added for product: {$product->name}");

            return ResponseService::success($product->load(['brand', 'category']), 'Product created and added to central stock', 201);
        }

        // Unit-produced products don't go to central stock
        AuditService::log('product_created', $product->id, $product, null, $product->toArray(), "Unit-produced product created: {$product->name}");
        return ResponseService::success($product->load(['brand', 'category']), 'Unit-produced product created successfully', 201);
    }

    public function show(string $id)
    {
        return Product::with(['brand', 'category'])->findOrFail($id);
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::findOrFail($id);

        Gate::authorize('update', $product);

        $oldValues = $product->toArray();
        $product->update($request->validated());
        AuditService::log('product_updated', $product->id, $product, $oldValues, $product->toArray(), "Product updated: {$product->name}");

        return  ResponseService::success($product, 'Product updated successfully');
    }

    public function destroy(string $id)
    {
        $product = Product::find($id);
        if ($product) {
            Gate::authorize('delete', $product);
            $oldValues = $product->toArray();
            $productName = $product->name;
            $product->delete();
            AuditService::log('product_deleted', $oldValues['id'], null, $oldValues, null, "Product deleted: {$productName}");
        }
        return ResponseService::success(null, 'Product deleted successfully');
    }
}
