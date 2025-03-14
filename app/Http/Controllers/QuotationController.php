<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuotationModel;
use App\Models\QuotationItemModel;
use App\Models\CartModel;
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
            'status' => 'required|in:pending,completed,cancelled,refunded',
            'payment_status' => 'required|in:pending,paid,failed',
            'shipping_address' => 'required|string',
        ]);

        $user = Auth::user(); // Get the user object
        $user_id = $user->id; // Extract the user ID

        // Fetch user details from User model
        $quotationUser = User::find($user_id);
        if (!$quotationUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user_name = $quotationUser->name;  // Fetch name
        $user_email = $quotationUser->email;  // Fetch email
        $user_phone = $quotationUser->mobile;  // Fetch mobile (Ensure the column exists in the `users` table)

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


            // Call Razorpay quotation API Before Saving quotation in DB**
            $razorpayController = new RazorpayController(); 
            $razorpayRequest = new Request([
                'amount' => $totalAmount,
                'currency' => 'INR'
            ]);
            $razorpayResponse = $razorpayController->createOrder($razorpayRequest);

            // Decode Razorpay response
            $razorpayData = json_decode($razorpayResponse->getContent(), true);
            if (!$razorpayData['success']) {
                DB::rollBack();
                return response()->json(['message' => 'Failed to create Razorpay quotation.'], 500);
            }

            // Create the quotation record
            $quotation = QuotationModel::create([
                'user_id' => $user_id,
                'total_amount' => $totalAmount,
                'status' => $request->input('status', 'pending'),
                'payment_status' => $request->input('payment_status', 'pending'),
                'shipping_address' => $request->input('shipping_address'),
                'razorpay_order_id' => $razorpayData['order']['id'],
            ]);

            // Iterate through each cart item to add it to the order items table
            foreach($cartItems as $cartItem)
            {
                // Create the order item record
                QuotationItemModel::create([
                    'quotation_id' => $quotation->id, // Link to the created order
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id), // Final price per item
                ]);
            }

            // After successfully adding order items, delete the cart items
            CartModel::where('user_id', $user_id)->delete();

            // Commit the transaction
            DB::commit();

            // Prepare response
            $response = [
                'message' => 'Quotation created successfully!',
                'data' => [
                    'quotation' => $quotation->id,
                    'total_amount' => $quotation->total_amount,
                    'status' => $quotation->status,
                    'payment_status' => $quotation->payment_status,
                    'shipping_address' => $quotation->shipping_address,
                    'razorpay_order_id' => $quotation->razorpay_quotation_id,
                    'name' => $user_name,
                    'email' => $user_email, 
                    'phone' => $user_phone, 
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
        // Assuming we have a method to fetch the product price and variant price
        $product = \App\Models\ProductModel::find($product_id);

        if ($variant_id) {
            // Assuming you have a method for variant price, like `getVariantPrice()`
            $variant = \App\Models\ProductVariantModel::find($variant_id);
            // return $variant ? $variant->price : $product->price;  // Fallback to product price if variant not found

            return $variant ? $variant->selling_price : 0;  // Fallback to product price if variant not found
        }

        return $product->price;  // Return product price if no variant
    }

    // View all quotations for a user
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
