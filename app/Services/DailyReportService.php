<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\DailyReportItem;
use App\Models\Inventory;
use App\Models\Sale;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    /**
     * Generate a daily report for a user at a specific unit.
     * All stock quantities are measured in INDIVIDUAL UNITS (not sets).
     *
     * @param User $user The user generating the report
     * @param int $unitId The unit ID
     * @param array|null $damages Optional array of damages per product: [product_id => damage_count] (in individual units)
     * @param string|null $remark Optional remark
     * @return DailyReport
     * @throws \Exception
     */
    public function generate(User $user, $date = null, int $unitId, ?array $damages = null, ?string $remark = null): DailyReport
    {
        $today = $date ?? now()->toDateString();

        // Check if report already exists for this user/unit/date
        $existingReport = DailyReport::where('user_id', $user->id)
            ->where('unit_id', $unitId)
            ->where('report_date', $today)
            ->first();

        if ($existingReport) {
            $formattedDate = date('Y-m-d', strtotime($today));
            throw new \Exception("A daily report for this unit has already been generated for $formattedDate.");
        }

        return DB::transaction(function () use ($user, $unitId, $today, $damages, $remark) {
            // Get all products that have inventory at this unit
            $inventoryItems = Inventory::with('product')
                ->where('unit_id', $unitId)
                ->get();

            // Get the previous day's closing report for opening stock reference
            // This is crucial for consistency.
            $previousReport = DailyReport::where('unit_id', $unitId)
                ->where('report_date', '<', $today)
                ->orderBy('report_date', 'desc')
                ->first();

            $previousItems = [];
            if ($previousReport) {
                $previousItems = $previousReport->items->keyBy('product_id');
            }

            // Get today's sales for this unit
            $todaySales = Sale::with('saleItems.product')
                ->where('unit_id', $unitId)
                ->whereDate('created_at', $today)
                ->get();

            // Aggregate sales by product (already in individual units)
            $salesByProduct = [];
            $totalSalesAmount = 0;
            $totalItemsSold = 0;

            foreach ($todaySales as $sale) {
                // We sum all sales at this unit, even if made by other users, 
                // because the report is for the unit's stock level.
                $totalSalesAmount += $sale->total_amount;
                foreach ($sale->saleItems as $item) {
                    $productId = $item->product_id;
                    if (!isset($salesByProduct[$productId])) {
                        $salesByProduct[$productId] = 0;
                    }
                    $salesByProduct[$productId] += $item->quantity;
                    $totalItemsSold += $item->quantity;
                }
            }

            // Get today's approved stock requests for this unit
            $stockRequests = StockRequest::with('product')
                ->where('unit_id', $unitId)
                ->where('status', 'approved')
                ->whereDate('updated_at', $today)
                ->get();

            // Aggregate stock received by product (convert to individual units)
            $stockReceivedByProduct = [];
            foreach ($stockRequests as $request) {
                $productId = $request->product_id;
                $product = $request->product;
                
                $individualUnits = $this->toIndividualUnits($request->quantity, $product);
                
                if (!isset($stockReceivedByProduct[$productId])) {
                    $stockReceivedByProduct[$productId] = 0;
                }
                $stockReceivedByProduct[$productId] += $individualUnits;
            }

            $totalStockReceived = array_sum($stockReceivedByProduct);

            // Calculate total damages
            $totalDamages = $damages ? array_sum($damages) : 0;

            // Create the main report
            $report = DailyReport::create([
                'user_id' => $user->id,
                'unit_id' => $unitId,
                'report_date' => $today,
                'total_sales_amount' => $totalSalesAmount,
                'total_items_sold' => $totalItemsSold,
                'total_stock_received' => $totalStockReceived,
                'total_damages' => $totalDamages,
                'remark' => $remark,
                'status' => 'closed',
            ]);

            // Create report items for each product in inventory
            foreach ($inventoryItems as $inventory) {
                $productId = $inventory->product_id;
                $product = $inventory->product;

                $received = $stockReceivedByProduct[$productId] ?? 0;
                $sold = $salesByProduct[$productId] ?? 0;
                $productDamages = $damages[$productId] ?? 0;

                // Opening stock calculation
                if (isset($previousItems[$productId])) {
                    // Standard case: use previous report's closing stock
                    $openingStock = $previousItems[$productId]->closing_stock;
                    // Closing stock = Opening + Received - Sold - Damages
                    $closingStock = $openingStock + $received - $sold - $productDamages;
                } else {
                    // First report for this product/unit: 
                    // If reporting for today, use current inventory as closing.
                    // If reporting for past, we still have to use current inventory as a baseline
                    // but it might be inaccurate if there were sales between then and now.
                    // However, we'll follow the same logic as before for the first report.
                    $currentInventoryQuantity = $this->toIndividualUnits($inventory->quantity, $product);
                    
                    if ($today === now()->toDateString()) {
                        $closingStock = $currentInventoryQuantity;
                        $openingStock = $closingStock + $sold - $received + $productDamages;
                    } else {
                        // For retrospective first reports, we just calculate based on transactions
                        // But we don't know the starting point. Let's assume current inventory
                        // is the only truth if no previous reports exist.
                        $closingStock = $currentInventoryQuantity; // This is a fallback
                        $openingStock = $closingStock + $sold - $received + $productDamages;
                    }
                }

                DailyReportItem::create([
                    'daily_report_id' => $report->id,
                    'product_id' => $productId,
                    'opening_stock' => $openingStock,
                    'stock_received' => $received,
                    'quantity_sold' => $sold,
                    'damages' => $productDamages,
                    'closing_stock' => $closingStock,
                ]);
            }

            return $report->load('items.product', 'user', 'unit');
        });
    }

    /**
     * Convert a quantity to individual units based on product type.
     */
    private function toIndividualUnits(int $quantity, $product): int
    {
        if (($product->product_type ?? 'set') === 'individual') {
            return $quantity;
        }
        
        $itemsPerSet = $product->items_per_set ?? 1;
        return $quantity * $itemsPerSet;
    }
}
