<?php

namespace App\Http\Controllers;

use App\Models\StockRequest;
use App\Models\Stock;
use App\Models\Inventory;
use Illuminate\Http\Request;
use App\Http\Services\ResponseService;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;

class StockRequestController extends Controller
{
    /**
     * List stock requests (filtered by role/unit)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = StockRequest::with(['unit', 'product', 'requestedBy', 'approvedBy']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Stockist/Admin sees all, others see only their unit's requests
        if (!in_array($user->role, ['admin', 'stockist'])) {
            $unitIds = $user->units()->pluck('units.id');
            $query->whereIn('unit_id', $unitIds);
        }

        return ResponseService::success($query->latest()->get(), 'Stock requests fetched successfully');
    }

    /**
     * Create a stock request (Unit managers)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        $validated['requested_by'] = $request->user()->id;
        $validated['status'] = 'pending';

        $stockRequest = StockRequest::create($validated);

        return ResponseService::success(
            $stockRequest->load(['unit', 'product', 'requestedBy']),
            'Stock request created successfully',
            201
        );
    }

    /**
     * Approve a stock request (Stockist/Admin only)
     */
    public function approve(Request $request, StockRequest $stockRequest)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'stockist'])) {
            return ResponseService::error('Unauthorized', 403);
        }

        if ($stockRequest->status !== 'pending') {
            return ResponseService::error('Request already processed', 400);
        }

        // Check central stock availability
        $centralStock = Stock::where('product_id', $stockRequest->product_id)->first();

        if (!$centralStock || $centralStock->quantity < $stockRequest->quantity) {
            return ResponseService::error('Insufficient central stock', 400);
        }

        DB::transaction(function () use ($stockRequest, $centralStock, $user) {
            $oldCentralStock = $centralStock->quantity;
            // Deduct from central stock
            $centralStock->quantity -= $stockRequest->quantity;
            $centralStock->save();

            // Add to unit inventory
            $inventory = Inventory::firstOrNew([
                'unit_id' => $stockRequest->unit_id,
                'product_id' => $stockRequest->product_id
            ]);
            $oldInventory = $inventory->exists ? $inventory->quantity : 0;
            $inventory->quantity = $oldInventory + $stockRequest->quantity;
            $inventory->save();

            // Update request status
            $stockRequest->status = 'approved';
            $stockRequest->approved_by = $user->id;
            $stockRequest->save();

            // Audit logging
            AuditService::log('stock_request_approved', $stockRequest->product_id, $stockRequest, ['status' => 'pending'], ['status' => 'approved'], "Request #{$stockRequest->id} approved by {$user->name}.");
            AuditService::log('stock_updated', $stockRequest->product_id, $centralStock, ['quantity' => $oldCentralStock], ['quantity' => $centralStock->quantity], "Deducted {$stockRequest->quantity} units for request #{$stockRequest->id}.");
            AuditService::log('inventory_updated', $stockRequest->product_id, $inventory, ['quantity' => $oldInventory], ['quantity' => $inventory->quantity], "Added {$stockRequest->quantity} units from central warehouse.");
        });

        return ResponseService::success(
            $stockRequest->load(['unit', 'product', 'requestedBy', 'approvedBy']),
            'Request approved and stock distributed'
        );
    }

    /**
     * Reject a stock request (Stockist/Admin only)
     */
    public function reject(Request $request, StockRequest $stockRequest)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'stockist'])) {
            return ResponseService::error('Unauthorized', 403);
        }

        if ($stockRequest->status !== 'pending') {
            return ResponseService::error('Request already processed', 400);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string'
        ]);

        $stockRequest->status = 'rejected';
        $stockRequest->approved_by = $user->id;
        if (isset($validated['notes'])) {
            $stockRequest->notes = $validated['notes'];
        }
        $stockRequest->save();

        return ResponseService::success(
            $stockRequest->load(['unit', 'product', 'requestedBy', 'approvedBy']),
            'Request rejected'
        );
    }

    /**
     * Show specific request
     */
    public function show(StockRequest $stockRequest)
    {
        return ResponseService::success(
            $stockRequest->load(['unit', 'product', 'requestedBy', 'approvedBy']),
            'Stock request fetched'
        );
    }

    /**
     * Not used - requests are approved/rejected, not updated
     */
    public function update(Request $request, StockRequest $stockRequest)
    {
        return ResponseService::error('Use approve/reject endpoints', 400);
    }

    /**
     * Not used
     */
    public function destroy(StockRequest $stockRequest)
    {
        return ResponseService::error('Requests cannot be deleted', 400);
    }
}
