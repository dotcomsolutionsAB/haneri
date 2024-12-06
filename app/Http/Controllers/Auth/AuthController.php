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
    public function generate_otp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'string', 'min:12', 'max:14'],
        ]);

        $mobile = $request->input('mobile');

        $get_user = User::select('id')
                        ->where('mobile', $mobile)
                        ->first();

        if(!$get_user == null)
        {
            // $six_digit_otp = random_int(100000, 999999);
            $six_digit_otp = substr(bin2hex(random_bytes(3)), 0, 6);

            $expiresAt = now()->addMinutes(10);

            $store_otp = User::where('mobile', $mobile)
                             ->update([
                                'otp' => $six_digit_otp,
                                'expires_at' => $expiresAt,
                            ]);

            if($store_otp)
            {
                $templateParams = [
                    'name' => 'ace_otp', // Replace with your WhatsApp template name
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

                $response = $whatsappUtility->sendWhatsApp($mobile, $templateParams, $mobile, 'OTP Campaign');

                return response()->json([
                    'message' => 'Otp send successfully!',
                    'data' => $store_otp
                ], 200);
            }
        }
        else {
            return response()->json([
                'message' => 'User has not registered!',
            ], 404);
        }
    }
    // user `login`
    public function login(Request $request, $otp = null)
    {
        if ($otp) {
            $request->validate([
                'mobile' => ['required', 'string'],
            ]);

            $otpRecord = User::select('otp', 'expires_at')
                ->where('mobile', $request->mobile)
                ->first();

            if ($otpRecord) {
                if (!$otpRecord || $otpRecord->otp != $otp) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid OTP Entered',
                    ], 200);
                } elseif ($otpRecord->expires_at < now()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP has expired!',
                    ], 200);
                } else {
                    // Remove OTP record after successful validation
                    User::where('mobile', $request->mobile)
                        ->update(['otp' => null, 'expires_at' => null]);

                    // Retrieve the user
                    $user = User::where('mobile', $request->mobile)->first();

                    // Generate a Sanctum token
                    $generated_token = $user->createToken('API TOKEN')->plainTextToken;

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'token' => $generated_token,
                            'name' => $user->name,
                            'role' => $user->role,
                            'id' => $user->id,
                        ],
                        'message' => 'User logged in successfully!',
                    ], 200);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Username is not valid.',
                ], 200);
            }
        } else {
            $request->validate([
                'email' => ['required', 'string'],
                'password' => 'required',
            ]);

            // Find the user by email
            $user = User::where('email', $request->username)->first();

            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();

                // Generate a Sanctum token
                $generated_token = $user->createToken('API TOKEN')->plainTextToken;

                // Retrieve user permissions
                // $permissions = $user->getAllPermissions()->pluck('name');

                return response()->json([
                    'success' => true,
                    'data' => [
                        'token' => $generated_token,
                        'name' => $user->name,
                        'role' => $user->role,
                        'id' => $user->id,
                    ],
                    'message' => 'User logged in successfully!',
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid username or password.',
                ], 200);
            }
        }
    }

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
