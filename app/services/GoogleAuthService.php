<?php

namespace App\services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class GoogleAuthService
{
    protected FirebaseAuth $auth;

    public function __construct(FirebaseAuth $auth)
    {
        // Firebase Auth instance from Kreait
        $this->auth = $auth;
    }

    /**
     * Verify Firebase ID token & return normalized payload
     * (sub, email, name)
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $verifiedToken = $this->auth->verifyIdToken($idToken);
            $claims = $verifiedToken->claims();

            $sub   = $claims->get('sub');   // uid
            $email = $claims->get('email'); // email
            $name  = $claims->get('name')
                ?? trim(($claims->get('given_name') ?? '').' '.($claims->get('family_name') ?? ''))
                ?? $email;

            if (! $sub || ! $email) {
                Log::warning('Firebase token missing sub or email', [
                    'sub'   => $sub,
                    'email' => $email,
                ]);
                return null;
            }

            return [
                'sub'   => $sub,
                'email' => $email,
                'name'  => $name,
            ];
        } catch (FailedToVerifyToken $e) {
            Log::warning('Firebase: FailedToVerifyToken', ['error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Firebase verifyIdToken failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
