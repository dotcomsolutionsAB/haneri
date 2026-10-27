<?php

namespace App\Services;

use App\Http\Controllers\SettingController;
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

    /**
     * Admin settings win. Env values are used only when a field was never saved.
     *
     * @return array{email:string,password:string,pickup_location:string,cargo_access_token:string,cargo_refresh_token:string,cargo_client_id:string,cargo_base_url:string}
     */
    public function credentials(): array
    {
        $saved = SettingController::shiprocketCredentials();

        return [
            'email' => $saved['email'] !== '' ? $saved['email'] : $this->email,
            'password' => $saved['password'] !== '' ? $saved['password'] : $this->password,
            'pickup_location' => $saved['pickup_location'] !== ''
                ? $saved['pickup_location']
                : (string) config('services.shiprocket.pickup_location'),
            'cargo_access_token' => $saved['cargo_access_token'],
            'cargo_refresh_token' => $saved['cargo_refresh_token'],
            'cargo_client_id' => $saved['cargo_client_id'],
            'cargo_base_url' => rtrim((string) config('services.shiprocket.cargo_base_url', 'https://api.rocketbox.in/api'), '/'),
        ];
    }

    public function pickupLocation(): string
    {
        return $this->credentials()['pickup_location'];
    }

    public static function forgetCachedToken(string $email, string $password): void
    {
        Cache::forget('shiprocket_token');
        Cache::forget(self::tokenCacheKey($email, $password));
    }

    public static function forgetCachedCargoToken(string $accessToken, string $refreshToken): void
    {
        Cache::forget(self::cargoTokenCacheKey($accessToken, $refreshToken));
    }

    public function token(): string
    {
        $creds = $this->credentials();
        if ($creds['email'] === '' || $creds['password'] === '') {
            throw new \RuntimeException('Shiprocket API email and password are not set. Add them in Admin settings.');
        }

        return Cache::remember(self::tokenCacheKey($creds['email'], $creds['password']), now()->addDays(9), function () use ($creds) {
            $res = Http::asJson()->post($this->baseUrl . '/v1/external/auth/login', [
                'email' => $creds['email'],
                'password' => $creds['password'],
            ]);

            if (!$res->successful()) {
                throw new \RuntimeException(self::errorText($res->json(), $res->body()));
            }

            $token = $res->json('token');
            if (!$token) {
                throw new \RuntimeException('Shiprocket token missing in response');
            }

            return $token;
        });
    }

    private static function tokenCacheKey(string $email, string $password): string
    {
        return 'shiprocket_token_' . sha1($email . '|' . $password);
    }

    public static function cargoTokenCacheKey(string $accessToken, string $refreshToken): string
    {
        return 'shiprocket_cargo_token_' . sha1($accessToken . '|' . $refreshToken);
    }

    public static function errorText(mixed $json, string $body): string
    {
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? $json['detail'] ?? null) : null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }
        if (is_array($message)) {
            return json_encode($message);
        }
        $trimmed = trim($body);

        return $trimmed !== '' ? $trimmed : 'Shiprocket request failed.';
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
  
    public function getCourierRates(array $params): array // ✅ Shiprocket courier serviceability (rates)
    {
        // Important: this is GET with query params
        return $this->client()
            ->get($this->baseUrl . '/v1/external/courier/serviceability', $params) // no trailing slash also ok
            ->throw()
            ->json();
    }
    public function getTatRates(array $params): array  // Get Expected Delivery date
    {
        return $this->client()
            ->get($this->baseUrl . '/v1/external/courier/serviceability', $params) // no trailing slash also ok
            ->throw()
            ->json();
    }

    // Track by AWB
    public function trackByAwb(string $awb): array
    {
        $awb = trim($awb);
        return $this->client()
            ->get($this->baseUrl . '/v1/external/courier/track/awb/' . urlencode($awb))
            ->throw()
            ->json();
    }
    // Track by Shipment ID
    public function trackByShipment(int $shipmentId): array
    {
        return $this->client()
            ->get($this->baseUrl . '/v1/external/courier/track/shipment/' . (int)$shipmentId)
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

}
