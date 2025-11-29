<?php

namespace App\services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAuthService
{
    /**
     * Verify a Firebase ID token using Firebase Identity Toolkit REST API.
     *
     * Frontend sends: result.user.getIdToken(true)
     * We call: https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=API_KEY
     */
    public function verifyIdToken(string $idToken): ?array
    {
        $apiKey = config('services.firebase.api_key');

        if (! $apiKey) {
            Log::error('Firebase API key is not configured (services.firebase.api_key).');
            return null;
        }

        try {
            $response = Http::post(
                'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . $apiKey,
                ['idToken' => $idToken]
            );

            if (! $response->successful()) {
                Log::warning('Firebase accounts:lookup failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            // Firebase returns users[0] on success
            if (empty($data['users'][0])) {
                Log::warning('Firebase accounts:lookup returned no user for given idToken.');
                return null;
            }

            $u = $data['users'][0];

            // Normalize to what your AuthController expects:
            // sub  => unique user ID
            // email => email
            // name  => displayName (if present)
            return [
                'sub'   => $u['localId']      ?? null,   // Firebase user ID
                'email' => $u['email']        ?? null,
                'name'  => $u['displayName']  ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Firebase ID token verification error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
