<?php

namespace App\Http\Controllers;

use App\Http\Services\ResponseService;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\TransferInventoryRequest;
use App\Services\AuditService;

class InventoryController extends Controller
{
    /**
     * List inventory for a specific unit.
     */
    public function index(Request $request)
    {
        $request->validate(['unit_id' => 'required|exists:units,id']);

        return ResponseService::success( Inventory::with('product','product.brand','product.category')
            ->where('unit_id', $request->unit_id)
            ->get(), "Inventory for unit {$request->unit_id}");
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

        $oldQuantity = $inventory->exists ? $inventory->quantity : 0;
        $inventory->quantity = $oldQuantity + $validated['quantity'];

        if (isset($validated['low_stock_threshold'])) {
            $inventory->low_stock_threshold = $validated['low_stock_threshold'];
        }

        $inventory->save();

        AuditService::log('inventory_updated', $inventory->product_id, $inventory, ['quantity' => $oldQuantity], ['quantity' => $inventory->quantity], "Manual stock adjustment for product ID: {$validated['product_id']}.");

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

            $oldSourceQuantity = $source->quantity;
            $source->decrement('quantity', $validated['quantity']);

            // Increment destination
            $dest = Inventory::firstOrCreate(
                ['unit_id' => $validated['to_unit_id'], 'product_id' => $validated['product_id']],
                ['quantity' => 0]
            );

            $oldDestQuantity = $dest->quantity;
            $dest->increment('quantity', $validated['quantity']);

            AuditService::log('inventory_transfer', $validated['product_id'], $source, ['quantity' => $oldSourceQuantity], ['quantity' => $source->fresh()->quantity], "Transferred {$validated['quantity']} units out to unit ID: {$validated['to_unit_id']}.");
            AuditService::log('inventory_transfer', $validated['product_id'], $dest, ['quantity' => $oldDestQuantity], ['quantity' => $dest->fresh()->quantity], "Transferred {$validated['quantity']} units in from unit ID: {$validated['from_unit_id']}.");

            return response()->json(['message' => 'Transfer successful']);
        });
    }
}
