<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Utils\sendWhatsAppUtility;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    //
    // genearate otp and send to `whatsapp`
    // public function generate_otp(Request $request)
    // {
    //     $request->validate([
    //         'mobile' => ['required', 'string', 'min:12', 'max:14'],
    //     ]);

    //     $mobile = $request->input('mobile');

    //     $get_user = User::select('id')
    //                     ->where('mobile', $mobile)
    //                     ->first();

    //     if(!$get_user == null)
    //     {
    //         // $six_digit_otp = random_int(100000, 999999);
    //         $six_digit_otp = substr(bin2hex(random_bytes(3)), 0, 6);

    //         $expiresAt = now()->addMinutes(10);

    //         $store_otp = User::where('mobile', $mobile)
    //                          ->update([
    //                             'otp' => $six_digit_otp,
    //                             'expires_at' => $expiresAt,
    //                         ]);

    //         if($store_otp)
    //         {
    //             $templateParams = [
    //                 'name' => 'ace_otp', // Replace with your WhatsApp template name
    //                 'language' => ['code' => 'en'],
    //                 'components' => [
    //                     [
    //                         'type' => 'body',
    //                         'parameters' => [
    //                             [
    //                                 'type' => 'text',
    //                                 'text' => $six_digit_otp,
    //                             ],
    //                         ],
    //                     ],
    //                     [
    //                         'type' => 'button',
    //                         'sub_type' => 'url',
    //                         "index" => "0",
    //                         'parameters' => [
    //                             [
    //                                 'type' => 'text',
    //                                 'text' => $six_digit_otp,
    //                             ],
    //                         ],
    //                     ]
    //                 ],
    //             ];

    //             $whatsappUtility = new sendWhatsAppUtility();

    //             $response = $whatsappUtility->sendWhatsApp($mobile, $templateParams, $mobile, 'OTP Campaign');

    //             return response()->json([
    //                 'message' => 'Otp send successfully!',
    //                 'data' => $store_otp
    //             ], 200);
    //         }
    //     }
    //     else {
    //         return response()->json([
    //             'message' => 'User has not registered!',
    //         ], 404);
    //     }
    // }
    
    /**
     * Your existing random password helper
     */
    private function generateRandomPassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
        return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
    }

    /**
     * Shared GOOGLE handler
     * - If user exists (by google_id or email) => LOGIN
     * - Else => REGISTER + LOGIN
     *
     * Used by both /register and /login when auth_provider = 'google'
     */
    protected function handleGoogleAuth(Request $request)
    {
        $validated = $request->validate([
            'google_id'     => 'required|string',
            'email'         => 'required|email',
            'name'          => 'required|string|max:255',
            'mobile'        => 'nullable|string|min:10|max:15',
            'gstin'         => [
                'nullable',
                'string',
                'max:15',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i',
            ],
            'selected_type' => 'nullable|string',
            'suppress_welcome_mail' => 'nullable|boolean',
        ]);

        $user = User::where('google_id', $validated['google_id'])
            ->orWhere('email', $validated['email'])
            ->first();

        if (! $user) {
            // First time: REGISTER via Google
            $user = User::create([
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'mobile'        => $validated['mobile'] ?? null,
                'gstin'         => $validated['gstin'] ?? null,
                'selected_type' => $validated['selected_type'] ?? null,
                'google_id'     => $validated['google_id'],
                // Will be hashed because of casts()
                'password'      => $this->generateRandomPassword(16),
            ]);

            if (! ($validated['suppress_welcome_mail'] ?? false)) {
                try {
                    Log::info('Sending WelcomeUserMail (Google) to '.$user->email);
                    Mail::to($user->email)->send(new WelcomeUserMail($user, 'Haneri'));
                } catch (\Throwable $e) {
                    Log::error('Welcome email failed (Google)', ['error' => $e->getMessage()]);
                }
            }

            $message    = 'User registered successfully with Google!';
            $statusCode = 201;
        } else {
            // Existing user: just ensure google_id is set
            if (! $user->google_id) {
                $user->google_id = $validated['google_id'];
                $user->save();
            }

            $message    = 'User logged in successfully with Google!';
            $statusCode = 200;
        }

        $generated_token = $user->createToken('API TOKEN')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $generated_token,
                'name'  => $user->name,
                'role'  => $user->role,
                'id'    => $user->id,
            ],
            'message' => $message,
        ], $statusCode);
    }

    /**
     * REGISTER – Email/Password OR Google (same endpoint)
     * POST /api/register
     */
    public function register(Request $request)
    {
        // 1️⃣ Google register/login (same function)
        if ($request->input('auth_provider') === 'google') {
            return $this->handleGoogleAuth($request);
        }

        // 2️⃣ Normal email/password register (your original logic)
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'mobile'        => 'required|string|unique:users,mobile|min:10|max:15',
            'gstin'         => [
                'nullable',
                'string',
                'max:15',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i',
            ],
            'selected_type' => 'nullable|string',
            'suppress_welcome_mail' => 'nullable|boolean',
        ]);

        // Cast will auto-hash password
        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => $validated['password'],
            'mobile'        => $validated['mobile'],
            'gstin'         => $validated['gstin'] ?? null,
            'selected_type' => $validated['selected_type'] ?? null,
        ]);

        if (! ($validated['suppress_welcome_mail'] ?? false)) {
            try {
                Log::info('Sending WelcomeUserMail to '.$user->email);
                Mail::to($user->email)->send(new WelcomeUserMail($user, 'Haneri'));
            } catch (\Throwable $e) {
                Log::error('Welcome email failed', ['error' => $e->getMessage()]);
            }
        }

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully!',
            'data'    => $user->only(['name','email','mobile','role','gstin','selected_type']),
            'token'   => $token,
        ], 201);
    }

    /**
     * Generate OTP and send to WhatsApp (your existing method)
     */
    public function generate_otp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'string', 'min:12', 'max:14'],
        ]);

        $mobile = $request->input('mobile');

        $get_user = User::select('id')
                        ->where('mobile', $mobile)
                        ->first();

        if ($get_user) {
            $six_digit_otp = substr(bin2hex(random_bytes(3)), 0, 6);
            $expiresAt = now()->addMinutes(10);

            $store_otp = User::where('mobile', $mobile)
                             ->update([
                                'otp'        => $six_digit_otp,
                                'expires_at' => $expiresAt,
                            ]);

            if ($store_otp) {
                $templateParams = [
                    'name' => 'ace_otp',
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $six_digit_otp,
                                ],
                            ],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            "index" => "0",
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $six_digit_otp,
                                ],
                            ],
                        ]
                    ],
                ];

                $whatsappUtility = new sendWhatsAppUtility();
                $whatsappUtility->sendWhatsApp($mobile, $templateParams, $mobile, 'OTP Campaign');

                return response()->json([
                    'success' => true,
                    'message' => 'Otp send successfully!',
                ], 200);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'User has not registered!',
        ], 404);
    }

    /**
     * LOGIN – Email/Password OR OTP OR Google
     * POST /api/login or POST /api/login/{otp?}
     */
    public function login(Request $request, $otp = null)
    {
        // 1️⃣ Google login (or auto-register if needed)
        if ($request->input('auth_provider') === 'google') {
            return $this->handleGoogleAuth($request);
        }

        // 2️⃣ OTP login
        if ($otp) {
            $request->validate([
                'mobile' => ['required', 'string'],
            ]);

            $otpRecord = User::select('otp', 'expires_at')
                ->where('mobile', $request->mobile)
                ->first();

            if ($otpRecord) {
                if ($otpRecord->otp != $otp) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid OTP Entered',
                    ], 200);
                } elseif ($otpRecord->expires_at < now()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP has expired!',
                    ], 200);
                }

                // Clear OTP
                User::where('mobile', $request->mobile)
                    ->update(['otp' => null, 'expires_at' => null]);

                $user = User::where('mobile', $request->mobile)->first();
                $generated_token = $user->createToken('API TOKEN')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'token' => $generated_token,
                        'name'  => $user->name,
                        'role'  => $user->role,
                        'id'    => $user->id,
                    ],
                    'message' => 'User logged in successfully!',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Username is not valid.',
            ], 200);
        }

        // 3️⃣ Email + password login
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => 'required',
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            $generated_token = $user->createToken('API TOKEN')->plainTextToken;

            return response()->json([
                'success' => true,
                'data'    => [
                    'token' => $generated_token,
                    'name'  => $user->name,
                    'role'  => $user->role,
                    'id'    => $user->id,
                ],
                'message' => 'User logged in successfully!',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid username or password.',
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
