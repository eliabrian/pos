<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $tokenAbilities = collect($request->user()->currentAccessToken()->abilities);
        $tenantAbility = $tokenAbilities->first(fn($ability) => str_starts_with($ability, 'tenant:'));

        if (!$tenantAbility) {
            return response()->json(['message' => 'Tenant context missing from token.'], 403);
        }

        $tenantId = explode(':', $tenantAbility)[1];

        $products = Product::with([
                'tenant',
                'category',
                'variants.variantItems',
            ])
            ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->orderBy('sort')
            ->get();

        return ProductResource::collection($products);
    }
}
