<?php

namespace App\services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function isConfigured(): bool
    {
        return $this->siteKey() !== '' && $this->secretKey() !== '';
    }

    public function siteKey(): string
    {
        return trim((string) config('services.recaptcha.site_key', ''));
    }

    public function secretKey(): string
    {
        return trim((string) config('services.recaptcha.secret_key', ''));
    }

    public function minScore(): float
    {
        $score = (float) config('services.recaptcha.min_score', 0.5);
        if ($score < 0) {
            return 0.0;
        }
        if ($score > 1) {
            return 1.0;
        }
        return $score;
    }

    public function verify(?string $token, ?string $remoteIp = null, ?string $expectedAction = null): bool
    {
        if (!$this->isConfigured()) {
            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        try {
            $payload = [
                'secret' => $this->secretKey(),
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

            $body = $response->json() ?: [];
            if (!($body['success'] ?? false)) {
                return false;
            }

            // v3 responses include score + action; v2 checkbox responses omit them.
            if (array_key_exists('score', $body)) {
                if ((float) $body['score'] < $this->minScore()) {
                    return false;
                }
            }

            if ($expectedAction && !empty($body['action']) && $body['action'] !== $expectedAction) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification failed: ' . $e->getMessage());
            return false;
        }
    }
}
