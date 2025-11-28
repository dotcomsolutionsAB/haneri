<?php

namespace App\Services;

use Google_Client;
use Illuminate\Support\Facades\Log;

class GoogleAuthService
{
    protected Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        // This tells Google client which audience (client_id) is valid
        $this->client->setClientId(config('services.google.client_id'));
    }

    /**
     * Verify ID token & return payload (sub, email, name, etc.)
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $payload = $this->client->verifyIdToken($idToken);

            if (! $payload) {
                Log::warning('GoogleAuthService: verifyIdToken returned null');
                return null;
            }

            // Extra safety: check audience explicitly
            $aud = $payload['aud'] ?? null;
            if ($aud !== config('services.google.client_id')) {
                Log::warning('GoogleAuthService: audience mismatch', [
                    'aud'   => $aud,
                    'expect'=> config('services.google.client_id'),
                ]);
                return null;
            }

            return $payload;
        } catch (\Throwable $e) {
            Log::error('GoogleAuthService: verifyIdToken failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
