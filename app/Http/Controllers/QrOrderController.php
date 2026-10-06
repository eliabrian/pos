<?php

namespace App\Http\Controllers;

use App\Events\OrderSentToKds;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\VariantItem;
use App\Models\VenueTable;
use App\Services\DokuService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

    public function checkStatus(string $receiptNumber)
    {
        $tenantId = session('tenant_id');

        // Find the order for this specific tenant and receipt
        $order = Order::where('tenant_id', $tenantId)
                    ->where('receipt_number', $receiptNumber)
                    ->firstOrFail();

        if ($order->status === 'completed') {
            return response()->json(['status' => 'completed', 'order' => $order]);
        }

        $dokuService = new DokuService($order->tenant);

        try {
            $dokuResponse = $dokuService->checkStatus($order);
            $transactionStatus = $dokuResponse['transaction']['status'] ?? 'PENDING';

            if ($transactionStatus === 'SUCCESS') {
                $order->update(['status' => 'completed']);
                OrderSentToKds::dispatch($order, $order->tenant->slug);
            } elseif (in_array($transactionStatus, ['FAILED', 'EXPIRED'])) {
                $order->update(['status' => 'failed']);
            }

            return response()->json([
                'status' => $order->status,
                'doku_status' => $transactionStatus,
                'order' => $order
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengecek status ke DOKU.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function submitOrder(Request $request)
    {
        $tenantId = session('tenant_id');
        $tableId = session('table_id');

        if (!$tenantId || !$tableId) {
            return response()->json(['message' => 'Sesi tidak valid. Silahkan scan QR ulang di meja Anda.'], 403);
        }

        $sessionId = session()->getId();
        $payloadHash = md5(json_encode($request->all()));
        $lockKey = "qr_order_lock_{$sessionId}_{$payloadHash}";

        $lock = Cache::lock($lockKey, 5);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'Terdapat indikasi klik ganda. Transaksi ini sedang diproses.'
            ], 429);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variant_items' => 'nullable|array',
            'products.*.variant_items.*' => 'integer|exists:variant_items,id',
            'products.*.notes' => 'nullable|string',
        ]);

        $order = DB::transaction(function () use ($tableId, $tenantId, $validated) {
            $productIds = collect($validated['products'])->pluck('id');
            $dbProducts = Product::where('tenant_id', $tenantId)
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $allVariantItemIds = collect($validated['products'])
                ->pluck('variant_items')
                ->flatten()
                ->filter()
                ->unique();

            $dbVariantItems = VariantItem::with('variant')
                ->whereIn('id', $allVariantItemIds)
                ->get()
                ->keyBy('id');

            $order = Order::create([
                'tenant_id' => $tenantId,
                'venue_table_id' => $tableId,
                'total_price' => 0,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'],
                'status' => 'pending',
                'order_source' => 'qr_menu',
            ]);

            $totalPrice = 0;

            foreach ($validated['products'] as $item) {
                $product = $dbProducts->get($item['id']);

                if (!$product) {
                    throw ValidationException::withMessages(['products' => "Product ID {$item['id']} not found."]);
                }

                $baseUnitPrice = $product->final_price > 0 ? $product->final_price : $product->price;
                $variantAddonSum = 0;
                $savedVariantsSnapshot = [];

                if (!empty($item['variant_items'])) {
                    foreach ($item['variant_items'] as $vItemId) {
                        $vItem = $dbVariantItems->get($vItemId);

                        if ($vItem && $vItem->variant->product_id === $product->id) {
                            $variantAddonSum += $vItem->price;
                            $savedVariantsSnapshot[] = [
                                'variant_name' => $vItem->variant->name,
                                'item_name' => $vItem->name,
                                'price' => $vItem->price
                            ];
                        }
                    }
                }

                $finalUnitPrice = $baseUnitPrice + $variantAddonSum;
                $subTotal = $finalUnitPrice * $item['quantity'];
                $totalPrice += $subTotal;

                $order->products()->attach($product->id, [
                    'unit_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $baseUnitPrice,
                    'sub_total' => $subTotal,
                    'variant_selected' => json_encode($savedVariantsSnapshot),
                    'notes' => $item['notes'] ?? null,
                ]);

                if ($product->stock > 0) {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            $order->total_price = $totalPrice;
            $order->save();

            if ($validated['payment_method'] === 'dynamic_qris') {
                $tenant = Tenant::findOrFail($tenantId);
                $dokuService = new DokuService($tenant);

                $dokuResponse = $dokuService->dokuCheckout($order);

                if (isset($dokuResponse['message']) && in_array('SUCCESS', (array)$dokuResponse['message'])) {
                    $paymentUrl = $dokuResponse['response']['payment']['url'] ?? null;

                    $order->update([
                        'status' => 'pending',
                        'payment_url' => $paymentUrl,
                    ]);
                } else {
                    throw new Exception('Gagal generate DOKU Checkout: ' . json_encode($dokuResponse));
                }
            } else {
                OrderSentToKds::dispatch($order, $order->tenant->slug);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order
        ], 201);
    }
}
