<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SiteConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $mode = config('site.mode', 'shopping');
        $data = ['site_mode' => $mode];

        if ($mode === 'enquiry') {
            $data['google_sheets_enquiry_url'] = config('site.google_sheets_enquiry_url', '');
        }

        $data['analytics'] = SettingController::publicAnalytics();

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Site config fetched successfully.',
            'data'    => $data,
        ]);
    }
}
