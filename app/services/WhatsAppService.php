<?php

namespace App\services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send the same OTP on the approved WhatsApp authentication template.
     * A missing setup or a Meta error does not affect the SMS send.
     */
    public static function sendOtp(string $mobile, string $otp): bool
    {
        $token = (string) config('services.whatsapp.token');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = trim((string) config('services.whatsapp.api_version', 'v22.0'), '/');
        $template = (string) config('services.whatsapp.otp_template', 'haneri_otp');
        $language = (string) config('services.whatsapp.otp_language', 'en');

        if ($token === '' || $phoneNumberId === '') {
            Log::warning('WhatsApp OTP skipped: WHATSAPP_ACCESS_TOKEN or WHATSAPP_PHONE_NUMBER_ID is not set');

            return false;
        }

        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        $to = strlen($digits) === 10 ? '91'.$digits : $digits;
        if ($to === '') {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => $language],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $otp],
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $otp],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('WhatsApp OTP send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'mobile' => $to,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsApp OTP exception: '.$e->getMessage(), [
                'mobile' => $to,
            ]);

            return false;
        }
    }
}
