<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * List inventory for a specific unit.
     */
    public function index(Request $request)
    {
        $request->validate(['unit_id' => 'required|exists:units,id']);

        return Inventory::with('product')
            ->where('unit_id', $request->unit_id)
            ->get();
    }

    /**
     * Add or update stock.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer',
            'low_stock_threshold' => 'integer'
        ]);

        $inventory = Inventory::firstOrNew([
            'unit_id' => $validated['unit_id'],
            'product_id' => $validated['product_id']
        ]);

        $inventory->quantity = ($inventory->exists ? $inventory->quantity : 0) + $validated['quantity'];

        if (isset($validated['low_stock_threshold'])) {
            $inventory->low_stock_threshold = $validated['low_stock_threshold'];
        }

        $inventory->save();

        return response()->json($inventory);
    }

    /**
     * Transfer stock between units.
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_unit_id' => 'required|exists:units,id',
            'to_unit_id' => 'required|exists:units,id|different:from_unit_id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            // Decrement source
            // We lock for update to prevent race conditions
            $source = Inventory::where('unit_id', $validated['from_unit_id'])
                ->where('product_id', $validated['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$source || $source->quantity < $validated['quantity']) {
                return response()->json(['message' => 'Insufficient stock in source unit.'], 400);
            }

            $source->decrement('quantity', $validated['quantity']);

            // Increment destination
            $dest = Inventory::firstOrCreate(
                ['unit_id' => $validated['to_unit_id'], 'product_id' => $validated['product_id']],
                ['quantity' => 0]
            );

            $dest->increment('quantity', $validated['quantity']);

            return response()->json(['message' => 'Transfer successful']);
        });
    }
}
