<?php

namespace App\Http\Controllers;
use App\Models\UsersDiscountModel;
use Illuminate\Http\Request;

class UsersDiscountController extends Controller
{
    // create
    public function store(Request $request)
    {
        try {
            // Validate the incoming data
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'product_variant_id' => 'required|exists:t_product_variants,id',
                'category_id' => 'required|exists:t_categories,id',
                'discount' => 'required|numeric|min:0|max:100',
            ]);

            // Create a new discount record
            $discount = UsersDiscountModel::create([
                'user_id' => $validated['user_id'],
                'product_variant_id' => $validated['product_variant_id'],
                'category_id' => $validated['category_id'],
                'discount' => $validated['discount'],
            ]);

            return response()->json([
                'message' => 'Discount added successfully',
                'data' => $discount
            ], 201);
        } catch (ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Handle any other general exceptions
            return response()->json([
                'message' => 'An error occurred while processing your request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // fetch
    // public function fetch(Request $request, $id = null)
    // {
    //     try {
    //         $userName = $request->input('user_name');
    //         $productName = $request->input('product_name');

    //         $query = UsersDiscountModel::with(['user', 'productVariant.product', 'category'])
    //             ->when($id, function ($q, $id) {
    //                 $q->where('id', $id);
    //             })
    //             ->when($userName, function ($q, $name) {
    //                 $q->whereHas('user', function ($q) use ($name) {
    //                     $q->where('name', 'like', "%$name%");
    //                 });
    //             })
    //             ->when($productName, function ($q, $name) {
    //                 $q->whereHas('productVariant.product', function ($q) use ($name) {
    //                     $q->where('name', 'like', "%$name%");
    //                 });
    //             })
    //             ->orderBy('id', 'desc');

    //         $data = $query->get();

    //         return response()->json([
    //             'success' => true,
    //             'data' => $data
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error fetching discount data',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function fetch(Request $request, $id = null)
    {
        try {
            $userName = $request->input('user_name');
            $productName = $request->input('product_name');
            $limit = (int) $request->input('limit', 10); // default 10
            $offset = (int) $request->input('offset', 0); // default 0

            $query = UsersDiscountModel::with(['user', 'productVariant.product', 'category'])
                ->when($id, function ($q, $id) {
                    $q->where('id', $id);
                })
                ->when($userName, function ($q, $name) {
                    $q->whereHas('user', function ($q) use ($name) {
                        $q->where('name', 'like', "%$name%");
                    });
                })
                ->when($productName, function ($q, $name) {
                    $q->whereHas('productVariant.product', function ($q) use ($name) {
                        $q->where('name', 'like', "%$name%");
                    });
                })
                ->orderBy('id', 'desc');

            // Total count before applying limit/offset
            $total = $query->count();

            // Apply pagination
            $data = $query->skip($offset)->take($limit)->get();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Discount data fetched successfully.',
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                    'has_more' => ($offset + $limit) < $total
                ],
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error fetching discount data',
                'error' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    // update
    public function update(Request $request, $id)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'product_variant_id' => 'required|exists:t_product_variants,id',
                'category_id' => 'required|exists:t_categories,id',
                'discount' => 'required|numeric|min:0|max:100',
            ]);

            // Find record
            $discount = UsersDiscountModel::findOrFail($id);

            // Update record
            $discount->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Discount updated successfully',
                'data' => $discount
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Discount entry not found',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // delete
    public function delete($id)
    {
        try {
            // Find and delete
            $discount = UsersDiscountModel::findOrFail($id);
            $discount->delete();

            return response()->json([
                'success' => true,
                'message' => 'Discount deleted successfully',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Discount entry not found',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
