<?php

namespace App\Utils;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send SMS via SMS Alert (https://www.smsalert.co.in/api/doc).
 * Used for OTP and other transactional SMS.
 */
class SendSmsAlertUtility
{
    /**
     * Send SMS to a mobile number.
     *
     * @param  string  $mobile  10-digit Indian mobile (will be prefixed with 91)
     * @param  string  $message  Plain text message
     * @return bool  True if send accepted by API, false otherwise
     */
    public static function send(string $mobile, string $message): bool
    {
        $apiKey = config('services.smsalert.api_key');
        $sender = config('services.smsalert.sender');
        $url   = config('services.smsalert.url');

        if (empty($apiKey) || empty($url)) {
            Log::warning('SMS Alert not configured: missing SMSALERT_API_KEY or SMSALERT_URL');
            return false;
        }

        // SMS Alert expects mobileno with country code (91 for India)
        $mobileno = strlen($mobile) === 10 ? '91' . $mobile : $mobile;

        $endpoint = $url . (str_contains($url, '?') ? '&' : '?') . 'apikey=' . urlencode($apiKey);

        $payload = [
            'sender'   => $sender,
            'mobileno' => $mobileno,
            'text'     => $message,
        ];
        $templateId = config('services.smsalert.template_id');
        $entityId   = config('services.smsalert.entity_id');
        if (! empty($templateId)) {
            $payload['template_id'] = $templateId;
        }
        if (! empty($entityId)) {
            $payload['entity_id'] = $entityId;
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::warning('SMS Alert send failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'mobile' => $mobileno,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('SMS Alert exception: ' . $e->getMessage(), [
                'mobile' => $mobileno,
            ]);
            return false;
        }
    }

    /**
     * Send OTP using the approved DLT template.
     * Template text is from config (default matches approved "Your OTP is {#var#}. Do not share...").
     * The {#var#} placeholder is replaced with the actual OTP.
     *
     * @param  string  $validMinutes  Kept for API compatibility; not included in SMS body to match DLT template.
     */
    public static function sendOtp(string $mobile, string $otp, string $validMinutes = '10'): bool
    {
        $template = config('services.smsalert.otp_template', 'Your OTP is {#var#}. Do not share this code with anyone. Team Haneri');
        $message  = str_replace('{#var#}', $otp, $template);
        return self::send($mobile, $message);
    }
}
