<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Http\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicMenuController extends Controller
{
    /**
     * Get in-stock products for a specific unit.
     */
    public function index(string $unit_id)
    {
        $unit = Unit::find($unit_id);
        if (!$unit || !$unit->is_active) {
            return ResponseService::error('Unit not found or inactive', 404);
        }

        $menu = Inventory::with(['product.brand', 'product.category'])
            ->where('unit_id', $unit_id)
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($inventory) {
                return [
                    'id' => $inventory->product->id,
                    'name' => $inventory->product->name,
                    'brand' => $inventory->product->brand->name ?? null,
                    'category' => $inventory->product->category->name ?? null,
                    'image' => $inventory->product->image_path,
                    'price' => $inventory->product->selling_price,
                    'available_quantity' => $inventory->quantity,
                    'unit_of_measurement' => $inventory->product->unit_of_measurement,
                    'source_type' => $inventory->product->source_type,
                ];
            });

        return ResponseService::success([
            'unit_name' => $unit->name,
            'menu' => $menu
        ], "Menu for {$unit->name} fetched successfully");
    }

    /**
     * Place a guest order from the public menu.
     */
    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'table_number' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $totalAmount = 0;
                $itemsToCreate = [];
                $unit = Unit::find($validated['unit_id']);

                foreach ($validated['items'] as $item) {
                    $inventory = Inventory::where('unit_id', $validated['unit_id'])
                        ->where('product_id', $item['product_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$inventory || $inventory->quantity < $item['quantity']) {
                        $productName = Product::find($item['product_id'])->name ?? 'Product';
                        throw new \Exception("Insufficient stock for {$productName}");
                    }

                    $inventory->decrement('quantity', $item['quantity']);

                    $product = $inventory->product;
                    $lineTotal = $item['quantity'] * $product->selling_price;
                    $totalAmount += $lineTotal;

                    $itemsToCreate[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->selling_price,
                        'total_price' => $lineTotal,
                    ];
                }

                $sale = Sale::create([
                    'unit_id' => $validated['unit_id'],
                    'user_id' => null, // Guest order
                    'invoice_number' => 'GUEST-' . strtoupper(Str::random(10)),
                    'table_number' => $validated['table_number'] ?? null,
                    'total_amount' => $totalAmount,
                    'payment_method' => 'unspecified',
                    'payment_status' => 'pending',
                ]);

                $sale->saleItems()->createMany($itemsToCreate);

                return ResponseService::success([
                    'invoice_number' => $sale->invoice_number,
                    'total_amount' => $sale->total_amount,
                    'status' => $sale->payment_status
                ], 'Order placed successfully. Please proceed to payment.', 201);
            });
        } catch (\Exception $e) {
            return ResponseService::error($e->getMessage(), 400);
        }
    }
}
