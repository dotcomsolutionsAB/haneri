<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequestModel;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
    public function create(Request $request)
    {
        // Validate the required fields
        $validated = $request->validate([
            'order_id' => 'required|exists:t_orders,id', // Ensure order_id exists in orders table
            'reason'   => 'required|string|max:255',     // Ensure reason is provided
        ]);

        // Create the return request with default status 'initiated'
        $returnRequest = ReturnRequestModel::create([
            'order_id' => $validated['order_id'],
            'user_id'  => auth()->id(), // Store logged-in user's ID
            'amount'   => 0, // You can change this based on logic
            'reason'   => $validated['reason'],
            'status'   => 'initiated', // Default status
        ]);

        return response()->json([
            'message' => 'Return request created successfully!',
            'data'    => $returnRequest,
        ], 201);
    }

    public function fetch(Request $request)
    {
        // Validate filters
        $validated = $request->validate([
            'limit'    => 'nullable|integer|min:1',
            'offset'   => 'nullable|integer|min:0',
            'order_id' => 'nullable|exists:t_orders,id',
            'user_id'  => 'nullable|exists:users,id',
            'status'   => 'nullable|in:initiated,accepted,declined',
        ]);

        $query = ReturnRequestModel::query();

        // Apply filters if present
        if ($validated['order_id'] ?? false) {
            $query->where('order_id', $validated['order_id']);
        }

        if ($validated['user_id'] ?? false) {
            $query->where('user_id', $validated['user_id']);
        }

        if ($validated['status'] ?? false) {
            $query->where('status', $validated['status']);
        }

        // Apply pagination
        $limit = $validated['limit'] ?? 10;
        $offset = $validated['offset'] ?? 0;

        $returnRequests = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'message' => $returnRequests->isNotEmpty() ? 'Return requests fetched successfully!' : 'No return requests found.',
            'data'    => $returnRequests,
            'count'   => $returnRequests->count(),
        ]);
    }

    public function delete($id)
    {
        // Find the return request by ID
        $returnRequest = ReturnRequestModel::find($id);

        if (!$returnRequest) {
            return response()->json([
                'message' => 'Return request not found.',
            ], 404);
        }

        // Delete the return request
        $returnRequest->delete();

        return response()->json([
            'message' => 'Return request deleted successfully!',
        ], 200);
    }

    public function update(Request $request, $id)
    {
        // Validate the status field
        $validated = $request->validate([
            'status' => 'required|in:initiated,accepted,declined',
        ]);

        // Find the return request by ID
        $returnRequest = ReturnRequestModel::find($id);

        if (!$returnRequest) {
            return response()->json([
                'message' => 'Return request not found.',
            ], 404);
        }

        // Update the status
        $returnRequest->status = $validated['status'];
        $returnRequest->save();

        return response()->json([
            'message' => 'Return request status updated successfully!',
            'data'    => $returnRequest,
        ], 200);
    }
}