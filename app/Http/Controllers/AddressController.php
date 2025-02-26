<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AddressModel;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    //
    // List all addresses for a user
    public function index(Request $request)
    {
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

        return $addresses->isNotEmpty()
        ? response()->json(['message' => 'Addresses retrieved successfully!', 'data' => $addresses->makeHidden(['created_at', 'updated_at']), 'count' => count($addresses)], 200)
        : response()->json(['message' => 'No address found.'], 400);

    }

    // Add a new address
    public function store(Request $request)
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

        $user = Auth::user();

        if ($user->role == 'admin') {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
            ]);  
            $user_id =  $request->input('user_id');
        }

        else {
            $user_id =  $user->id;
        }

        // If the user selects a default address, set all other addresses to not default
        if ($request->input('is_default') == true) {
            AddressModel::where('user_id', $user_id)->update(['is_default' => false]);
        }

        // Create the new address
        $address = AddressModel::create([
            'user_id' => $user->id,
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
            'message' => 'Address created successfully!',
            'data' => $address
        ], 201);
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
