<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\ProductModel;
use App\services\RecaptchaService;

class SiteConfigController extends Controller
{
    public function index(RecaptchaService $recaptcha): JsonResponse
    {
        $mode = config('site.mode', 'shopping');
        $enquiryUrl = config('site.google_sheets_enquiry_url', '');

        $hasEcommerceProducts = ProductModel::query()
            ->where('is_active', '!=', 0)
            ->where('is_ecommerce', true)
            ->exists();

        $data = [
            'site_mode' => $mode,
            'has_ecommerce_products' => $hasEcommerceProducts,
            'analytics' => SettingController::publicAnalytics(),
        ];

        if ($enquiryUrl !== '') {
            $data['google_sheets_enquiry_url'] = $enquiryUrl;
        }

        // Public site key only — never expose the secret. Required for the Contact Us widget.
        if ($recaptcha->isConfigured()) {
            $data['recaptcha_site_key'] = $recaptcha->siteKey();
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Site config fetched successfully.',
            'data'    => $data,
        ]);
    }
}
