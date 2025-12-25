<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Http\Requests\StoreSaleRequest;

class SalesController extends Controller
{
    /**
     * Store a new sale transaction.
     */
    public function store(StoreSaleRequest $request)
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated, $request) {
                $totalAmount = 0;
                $itemsToCreate = [];

                // Check stock and calculate total
                foreach ($validated['items'] as $item) {
                    $inventory = Inventory::where('unit_id', $validated['unit_id'])
                        ->where('product_id', $item['product_id'])
                        ->lockForUpdate() // Pessimistic locking
                        ->first();

                    if (!$inventory || $inventory->quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product ID {$item['product_id']}");
                    }

                    $inventory->decrement('quantity', $item['quantity']);

                    $lineTotal = $item['quantity'] * $item['unit_price'];
                    $totalAmount += $lineTotal;

                    $itemsToCreate[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $lineTotal,
                    ];
                }

                // Create Sale
                $sale = Sale::create([
                    'unit_id' => $validated['unit_id'],
                    'user_id' => $request->user()->id,
                    'invoice_number' => 'INV-' . strtoupper(Str::random(10)),
                    'total_amount' => $totalAmount,
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $validated['payment_status'] ?? 'pending',
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                ]);

                // Create Items
                $sale->saleItems()->createMany($itemsToCreate);

                return response()->json($sale->load('saleItems'), 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * View sales history for a unit.
     */
    public function history(Request $request, string $unit_id)
    {
        return Sale::with(['user', 'saleItems.product'])
            ->where('unit_id', $unit_id)
            ->latest()
            ->paginate(20);
    }
}
