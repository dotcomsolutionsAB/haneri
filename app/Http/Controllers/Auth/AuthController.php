<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    //
        // user `login`
    // public function login(Request $request, $otp = null)
    public function login(Request $request, $otp = null)
    {
        // if ($otp) {
        //     $request->validate([
        //         'username' => ['required', 'string'],
        //     ]);

        //     $otpRecord = User::select('otp', 'expires_at')
        //         ->where('username', $request->username)
        //         ->first();

        //     if ($otpRecord) {
        //         if (!$otpRecord || $otpRecord->otp != $otp) {
        //             return response()->json([
        //                 'success' => false,
        //                 'message' => 'Invalid OTP Entered',
        //             ], 200);
        //         } elseif ($otpRecord->expires_at < now()) {
        //             return response()->json([
        //                 'success' => false,
        //                 'message' => 'OTP has expired!',
        //             ], 200);
        //         } else {
        //             // Remove OTP record after successful validation
        //             User::where('username', $request->username)
        //                 ->update(['otp' => null, 'expires_at' => null]);

        //             // Retrieve the user
        //             $user = User::where('username', $request->username)->first();

        //             // Generate a Sanctum token
        //             $generated_token = $user->createToken('API TOKEN')->plainTextToken;

        //             // Retrieve user permissions
        //             $permissions = $user->getAllPermissions()->pluck('name');

        //             return response()->json([
        //                 'success' => true,
        //                 'data' => [
        //                     'token' => $generated_token,
        //                     'name' => $user->name,
        //                     'role' => $user->role,
        //                     'id' => $user->id,
        //                     'jamiat_id' => $user->jamiat_id,
        //                     'permissions' => $permissions, // Add permissions here
        //                 ],
        //                 'message' => 'User logged in successfully!',
        //             ], 200);
        //         }
        //     } else {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Username is not valid.',
        //         ], 200);
        //     }
        // } else {
            $request->validate([
                'email' => ['required', 'string'],
                'password' => 'required',
            ]);

            // Find the user by username
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
        // }
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
