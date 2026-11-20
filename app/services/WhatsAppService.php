<?php

namespace App\services;

use Illuminate\Support\Facades\Cache;
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

    /**
     * @param  array<int, array{name?:string,variant?:?string,qty?:int}>  $items
     */
    public static function sendOrderConfirmed(string $mobile, string $name, string $orderId, array $items, float $amount): bool
    {
        return self::sendUtility(
            (string) config('services.whatsapp.order_confirmed_template', 'haneri_order_confirmed'),
            $mobile,
            [self::name($name), $orderId, self::summarizeItems($items), self::money($amount)],
            $orderId
        );
    }

    public static function sendOrderDispatched(string $mobile, string $name, string $orderId, string $courier, string $tracking): bool
    {
        return self::sendUtility(
            (string) config('services.whatsapp.order_dispatched_template', 'haneri_order_dispatched'),
            $mobile,
            [self::name($name), $orderId, self::param($courier, 'Courier'), self::param($tracking, 'Tracking pending')],
            $orderId
        );
    }

    /**
     * @param  array<int, array{name?:string,variant?:?string,qty?:int}>  $items
     */
    public static function sendCheckoutIncomplete(string $mobile, string $name, array $items, string $restoreToken): bool
    {
        $restoreToken = self::param($restoreToken, '');
        if ($restoreToken === '') {
            return false;
        }

        return self::sendUtility(
            (string) config('services.whatsapp.checkout_incomplete_template', 'haneri_checkout_incomplete'),
            $mobile,
            [self::name($name), self::summarizeItems($items)],
            $restoreToken
        );
    }

    /**
     * One-day cart reminder. The coupon is part of this template, so it is only
     * sent with the second email.
     *
     * @param  array<int, array{name?:string,variant?:?string,qty?:int}>  $items
     */
    public static function sendCartCoupon(
        string $mobile,
        string $name,
        array $items,
        string $code,
        string $discount,
        string $expires,
        string $restoreToken
    ): bool {
        $restoreToken = self::param($restoreToken, '');
        $code = self::param($code, '');
        if ($restoreToken === '' || $code === '') {
            return false;
        }

        return self::sendUtility(
            (string) config('services.whatsapp.cart_coupon_template', 'haneri_cart_offer'),
            $mobile,
            [
                self::name($name),
                self::summarizeItems($items),
                $code,
                self::param($discount, 'Discount'),
                self::param($expires, 'Soon'),
            ],
            $restoreToken
        );
    }

    /**
     * Paid-order alert for a mobile saved in the admin panel.
     *
     * @param  array<int, array{name?:string,variant?:?string,qty?:int}>  $items
     */
    public static function sendAdminOrderPaid(
        string $mobile,
        string $adminName,
        string $orderId,
        string $customer,
        array $items,
        float $amount
    ): bool {
        return self::sendUtility(
            (string) config('services.whatsapp.admin_order_template', 'haneri_admin_order_paid'),
            $mobile,
            [
                self::name($adminName),
                $orderId,
                self::param($customer, 'Customer'),
                self::summarizeItems($items),
                self::money($amount),
            ],
            $orderId
        );
    }

    /**
     * @param  array<int, array{name?:string,variant?:?string,qty?:int}>  $items
     */
    public static function summarizeItems(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['name'] ?? 'Item'));
            $variant = trim((string) ($item['variant'] ?? ''));
            if ($variant !== '') {
                $label .= ' ('.$variant.')';
            }
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $parts[] = $label.' x '.$qty;
        }

        return self::param(implode(', ', $parts), 'Your items');
    }

    public static function money(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }

    /**
     * @param  array<int, string>  $body
     */
    private static function sendUtility(string $template, string $mobile, array $body, string $button): bool
    {
        if (! self::templateApproved($template)) {
            Log::info('WhatsApp template skipped until Meta approves it', ['template' => $template]);

            return false;
        }

        return self::deliver($mobile, $template, (string) config('services.whatsapp.otp_language', 'en'), $body, $button);
    }

    private static function templateApproved(string $template): bool
    {
        $token = (string) config('services.whatsapp.token');
        $accountId = (string) config('services.whatsapp.business_account_id');
        if ($token === '' || $accountId === '' || $template === '') {
            return false;
        }

        $version = trim((string) config('services.whatsapp.api_version', 'v22.0'), '/');

        return (bool) Cache::remember('whatsapp.template.'.$template, 600, function () use ($token, $accountId, $version, $template) {
            try {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("https://graph.facebook.com/{$version}/{$accountId}/message_templates", [
                        'name' => $template,
                        'fields' => 'name,status',
                    ]);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp template status check failed: '.$e->getMessage(), ['template' => $template]);

                return false;
            }

            if (! $response->successful()) {
                return false;
            }

            foreach ((array) $response->json('data') as $row) {
                if (is_array($row) && ($row['name'] ?? '') === $template && ($row['status'] ?? '') === 'APPROVED') {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @param  array<int, string>  $body
     */
    private static function deliver(string $mobile, string $template, string $language, array $body, ?string $button): bool
    {
        $token = (string) config('services.whatsapp.token');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = trim((string) config('services.whatsapp.api_version', 'v22.0'), '/');
        if ($token === '' || $phoneNumberId === '') {
            return false;
        }

        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        $to = strlen($digits) === 10 ? '91'.$digits : $digits;
        if ($to === '') {
            return false;
        }

        $parameters = [];
        foreach ($body as $value) {
            $parameters[] = ['type' => 'text', 'text' => self::param((string) $value, '-')];
        }

        $components = [
            ['type' => 'body', 'parameters' => $parameters],
        ];
        if ($button !== null && $button !== '') {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => self::param($button, '-')],
                ],
            ];
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
                        'language' => ['code' => $language !== '' ? $language : 'en'],
                        'components' => $components,
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('WhatsApp template send failed', [
                'template' => $template,
                'status' => $response->status(),
                'body' => $response->body(),
                'mobile' => $to,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsApp template exception: '.$e->getMessage(), [
                'template' => $template,
                'mobile' => $to,
            ]);

            return false;
        }
    }

    private static function name(string $name): string
    {
        return self::param($name, 'Customer');
    }

    private static function param(string $value, string $fallback): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $value)));
        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, 200);
    }
}
