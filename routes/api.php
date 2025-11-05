<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UsersDiscountController;
use App\Http\Controllers\DelhiveryServiceController;
use App\Http\Controllers\UploadController;


// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Route::group(['middleware' => ['cors']], function () {
//     Route::post('/cart/set-cookie', function (Request $request) {
//         $cartId = $request->input('cart_id');

//         if (!$cartId) {
//             return response()->json(['error' => 'Cart ID is required'], 400);
//         }

//         $cookie = cookie('cart_id', $cartId, 60 * 24 * 365) // Expire in 1 year
//             ->withHttpOnly(true)  // Prevent access from JavaScript
//             ->withSecure(true)    // Only send over HTTPS
//             ->withSameSite('None'); // Required for cross-domain cookies

//         return response()->json(['success' => true])->cookie($cookie);
//     });
// });

Route::post('/register', [UserController::class, 'register']);
Route::post('/login/{otp?}', [AuthController::class, 'login']); // Log in a user
Route::post('/otp', [AuthController::class, 'generate_otp']);
Route::post('/make_user', [UserController::class, 'guest_register']);
Route::post('/forgot_password', [UserController::class, 'forgotPassword']);

// Route::middleware(['cors'])->group(function () {
    Route::prefix('cart')->group(function () {
        Route::post('/fetch', [CartController::class, 'index']);             // Get all cart items for a user
        Route::post('/add', [CartController::class, 'store']);         // Add an item to the cart
        Route::post('/update/{id}', [CartController::class, 'update']); // Update an item in the cart
        Route::delete('/remove/{id}', [CartController::class, 'destroy']);// Remove an item from the cart
    });
// });

Route::prefix('products')->group(function () {
    Route::post('/get_products/{id?}', [ProductController::class, 'index']);          // List all products
    Route::get('/product_slug/{slug}', [ProductController::class, 'show']);     // Get details of a single product
    Route::get('/unique_variant', [ProductController::class, 'unique_type']);     // Get details of a single product
});

// Category Routes
Route::prefix('categories')->group(function () {
    Route::post('/fetch', [CategoryController::class, 'index']);         // List all categories
    Route::post('/fetch/{id}', [CategoryController::class, 'show']);      // Get details of a single category
});

