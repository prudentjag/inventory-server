#!/usr/bin/env php
<?php

/**
 * Diagnostic Script: Identify Inventory Discrepancies
 * 
 * This script analyzes stock requests and audit logs to identify
 * potential inventory discrepancies caused by additive quantity fields.
 * 
 * Usage: php scripts/diagnose_inventory_discrepancies.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=================================================\n";
echo "  Inventory Discrepancy Diagnostic Report\n";
echo "=================================================\n\n";

// 1. Check for suspiciously high stock request quantities
echo "1. Analyzing Stock Requests for Suspicious Quantities...\n";
echo str_repeat("-", 80) . "\n";

$suspiciousRequests = DB::table('stock_requests as sr')
    ->join('products as p', 'sr.product_id', '=', 'p.id')
    ->join('units as u', 'sr.unit_id', '=', 'u.id')
    ->select(
        'sr.id',
        'sr.created_at',
        'p.name as product_name',
        'p.items_per_set',
        'sr.quantity as stored_quantity',
        'sr.status',
        'u.name as unit_name'
    )
    ->where('sr.status', 'approved')
    ->whereRaw('sr.quantity % p.items_per_set = 0')  // Exact multiples
    ->whereRaw('(sr.quantity / p.items_per_set) % 2 = 0')  // Even number of sets (potential double)
    ->orderBy('sr.created_at', 'desc')
    ->limit(50)
    ->get();

if ($suspiciousRequests->isEmpty()) {
    echo "✓ No obviously suspicious stock requests found.\n\n";
} else {
    echo "⚠ Found " . $suspiciousRequests->count() . " potentially doubled stock requests:\n\n";
    
    foreach ($suspiciousRequests as $req) {
        $sets = $req->stored_quantity / $req->items_per_set;
        echo sprintf(
            "  Request #%d (%s)\n" .
            "    Product: %s (items_per_set: %d)\n" .
            "    Unit: %s\n" .
            "    Stored Quantity: %d items (%d sets)\n" .
            "    Status: %s\n" .
            "    → Possible Issue: Could be %d sets instead of %d sets\n\n",
            $req->id,
            $req->created_at,
            $req->product_name,
            $req->items_per_set,
            $req->unit_name,
            $req->stored_quantity,
            $sets,
            $req->status,
            $sets / 2,
            $sets
        );
    }
}

// 2. Check audit logs for large inventory additions
echo "2. Analyzing Recent Large Inventory Additions...\n";
echo str_repeat("-", 80) . "\n";

$largeAdditions = DB::table('audit_logs as al')
    ->join('products as p', 'al.product_id', '=', 'p.id')
    ->select(
        'al.id',
        'al.created_at',
        'al.action',
        'p.name as product_name',
        'p.items_per_set',
        'al.new_values',
        'al.old_values',
        'al.description'
    )
    ->where('al.action', 'inventory_updated')
    ->whereDate('al.created_at', '>=', now()->subDays(30))
    ->orderBy('al.created_at', 'desc')
    ->limit(100)
    ->get();

$suspiciousAdditions = [];
foreach ($largeAdditions as $log) {
    $oldValues = json_decode($log->old_values, true);
    $newValues = json_decode($log->new_values, true);
    
    if (isset($oldValues['quantity']) && isset($newValues['quantity'])) {
        $added = $newValues['quantity'] - $oldValues['quantity'];
        
        // Check if added quantity is suspiciously high (exact double of a set count)
        if ($added > 0 && $log->items_per_set > 1) {
            $sets = $added / $log->items_per_set;
            if ($sets == floor($sets) && $sets % 2 == 0 && $sets >= 4) {
                $suspiciousAdditions[] = [
                    'log' => $log,
                    'added' => $added,
                    'sets' => $sets
                ];
            }
        }
    }
}

if (empty($suspiciousAdditions)) {
    echo "✓ No obviously suspicious inventory additions found.\n\n";
} else {
    echo "⚠ Found " . count($suspiciousAdditions) . " potentially doubled inventory additions:\n\n";
    
    foreach ($suspiciousAdditions as $item) {
        $log = $item['log'];
        $added = $item['added'];
        $sets = $item['sets'];
        
        echo sprintf(
            "  Audit Log #%d (%s)\n" .
            "    Product: %s (items_per_set: %d)\n" .
            "    Added: %d items (%d sets)\n" .
            "    Description: %s\n" .
            "    → Possible Issue: Could be %d sets instead of %d sets\n\n",
            $log->id,
            $log->created_at,
            $log->product_name,
            $log->items_per_set,
            $added,
            $sets,
            $log->description,
            $sets / 2,
            $sets
        );
    }
}

// 3. Summary statistics
echo "3. Summary Statistics\n";
echo str_repeat("-", 80) . "\n";

$totalApprovedRequests = DB::table('stock_requests')
    ->where('status', 'approved')
    ->count();

$totalInventoryUpdates = DB::table('audit_logs')
    ->where('action', 'inventory_updated')
    ->whereDate('created_at', '>=', now()->subDays(30))
    ->count();

echo sprintf(
    "  Total Approved Stock Requests: %d\n" .
    "  Total Inventory Updates (last 30 days): %d\n" .
    "  Suspicious Stock Requests: %d\n" .
    "  Suspicious Inventory Additions: %d\n\n",
    $totalApprovedRequests,
    $totalInventoryUpdates,
    $suspiciousRequests->count(),
    count($suspiciousAdditions)
);

echo "=================================================\n";
echo "  Recommendations:\n";
echo "=================================================\n\n";

if ($suspiciousRequests->count() > 0 || count($suspiciousAdditions) > 0) {
    echo "⚠ POTENTIAL ISSUES DETECTED\n\n";
    echo "1. Review the suspicious entries listed above\n";
    echo "2. Check your frontend code to see if it's sending both 'quantity' and 'sets' fields\n";
    echo "3. Consider creating a correction script to fix inflated inventory quantities\n";
    echo "4. The fix has been applied to prevent future occurrences\n\n";
} else {
    echo "✓ No obvious discrepancies detected\n\n";
    echo "However, this script uses heuristics and may not catch all issues.\n";
    echo "If you're still seeing discrepancies:\n";
    echo "1. Check your frontend request payloads\n";
    echo "2. Review audit logs for specific products\n";
    echo "3. Compare expected vs actual inventory quantities\n\n";
}

echo "=================================================\n";
