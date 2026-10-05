<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $request->active_tenant;
        if (!$tenant || !$tenant->hasFeature($feature)) {
            return response()->json([
                'message' => "Upgrade required. Your plan does not include access to: {$feature}",
                'error_code' => 'FEATURE_LOCKED'
            ], 403);
        }
        return $next($request);
    }
}
