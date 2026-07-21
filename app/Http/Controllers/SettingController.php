<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\SettingModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public const ANALYTICS_KEY = 'analytics';
    public const ANALYTICS_CACHE_KEY = 'site.analytics';

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

    private static function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
