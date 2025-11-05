<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserRegisteredMail;
use App\Mail\WelcomeUserMail;
use App\Mail\PasswordResetMail;
use App\Models\CartModel;
use App\Models\OrderModel;
use App\Models\BrandModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
//use Illuminate\Support\Facades\Cookie;

class UserController extends Controller
{
    //
    // Register a new user
    // public function register(Request $request)
    // {
    //     $request->validate([
    //         'name'          => 'required|string|max:255',
    //         'email'         => 'required|email|unique:users,email',
    //         'password'      => 'required|string|min:8',
    //         'mobile'        => 'required|string|unique:users,mobile|min:10|max:15',
    //         'selected_type' => 'nullable|string',
    //     ]);

    //     $user = User::create([
    //         'name'          => $request->input('name'),
    //         'email'         => $request->input('email'),
    //         'password'      => Hash::make($request->input('password')),
    //         'mobile'        => $request->input('mobile'),
    //         'role'          => 'customer',
    //         'selected_type' => $request->input('selected_type'),
    //     ]);

    //     // Send immediately (debugging). Later you can queue it.
    //     try {
    //         Log::info('Sending WelcomeUserMail to '.$user->email);
    //         Mail::to($user->email)->send(new WelcomeUserMail($user, 'Haneri'));
    //         Log::info('WelcomeUserMail sent');
    //     } catch (\Throwable $e) {
    //         Log::error('Welcome email failed', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);
    //     }

    //     $token = $user->createToken('authToken')->plainTextToken;

