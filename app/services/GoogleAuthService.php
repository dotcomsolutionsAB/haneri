<?php

namespace App\services;

use Google_Client;
use Illuminate\Support\Facades\Log;

class GoogleAuthService
{
    protected Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client([
            // This comes from config/services.php → env('GOOGLE_CLIENT_ID')
            'client_id' => config('services.google.client_id'),
        ]);
    }

    /**
     * Verify ID token from frontend and return payload array or null.
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $payload = $this->client->verifyIdToken($idToken);

            if ($payload) {
                return $payload; // e.g. ['sub' => '...', 'email' => '...', 'name' => '...']
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Google ID token verification failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
