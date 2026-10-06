<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\VenueTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QrOrderController extends Controller
{
    public function scan(Request $request, Tenant $tenant, string $token)
    {
        $table = VenueTable::with(['tenant'])
            ->where('qr_token', $token)
            ->firstOrFail();

        $isSubscriptionActive = $tenant->ends_at && $tenant->ends_at->isFuture() && $tenant->hasFeature('has_qr_order');

        if ($isSubscriptionActive) {
            abort(403, 'Sorry, this store is currently offline and not accepting mobile orders.');
        }

        session([
            'active_qr_token' => $table->qr_token,
            'tenant_id' => $tenant->id,
            'table_id' => $table->id,
            'table_name' => $table->name,
        ]);

        return redirect()->route('mobile.menu', ['shop' => $tenant->slug]);
    }

    public function getStoreProfile()
    {
        $tenantId = session('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No active session'], 401);
        }

        $tenant = Tenant::findOrFail($tenantId);

        return response()->json([
            'name' => $tenant->name,
            'address' => $tenant->address,
            'open_hours' => $tenant->open_hours,
            'logo_url' => $tenant->logo ? Storage::url($tenant->logo) : null,
            'cover_url' => $tenant->cover ? Storage::url($tenant->cover) : null,
            'table_name' => session('table_name'),
        ]);
    }

    public function getProducts()
    {
        $tenantId = session('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No active session'], 401);
        }

        $products = Product::with([
            'tenant',
            'category',
            'variants.variantItems',
        ])
        ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->where('is_qr_order', true)
            ->orderBy('sort')
            ->get();

        return ProductResource::collection($products);
    }
}
