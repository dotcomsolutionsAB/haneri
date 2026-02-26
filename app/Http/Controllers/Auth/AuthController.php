<?php

namespace App\Http\Controllers\Auth;

use App\services\GoogleAuthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Utils\sendWhatsAppUtility;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OtpModel;
use App\Models\AddressModel;
use App\Mail\WelcomeUserMail;

class AuthController extends Controller
{

    protected GoogleAuthService $googleAuthService;

    public function __construct(GoogleAuthService $googleAuthService)
    {
        $this->googleAuthService = $googleAuthService;
    }
    /**
     * Your existing random password helper
     */
    private function generateRandomPassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
        return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
    }
    private function sendWelcomeMail(User $user, string $appName = 'Haneri'): void
    {
        try {
            Log::info('Sending WelcomeUserMail to '.$user->email);
            Mail::to($user->email)->send(new WelcomeUserMail($user, $appName));
        } catch (\Throwable $e) {
            Log::error('Welcome email failed', ['error' => $e->getMessage()]);
        }
    }
    protected function handleGoogleAuthFromIdToken(Request $request, bool $mustMatchEmail = false)
    {
        // 1️⃣ Basic validation for extra fields
        $validated = $request->validate([
            'idToken' => 'required|string',
            'mobile'  => 'nullable|string|min:10|max:15',
            'role'    => 'nullable|in:customer,architect,dealer',   // used ONLY on first-time register
            'gstin'   => [
                'nullable',
                'string',
                'max:15',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i',
            ],
            'email'   => $mustMatchEmail ? 'required|email' : 'nullable|email',
        ]);

        // 2️⃣ Verify ID token with Google
        $payload = $this->googleAuthService->verifyIdToken($validated['idToken']);

        if (! $payload) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Invalid or expired Google token.',
                'data'    => [],
            ], 422);
        }

        // 3️⃣ Extract details from token
        $googleId = $payload['sub']   ?? null; // unique Google user ID
        $email    = $payload['email'] ?? null;
        $name     = $payload['name']
            ?? trim(($payload['given_name'] ?? '').' '.($payload['family_name'] ?? ''))
            ?: 'Google User';

        if (! $googleId || ! $email) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Google token is missing required data (email or id).',
                'data'    => [],
            ], 422);
        }

        // 4️⃣ If login payload gave email, ensure it matches token email
        if ($mustMatchEmail && isset($validated['email'])) {
            if (strtolower($validated['email']) !== strtolower($email)) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => 'Email does not match Google account.',
                    'data'    => [],
                ], 422);
            }
        }

        // 5️⃣ Find existing user by google_id OR email
        $user = User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        // ─────────────────────────────────────────
        // 🚀 CASE A: NEW USER → REGISTER via Google
        // ─────────────────────────────────────────
        if (! $user) {
            // frontend selection (customer/architect/dealer) – OPTIONAL
            $selectedType = $validated['role'] ?? 'customer';

            // DB role ALWAYS 'customer' for self-register
            $role = 'customer';

            // For first-time register, enforce GSTIN only if selectedType is architect/dealer
            if (in_array($selectedType, ['architect', 'dealer']) && empty($validated['gstin'])) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => 'GSTIN is required for architect/dealer.',
                    'data'    => [],
                ], 422);
            }

            $user = User::create([
                'name'          => $name,
                'email'         => $email,
                'mobile'        => $validated['mobile'] ?? null,
                'role'          => $role,              // ALWAYS 'customer' here
                'selected_type' => $selectedType,      // store front choice
                'gstin'         => $validated['gstin'] ?? null,
                'google_id'     => $googleId,
                'password'      => $this->generateRandomPassword(16),
            ]);

            $this->sendWelcomeMail($user, 'Haneri');

            $message    = 'User registered successfully with Google!';
            $statusCode = 201;
        }

        // ─────────────────────────────────────────
        // 🚀 CASE B: EXISTING USER → JUST LOGIN
        // ─────────────────────────────────────────
        else {
            // Make sure google_id is stored
            if (! $user->google_id) {
                $user->google_id = $googleId;
            }

            // ✅ UPDATE ONLY SAFE FIELDS
            if (! empty($validated['mobile'])) {
                $user->mobile = $validated['mobile'];
            }

            // ❌ DO NOT TOUCH $user->role (might be set by admin to architect/dealer)
            // ❌ DO NOT TOUCH $user->selected_type (keep existing preference)

            // Optionally update GSTIN if passed
            if (! empty($validated['gstin'])) {
                $user->gstin = $validated['gstin'];
            }

            $user->save();

            $message    = 'User logged in successfully with Google!';
            $statusCode = 200;
        }

        // 6️⃣ Generate Sanctum token
        $generated_token = $user->createToken('API TOKEN')->plainTextToken;

        // Shape user object for frontend
        $userData = [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'mobile'        => $user->mobile,
            'role'          => $user->role,          // stays as architect if admin changed it
            'selected_type' => $user->selected_type, // whatever is in DB, not overwritten
            'gstin'         => $user->gstin,
        ];

        return response()->json([
            'code'    => $statusCode,
            'success' => true,
            'message' => $message,
            'data'    => [
                'token' => $generated_token,
                'user'  => $userData,
            ],
        ], $statusCode);
    }

    public function register(Request $request)
    {
        // GOOGLE SIGNUP (via idToken)
        if ($request->input('auth_provider') === 'google') {
            // Here we treat as register+login; can be first time or existing
            return $this->handleGoogleAuthFromIdToken($request, false);
        }

        // NORMAL EMAIL + PASSWORD REGISTER
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'mobile'   => 'required|string|unique:users,mobile|min:10|max:15',
            'role'     => 'required|in:customer,architect,dealer',
            'gstin'    => [
                'nullable',
                'string',
                'max:15',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i',
            ],
        ], [
            'gstin.regex' => 'Invalid GSTIN format.',
        ]);

        $selectedType = $validated['role'];          // ⭐ store frontend choice
        $role         = 'customer';                 // ⭐ DB role always customer

        if (in_array($selectedType, ['architect','dealer']) && empty($validated['gstin'])) {  // ⭐
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'GSTIN is required for architect/dealer.',
                'data'    => [],
            ], 422);
        }

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'], // auto hash via cast/mutator in User model
            'mobile'   => $validated['mobile'],
            'role'          => $role,                 // ⭐ always customer
            'selected_type' => $selectedType,         // ⭐ save here
            'gstin'    => $validated['gstin'] ?? null,
        ]);

        // ✅ Optional: mimic old behaviour and allow suppressing email
        if (! $request->boolean('suppress_welcome_mail')) {
            $this->sendWelcomeMail($user, 'Haneri');
        }

        $token = $user->createToken('authToken')->plainTextToken;

        $userData = [
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'mobile' => $user->mobile,
            'role'   => $user->role,
            'selected_type' => $user->selected_type,   // ⭐ return too
            'gstin'  => $user->gstin,
        ];

        return response()->json([
            'code'    => 201,
            'success' => true,
            'message' => 'User registered successfully!',
            'data'    => [
                'token' => $token,
                'user'  => $userData,
            ],
        ], 201);
    }

    public function generate_otp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'string', 'min:10', 'max:15'],
        ]);

        $mobile = $request->input('mobile');

        $get_user = User::select('id')
                        ->where('mobile', $mobile)
                        ->first();

        if (! $get_user) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'User has not registered!',
                'data'    => [],
            ], 404);
        }

        // $six_digit_otp = substr(bin2hex(random_bytes(3)), 0, 6);
        $six_digit_otp = random_int(100000, 999999); // 6 digit numeric OTP only
        $expiresAt     = now()->addMinutes(10);

        $store_otp = User::where('mobile', $mobile)
            ->update([
                'otp'        => $six_digit_otp,
                'expires_at' => $expiresAt,
            ]);

        if (! $store_otp) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to generate OTP. Please try again.',
                'data'    => [],
            ], 500);
        }

        $templateParams = [
            'name'      => 'ace_otp',
            'language'  => ['code' => 'en'],
            'components'=> [
                [
                    'type'       => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $six_digit_otp,
                        ],
                    ],
                ],
                [
                    'type'     => 'button',
                    'sub_type' => 'url',
                    'index'    => '0',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $six_digit_otp,
                        ],
                    ],
                ],
            ],
        ];

        $whatsappUtility = new sendWhatsAppUtility();
        $whatsappUtility->sendWhatsApp($mobile, $templateParams, $mobile, 'OTP Campaign');

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'OTP sent successfully!',
            'data'    => [],          // nothing extra to send back
        ], 200);
    }

    public function login(Request $request, $otp = null)
    {
        // 1️⃣ GOOGLE LOGIN (via idToken) – already standardized
        if ($request->input('auth_provider') === 'google') {
            // Enforce: token email == payload email (if you send email in body)
            return $this->handleGoogleAuthFromIdToken($request, true);
        }

        // Small helper to shape user data
        $buildUserData = function (User $user) {
            return [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'mobile' => $user->mobile,
                'role'   => $user->role,
                'selected_type' => $user->selected_type,   // ✅ add this
                'gstin'  => $user->gstin,
            ];
        };

        // 2️⃣ OTP LOGIN
        if ($otp) {
            $request->validate([
                'mobile' => ['required', 'string'],
            ]);

            $otpRecord = User::select('otp', 'expires_at')
                ->where('mobile', $request->mobile)
                ->first();

            if (! $otpRecord) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'User not found for this mobile.',
                    'data'    => [],
                ], 404);
            }

            if ($otpRecord->otp != $otp) {
                return response()->json([
                    'code'    => 400,
                    'success' => false,
                    'message' => 'Invalid OTP entered.',
                    'data'    => [],
                ], 400);
            }

            if ($otpRecord->expires_at < now()) {
                return response()->json([
                    'code'    => 400,
                    'success' => false,
                    'message' => 'OTP has expired!',
                    'data'    => [],
                ], 400);
            }

            // Clear OTP
            User::where('mobile', $request->mobile)
                ->update(['otp' => null, 'expires_at' => null]);

            $user            = User::where('mobile', $request->mobile)->first();
            $generated_token = $user->createToken('API TOKEN')->plainTextToken;
            $userData        = $buildUserData($user);

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'User logged in successfully!',
                'data'    => [
                    'token' => $generated_token,
                    'user'  => $userData,
                ],
            ], 200);
        }

        // 3️⃣ EMAIL + PASSWORD LOGIN
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => 'required',
        ]);

        if (! Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json([
                'code'    => 401,
                'success' => false,
                'message' => 'Invalid username or password.',
                'data'    => [],
            ], 401);
        }

        /** @var User $user */
        $user            = Auth::user();
        $generated_token = $user->createToken('API TOKEN')->plainTextToken;
        $userData        = $buildUserData($user);

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'User logged in successfully!',
            'data'    => [
                'token' => $generated_token,
                'user'  => $userData,
            ],
        ], 200);
    }

    // Verify user by otp 
    public function request_otp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'string', 'min:10', 'max:15'],
        ]);

        $mobile = $request->input('mobile');

        // If mobile exists in users => already validated
        // if (User::where('mobile', $mobile)->exists()) {
        //     return response()->json([
        //         'code'    => 200,
        //         'success' => true,
        //         'message' => 'Mobile already validated.',
        //         'data'    => [],
        //     ], 200);
        // }

        if (
            User::where('mobile', $mobile)->exists()
            || AddressModel::where('contact_no', $mobile)->exists()
        ) {
            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Mobile already validated.',
                'data'    => [],
            ], 200);
        }

        $six_digit_otp = (string) random_int(100000, 999999);

        // Save OTP in otp table with status = invalid (until verified)
        $otpRow = OtpModel::updateOrCreate(
            ['mobile' => $mobile],
            [
                'otp'    => $six_digit_otp,
                'status' => 'invalid',
            ]
        );

        if (! $otpRow) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to generate OTP. Please try again.',
                'data'    => [],
            ], 500);
        }

        // Send WhatsApp (same as your template)
        $templateParams = [
            'name'      => 'ace_otp',
            'language'  => ['code' => 'en'],
            'components'=> [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $six_digit_otp],
                    ],
                ],
                [
                    'type'     => 'button',
                    'sub_type' => 'url',
                    'index'    => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $six_digit_otp],
                    ],
                ],
            ],
        ];

        $whatsappUtility = new sendWhatsAppUtility();
        $whatsappUtility->sendWhatsApp($mobile, $templateParams, $mobile, 'OTP Campaign');

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'OTP sent successfully!',
            'data'    => [],
        ], 200);
    }
    public function verify_otp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'string', 'min:10', 'max:15'],
            'otp'    => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $mobile = $request->input('mobile');
        $otp    = $request->input('otp');

        $row = OtpModel::where('mobile', $mobile)->first();

        if (! $row) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'OTP record not found for this mobile.',
                'data'    => [],
            ], 404);
        }

        // If already verified
        if ($row->status === 'valid') {
            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'OTP already verified.',
                'data'    => [],
            ], 200);
        }

        // Check OTP match
        if ((string)$row->otp !== (string)$otp) {
            // keep invalid
            $row->update(['status' => 'invalid']);

            return response()->json([
                'code'    => 400,
                'success' => false,
                'message' => 'Invalid OTP.',
                'data'    => [],
            ], 400);
        }

        // Check 2-minute expiry using updated_at (OTP must be generated within last 2 minutes)
        $expiresAt = $row->updated_at->copy()->addMinutes(2);
        if (now()->greaterThan($expiresAt)) {
            $row->update(['status' => 'invalid']);

            return response()->json([
                'code'    => 400,
                'success' => false,
                'message' => 'OTP has expired.',
                'data'    => [],
            ], 400);
        }

        // SUCCESS: mark as valid
        $row->update([
            'status' => 'valid',
            // optional: you can also clear otp if you want one-time usage
            // 'otp' => null,
        ]);

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'OTP verified successfully!',
            'data'    => [],
        ], 200);
    }


    // user `login`
    // public function login(Request $request, $otp = null)
    // {
    //     if ($otp) {
    //         $request->validate([
    //             'mobile' => ['required', 'string'],
    //         ]);

    //         $otpRecord = User::select('otp', 'expires_at')
    //             ->where('mobile', $request->mobile)
    //             ->first();

    //         if ($otpRecord) {
    //             if (!$otpRecord || $otpRecord->otp != $otp) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Invalid OTP Entered',
    //                 ], 200);
    //             } elseif ($otpRecord->expires_at < now()) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'OTP has expired!',
    //                 ], 200);
    //             } else {
    //                 // Remove OTP record after successful validation
    //                 User::where('mobile', $request->mobile)
    //                     ->update(['otp' => null, 'expires_at' => null]);

    //                 // Retrieve the user
    //                 $user = User::where('mobile', $request->mobile)->first();

    //                 // Generate a Sanctum token
    //                 $generated_token = $user->createToken('API TOKEN')->plainTextToken;

    //                 return response()->json([
    //                     'success' => true,
    //                     'data' => [
    //                         'token' => $generated_token,
    //                         'name' => $user->name,
    //                         'role' => $user->role,
    //                         'id' => $user->id,
    //                     ],
    //                     'message' => 'User logged in successfully!',
    //                 ], 200);
    //             }
    //         } else {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Username is not valid.',
    //             ], 200);
    //         }
    //     } else {
    //         $request->validate([
    //             'email' => ['required', 'string'],
    //             'password' => 'required',
    //         ]);

    //         // Find the user by email
    //         $user = User::where('email', $request->username)->first();

    //         if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
    //             $user = Auth::user();

    //             // Generate a Sanctum token
    //             $generated_token = $user->createToken('API TOKEN')->plainTextToken;

    //             // Retrieve user permissions
    //             // $permissions = $user->getAllPermissions()->pluck('name');

    //             return response()->json([
    //                 'success' => true,
    //                 'data' => [
    //                     'token' => $generated_token,
    //                     'name' => $user->name,
    //                     'role' => $user->role,
    //                     'id' => $user->id,
    //                 ],
    //                 'message' => 'User logged in successfully!',
    //             ], 200);
    //         } else {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid username or password.',
    //             ], 200);
    //         }
    //     }
    // }

    // user `logout`
    public function logout(Request $request)
    {
        // Check if the user is authenticated
        if(!$request->user()) {
            return response()->json([
                'success'=> false,
                'message'=>'Sorry, no user is logged in now!',
            ], 401);
        }

        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully!',
        ], 204);
    }
}
