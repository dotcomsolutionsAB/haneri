<?php

namespace App\Http\Controllers;

use App\Models\PageSeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    private const PAGES = [
        'home' => 'Home',
        'shop' => 'Shop',
        'air-curve-design' => 'Air Curve Design',
        'turbosilent-bldc' => 'TurboSilent BLDC',
        'hass' => 'HASS',
        'lumiambience' => 'LumiAmbience',
        'scan' => 'SCAN',
        'our-story' => 'Our Story',
        'our-brands' => 'Our Brands',
        'capabilities' => 'Capabilities',
        'fancraft' => 'Fancraft',
        'faqs' => 'FAQs',
        'contact' => 'Contact',
        'shipping-policy' => 'Shipping Policy',
        'privacy-policy' => 'Privacy Policy',
        'wir-policy' => 'Warranty, Installation and Returns Policy',
        'live' => 'Live',
    ];

    public function show(string $pageKey): JsonResponse
    {
        if (!array_key_exists($pageKey, self::PAGES)) {
            return response()->json([
                'success' => false,
                'message' => 'SEO page not found.',
            ], 404);
        }

        $seo = PageSeo::where('page_key', $pageKey)->first();

        return response()->json([
            'success' => true,
            'data' => $seo ?: [
                'page_key' => $pageKey,
                'page_name' => self::PAGES[$pageKey],
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $stored = PageSeo::whereIn('page_key', array_keys(self::PAGES))
            ->get()
            ->keyBy('page_key');

        $pages = collect(self::PAGES)->map(function (string $name, string $key) use ($stored) {
            return $stored->get($key) ?: [
                'page_key' => $key,
                'page_name' => $name,
                'meta_title' => null,
                'meta_description' => null,
                'meta_keywords' => null,
                'canonical_url' => null,
                'og_title' => null,
                'og_description' => null,
                'og_image' => null,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $pages]);
    }

    public function update(Request $request, string $pageKey): JsonResponse
    {
        if (!array_key_exists($pageKey, self::PAGES)) {
            return response()->json([
                'success' => false,
                'message' => 'SEO page not found.',
            ], 404);
        }

        $data = $request->validate($this->rules());
        $data = array_map(
            fn ($value) => is_string($value) && trim($value) === '' ? null : $value,
            $data
        );

        $seo = PageSeo::updateOrCreate(
            ['page_key' => $pageKey],
            array_merge($data, ['page_name' => self::PAGES[$pageKey]])
        );

        return response()->json([
            'success' => true,
            'message' => 'SEO settings updated successfully.',
            'data' => $seo,
        ]);
    }

    public static function rules(): array
    {
        return [
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:1000',
            'meta_keywords' => 'nullable|string|max:2000',
            'canonical_url' => 'nullable|url|max:2048',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:1000',
            'og_image' => 'nullable|url|max:2048',
        ];
    }
}
