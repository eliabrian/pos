<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\VariantItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Idempotency Lock (Payload Signature Lock)
        $userId = $request->user()->id;
        $payloadHash = md5(json_encode($request->all()));
        $lockKey = "pos_order_lock_{$userId}_{$payloadHash}";

        $lock = Cache::lock($lockKey, 5);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'Terdapat indikasi klik ganda. Transaksi ini sedang diproses atau sudah berhasil.'
            ], 429); // 429 Too Many Requests
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'status' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variant_items' => 'nullable|array',
            'products.*.variant_items.*' => 'integer|exists:variant_items,id',
            'products.*.notes' => 'nullable|string',
        ]);

        $tokenAbilities = collect($request->user()->currentAccessToken()->abilities);
        $tenantAbility = $tokenAbilities->first(fn($ability) => str_starts_with($ability, 'tenant:'));

        if (!$tenantAbility) {
            return response()->json(['message' => 'Tenant context missing.'], 403);
        }

        $tenantId = explode(':', $tenantAbility)[1];

        $order = DB::transaction(function () use ($tenantId, $validated) {
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
                'total_price' => 0,
                'payment_method' => $validated['payment_method'],
                'status' => $validated['status'] ?? 'completed',
            ]);

            $totalPrice = 0;

            foreach ($validated['products'] as $item) {
                $product = $dbProducts->get($item['id']);

                if (!$product) {
                    throw ValidationException::withMessages(['products' => "Product ID {$item['id']} not found or unauthorized."]);
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
                    'unit_price' => $finalUnitPrice,
                    'sub_total' => $subTotal,
                    'variant_selected' => json_encode($savedVariantsSnapshot),
                    'notes' => $item['notes'] ?? null,
                ]);

                if ($product->stock > 0) {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            $order->update(['total_price' => $totalPrice]);

            return $order;
        });

        $order->load('products');

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order
        ], 201);
    }
}
