<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    //
    // Register a new user
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'mobile' => 'required|string|unique:users,mobile|min:10|max:15',
            'role' => 'required|in:admin,customer,vendor',
        ]);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'mobile' => $request->input('mobile'),
            'role' => $request->input('role'),
        ]);

        unset($user['id'], $user['created_at'], $user['updated_at']);

        return response()->json(['message' => 'User registered successfully!', 'data' => $user], 201);
    }

    // Get logged-in user details
    public function profile()
    {
        $user = Auth::user();

        return $user
            ? response()->json(['message' => 'Profile fetched successfully!', 'data' => $user->makeHidden(['id', 'otp', 'expires_at', 'created_at', 'updated_at'])], 200)
            : response()->json(['message' => 'User not found.'], 404);
    }

    // Update user details
    public function update(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'mobile' => 'sometimes|string|unique:users,mobile,' . $user->id . '|min:10|max:15',
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:admin,customer,vendor',
        ]);

        $user->update([
            'name' => $request->input('name', $user->name),
            'email' => $request->input('email', $user->email),
            'mobile' => $request->input('mobile', $user->mobile),
            'password' => $request->input('password') ? Hash::make($request->input('password')) : $user->password,
            'role' => $request->input('role', $user->role),
        ]);

        unset($user['id'], $user['created_at'], $user['updated_at']);

        return response()->json(['message' => 'User updated successfully!', 'data' => $user], 200);
    }
}
