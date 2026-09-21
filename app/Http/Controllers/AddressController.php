<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AddressModel;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{

    public function index(Request $request)
    {
        try {
            $user = Auth::user(); 

            // If the user is an admin, validate user_id in the request
            if ($user->role == 'admin') {
                $request->validate([
                    'user_id' => 'required|integer|exists:users,id',
                ]);
                $user_id =  $request->input('user_id');
            } else {
                $user_id =  $user->id;
            }

            $addresses = AddressModel::where('user_id', $user_id)->get();

            return response()->json(['message' => 'Addresses retrieved successfully!', 'data' => $addresses->makeHidden(['created_at', 'updated_at']), 'count' => count($addresses)], 200);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unable to retrieve addresses.', 'data' => [], 'count' => 0], 500);
        }
    }

    // Add a new address
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'contact_no' => 'required|string|max:20',
                'address_line1' => 'required|string|max:500',
                'address_line2' => 'nullable|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'postal_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
                'is_default' => 'nullable|boolean',
                'gst_no' => 'nullable|string|max:50',
            ]);

            $user = Auth::user();
            if (! $user) {
                return response()->json(['message' => 'Unauthenticated.', 'data' => null], 401);
            }

            if ($user->role == 'admin') {
                $request->validate([
                    'user_id' => 'required|integer|exists:users,id',
                ]);
                $user_id = (int) $request->input('user_id');
            } else {
                $user_id = (int) $user->id;
            }

            $isDefault = filter_var($request->input('is_default', false), FILTER_VALIDATE_BOOLEAN);

            if ($isDefault) {
                AddressModel::where('user_id', $user_id)->update(['is_default' => false]);
            }

            $address = AddressModel::create([
                'user_id' => $user_id,
                'name' => $request->input('name'),
                'contact_no' => $request->input('contact_no'),
                'address_line1' => $request->input('address_line1'),
                'address_line2' => $request->input('address_line2') ?: null,
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'postal_code' => $request->input('postal_code'),
                'country' => $request->input('country'),
                'is_default' => $isDefault,
                'gst_no' => $request->input('gst_no') ?: null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully!',
                'data' => $address->makeHidden(['created_at', 'updated_at']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Address create failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save address. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null,
            ], 500);
        }
    }

    // Update an address
    public function update(Request $request, $id)
    {
        // Validate the incoming data
        $request->validate([
            'name' => 'required|string',
            'contact_no' => 'required|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postal_code' => 'required|string',
            'country' => 'required|string',
            'is_default' => 'nullable|boolean',
            'gst_no' => 'nullable|string',
        ]);

        $address = AddressModel::find($id);

        // Check if address exists and belongs to the authenticated user
        if (!$address || $address->user_id != Auth::id()) {
            return response()->json(['message' => 'Address not found or unauthorized'], 404);
        }

        // If the user selects a default address, set all other addresses to not default
        if ($request->input('is_default') == true) {
            AddressModel::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        // Update the address
        $address->update([
            'name' => $request->input('name'),
            'contact_no' => $request->input('contact_no'),
            'address_line1' => $request->input('address_line1'),
            'address_line2' => $request->input('address_line2', null),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'postal_code' => $request->input('postal_code'),
            'country' => $request->input('country'),
            'is_default' => $request->input('is_default', false),
            'gst_no' => $request->input('gst_no'),
        ]);

        unset($address['id'], $address['created_at'], $address['updated_at']);

        return response()->json([
            'message' => 'Address updated successfully!',
            'data' => $address,
        ], 200);
    }

    // Delete an address
    public function destroy($id)
    {
        $address = AddressModel::find($id);

        // Check if address exists and belongs to the authenticated user
        if (!$address || $address->user_id != Auth::id()) {
            return response()->json(['message' => 'Address not found or unauthorized'], 404);
        }

        $address->delete();

        return response()->json([
            'message' => 'Address deleted successfully!',
        ], 200);
    }
}
