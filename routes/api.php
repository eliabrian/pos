<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\QrOrderController;
use App\Http\Middleware\CheckTenantAccess;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', CheckTenantAccess::class, 'feature:has_pos'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order:receipt_number}/status', [OrderController::class, 'checkStatus']);
    Route::post('/orders/{order}/bump', [OrderController::class, 'bump']);
});

Route::middleware(['auth:sanctum', CheckTenantAccess::class, 'feature:has_kds'])
    ->group(function () {
        Route::get('/stations', function (Request $request) {
            $tokenAbilities = collect($request->user()->currentAccessToken()->abilities);
            $tenantAbility = $tokenAbilities->first(fn ($ability) => str_starts_with($ability, 'tenant:'));

            if (!$tenantAbility) {
                return response()->json(['message' => 'Tenant context missing from token.'], 403);
            }

            $tenantId = explode(':', $tenantAbility)[1];

            return Station::where('tenant_id', $tenantId)->get();
        });
    });