    //     return response()->json([
    //         'message' => 'User registered successfully!',
    //         'data'    => $user->only(['name','email','mobile','role','selected_type']),
    //         'token'   => $token,
    //     ], 201);
    // }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'mobile'        => 'required|string|unique:users,mobile|min:10|max:15',
            'gstin'         => [
                                'nullable',
                                'string',
                                'max:15',
                                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i',
                            ],
            'selected_type' => 'nullable|string',
            
        ]);

        $user = $this->createUser($validated);

        // Optional: send welcome mail (skip if caller sets a flag)
        if (!$request->boolean('suppress_welcome_mail')) {
            try {
                Log::info('Sending WelcomeUserMail to '.$user->email);
                Mail::to($user->email)->send(new WelcomeUserMail($user, 'Haneri'));
            } catch (\Throwable $e) {
                Log::error('Welcome email failed', ['error' => $e->getMessage()]);
            }
        }

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully!',
            'data'    => $user->only(['name','email','mobile','role','gstin','selected_type']),
            'token'   => $token,
        ], 201);
    }

    private function createUser(array $attrs): \App\Models\User
    {
        return User::create([
            'name'          => $attrs['name'],
            'email'         => $attrs['email'],
            'password'      => Hash::make($attrs['password']),
            'mobile'        => $attrs['mobile'],
            'role'          => $attrs['role'] ?? 'customer',
            'selected_type' => $attrs['selected_type'] ?? null,
            'gstin'         => $attrs['gstin'] ?? null, // ✅ FIXED
        ]);
    }

    public function guest_register(Request $request)
    {
        $cartId = $request->input('cart_id');
        if (!$cartId) {
            return response()->json(['message' => 'Cart ID not found.'], 400);
        }

        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email',
            'mobile' => 'required|string|unique:users,mobile|min:10|max:15',
        ]);

        // Generate random password for the guest
        $randomPassword = $this->generateRandomPassword(12);

        // All-or-nothing: user creation + cart migration
        [$user, $updated] = DB::transaction(function () use ($validated, $randomPassword, $cartId) {
            $user = $this->createUser([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => $randomPassword, // (hashed inside createUser)
                'mobile'   => $validated['mobile'],
                'role'     => 'customer',
            ]);

            // IMPORTANT: confirm your column. If your temp ID is stored in `cart_id`, change where('cart_id', $cartId)
            $updated = CartModel::where('user_id', $cartId)->update(['user_id' => $user->id]);

            return [$user, $updated];
        });

        // Send credentials email to the user (don’t fail the whole flow if mail breaks)
        try {
            Mail::to($user->email)->send(new UserRegisteredMail($user, $randomPassword));
        } catch (\Throwable $e) {
            Log::error('UserRegisteredMail failed', ['error' => $e->getMessage()]);
        }


        // Log the user in
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'message'  => 'User registered successfully! Cart updated and login credentials sent to email.',
            'password' => $randomPassword, // if you want to show it (optional)
            'token'    => $token,
            'user'     => $user->only(['name','email','mobile','role']),
        ], 201);
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

    // public function guest_register(Request $request)
    // {
    //     // Retrieve the cart_id from cookies
    //     //$cartId = $request->cookie('cart_id');

    //     // Replace with Normal Request Input
    //     $cartId = $request->input('cart_id');

    //     if (!$cartId) {
    //         return response()->json(['message' => 'Cart ID not found in cookies.'], 400);
    //     }

    //     // Validate name, email, and mobile
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|email|unique:users,email',
    //         'mobile' => 'required|string|unique:users,mobile|min:10|max:15',
    //     ]);

    //     // Generate a random password
    //     $randomPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
    //     $hashedPassword = Hash::make($randomPassword);

    //     // Prepare request data for registration
    //     $registrationData = new Request([
    //         'name' => $request->name,
    //         'email' => $request->email,
    //         'password' => $randomPassword,
    //         'mobile' => $request->mobile,
    //         'role' => 'customer'
    //     ]);

    //     // Call the register function
    //     $registerResponse = $this->register($registrationData);

    //     // Extract data properly from `original`
    //     $registerData = $registerResponse->original ?? [];

    //    // Ensure 'data' key exists in the response
    //     if (!isset($registerData['data'])) {
    //         return response()->json([
    //             'message' => 'User registration failed',
    //             'errors' => $registerData['errors'] ?? []
    //         ], 400);
    //     }

    //     // Retrieve the user data correctly
    //     $user = $registerData['data']; // This is now an array

    //     // Extract user ID from `original`
    //     $userId = $user->getOriginal('id'); // Safe way to get the original ID

    //     // Update cart: Replace cart_id with the new user_id
    //     CartModel::where('user_id', $cartId)->update(['user_id' => $userId]);

    //     // Send email using Mailable
    //     Mail::to($user->email)->send(new UserRegisteredMail($user, $randomPassword));

    //     // Retrieve the user
    //     $get_user = User::where('mobile', $request->mobile)->first();

    //     // Automatically log in the user
    //     $token = $get_user->createToken('authToken')->plainTextToken;

    //     // Remove the cart_id cookie after transferring the cart to the user
    //     //Cookie::queue(Cookie::forget('cart_id'));

    //     return response()->json([
    //         'message' => 'User registered successfully! Cart updated and login credentials sent to email.',
    //         'password' => $randomPassword,
    //         'token' => $token,
    //         'user' => $user
    //     ], 201);
    // }

    /**
     * Fetch All Users with Search & Role Filter (Admin Only)
     */
    public function fetchUsers(Request $request)
    {
        try {
            // Ensure the user is an admin
            $admin = Auth::user();
            if ($admin->role !== 'admin') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Set default limit and offset
            $limit = $request->input('limit', 10); // Default limit is 10
            $offset = $request->input('offset', 0); // Default offset is 0

            // Query Users
            $query = User::query();

            // Search by User Name
            if ($request->has('user_name')) {
                $query->where('name', 'like', '%' . $request->user_name . '%');
            }

            // Filter by Role
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Get Filtered Users with Pagination
            $users = $query->offset($offset)->limit($limit)->get();

            // Get Total Users Count (for pagination)
            $totalUsers = $query->count();

            // Return Response
            return response()->json([
                'success' => true,
                'message' => 'Users fetched successfully!',
                'total_users' => $totalUsers,
                'data' => $users->makeHidden(['email_verified_at', 'otp', 'expires_at', 'created_at', 'updated_at']),
            ], 200);

        } catch (\Exception $e) {
            // Handle Errors
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch total no of orders, brand, category and products
     */
    public function record_count()
    {
        try {
            $total_order    = OrderModel::count();
            $total_brand    = BrandModel::count();
            $total_category = CategoryModel::count();
            $total_product  = ProductModel::count();

            // Sales by order status
            $statusCounts = OrderModel::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

            // Total sales amount where status is completed
            $total_sales = OrderModel::where('status', 'completed')->sum('total_amount');

            // Last 5 orders with user name
            $last_5_orders = OrderModel::with('user:id,name')
                    ->select('user_id', 'total_amount', 'status')
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(function ($order) {
                        return [
                            'user_name' => optional($order->user)->name,
                            'amount'    => $order->total_amount,
                            'status'    => $order->status,
                        ];
                    });

            // Monthly sales for current year
            $currentYear = Carbon::now()->year;

            $monthly_sales = OrderModel::selectRaw('MONTH(created_at) as month, SUM(total_amount) as total')
                    ->whereYear('created_at', $currentYear)
                    ->where('status', 'completed')
                    ->groupBy(DB::raw('MONTH(created_at)'))
                    ->pluck('total', 'month')
                    ->toArray();

            // Convert month numbers to names
            $monthNames = [];
            foreach (range(1, 12) as $m) {
            $monthName = Carbon::create()->month($m)->format('F');
            $monthNames[$monthName] = array_key_exists($m, $monthly_sales) ? round($monthly_sales[$m], 2) : 0.00;
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Record counts fetched successfully!',
                'data' => [
                    'total_orders'   => $total_order,
                    'total_brands'   => $total_brand,
                    'total_categories' => $total_category,
                    'total_products' => $total_product,
                ],
                'order_status_counts' => [
                    'pending'   => $statusCounts['pending'] ?? 0,
                    'completed' => $statusCounts['completed'] ?? 0,
                    'cancelled' => $statusCounts['cancelled'] ?? 0,
                    'refunded'  => $statusCounts['refunded'] ?? 0,
                ],
                'total_sales'     => round($total_sales, 2),
                'recent_orders'   => $last_5_orders,
                'year_records'    => $monthNames,
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching record counts.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        $newPassword = $this->generateRandomPassword();

        $user->password = bcrypt($newPassword);
        $user->save();

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $newPassword));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email. Try again later.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new password has been sent to your email address.',
            'data' => $newPassword
        ]);
    }

    private function generateRandomPassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
        return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
    }

    public function switchUser(Request $request)
    {
        // Validate the input data
        $validator = \Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',  // Ensure the user_id exists in the users table
            'role' => 'required|string|in:customer,admin,manager,dealer,architect',  // Only valid roles
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Get the validated data
        $userId = $request->input('user_id');
        $role = $request->input('role');

        try {
            // Find the user by user_id
            $user = User::findOrFail($userId);

            // Update the user's role
            $user->role = $role;
            //$user->selected_type = null;
            $user->save();  // Save the updated user

            // Return a success response
            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully!',
                'data' => $user
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // User not found
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            // General error (e.g., database issues)
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    // Admin-only hard delete (no soft deletes used)
    public function deleteUser(Request $request, $id)
    {
        $authUser = $request->user();

        if (($authUser->role ?? null) !== 'admin') {
            return response()->json([
                'code' => 403, 'success' => false,
                'message' => 'Only admins can delete users.',
                'data' => [],
            ], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'code' => 404, 'success' => false,
                'message' => 'User not found.',
                'data' => [],
            ], 404);
        }

        try {
            // Revoke Sanctum tokens
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            // Hard delete permanently (no soft deletes)
            $user->forceDelete();

            return response()->json([
                'code' => 200, 'success' => true,
                'message' => 'User permanently deleted.',
                'data' => [],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500, 'success' => false,
                'message' => 'Failed to delete user.',
                'data' => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    public function getProfile(Request $request, int $id)
    {
        // Eager-load everything we need while selecting safe columns
        $user = User::select('id','name','email','mobile','role','selected_type','gstin','created_at')
            ->with([
                'addresses' => function ($q) {
                    $q->select(
                        'id','user_id','name','contact_no',
                        'address_line1','address_line2','city','state',
                        'postal_code','country','is_default','gst_no'
                    );
                },
                'orders' => function ($q) {
                    $q->select(
                        'id','user_id','total_amount','status',
                        'payment_status','shipping_address','razorpay_order_id',
                        'created_at'
                    )
                    ->with([
                        'items' => function ($iq) {
                            $iq->select('id','order_id','product_id','variant_id','quantity','price')
                               ->with([
                                   'product' => function ($pq) {
                                       // adjust columns to your ProductModel
                                       $pq->select('id','name','slug');
                                   },
                                   'variant' => function ($vq) {
                                       // adjust columns to your ProductVariantModel
                                       $vq->select('id','product_id','variant_value','selling_price','regular_price');
                                   },
                               ]);
                        },
                        'payments' => function ($pq) {
                            // adjust columns to your PaymentModel table
                            $pq->select('id','order_id','amount','status','pg_reference_no','created_at');
                        },
                    ])
                    ->orderByDesc('id');
                },
            ])
            ->find($id);

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'User not found.',
                'data'    => [],
            ], 404);
        }

        // Shape the response
        $payload = [
            'user'      => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'mobile'        => $user->mobile,
                'role'          => $user->role,
                'selected_type' => $user->selected_type,
                'gstin'         => $user->gstin,
                'joined_at'     => $user->created_at,
            ],
            'addresses' => $user->addresses, // already selected fields
            'orders'    => $user->orders->map(function ($o) {
                return [
                    'id'              => $o->id,
                    'total_amount'    => (float) $o->total_amount,
                    'status'          => $o->status,
                    'payment_status'  => $o->payment_status,
                    'shipping_address'=> $o->shipping_address,
                    'razorpay_order_id' => $o->razorpay_order_id,
                    'created_at'      => $o->created_at,
                    'items'           => $o->items->map(function ($it) {
                        return [
                            'id'         => $it->id,
                            'product_id' => $it->product_id,
                            'variant_id' => $it->variant_id,
                            'quantity'   => (int) $it->quantity,
                            'price'      => (float) $it->price,
                            'subtotal'   => (float) $it->price * (int) $it->quantity,
                            'product'    => $it->product ? [
                                'id'   => $it->product->id,
                                'name' => $it->product->name,
                                'slug' => $it->product->slug,
                            ] : null,
                            'variant'    => $it->variant ? [
                                'id'            => $it->variant->id,
                                'variant_value' => $it->variant->variant_value,
                                'selling_price' => (float) $it->variant->selling_price,
                                'regular_price' => (float) $it->variant->regular_price,
                            ] : null,
                        ];
                    }),
                    'payments'        => $o->payments->map(function ($p) {
                        return [
                            'id'             => $p->id,
                            'amount'         => (float) $p->amount,
                            'status'         => $p->status,
                            'pg_reference_no'=> $p->pg_reference_no,
                            'created_at'     => $p->created_at,
                        ];
                    }),
                ];
            }),
        ];

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Profile fetched successfully!',
            'data'    => $payload,
        ], 200);
    }
}
