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
        $unitId = $request->unit_id;

        // Get existing inventory records
        $inventory = Inventory::with(['product.brand', 'product.category'])
            ->where('unit_id', $unitId)
            ->get();

        $existingProductIds = $inventory->pluck('product_id')->toArray();

        // Dynamically include all 'unit_produced' products that aren't in the inventory table yet
        $unitProducedProducts = \App\Models\Product::where('source_type', 'unit_produced')
            ->whereNotIn('id', $existingProductIds)
            ->with(['brand', 'category'])
            ->get();

        // Create virtual inventory entries for on-demand items
        $virtualEntries = $unitProducedProducts->map(function ($product) use ($unitId) {
            $iv = new Inventory([
                'unit_id' => (int)$unitId,
                'product_id' => $product->id,
                'quantity' => 0,
                'low_stock_threshold' => 0,
            ]);
            // Manually load the relation so the frontend gets the expected structure
            $iv->setRelation('product', $product);
            return $iv;
        });

        // Merge actual and virtual inventory
        $combined = $inventory->concat($virtualEntries);

        return ResponseService::success($combined, "Inventory for unit {$unitId}");
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
        
        // Add support for sets and items in StoreInventoryRequest (or handle here if not in request)
        $product = \App\Models\Product::findOrFail($validated['product_id']);
        $itemsPerSet = $product->items_per_set ?? 1;

        // Ensure only one input method is used to prevent double-counting
        $hasQuantity = isset($validated['quantity']) && $validated['quantity'] > 0;
        $hasSetsOrItems = (isset($request->sets) && $request->sets > 0) 
                       || (isset($request->items) && $request->items > 0);

        if ($hasQuantity && $hasSetsOrItems) {
            return ResponseService::error(
                'Cannot provide both "quantity" and "sets/items". Please use one input method or the other.',
                400
            );
        }

        // Calculate total based on the input method used
        if ($hasQuantity) {
            $totalToAdd = $validated['quantity'];
        } else {
            $totalToAdd = 0;
            if (isset($request->sets)) {
                $totalToAdd += $request->sets * $itemsPerSet;
            }
            if (isset($request->items)) {
                $totalToAdd += $request->items;
            }
        }

        if ($totalToAdd <= 0 && !isset($validated['low_stock_threshold'])) {
            return ResponseService::error('No quantity or threshold provided', 400);
        }

        $inventory = Inventory::firstOrNew([
            'unit_id' => $validated['unit_id'],
            'product_id' => $validated['product_id']
        ]);

        $oldQuantity = $inventory->exists ? $inventory->quantity : 0;
        $inventory->quantity = $oldQuantity + $totalToAdd;

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
