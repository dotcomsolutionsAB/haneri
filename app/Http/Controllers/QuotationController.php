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
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuotationController extends Controller
{
    //
    // Inside QuotationController (private helper)
    private function buildQuotationNumber(QuotationModel $quotation): string
    {
        $year  = optional($quotation->created_at)->format('Y') ?? now()->format('Y');
        $month = optional($quotation->created_at)->format('m') ?? now()->format('m');

        // "cart quote id" → we'll use quotation id
        $id = (int) $quotation->id;

        // Base sequence:
        // <= 9999 -> 4-digit padded
        //  > 9999 -> 2-digit group + full id  (e.g. 42562 => 04-42562)
        if ($id <= 9999) {
            $sequence = str_pad($id, 4, '0', STR_PAD_LEFT);
        } else {
            $group    = (int) floor($id / 10000);        // 42562 -> 4
            $prefix   = str_pad($group, 2, '0', STR_PAD_LEFT); // "04"
            $sequence = $prefix . '-' . $id;             // "04-42562"
        }

        $base = "HAN-QT-{$year}-{$month}-{$sequence}";

        // If this exact number doesn't exist, use it directly
        if (! QuotationModel::where('quotation_no', $base)->exists()) {
            return $base;
        }

        // If same exists, append -D1, -D2, ... (e.g. HAN-QT-...-0001-D1)
        $last = QuotationModel::where('quotation_no', 'like', $base . '-D%')
            ->orderBy('quotation_no', 'desc')
            ->first();

        $nextIndex = 1;
        if ($last && preg_match('/-D(\d+)$/', $last->quotation_no, $m)) {
            $nextIndex = ((int) $m[1]) + 1;
        }

        return $base . '-D' . $nextIndex;
    }

    // Store a new quotation
    public function store(Request $request)
    {
        // Validate request data
        $request->validate([
            'q_user'    => 'required|string',
            'q_email'   => 'nullable|email',
            'q_mobile'  => 'nullable|string',
            'q_address' => 'nullable|string',
        ]);

        $user    = Auth::user();
        $user_id = $user->id;

        DB::beginTransaction();

        try {
            // Fetch all items from the cart for the user
            $cartItems = CartModel::where('user_id', $user_id)->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['message' => 'Sorry, cart is empty.'], 400);
            }

            // Calculate total (already INCLUDING 18% tax in your logic)
            $totalAmount = 0;

            foreach ($cartItems as $cartItem) {
                $totalAmount += $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id) * $cartItem->quantity;
            }

            // Round for currency
            $totalAmount = round($totalAmount, 2);

            // 👉 Split into subtotal + tax as per your rule:
            // tax = 18% of total, subtotal = total - tax
            $taxRate   = 0.18;
            $taxAmount = round($totalAmount * $taxRate, 2);
            $subTotal  = round($totalAmount - $taxAmount, 2);

            // Create the quotation record (without number yet)
            $quotation = QuotationModel::create([
                'user_id'      => $user_id,
                'total_amount' => (float) $totalAmount,
                'q_user'       => $request->input('q_user'),
                'q_email'      => $request->input('q_email'),
                'q_mobile'     => $request->input('q_mobile'),
                'q_address'    => $request->input('q_address'),
            ]);

            // 👉 Generate quotation number now that we have the ID
            $quotationNo              = $this->buildQuotationNumber($quotation);
            $quotation->quotation_no  = $quotationNo;
            $quotation->save();

            // Add quotation items
            foreach ($cartItems as $cartItem) {
                QuotationItemModel::create([
                    'quotation_id' => $quotation->id,
                    'product_id'   => $cartItem->product_id,
                    'variant_id'   => $cartItem->variant_id,
                    'quantity'     => $cartItem->quantity,
                    'price'        => $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id),
                ]);
            }

            DB::commit();

            // Generate the PDF invoice and store the path
            $generator  = new InvoiceController();
            $invoiceUrl = $generator->generateQuotationInvoice($quotation, $subTotal, $taxAmount);

            // Save URL on quotation
            $quotation->update(['invoice_quotation' => $invoiceUrl]);

            // Send email with the attached PDF to the authenticated user's email
            try {
                // Send the email to the authenticated user's email (not q_email, but the user's email)
                Mail::to($user->email)->send(new QuotationMail($quotation, $user));  // Send to the authenticated user's email
                \Log::info('Quotation email sent successfully to ' . $user->email);
            } catch (\Exception $e) {
                \Log::error('Failed to send quotation email: ' . $e->getMessage());
            }

            // Response payload
            $response = [
                'message' => 'Quotation created successfully!',
                'data'    => [
                    'quotation_id'      => $quotation->id,
                    'quotation_no'      => $quotation->quotation_no,
                    'total_amount'      => $quotation->total_amount,
                    'subtotal'          => $subTotal,
                    'tax'               => $taxAmount,
                    'name'              => $quotation->q_user,
                    'email'             => $quotation->q_email,
                    'phone'             => $quotation->q_mobile,
                    'invoice_quotation' => $invoiceUrl,
                ],
            ];

            return response()->json([
                'message' => 'Quotation created and email sent successfully!',
                'data'    => $response,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to create quotation: ' . $e->getMessage());
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create quotation. Please try again.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     // Validate request data
    //     $request->validate([
    //         'q_user' => 'required|string',
    //         'q_email' => 'nullable|email',
    //         'q_mobile' => 'nullable|string',
    //         'q_address' => 'nullable|string',
    //     ]);

    //     $user = Auth::user(); // Get the user object
    //     $user_id = $user->id; // Extract the user ID

    //     // Start a transaction to ensure all operations are atomic
    //     DB::beginTransaction();

    //     try{
    //         // Fetch all items from the cart for the user
    //         $cartItems = CartModel::where('user_id', $user_id)->get();

    //         // Check if the cart is empty
    //         if ($cartItems->isEmpty()) {
    //             return response()->json(['message' => 'Sorry, cart is empty.'], 400);
    //         }

    //         // Calculate the total amount by iterating through the cart items
    //         $totalAmount  = 0 ;

    //         foreach($cartItems as $cartItem)
    //         {
    //             $totalAmount += $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id) *$cartItem->quantity;
    //         }

    //         // Round the total amount to 2 decimal places for currency
    //         $totalAmount = round($totalAmount, 2);

    //         // Create the quotation record
    //         $quotation = QuotationModel::create([
    //             'user_id' => $user_id,
    //             'total_amount' => (float)$totalAmount,
    //             'q_user' => $request->input('q_user'),
    //             'q_email' => $request->input('q_email'),
    //             'q_mobile' => $request->input('q_mobile'),
    //             'q_address' => $request->input('q_address'),
    //         ]);

    //         // Iterate through each cart item to add it to the order items table
    //         foreach($cartItems as $cartItem)
    //         {
    //             // Create the quotation record
    //             QuotationItemModel::create([
    //                 'quotation_id' => $quotation->id, // Link to the created order
    //                 'product_id' => $cartItem->product_id,
    //                 'variant_id' => $cartItem->variant_id,
    //                 'quantity' => $cartItem->quantity,
    //                 'price' => $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id), // Final price per item
    //             ]);
    //         }

    //         // After successfully adding order items, delete the cart items
    //         //CartModel::where('user_id', (string)$user_id)->delete();

    //         // Commit the transaction
    //         DB::commit();

    //         // Now generate the invoice and store the file path
    //         $generate_quotation_order = new InvoiceController();
    //         $invoiceUrl = $generate_quotation_order->generateQuotationInvoice($quotation);

    //         // Store the invoice URL in the QuotationModel
    //         $quotation->update(['invoice_quotation' => $invoiceUrl]);

    //         // Prepare response
    //         $response = [
    //             'message' => 'Quotation created successfully!',
    //             'data' => [
    //                 'quotation' => $quotation->id,
    //                 'total_amount' => $quotation->total_amount,
    //                 'name' => $quotation->q_user,
    //                 'email' => $quotation->q_email, 
    //                 'phone' => $quotation->q_mobile, 
    //                 'phone' => $quotation->q_mobile, 
    //                 'invoice_quotation' => $invoiceUrl,
    //             ]
    //         ];

    //         // Return success response
    //         return response()->json(['message' => 'Quotation created successfully!', 'data' => $response], 201);
    //     }

    //     catch(\Exception $e)
    //     {
    //         // Log the exception for debugging
    //         \Log::error('Failed to create quotation: ' . $e->getMessage());

    //         // In case of any failure, roll back the transaction
    //         DB::rollBack();

    //         // Return error response
    //         return response()->json(['message' => 'Failed to create quotation. Please try again.', 'error' => $e->getMessage()], 500);
    //     }
    // }

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
            : response()->json(['message' => 'No quotations found.'], 200);
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

            // --- delete the PDF file ---
            if ($quotation->invoice_quotation) {
                // convert public URL -> relative storage path
                // e.g.  https://api.haneri.com/storage/upload/invoice_quotations/invoice_Q-123.pdf
                //  ->  upload/invoice_quotations/invoice_Q-123.pdf
                $relativePath = Str::after($quotation->invoice_quotation, '/storage/');
                Storage::disk('public')->delete($relativePath);
            }

            // Delete related order items
            QuotationItemModel::where('quotation_id', $quotationId)->delete();

            // Delete the order
            $quotation->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Quotation, items and PDF file deleted successfully!',
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
