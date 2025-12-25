<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\TransferInventoryRequest;

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
    public function store(StoreInventoryRequest $request)
    {
        // Only Admin or Manager can manually adjust stock
        if (!in_array($request->user()->role, ['admin', 'manager'])) {
            abort(403, 'Unauthorized. Only managers can adjust stock manually.');
        }

        $validated = $request->validated();

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
    public function transfer(TransferInventoryRequest $request)
    {
        $validated = $request->validated();

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
