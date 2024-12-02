<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SettingModel;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    //
    // Fetch all settings (Admin only)
    public function index()
    {
        // Only allow admins to view all settings
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Retrieve all settings
        $settings = SettingModel::all();

        return $settings->isNotEmpty()
            ? response()->json(['message' => 'Settings retrieved successfully!', 'data' => $settings->makeHidden(['id', 'created_at', 'updated_at']), 'count' => count($settings)], 200)
            : response()->json(['message' => 'No settings found.'], 400);
    }

    // Update a specific setting (Admin only)
    public function update(Request $request, $key)
    {
        // Only allow admins to update settings
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate the incoming data
        $request->validate([
            'value' => 'required|json', // Ensure value is a valid JSON string
        ]);

        // Retrieve the setting by key
        $setting = SettingModel::where('key', $key)->first();

        // If setting doesn't exist, return error
        if (!$setting) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        // Update the setting value
        $setting->update([
            'value' => $request->input('value'),
        ]);

        unset($setting['id'],$setting['created_at'], $setting['updated_at']);

        // Return the updated setting
        return response()->json(['message' => 'Setting updated successfully!', 'data' => $setting], 200);
    }
}
