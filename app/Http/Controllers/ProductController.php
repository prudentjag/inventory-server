<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;

use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index()
    {
        return Product::with(['brand', 'category'])->get();
    }

    public function store(StoreProductRequest $request)
    {
        Gate::authorize('create', Product::class);

        $product = Product::create($request->validated());

        return response()->json($product, 201);
    }

    public function show(string $id)
    {
        return Product::with(['brand', 'category'])->findOrFail($id);
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::findOrFail($id);

        Gate::authorize('update', $product);

        $product->update($request->validated());

        return response()->json($product);
    }

    public function destroy(string $id)
    {
        $product = Product::find($id);
        if ($product) {
            Gate::authorize('delete', $product);
            $product->delete();
        }
        return response()->noContent();
    }
}
