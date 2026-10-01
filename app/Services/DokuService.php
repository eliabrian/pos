<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DokuService
{
    protected Tenant $tenant;
    protected string $baseUrl;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->baseUrl = config('payment.doku.sandbox');

        if (app()->environment('production')) {
            $this->baseUrl =config('payment.doku.production');
        }
    }

    public function checkStatus(Order $order)
    {
        $clientId = $this->tenant->payment_client_id;
        $requestTarget = config('payment.doku.paths.check_status') . $order->receipt_number;

        $requestId = Str::uuid()->toString();
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');

        $signature = $this->generateSignature('GET', [], $requestTarget, $requestId, $timestamp);

        $response = Http::withHeaders([
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $signature,
        ])->get($this->baseUrl . $requestTarget);

        return $response->json();
    }

    public function dokuCheckout(Order $order)
    {
        $items = [];
        $products = $order->products;

        foreach ($products as $index => $product) {
            $items[$index] = [
                'id' => $product->id,
                'name' => $product->name,
                'quantity' => $product->pivot->quantity,
                'price' => (int) $product->pivot->sub_total,
            ];
        }

        $payload = [
            'order' => [
                'amount' => (int) $order->total_price,
                'invoice_number' => $order->receipt_number,
                'currency' => 'IDR',
                'language' => 'ID',
                'line_items' => $items,
            ],
            'payment' => [
                'payment_due_date' => 60,
                'type' => 'SALE',
                'payment_method_types' => [
                    'QRIS',
                    'VIRTUAL_ACCOUNT_BCA'
                ]
            ],
        ];

        $clientId = $this->tenant->payment_client_id;
        $requestTarget = config('payment.doku.paths.doku_checkout');

        $requestId = Str::uuid()->toString();
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');

        $signature = $this->generateSignature('POST', $payload, $requestTarget, $requestId, $timestamp);

        $response = Http::withHeaders([
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $signature,
        ])->post($this->baseUrl . $requestTarget, $payload);

        return $response->json();
    }

    public function generateSignature(string $method, array $payload, string $requestTarget, string $requestId, string $timestamp)
    {
        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $digest = base64_encode(hash('sha256', $jsonBody, true));

        $clientId = $this->tenant->payment_client_id;
        $secretKey = $this->tenant->payment_secret_key;

        if ($method === 'GET') {
            $stringToSign = "Client-Id:{$clientId}\n"
                . "Request-Id:{$requestId}\n"
                . "Request-Timestamp:{$timestamp}\n"
                . "Request-Target:{$requestTarget}";
        } else {
            $stringToSign = "Client-Id:{$clientId}\n"
                . "Request-Id:{$requestId}\n"
                . "Request-Timestamp:{$timestamp}\n"
                . "Request-Target:{$requestTarget}\n"
                . "Digest:{$digest}";
        }

        $rawHmac = hash_hmac('sha256', $stringToSign, $secretKey, true);
        $encodedHmac = base64_encode($rawHmac);

        return 'HMACSHA256=' . $encodedHmac;
    }
}
