<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return Product::with(['brand', 'category'])->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|unique:products|string',
            'unit_of_measurement' => 'required|string',
            'cost_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'expiry_date' => 'nullable|date',
            'trackable' => 'boolean',
        ]);

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function show(string $id)
    {
        return Product::with(['brand', 'category'])->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string',
            'brand_id' => 'exists:brands,id',
            'category_id' => 'exists:categories,id',
            'sku' => 'unique:products,sku,' . $id,
            'unit_of_measurement' => 'string',
            'cost_price' => 'numeric',
            'selling_price' => 'numeric',
            'expiry_date' => 'nullable|date',
            'trackable' => 'boolean',
        ]);

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(string $id)
    {
        Product::destroy($id);
        return response()->noContent();
    }
}
