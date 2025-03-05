<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/register', [UserController::class, 'register']);
Route::post('/login/{otp?}', [AuthController::class, 'login']);
Route::post('/otp', [AuthController::class, 'generate_otp']);
Route::post('/make_user', [UserController::class, 'guest_register']);

Route::prefix('cart')->group(function () {
    Route::post('/fetch', [CartController::class, 'index']);             // Get all cart items for a user
    Route::post('/add', [CartController::class, 'store']);         // Add an item to the cart
    Route::post('/update/{id}', [CartController::class, 'update']); // Update an item in the cart
    Route::delete('/remove/{id}', [CartController::class, 'destroy']);// Remove an item from the cart
});

Route::prefix('products')->group(function () {
    Route::post('/get_products/{id?}', [ProductController::class, 'index']);          // List all products
    Route::get('/product_slug/{slug}', [ProductController::class, 'show']);     // Get details of a single product
    Route::get('/unique_variant', [ProductController::class, 'unique_type']);     // Get details of a single product
});

// Category Routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);         // List all categories
    Route::get('/{id}', [CategoryController::class, 'show']);      // Get details of a single category
});

// Brand Routes
Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index']);            // List all brands
    Route::get('/{id}', [BrandController::class, 'show']);         // Get details of a single brand
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // User Routes
    Route::prefix('users')->group(function () {
        Route::post('/register', [UserController::class, 'register']); // Register a new user
        // Route::post('/login', [UserController::class, 'login']);       // Log in a user
        Route::get('/profile', [UserController::class, 'profile']);    // Get logged-in user details
        Route::post('/update', [UserController::class, 'update']);     // Update user details
        // Route::post('/logout', [UserController::class, 'logout']);     // Log out the user
        });
    
    // for vendors
    Route::middleware('role:vendor')->group(function () {
        
        // Quotation Routes
        Route::prefix('quotation')->group(function () {
            Route::get('/', [QuotationController::class, 'index']);            // List all orders for a user
            Route::get('/{id}', [QuotationController::class, 'show']);         // Get details of a single order
            Route::post('/', [QuotationController::class, 'store']);           // Create a new order
        });
    });

    Route::middleware('role:admin')->group(function () {
        
        // Product Routes
        Route::prefix('products')->group(function () {
            Route::post('/register', [ProductController::class, 'store']);         // Add a new product (Admin only)
            Route::put('/{id}', [ProductController::class, 'update']);     // Update a product (Admin only)
            Route::delete('/{id}', [ProductController::class, 'destroy']); // Delete a product (Admin only)
            Route::post('/import', [ProductController::class, 'importProductsFromCsv']);
        });
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
