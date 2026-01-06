<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    
    /**
     * Log an action in the audit logs.
     *
     * @param string $action The action type (e.g., 'stock_added', 'product_updated')
     * @param int|null $productId The ID of the product related to this log (optional)
     * @param mixed $auditable The model instance being audited (optional)
     * @param array|null $oldValues The previous state of the model
     * @param array|null $newValues The new state of the model
     * @param string|null $description A human-readable description
     * @return AuditLog
     */
    public static function log(string $action, int $productId = null, $auditable = null, array $oldValues = null, array $newValues = null, string $description = null)
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'product_id' => $productId,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