// Brand Routes
Route::prefix('brands')->group(function () {
    Route::post('/fetch', [BrandController::class, 'index']);            // List all brands
    Route::post('/fetch/{id}', [BrandController::class, 'show']);         // Get details of a single brand
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);     // Log out the user
    Route::get('/profile', [UserController::class, 'profile']);    // Get logged-in user details
    
    // User Routes
    Route::prefix('users')->group(function () {
        Route::post('/register', [UserController::class, 'register']); // Register a new user
        Route::post('/update', [UserController::class, 'update']);     // Update user details
    });
    
    // for architect and dealer
    Route::middleware('role:architect,dealer')->group(function () {    
        // Quotation Routes
        Route::prefix('quotation')->group(function () {
            Route::get('/fetch', [QuotationController::class, 'index']);            // List all orders for a user
            Route::get('/{id}', [QuotationController::class, 'show']);         // Get details of a single order
            Route::post('/', [QuotationController::class, 'store']);           // Create a new order
            Route::delete('/{id}', [QuotationController::class, 'delete']);           // Create a new quotation
        });
    });

    Route::middleware('role:admin')->group(function () {
        
        // Product Routes
        Route::prefix('products')->group(function () {
            Route::post('/register', [ProductController::class, 'store']);         // Add a new product (Admin only)
            Route::post('/{productId}/variants', [ProductController::class, 'addVariant']); // create variant(s)
            Route::post('/{variant_id}/banners', [ProductController::class, 'uploadBanner']); // upload banner by variant id
            Route::post('/{variant}/photos', [ProductController::class, 'uploadPhotos']); // upload banner by variant id
            Route::delete('/variants/{variantId}', [ProductController::class, 'deleteVariant']); // Delete variant by id
            Route::delete('/variants/{variant}/photos/{upload}',  [UploadController::class, 'deleteVariantPhoto']);
            Route::delete('/variants/{variant}/banners/{upload}', [UploadController::class, 'deleteVariantBanner']);
            Route::put('/{id}', [ProductController::class, 'update']);     // Update a product (Admin only)
            Route::delete('/{id}', [ProductController::class, 'destroy']); // Delete a product (Admin only)
            Route::post('/import', [ProductController::class, 'importProductsFromCsv']);
            Route::get('/pull_images', [ProductController::class, 'mapVariantImagesToPhotoId']);
            Route::post('/get_admin/{id?}', [ProductController::class, 'index_admin']);            
            Route::post('/{productId}/features', [ProductController::class, 'addFeature']);
            Route::delete('/features/{id}', [ProductController::class, 'deleteFeature']);
        });
        Route::get('/get_profile/{id?}', [UserController::class, 'getProfile']);
        // for all orders
        Route::post('/fetch_all', [OrderController::class, 'fetchOrders']); // List all orders for admin

        // for all users
        Route::post('/all_users', [UserController::class, 'fetchUsers']); // List all users for admin
        Route::delete('/delete/{id}', [UserController::class, 'deleteUser']);

        Route::prefix('users')->group(function () {
            Route::get('/admin_dashboard', [UserController::class, 'record_count']); //get product count
        });

        // Users Discount Routes
        Route::prefix('discount')->group(function () {
            Route::post('/add', [UsersDiscountController::class, 'store']); // Add a new discount record 
            Route::post('/fetch/{id?}', [UsersDiscountController::class, 'fetch']); // Fetch record
            Route::post('/edit/{id}', [UsersDiscountController::class, 'update']); // Update record
            Route::delete('/{id}', [UsersDiscountController::class, 'delete']); // Delete record
        });

        // delhivery
        Route::prefix('delivery')->group(function () {
            Route::post('/make_order', [DelhiveryServiceController::class, 'createOrder']); 
            //Route::post('/track', [DelhiveryServiceController::class, 'trackAllShipments']);
            Route::get('/track', [DelhiveryServiceController::class, 'trackShipments']);
            Route::get('/pincode-serviceability', [DelhiveryServiceController::class, 'checkPincodeServiceability']);
            Route::get('/shipping-cost', [DelhiveryServiceController::class, 'getShippingCost']);
        });

        Route::post('/switch_user', [UserController::class, 'switchUser']);
    });

    // Category Routes
    Route::prefix('categories')->group(function () {
        // Route::get('/', [CategoryController::class, 'index']);         // List all categories
        // Route::get('/{id}', [CategoryController::class, 'show']);      // Get details of a single category
        Route::post('/', [CategoryController::class, 'store']);        // Add a new category (Admin only)
        Route::put('/{id}', [CategoryController::class, 'update']);    // Update a category (Admin only)
        Route::delete('/{id}', [CategoryController::class, 'destroy']);// Delete a category (Admin only)
    });

    // Brand Routes
    Route::prefix('brands')->group(function () {
        // Route::get('/', [BrandController::class, 'index']);            // List all brands
        // Route::get('/{id}', [BrandController::class, 'show']);         // Get details of a single brand
        Route::post('/', [BrandController::class, 'store']);           // Add a new brand (Admin only)
        Route::put('/{id}', [BrandController::class, 'update']);       // Update a brand (Admin only)
        Route::delete('/{id}', [BrandController::class, 'destroy']);   // Delete a brand (Admin only)
    });

    // // Cart Routes
    // Route::prefix('cart')->group(function () {
    //     Route::get('/', [CartController::class, 'index']);             // Get all cart items for a user
    //     Route::post('/add', [CartController::class, 'store']);         // Add an item to the cart
    //     Route::put('/update/{id}', [CartController::class, 'update']); // Update an item in the cart
    //     Route::delete('/remove/{id}', [CartController::class, 'destroy']);// Remove an item from the cart
    // });

    // Order Routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);            // List all orders for a user
        Route::get('/{id}', [OrderController::class, 'show']);         // Get details of a single order
        Route::post('/', [OrderController::class, 'store']);           // Create a new order
        Route::delete('/{id}', [OrderController::class, 'delete']);           // Create a new order
    });

    // Setting Routes
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index']);          // Retrieve all settings (Admin only)
        Route::put('/{key}', [SettingController::class, 'update']);    // Update a specific setting (Admin)
    });

    // Coupon Routes
    Route::prefix('coupons')->group(function () {
        Route::get('/', [CouponController::class, 'index']);           // List all coupons
        Route::post('/', [CouponController::class, 'store']);          // Add a new coupon
        Route::delete('/{id}', [CouponController::class, 'destroy']);  // Delete a coupon
    });

    // Address Routes
    Route::prefix('address')->group(function () {
        Route::get('/', [AddressController::class, 'index']);          // List all addresses for a user
        Route::post('/register', [AddressController::class, 'store']);         // Add a new address
        Route::post('/update/{id}', [AddressController::class, 'update']);     // Update an address
        Route::delete('/{id}', [AddressController::class, 'destroy']); // Delete an address
    });


    // Razorpay Routes
    Route::prefix('razorpay')->group(function () {
        // Route::get('/', [AddressController::class, 'index']);          // List all addresses for a user
        Route::post('/register', [RazorpayController::class, 'createOrder']);         // Add a new address
        Route::get('/payment-status/{paymentId}', [RazorpayController::class, 'fetchPaymentStatus']);
        Route::get('/order-status/{orderId}', [RazorpayController::class, 'fetchOrderStatus']);
    });

    Route::post('/payments', [PaymentController::class, 'store']);
});
