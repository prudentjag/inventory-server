<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToUnit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin can access everything
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Determine unit_id from request input or route parameter
        $unitId = $request->input('unit_id') ?? $request->route('unit_id');

        // If no unit context is involved, let it pass (or strict block depending on logic)
        // For now, if there is a unit_id, we check.
        if (!$unitId) {
            return $next($request);
        }

        // Check if user is assigned to this unit
        // We assume $user->units is available via "belongsToMany" relationship
        if (!$user->units()->where('units.id', $unitId)->exists()) {
            return response()->json(['message' => 'Unauthorized. You are not assigned to this unit.'], 403);
        }

        return $next($request);
    }
}
