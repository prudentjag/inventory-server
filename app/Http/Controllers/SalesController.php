<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Services\ResponseService;
use App\Services\MonnifyService;

class SalesController extends Controller
{
    protected $monnify;

    public function __construct(MonnifyService $monnify)
    {
        $this->monnify = $monnify;
    }

    /**
     * List all sales transactions (Admin/Stockist only).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'stockist'])) {
            return ResponseService::error('Unauthorized', 403);
        }

        $query = Sale::with(['user', 'saleItems.product', 'unit']);

        // Apply shared filters
        $this->applySalesFilters($query, $request);

        return ResponseService::success($query->latest()->paginate(50), 'All sales fetched successfully');
    }

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
                    $inventory = Inventory::with('product')
                        ->where('unit_id', $validated['unit_id'])
                        ->where('product_id', $item['product_id'])
                        ->lockForUpdate() // Pessimistic locking
                        ->first();

                    if (!$inventory) {
                        throw new \Exception("Insufficient stock for product ID {$item['product_id']}");
                    }

                    // Calculate available items (stored directly in quantity)
                    $availableItems = $inventory->quantity;
                    
                    // Check if enough items are available
                    if ($availableItems < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product ID {$item['product_id']}. Available: {$availableItems} items, Requested: {$item['quantity']} items");
                    }

                    // Deduct the exact quantity from inventory (tracked in base units)
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

                // Determine payment status: check passed status, default to pending
                $paymentStatus = $validated['payment_status'] ?? 'pending';

                // Create Sale
                $sale = Sale::create([
                    'unit_id' => $validated['unit_id'],
                    'user_id' => $validated['server_id'] ?? $request->user()->id,
                    'invoice_number' => 'INV-' . strtoupper(Str::random(10)),
                    'table_number' => $validated['table_number'] ?? null,
                    'total_amount' => $totalAmount,
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $paymentStatus,
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                ]);

                // Create Items
                $sale->saleItems()->createMany($itemsToCreate);

                // Handle Monnify Virtual Account (Invoice) if requested
                if (strtolower($validated['payment_method']) === 'monnify') {
                    if (!$this->monnify->isConfigured()) {
                        throw new \Exception("Monnify is not configured. Please check your credentials.");
                    }

                    $monnifyData = $this->monnify->createInvoice([
                        'amount' => $totalAmount,
                        'customer_name' => $request->user()->name,
                        'customer_email' => $request->user()->email,
                        'payment_reference' => $sale->invoice_number,
                        'description' => "Order #{$sale->invoice_number} from " . ($sale->unit->name ?? 'POS'),
                        'redirect_url' => $validated['redirect_url'] ?? config('app.url') . '/payment/callback',
                    ]);

                    if ($monnifyData && $monnifyData['requestSuccessful']) {
                        return ResponseService::success([
                            'sale' => $sale->load('saleItems'),
                            'account_details' => $monnifyData['responseBody']
                        ], 'Virtual Account Generated. Transfer funds to complete sale.', 201);
                    }

                    throw new \Exception("Failed to generate virtual account: " . ($monnifyData['responseMessage'] ?? 'Unknown error'));
                }

                return ResponseService::success($sale->load('saleItems'), 'Sale completed successfully', 201);
            });
        } catch (\Exception $e) {
            return ResponseService::error($e->getMessage(), 400);
        }
    }

    /**
     * View sales history for a unit.
     */
    public function history(Request $request, string $unit_id)
    {
        $query = Sale::with(['user', 'saleItems.product'])
            ->where('unit_id', $unit_id);

        $this->applySalesFilters($query, $request);

        return ResponseService::success($query->latest()->paginate(20), "Sales history for unit {$unit_id}");
    }

    /**
     * View personal sales history across all units.
     */
    public function mySales(Request $request)
    {
        $query = Sale::with(['unit', 'saleItems.product', 'user'])
            ->where('user_id', $request->user()->id);

        $this->applySalesFilters($query, $request);

        return ResponseService::success($query->latest()->paginate(20), 'Your personal sales history fetched successfully');
    }

