<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Services\DailyReportService;
use App\Http\Services\ResponseService;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    protected DailyReportService $reportService;

    public function __construct(DailyReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * List daily reports for the authenticated user.
     */
    public function index(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
        ]);

        $query = DailyReport::with(['user', 'unit'])
            ->where('unit_id', $request->unit_id);

        // Regular users can only see reports for units they belong to
        if (!in_array($request->user()->role, ['admin', 'stockist'])) {
            $belongsToUnit = $request->user()->units()->where('units.id', $request->unit_id)->exists();
            if (!$belongsToUnit) {
                // If not in unit, they can only see reports they created (if any)
                $query->where('user_id', $request->user()->id);
            }
        }

        $reports = $query->orderBy('report_date', 'desc')
            ->paginate(20);

        return ResponseService::success($reports, 'Daily reports fetched successfully');
    }

    /**
     * Generate a daily report (end-of-day closing).
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'date' => 'nullable|date|before_or_equal:today',
            'damages' => 'nullable|array',
            'damages.*' => 'integer|min:0',
            'remark' => 'nullable|string|max:1000',
        ]);

        try {
            $report = $this->reportService->generate(
                $request->user(),
                $validated['date'] ?? null,
                $validated['unit_id'],
                $validated['damages'] ?? null,
                $validated['remark'] ?? null
            );

            return ResponseService::success($report, 'Daily report generated successfully', 201);
        } catch (\Exception $e) {
            return ResponseService::error($e->getMessage(), 400);
        }
    }

    /**
     * View a specific daily report with all items.
     */
    public function show(Request $request, DailyReport $dailyReport)
    {
        // Allow if user is owner, admin/stockist, OR belongs to the unit
        $belongsToUnit = $request->user()->units()->where('units.id', $dailyReport->unit_id)->exists();
        
        if ($dailyReport->user_id !== $request->user()->id && 
            !in_array($request->user()->role, ['admin', 'stockist']) && 
            !$belongsToUnit) {
            return ResponseService::error('Unauthorized', 403);
        }

        return ResponseService::success(
            $dailyReport->load(['items.product', 'user', 'unit']),
            'Daily report fetched successfully'
        );
    }

    /**
     * Add or update remark on a daily report.
     * This is the only editable field on a report.
     */
    public function addRemark(Request $request, DailyReport $dailyReport)
    {
        // Ensure user can only edit their own reports
        if ($dailyReport->user_id !== $request->user()->id) {
            return ResponseService::error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'remark' => 'required|string|max:1000',
        ]);

        $dailyReport->update(['remark' => $validated['remark']]);

        return ResponseService::success(
            $dailyReport->fresh()->load(['items.product', 'user', 'unit']),
            'Remark updated successfully'
        );
    }
}
