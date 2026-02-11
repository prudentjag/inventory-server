<?php

namespace App\Console\Commands;

use App\Models\DailyReport;
use App\Models\DailyReportItem;
use Illuminate\Console\Command;

class DiagnoseDailyReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:diagnose {unit_id? : Optional unit ID to filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose and flag discrepancies in daily reports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $unitId = $this->argument('unit_id');
        
        $query = DailyReport::with('items.product');
        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        $reports = $query->orderBy('report_date', 'asc')->get();

        if ($reports->isEmpty()) {
            $this->info('No reports found to diagnose.');
            return 0;
        }

        $this->info("Diagnosing " . $reports->count() . " reports...");
        
        $discrepanciesCount = 0;

        foreach ($reports as $report) {
            $this->line("Checking Report ID: {$report->id} | Date: {$report->report_date->toDateString()} | Unit: {$report->unit_id}");
            
            foreach ($report->items as $item) {
                // Calculation: Closing = Opening + Received - Sold - Damages
                $expectedClosing = $item->opening_stock + $item->stock_received - $item->quantity_sold - $item->damages;
                $actualClosing = $item->closing_stock;

                if ($expectedClosing != $actualClosing) {
                    $this->error("  Mismatch in Product ID: {$item->product_id} ({$item->product->name})");
                    $this->error("    Expected Closing: {$expectedClosing} | Actual Closing: {$actualClosing}");
                    $discrepanciesCount++;
                }

                if ($item->opening_stock < 0) {
                    $this->warn("  Negative Opening Stock in Product ID: {$item->product_id} ({$item->product->name}): {$item->opening_stock}");
                    $discrepanciesCount++;
                }
            }
        }

        if ($discrepanciesCount === 0) {
            $this->info('All reports are consistent.');
        } else {
            $this->warn("Found {$discrepanciesCount} potential issues/discrepancies.");
        }

        return 0;
    }
}
