<?php

namespace App\Http\Controllers;

use App\Utils\MobileHelper;
use App\Utils\SendSmsAlertUtility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Test endpoint for SMS Alert integration.
 * Use for debugging and verifying OTP send + API response.
 */
class SmsAlertTestController extends Controller
{
    /**
     * Send a test OTP via SMS Alert and return the full API response.
     *
     * POST /api/test/sms-alert
     * Body: { "mobile": "9876543210", "otp": "123456" }  (otp optional, defaults to random 6 digits)
     */
    public function sendTestOtp(Request $request): JsonResponse
    {
        $request->merge(['mobile' => MobileHelper::normalize($request->input('mobile'))]);
        $validated = $request->validate([
            'mobile' => 'required|string|size:10|regex:/^[0-9]{10}$/',
            'otp'    => 'nullable|string|min:4|max:8',
        ]);

        $mobile = $validated['mobile'];
        $otp    = $validated['otp'] ?? (string) random_int(100000, 999999);

        $result = SendSmsAlertUtility::sendOtpWithResponse($mobile, $otp);

        return response()->json([
            'message' => $result['message'],
            'success' => $result['success'],
            'sms_alert' => [
                'status' => $result['status'],
                'body'   => $result['body'],
                'error'  => $result['error'],
            ],
            'sent_to' => '91' . $mobile,
            'otp_used' => $otp,
        ], $result['success'] ? 200 : 422);
    }
}
