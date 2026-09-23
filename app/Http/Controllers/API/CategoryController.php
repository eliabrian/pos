<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $tokenAbilities = collect($request->user()->currentAccessToken()->abilities);
        $tenantAbility = $tokenAbilities->first(fn($ability) => str_starts_with($ability, 'tenant:'));

        if (!$tenantAbility) {
            return response()->json(['message' => 'Tenant context missing from token.'], 403);
        }

        $tenantId = explode(':', $tenantAbility)[1];

        $categories = Category::with([
            'tenant',
            'products',
        ])
            ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->orderBy('sort')
            ->get();

        return CategoryResource::collection($categories);
    }
}
