<?php

namespace App\Http\Controllers\API;

use App\Events\OrderSentToKds;
use App\Events\PaymentSuccessful;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\VariantItem;
use App\Services\DokuService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $tokenAbilities = collect($request->user()->currentAccessToken()->abilities);
        $tenantAbility = $tokenAbilities->first(fn($ability) => str_starts_with($ability, 'tenant:'));

        if (!$tenantAbility) {
            return response()->json(['message' => 'Tenant context missing from token.'], 403);
        }

        $tenantId = explode(':', $tenantAbility)[1];

        $query = Order::with(['products']);

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        } else {
            $query->whereDate('created_at', now()->toDateString());
        }

        $orders = $query->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        return OrderResource::collection($orders);
    }

    public function checkStatus(Request $request, Order $order)
    {
        if ($order->status === 'completed') {
            return response()->json([
                'status' => 'completed',
                'order' => $order
            ]);
        }

        $tenant = $order->tenant;
        $dokuService = new \App\Services\DokuService($tenant);

        try {
            $dokuResponse = $dokuService->checkStatus($order);

            $transactionStatus = $dokuResponse['transaction']['status'] ?? 'PENDING';

            if ($transactionStatus === 'SUCCESS') {
                $order->update(['status' => 'completed']);
                OrderSentToKds::dispatch($order, $order->tenant->slug);
            } elseif (in_array($transactionStatus, ['FAILED', 'EXPIRED'])) {
                $order->update(['status' => 'pending']);
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
            'notes' =>'nullable|string',
            'order_discount' => 'nullable',
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
                'notes' => $validated['notes'],
                'status' => $validated['status'] ?? 'completed',
                'order_discount' => $validated['order_discount'],
                'order_source' => 'pos',
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
                    'unit_price' => $baseUnitPrice,
                    'sub_total' => $subTotal,
                    'variant_selected' => json_encode($savedVariantsSnapshot),
                    'notes' => $item['notes'] ?? null,
                ]);

                if ($product->stock > 0) {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            if ($order->order_discount) {
                $discountAmount = ($order->order_discount / 100) * $totalPrice;
                $totalPrice = $totalPrice - $discountAmount;
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

        $order->load('products');

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order
        ], 201);
    }

    public function bump(Request $request, Order $order)
    {
        $request->validate([
            'station_id' => 'required|exists:stations,id',
        ]);

        $stationId = $request->station_id;

        $productIds = $order->products()
            ->where('station_id', $stationId)
            ->pluck('products.id');

        if ($productIds->isNotEmpty()) {
            DB::table('order_product')
                ->where('order_id', $order->id)
                ->whereIn('product_id', $productIds)
                ->update(['status' => 'ready']);
        }

        return response()->json(['message' => 'Ticket bumped successfully']);
    }
}
