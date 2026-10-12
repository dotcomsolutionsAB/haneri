<?php

namespace App\services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.recaptcha.secret_key', '')) !== '';
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        $secret = trim((string) config('services.recaptcha.secret_key', ''));
        if ($secret === '') {
            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        try {
            $payload = [
                'secret' => $secret,
                'response' => $token,
            ];
            if ($remoteIp) {
                $payload['remoteip'] = $remoteIp;
            }

            $response = Http::asForm()
                ->timeout(8)
                ->post('https://www.google.com/recaptcha/api/siteverify', $payload);

            if (!$response->ok()) {
                Log::warning('reCAPTCHA siteverify HTTP error', [
                    'status' => $response->status(),
                ]);
                return false;
            }

            $body = $response->json();
            return (bool) ($body['success'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification failed: ' . $e->getMessage());
            return false;
        }
    }
}
