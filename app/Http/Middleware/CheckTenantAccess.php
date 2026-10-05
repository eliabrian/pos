<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tenantAbility = collect($token->abilities)->first(fn($ability) => str_starts_with($ability, 'tenant:'));

        if (!$tenantAbility) {
            return response()->json(['message' => 'Tenant context missing.'], 403);
        }

        $tenantId = explode(':', $tenantAbility)[1];
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if (!$tenant->hasValidAccess()) {
            return response()->json([
                'message' => 'Access suspended. Please update your subscription.',
                'error_code' => 'SUBSCRIPTION_EXPIRED'
            ], 402);
        }

        $request->merge(['active_tenant' => $tenant]);

        return $next($request);
    }
}
