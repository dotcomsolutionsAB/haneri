<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuotationModel;
use App\Models\QuotationItemModel;
use App\Models\CartModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\User;
use DB;
use App\Http\Controllers\RazorpayController;
use Illuminate\Support\Facades\Auth;

class QuotationController extends Controller
{
    //
    // Store a new quotation
    public function store(Request $request)
    {
        // Validate request data
        $request->validate([
            'q_user' => 'required|string',
            'q_email' => 'required|string',
            'q_mobile' => 'nullable|string',
            'q_address' => 'required|string',
        ]);

        $user = Auth::user(); // Get the user object
        $user_id = $user->id; // Extract the user ID

        // Start a transaction to ensure all operations are atomic
        DB::beginTransaction();

        try{
            // Fetch all items from the cart for the user
            $cartItems = CartModel::where('user_id', $user_id)->get();

            // Check if the cart is empty
            if ($cartItems->isEmpty()) {
                return response()->json(['message' => 'Sorry, cart is empty.'], 400);
            }

            // Calculate the total amount by iterating through the cart items
            $totalAmount  = 0 ;

            foreach($cartItems as $cartItem)
            {
                $totalAmount += $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id) *$cartItem->quantity;
            }

            // Round the total amount to 2 decimal places for currency
            $totalAmount = round($totalAmount, 2);

            // Create the quotation record
            $quotation = QuotationModel::create([
                'user_id' => $user_id,
                'total_amount' => $totalAmount,
                'q_user' => $request->input('q_user'),
                'q_email' => $request->input('q_email'),
                'q_mobile' => $request->input('q_mobile'),
                'q_address' => $request->input('q_address'),
            ]);

            // Iterate through each cart item to add it to the order items table
            foreach($cartItems as $cartItem)
            {
                // Create the quotation record
                QuotationItemModel::create([
                    'quotation_id' => $quotation->id, // Link to the created order
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id), // Final price per item
                ]);
            }

            // After successfully adding order items, delete the cart items
            //CartModel::where('user_id', (string)$user_id)->delete();

            // Commit the transaction
            DB::commit();

            // Prepare response
            $response = [
                'message' => 'Quotation created successfully!',
                'data' => [
                    'quotation' => $quotation->id,
                    'total_amount' => $quotation->total_amount,
                    'name' => $quotation->q_user,
                    'email' => $quotation->q_email, 
                    'phone' => $quotation->q_mobile, 
                    'phone' => $quotation->q_mobile, 
                ]
            ];

            // Return success response
            return response()->json(['message' => 'Quotation created successfully!', 'data' => $response], 201);
        }

        catch(\Exception $e)
        {
            // Log the exception for debugging
            \Log::error('Failed to create quotation: ' . $e->getMessage());

            // In case of any failure, roll back the transaction
            DB::rollBack();

            // Return error response
            return response()->json(['message' => 'Failed to create quotation. Please try again.', 'error' => $e->getMessage()], 500);
        }
    }

    // Helper function to get the final price for a product and its variant
    private function getFinalPrice($product_id, $variant_id = null)
    {
        // Fetch product details
        $product = ProductModel::find($product_id);

        if ($variant_id) {
            // Fetch variant details
            $variant = ProductVariantModel::find($variant_id);
            
            if ($variant) {
                // Calculate discounted price for the variant based on percentage discount
                $regularPrice = $variant->regular_price;
                $discount = $variant->customer_discount ?? 0; // Default to 0 if no discount is set

                // Apply the discount (calculate price after discount)
                $discountedPrice = $regularPrice - ($regularPrice * ($discount / 100));

                // Return the discounted price as a float, ensuring it doesn't go below 0
                return max(0, (float)$discountedPrice); // Ensure price doesn't go below 0
            }
            return 0; // Return 0 if variant not found
        }

        return 0; // Return 0 if variant_id is not provided
    }

    // View all quotations for a user
    public function index()
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

        // Fetch all quotations for the user
        $quotations = QuotationModel::with(['items', 'user'])
                            -> where('user_id', $user_id)
                            ->get()
                            ->map(function ($quotation) {
                            // Make sure to hide the unwanted fields from the user and items
                            if ($quotation->items) {
                                $quotation->items->makeHidden(['id', 'created_at', 'updated_at']);
                            }
                            if ($quotation->user) {
                                $quotation->user->makeHidden(['id', 'created_at', 'updated_at']);
                            }
                            // Optionally hide fields from the quotation
                            $quotation->makeHidden(['id', 'created_at', 'updated_at']);
                            return $quotation;
                        });

        return $quotations->isNotEmpty()
            ? response()->json(['message' => 'Quotations fetched successfully!', 'data' => $quotations, 'count' => count($quotations)], 200)
            : response()->json(['message' => 'No quotations found.'], 400);
    }

    // View details of a single quotation
    public function show($id)
    {
        $user = Auth::user();

        // Fetch the quotation by ID for the user
        $get_quotation = QuotationModel::with(['items', 'user'])
                            ->where('user_id', $user->id)
                            ->get()
                            ->map(function ($quotation) {
                                // Make sure to hide the unwanted fields from the user and items
                                if ($quotation->items) {
                                    $quotation->items->makeHidden(['id', 'created_at', 'updated_at']);
                                }
                                if ($quotation->user) {
                                    $quotation->user->makeHidden(['id', 'created_at', 'updated_at']);
                                }
                                // Optionally hide fields from the quotation
                                $quotation->makeHidden(['id', 'created_at', 'updated_at']);
                                return $quotation;
                            });

        if (!$get_quotation) {
            return response()->json(['message' => 'Quotation not found.'], 404);
        }

        // Hide unnecessary fields
        $get_quotation->makeHidden(['id', 'created_at', 'updated_at']);

        return response()->json(['message' => 'Quotation details fetched successfully!', 'data' => $get_quotation], 200);
    }

    // delete an quotation
    public function delete($quotationId)
    {
        try {
            // Start transaction
            DB::beginTransaction();

            // Fetch the order
            $quotation = QuotationModel::find($quotationId);

            if (!$quotation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found!',
                ], 404);
            }

            // Delete related order items
            QuotationItemModel::where('quotation_id', $quotationId)->delete();

            // Delete the order
            $quotation->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Quotation and corresponding items deleted successfully!',
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaction in case of failure
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete quotation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
