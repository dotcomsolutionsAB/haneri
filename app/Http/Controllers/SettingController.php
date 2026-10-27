<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\SettingModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public const ANALYTICS_KEY = 'analytics';
    public const ANALYTICS_CACHE_KEY = 'site.analytics';
    public const SELLER_KEY = 'seller';
    public const ABANDONED_CART_KEY = 'abandoned_cart';
    public const SHIPROCKET_KEY = 'shiprocket';
    public const SALES_INVOICE_KEY = 'sales_invoice';

    /**
     * Fetch all settings (Admin only)
     */
    public function index()
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = SettingModel::all();

        return $settings->isNotEmpty()
            ? response()->json(['message' => 'Settings retrieved successfully!', 'data' => $settings->makeHidden(['id', 'created_at', 'updated_at']), 'count' => count($settings)], 200)
            : response()->json(['message' => 'No settings found.'], 400);
    }

    /**
     * Update a specific setting (Admin only)
     */
    public function update(Request $request, $key)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'value' => 'required|json',
        ]);

        $setting = SettingModel::where('key', $key)->first();

        if (!$setting) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        $setting->update([
            'value' => $request->input('value'),
        ]);

        if ($key === self::ANALYTICS_KEY) {
            Cache::forget(self::ANALYTICS_CACHE_KEY);
        }

        unset($setting['id'], $setting['created_at'], $setting['updated_at']);

        return response()->json(['message' => 'Setting updated successfully!', 'data' => $setting], 200);
    }

    public function showSeller(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Seller details fetched successfully.',
            'data' => self::sellerDetails(),
        ]);
    }

    public function updateSeller(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'gstin' => ['nullable', 'string', 'max:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
            'state' => 'nullable|string|max:80',
        ]);

        $payload = [
            'name' => self::nullableTrim($data['name']),
            'address' => self::nullableTrim($data['address']),
            'gstin' => strtoupper(self::nullableTrim($data['gstin'] ?? null) ?? ''),
            'state' => self::nullableTrim($data['state'] ?? null) ?? '',
        ];

        if ($payload['name'] === null || $payload['address'] === null) {
            throw ValidationException::withMessages([
                'name' => ['Seller name and address are required.'],
            ]);
        }

        SettingModel::updateOrCreate(
            ['key' => self::SELLER_KEY],
            ['value' => json_encode($payload)]
        );

        return response()->json([
            'success' => true,
            'message' => 'Seller details saved.',
            'data' => $payload,
        ]);
    }

    /**
     * Name and address printed on the Delhivery label (seller_name / seller_add).
     *
     * @return array{name: string, address: string, gstin: string, state: string}
     */
    public static function sellerDetails(): array
    {
        $defaults = [
            'name' => trim((string) config('shipping.seller_name', 'Haneri Electricals LLP')),
            'address' => trim((string) config('shipping.seller_address', '')),
            'gstin' => strtoupper(trim((string) config('shipping.seller_gstin', ''))),
            'state' => trim((string) config('shipping.seller_state', '')),
        ];

        $row = SettingModel::where('key', self::SELLER_KEY)->first();
        if (!$row || empty($row->value)) {
            return $defaults;
        }

        $decoded = json_decode($row->value, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $name = self::nullableTrim($decoded['name'] ?? null);
        $address = self::nullableTrim($decoded['address'] ?? null);
        $gstin = self::nullableTrim($decoded['gstin'] ?? null);
        $state = self::nullableTrim($decoded['state'] ?? null);

        return [
            'name' => $name ?? $defaults['name'],
            'address' => $address ?? $defaults['address'],
            'gstin' => strtoupper($gstin ?? $defaults['gstin']),
            'state' => $state ?? $defaults['state'],
        ];
    }

    public function showSalesInvoice(): JsonResponse
    {
        $config = self::salesInvoiceConfig();

        return response()->json([
            'success' => true,
            'message' => 'Sales invoice series fetched successfully.',
            'data' => [
                'prefix' => $config['prefix'],
                'next_number' => $config['next_number'],
                'pad' => $config['pad'],
                'preview' => self::formatSalesInvoiceNumber($config),
            ],
        ]);
    }

    public function updateSalesInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prefix' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-\/]*$/'],
            'next_number' => 'required|integer|min:1|max:9999999',
            'pad' => 'required|integer|min:1|max:8',
        ]);

        $config = [
            'prefix' => trim($data['prefix']),
            'next_number' => (int) $data['next_number'],
            'pad' => (int) $data['pad'],
        ];

        SettingModel::updateOrCreate(
            ['key' => self::SALES_INVOICE_KEY],
            ['value' => json_encode($config)]
        );

        return $this->showSalesInvoice();
    }

    /**
     * Take the next sales invoice number and advance the counter.
     */
    public static function allocateSalesInvoiceNumber(): string
    {
        return DB::transaction(function () {
            $row = SettingModel::where('key', self::SALES_INVOICE_KEY)->lockForUpdate()->first();
            $parsed = json_decode((string) ($row->value ?? ''), true);
            $config = self::salesInvoiceConfig(is_array($parsed) ? $parsed : []);
            $number = self::formatSalesInvoiceNumber($config);
            $config['next_number']++;

            SettingModel::updateOrCreate(
                ['key' => self::SALES_INVOICE_KEY],
                ['value' => json_encode($config)]
            );

            return $number;
        });
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @return array{prefix: string, next_number: int, pad: int}
     */
    public static function salesInvoiceConfig(?array $decoded = null): array
    {
        if ($decoded === null) {
            $row = SettingModel::where('key', self::SALES_INVOICE_KEY)->first();
            $stored = $row ? json_decode($row->value ?? '', true) : null;
            $decoded = is_array($stored) ? $stored : [];
        }

        $prefix = trim((string) ($decoded['prefix'] ?? 'HAN-INV-'));
        if ($prefix === '') {
            $prefix = 'HAN-INV-';
        }
        $next = (int) ($decoded['next_number'] ?? 1);
        $pad = (int) ($decoded['pad'] ?? 4);

        return [
            'prefix' => $prefix,
            'next_number' => max(1, $next),
            'pad' => min(8, max(1, $pad)),
        ];
    }

    /**
     * @param  array{prefix: string, next_number: int, pad: int}  $config
     */
    public static function formatSalesInvoiceNumber(array $config): string
    {
        return $config['prefix'].str_pad((string) $config['next_number'], $config['pad'], '0', STR_PAD_LEFT);
    }

    public function showAbandonedCart(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Abandoned cart settings fetched successfully.',
            'data' => [
                'discount_percent' => self::abandonedCartDiscount(),
            ],
        ]);
    }

    public function updateAbandonedCart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'discount_percent' => 'required|integer|min:1|max:100',
        ]);

        SettingModel::updateOrCreate(
            ['key' => self::ABANDONED_CART_KEY],
            ['value' => json_encode(['discount_percent' => (int) $data['discount_percent']])]
        );

        return response()->json([
            'success' => true,
            'message' => 'Abandoned cart discount saved.',
            'data' => ['discount_percent' => (int) $data['discount_percent']],
        ]);
    }

    public static function abandonedCartDiscount(): int
    {
        $row = SettingModel::where('key', self::ABANDONED_CART_KEY)->first();
        if (!$row || empty($row->value)) {
            return 10;
        }

        $decoded = json_decode($row->value, true);
        $percent = (int) ($decoded['discount_percent'] ?? 10);

        return ($percent >= 1 && $percent <= 100) ? $percent : 10;
    }

    public function showAnalytics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Analytics settings fetched successfully.',
            'data' => self::analyticsPayload(),
        ]);
    }

    public function updateAnalytics(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ga4_enabled' => 'sometimes|boolean',
            'ga4_id' => 'nullable|string|max:32',
            'gtm_enabled' => 'sometimes|boolean',
            'gtm_id' => 'nullable|string|max:32',
            'meta_pixel_enabled' => 'sometimes|boolean',
            'meta_pixel_id' => 'nullable|string|max:32',
        ]);

        $current = self::analyticsPayload();
        $merged = array_merge($current, $data);

        $merged['ga4_id'] = self::nullableTrim($merged['ga4_id'] ?? null);
        $merged['gtm_id'] = self::nullableTrim($merged['gtm_id'] ?? null);
        $merged['meta_pixel_id'] = self::nullableTrim($merged['meta_pixel_id'] ?? null);
        $merged['ga4_enabled'] = (bool) ($merged['ga4_enabled'] ?? false);
        $merged['gtm_enabled'] = (bool) ($merged['gtm_enabled'] ?? false);
        $merged['meta_pixel_enabled'] = (bool) ($merged['meta_pixel_enabled'] ?? false);

        $errors = [];
        if ($merged['ga4_id'] !== null && !preg_match('/^G-[A-Z0-9]+$/i', $merged['ga4_id'])) {
            $errors['ga4_id'] = ['GA4 Measurement ID must look like G-XXXXXXXX.'];
        }
        if ($merged['gtm_id'] !== null && !preg_match('/^GTM-[A-Z0-9]+$/i', $merged['gtm_id'])) {
            $errors['gtm_id'] = ['GTM container ID must look like GTM-XXXXXXX.'];
        }
        if ($merged['meta_pixel_id'] !== null && !preg_match('/^\d{5,20}$/', $merged['meta_pixel_id'])) {
            $errors['meta_pixel_id'] = ['Meta Pixel ID must be a numeric ID.'];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Normalize casing for Google IDs.
        if ($merged['ga4_id'] !== null) {
            $merged['ga4_id'] = strtoupper($merged['ga4_id']);
        }
        if ($merged['gtm_id'] !== null) {
            $merged['gtm_id'] = strtoupper($merged['gtm_id']);
        }

        SettingModel::updateOrCreate(
            ['key' => self::ANALYTICS_KEY],
            ['value' => json_encode($merged)]
        );

        Cache::forget(self::ANALYTICS_CACHE_KEY);

        return response()->json([
            'success' => true,
            'message' => 'Analytics settings updated successfully.',
            'data' => $merged,
        ]);
    }

    /**
     * Public-safe analytics config (IDs only when enabled).
     */
    public static function publicAnalytics(): array
    {
        return Cache::remember(self::ANALYTICS_CACHE_KEY, 3600, function () {
            $payload = self::analyticsPayload();

            return [
                'ga4_enabled' => $payload['ga4_enabled'] && !empty($payload['ga4_id']),
                'ga4_id' => ($payload['ga4_enabled'] && !empty($payload['ga4_id'])) ? $payload['ga4_id'] : null,
                'gtm_enabled' => $payload['gtm_enabled'] && !empty($payload['gtm_id']),
                'gtm_id' => ($payload['gtm_enabled'] && !empty($payload['gtm_id'])) ? $payload['gtm_id'] : null,
                'meta_pixel_enabled' => $payload['meta_pixel_enabled'] && !empty($payload['meta_pixel_id']),
                'meta_pixel_id' => ($payload['meta_pixel_enabled'] && !empty($payload['meta_pixel_id']))
                    ? $payload['meta_pixel_id']
                    : null,
            ];
        });
    }

    public static function defaultAnalytics(): array
    {
        return [
            'ga4_enabled' => false,
            'ga4_id' => null,
            'gtm_enabled' => false,
            'gtm_id' => null,
            'meta_pixel_enabled' => false,
            'meta_pixel_id' => null,
        ];
    }

    private static function analyticsPayload(): array
    {
        $row = SettingModel::where('key', self::ANALYTICS_KEY)->first();
        $defaults = self::defaultAnalytics();

        if (!$row || empty($row->value)) {
            return $defaults;
        }

        $decoded = json_decode($row->value, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return [
            'ga4_enabled' => (bool) ($decoded['ga4_enabled'] ?? false),
            'ga4_id' => self::nullableTrim($decoded['ga4_id'] ?? null),
            'gtm_enabled' => (bool) ($decoded['gtm_enabled'] ?? false),
            'gtm_id' => self::nullableTrim($decoded['gtm_id'] ?? null),
            'meta_pixel_enabled' => (bool) ($decoded['meta_pixel_enabled'] ?? false),
            'meta_pixel_id' => self::nullableTrim($decoded['meta_pixel_id'] ?? null),
        ];
    }

    public function showShiprocket(): JsonResponse
    {
        $saved = self::shiprocketCredentials();

        return response()->json([
            'success' => true,
            'message' => 'Shiprocket settings fetched successfully.',
            'data' => [
                'email' => $saved['email'],
                'password_set' => $saved['password'] !== '',
                'pickup_location' => $saved['pickup_location'],
                'cargo_client_id' => $saved['cargo_client_id'],
                'cargo_access_token_set' => $saved['cargo_access_token'] !== '',
                'cargo_refresh_token_set' => $saved['cargo_refresh_token'] !== '',
                'configured' => $saved['email'] !== '' && $saved['password'] !== '' && $saved['pickup_location'] !== '',
                'cargo_configured' => $saved['cargo_access_token'] !== ''
                    && $saved['cargo_refresh_token'] !== ''
                    && $saved['cargo_client_id'] !== '',
            ],
        ]);
    }

    public function updateShiprocket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|max:255',
            'pickup_location' => 'required|string|max:100',
            'cargo_client_id' => 'nullable|string|max:100',
            'cargo_access_token' => 'nullable|string|max:500',
            'cargo_refresh_token' => 'nullable|string|max:500',
        ]);

        $current = self::shiprocketCredentials();
        $password = self::nullableTrim($data['password'] ?? null) ?? $current['password'];
        $cargoAccess = self::nullableTrim($data['cargo_access_token'] ?? null) ?? $current['cargo_access_token'];
        $cargoRefresh = self::nullableTrim($data['cargo_refresh_token'] ?? null) ?? $current['cargo_refresh_token'];

        if ($password === '') {
            throw ValidationException::withMessages([
                'password' => ['Shiprocket API password is required the first time you save.'],
            ]);
        }

        \App\services\ShiprocketService::forgetCachedToken($current['email'], $current['password']);
        \App\services\ShiprocketService::forgetCachedCargoToken($current['cargo_access_token'], $current['cargo_refresh_token']);

        $payload = [
            'email' => trim($data['email']),
            'password' => $password,
            'pickup_location' => trim($data['pickup_location']),
            'cargo_client_id' => self::nullableTrim($data['cargo_client_id'] ?? null) ?? '',
            'cargo_access_token' => $cargoAccess,
            'cargo_refresh_token' => $cargoRefresh,
        ];

        SettingModel::updateOrCreate(
            ['key' => self::SHIPROCKET_KEY],
            ['value' => json_encode($payload)]
        );

        return $this->showShiprocket();
    }

    /**
     * @return array{email:string,password:string,pickup_location:string,cargo_access_token:string,cargo_refresh_token:string,cargo_client_id:string}
     */
    public static function shiprocketCredentials(): array
    {
        $empty = [
            'email' => '',
            'password' => '',
            'pickup_location' => '',
            'cargo_access_token' => '',
            'cargo_refresh_token' => '',
            'cargo_client_id' => '',
        ];

        $row = SettingModel::where('key', self::SHIPROCKET_KEY)->first();
        if (!$row || empty($row->value)) {
            return $empty;
        }

        $decoded = json_decode($row->value, true);
        if (!is_array($decoded)) {
            return $empty;
        }

        foreach ($empty as $key => $value) {
            $empty[$key] = trim((string) ($decoded[$key] ?? ''));
        }

        return $empty;
    }

    private static function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
