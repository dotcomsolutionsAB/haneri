<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShiprocketService
{
    private string $baseUrl;
    private string $email;
    private string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('services.shiprocket.base_url'), '/');
        $this->email    = (string) config('services.shiprocket.email');
        $this->password = (string) config('services.shiprocket.password');
    }

    public function token(): string
    {
        // cache token for 9 days (Shiprocket tokens are long-lived; safe to refresh early)
        return Cache::remember('shiprocket_token', now()->addDays(9), function () {
            $res = Http::asJson()->post($this->baseUrl . '/v1/external/auth/login', [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            $res->throw();
            $token = $res->json('token');

            if (!$token) {
                throw new \RuntimeException('Shiprocket token missing in response');
            }

            return $token;
        });
    }

    private function client()
    {
        return Http::withToken($this->token())
            ->acceptJson()
            ->asJson();
    }

    public function createOrderAdhoc(array $payload): array
    {
        return $this->client()
            ->post($this->baseUrl . '/v1/external/orders/create/adhoc', $payload)
            ->throw()
            ->json();
    }

    public function cancelOrders(array $ids): array
    {
        return $this->client()
            ->post($this->baseUrl . '/v1/external/orders/cancel', [
                'ids' => array_values(array_map('intval', $ids)),
            ])
            ->throw()
            ->json();
    }

    // ✅ Shiprocket courier serviceability (rates)
    public function getCourierRates(array $params): array
    {
        // Important: this is GET with query params
        return $this->client()
            ->get($this->baseUrl . '/v1/external/courier/serviceability', $params) // no trailing slash also ok
            ->throw()
            ->json();
    }

    public function assignAwb(int $shipmentId, ?int $courierId = null): array
    {
        $body = ['shipment_id' => $shipmentId];
        if ($courierId) $body['courier_id'] = $courierId;

        return $this->client()
            ->post($this->baseUrl . '/v1/external/courier/assign/awb', $body)
            ->throw()
            ->json();
    }

    public function generateLabel(array $shipmentIds): array
    {
        return $this->client()
            ->post($this->baseUrl . '/v1/external/courier/generate/label', [
                'shipment_id' => $shipmentIds, // must be array
            ])
            ->throw()
            ->json();
    }

    public function generatePickup(int $shipmentId): array
    {
        return $this->client()
            ->post($this->baseUrl . '/v1/external/courier/generate/pickup', [
                'shipment_id' => $shipmentId,
            ])
            ->throw()
            ->json();
    }

    public function trackByAwb(string $awb): array
    {
        return $this->client()
            ->get($this->baseUrl . '/v1/external/courier/track/awb/' . urlencode($awb))
            ->throw()
            ->json();
    }
}
