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
        $this->baseUrl = 'https://api-sandbox.doku.com';
    }

    protected function generateAsymmetricSignature(string $clientId, string $timestamp, string $privateKey): string
    {
        $stringToSign = $clientId . '|' . $timestamp;

        $signature = '';

        $isSigned = openssl_sign(
            $stringToSign,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        if (!$isSigned) {
            throw new \Exception('Gagal membuat signature RSA. Periksa format Private Key Anda.');
        }

        return base64_encode($signature);
    }

    protected function getTokenApi()
    {
        $targetPath = '/authorization/v1/access-token/b2b';

        $timestamp = now()->format('c');

        $clientId = $this->tenant->payment_client_id;
        $privateKey = $this->tenant->rsa_private_key;

        $signature = $this->generateAsymmetricSignature($clientId, $timestamp, $privateKey);

        $payload = [
            'grantType' => 'client_credentials'
        ];

        $response = Http::withHeaders([
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-CLIENT-KEY' => $clientId,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . $targetPath, $payload);

        if ($response['responseCode'] == 2007300) {
            return [
                'access_token' => $response['accessToken'],
                'expires_in' => $response['expiresIn'],
            ];
        };

        return ['error' => $response['responseMessage']];
    }

    protected function generateSymmetricSignature(
        string $method,
        string $endpoint,
        string $accessToken,
        array $payload,
        string $timestamp,
        string $secretKey
    ): string {

        $minifiedBody = json_encode($payload);

        $hashedBody = hash('sha256', $minifiedBody);

        $stringToSign = implode(':', [
            strtoupper($method),
            $endpoint,
            $accessToken,
            $hashedBody,
            $timestamp
        ]);

        $signature = hash_hmac('sha512', $stringToSign, $secretKey, true);

        return base64_encode($signature);
    }

    public function generateQris($order)
    {
        $tokenData = $this->getTokenApi();

        if (isset($tokenData['error'])) {
            throw new \Exception('DOKU Token Error: ' . $tokenData['error']);
        }

        $accessToken = $tokenData['access_token'];

        $targetPath = '/snap-adapter/b2b/v1.0/qr/qr-mpm-generate';
        $timestamp = now()->format('c');
        $externalId = Str::uuid()->toString();

        $payload = [
            "partnerReferenceNo" => $order->id,
            "ammount" => [
                "value" => number_format($order->total_price, 2, '.', ''),
                "currency" => "IDR",
            ],
            "merchantId" => $this->tenant->qris_client_id,
            "terminalId" => "A01",
            "validityPeriod" => now()->addMinutes(30)->format('c'),
        ];

        $signature = $this->generateSymmetricSignature(
            'POST',
            $targetPath,
            $accessToken,
            $payload,
            $timestamp,
            $this->tenant->payment_secret_key,
        );

        $response = Http::withHeaders([
            'X-PARTNER-ID' => $this->tenant->payment_client_id,
            'X-EXTERNAL-ID' => $externalId,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'Authorization' => 'Bearer ' . $accessToken,
            'CHANNEL-ID' => 'H2H',
            'Content-Type' => 'application/json',
            'Accept' => '*/*'
        ])->post($this->baseUrl . $targetPath, $payload);

        dd($response);

        return $response->json();
    }

    public function generateQrisDirect($order)
    {
        // Direct API endpoint for QRIS
        $targetPath = '/orders/v1/qr/generate';

        $requestId = Str::uuid()->toString();

        // Direct API specifically expects UTC time in this exact format
        $timestamp = now()->timezone('UTC')->format('Y-m-d\TH:i:s\Z');

        // Much simpler payload. Note: amount must be an integer, not a decimal string
        $payload = [
            'order' => [
                'invoice_number' => $order->id . '-' . time(),
                'amount' => (int) $order->total_price
            ]
        ];

        $clientId = $this->tenant->payment_client_id;
        $secretKey = $this->tenant->payment_secret_key;

        $signature = $this->generateDirectSignature(
            $clientId,
            $requestId,
            $timestamp,
            $targetPath,
            $payload,
            $secretKey
        );

        $response = Http::withHeaders([
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . $targetPath, $payload);

        return $response->json();
    }

    /**
     * Generates DOKU Direct (Jokul) HMAC-SHA256 Signature
     */
    protected function generateDirectSignature($clientId, $requestId, $timestamp, $targetPath, $payload, $secretKey): string
    {
        // 1. Minify payload and create SHA256 digest
        $body = json_encode($payload);
        $digest = base64_encode(hash('sha256', $body, true));

        // 2. Construct the exact StringToSign required by DOKU
        $stringToSign = "Client-Id:" . $clientId . "\n"
            . "Request-Id:" . $requestId . "\n"
            . "Request-Timestamp:" . $timestamp . "\n"
            . "Request-Target:" . $targetPath . "\n"
            . "Digest:" . $digest;

        // 3. Hash with HMAC-SHA256 using the Secret Key
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $secretKey, true));

        // 4. Return with the required prefix
        return "HMACSHA256=" . $signature;
    }
}