    /**
     * Helper to apply common sales filters.
     */
    protected function applySalesFilters($query, Request $request)
    {
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->has('user_id')) {
            if ($request->user_id === 'unassigned') {
                $query->whereNull('user_id');
            } elseif (in_array($request->user()->role, ['admin', 'stockist'])) {
                $query->where('user_id', $request->user_id);
            }
        }
    }

    /**
     * Manually verify payment status for a sale.
     */
    public function verifyPayment(Request $request, string $invoice_number)
    {
        $sale = Sale::where('invoice_number', $invoice_number)->first();

        if (!$sale) {
            return ResponseService::error('Sale not found', 404);
        }

        if ($sale->payment_status === 'paid') {
            return ResponseService::success($sale, 'Payment already confirmed');
        }

        $result = $this->monnify->verifyTransaction($invoice_number);

        if ($result && $result['requestSuccessful']) {
            $transactionStatus = $result['responseBody']['paymentStatus'] ?? null;

            if ($transactionStatus === 'PAID') {
                $sale->update([
                    'payment_status' => 'paid',
                    'transaction_reference' => $result['responseBody']['transactionReference'] ?? null
                ]);
                return ResponseService::success($sale->fresh(), 'Payment verified and confirmed');
            }

            return ResponseService::success([
                'sale' => $sale,
                'monnify_status' => $transactionStatus
            ], 'Payment not yet received');
        }

        return ResponseService::error('Unable to verify payment: ' . ($result['responseMessage'] ?? 'Unknown error'), 400);
    }

    /**
     * Display a single sale/invoice.
     */
    public function show(string $invoice_number)
    {
        $sale = Sale::with(['user', 'saleItems.product', 'unit'])
            ->where('invoice_number', $invoice_number)
            ->first();

        if (!$sale) {
            return ResponseService::error('Invoice not found', 404);
        }

        return ResponseService::success($sale, 'Invoice retrieved successfully');
    }

    /**
     * Add items to an existing pending invoice.
     */
    public function addItems(Request $request, string $invoice_number)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric',
        ]);

        $sale = Sale::where('invoice_number', $invoice_number)->first();

        if (!$sale) {
            return ResponseService::error('Invoice not found', 404);
        }

        if ($sale->payment_status !== 'pending') {
            return ResponseService::error('Cannot add items to a completed sale', 400);
        }

        try {
            return DB::transaction(function () use ($validated, $sale, $request) {
                $additionalAmount = 0;
                $itemsToCreate = [];

                foreach ($validated['items'] as $item) {
                    $inventory = Inventory::where('unit_id', $sale->unit_id)
                        ->where('product_id', $item['product_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$inventory || $inventory->quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product ID {$item['product_id']}");
                    }

                    $inventory->decrement('quantity', $item['quantity']);

                    $lineTotal = $item['quantity'] * $item['unit_price'];
                    $additionalAmount += $lineTotal;

                    $itemsToCreate[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $lineTotal,
                    ];
                }

                $sale->saleItems()->createMany($itemsToCreate);
                $sale->increment('total_amount', $additionalAmount);

                $sale->refresh();

                // If monnify, re-generate/update virtual account details
                if (strtolower($sale->payment_method) === 'monnify') {
                    if ($this->monnify->isConfigured()) {
                        $monnifyData = $this->monnify->createInvoice([
                            'amount' => $sale->total_amount,
                            'customer_name' => $sale->user->name,
                            'customer_email' => $sale->user->email,
                            'payment_reference' => $sale->invoice_number,
                            'description' => "Updated Order #{$sale->invoice_number} from " . ($sale->unit->name ?? 'POS'),
                        ]);

                        if ($monnifyData && $monnifyData['requestSuccessful']) {
                            return ResponseService::success([
                                'sale' => $sale->load('saleItems.product'),
                                'account_details' => $monnifyData['responseBody']
                            ], 'Items added and payment amount updated.', 200);
                        }
                    }
                }

                return ResponseService::success($sale->load('saleItems.product'), 'Items added to invoice successfully');
            });
        } catch (\Exception $e) {
            return ResponseService::error($e->getMessage(), 400);
        }
    }
    /**
     * Mark a pending invoice as paid.
     */
    public function markPaid(Request $request, string $invoice_number)
    {
        $sale = Sale::where('invoice_number', $invoice_number)->first();

        if (!$sale) {
            return ResponseService::error('Invoice not found', 404);
        }

        if ($sale->payment_status === 'paid') {
            return ResponseService::success($sale, 'Invoice is already marked as paid');
        }

        // Authorization: Only owner (server) or admin/stockist can mark as paid
        $user = $request->user();
        if ($sale->user_id !== $user->id && !in_array($user->role, ['admin', 'stockist'])) {
            return ResponseService::error('Unauthorized. Only the owner of this sale can mark it as paid.', 403);
        }

        $sale->update([
            'payment_status' => 'paid',
            'transaction_reference' => $request->transaction_reference ?? $sale->transaction_reference
        ]);

        return ResponseService::success($sale->fresh(), 'Invoice marked as paid successfully');
    }

    /**
     * Claim an unassigned guest order.
     */
    public function claim(Request $request, string $invoice_number)
    {
        $sale = Sale::where('invoice_number', $invoice_number)
            ->whereNull('user_id')
            ->first();

        if (!$sale) {
            return ResponseService::error('Order not found or already assigned', 404);
        }

        if ($sale->payment_status !== 'pending') {
            return ResponseService::error('Only pending orders can be claimed', 400);
        }

        $sale->update([
            'user_id' => $request->user()->id
        ]);

        return ResponseService::success($sale->load('user'), 'Order claimed successfully. It is now assigned to you.');
    }
}
