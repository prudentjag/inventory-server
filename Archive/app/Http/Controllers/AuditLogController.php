<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\AuditLog;
use App\Http\Services\ResponseService;

class AuditLogController extends Controller
{
    /**
     * List all audit logs (Admin/Stockist only)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'stockist'])) {
            return ResponseService::error('Unauthorized', 403);
        }

        $query = AuditLog::with('user');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('auditable_type')) {
            $query->where('auditable_type', 'App\\Models\\' . $request->auditable_type);
        }

        if ($request->has('auditable_id')) {
            $query->where('auditable_id', $request->auditable_id);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return ResponseService::success($query->latest()->paginate(50), 'Audit logs fetched successfully');
    }

    /**
     * Get audit trail for a specific resource
     */
    public function resourceTrail(Request $request, $type, $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'stockist'])) {
            return ResponseService::error('Unauthorized', 403);
        }

        $logs = AuditLog::with('user')
            ->where('auditable_type', 'App\\Models\\' . ucfirst($type))
            ->where('auditable_id', $id)
            ->latest()
            ->get();

        return ResponseService::success($logs, 'Resource audit trail fetched successfully');
    }
}
