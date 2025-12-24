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
use App\Http\Controllers\ShiprocketController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\InvoiceController;



Route::post('/register', [AuthController::class, 'register']); // Register (normal or Google)
Route::post('/login/{otp?}', [AuthController::class, 'login']); // Login (normal, OTP, or Google)
Route::post('/generate-otp', [AuthController::class, 'generate_otp']); // Generate OTP for mobile login

Route::post('/make_user', [UserController::class, 'guest_register']);
Route::post('/forgot_password', [UserController::class, 'forgotPassword']);

Route::post('/request-otp', [AuthController::class, 'request_otp']);
Route::post('/verify-otp', [AuthController::class, 'verify_otp']);
Route::post('/delivery/shipping-cost', [DelhiveryServiceController::class, 'getShippingCost']);
Route::post('/delivery/track', [DelhiveryServiceController::class, 'trackShipments']);

// Route::middleware(['cors'])->group(function () {
    Route::prefix('cart')->group(function () {
        Route::post('/fetch', [CartController::class, 'index']);             // Get all cart items for a user
        Route::post('/add', [CartController::class, 'store']);         // Add an item to the cart
        Route::post('/update/{id}', [CartController::class, 'update']); // Update an item in the cart
        Route::delete('/remove/{id}', [CartController::class, 'destroy']);// Remove an item from the cart
        Route::post('/cart-from-quotation', [CartController::class, 'createCartFromQuotation']);

    });
// });

Route::prefix('products')->group(function () {
    Route::post('/get_products/{id?}', [ProductController::class, 'index']);          // List all products
    Route::post('/get_models', [ProductController::class, 'getProductModels']); 
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
        Route::get('/fetch_order_detail/{id}', [OrderController::class, 'fetchOrderDetails']); //Fetch order detail for admin
        Route::post('/status/{id}/update', [InvoiceController::class, 'updateOrderStatus']); // updated order status

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
            Route::get('/deliveryone/test', [DelhiveryServiceController::class, 'test']);
            Route::post('/make_order', [DelhiveryServiceController::class, 'createOrder']); // Raw data passing
            // manual creation through checking shipment detail before create(admin)
            Route::post('/check_shipment', [DelhiveryServiceController::class, 'checkShipment']); // check shipment all datas
            Route::post('/punch_shipment', [DelhiveryServiceController::class, 'punchShipment']); // after check punch
            Route::post('/create_ship_by_order', [DelhiveryServiceController::class, 'createShipByOrder']); // create shipment and save in db

            // All checks
            Route::post('/fetch_shipments/{id?}', [DelhiveryServiceController::class, 'fetchShipment']); // fetch from db
            // Route::post('/delivery/track', [DelhiveryServiceController::class, 'trackShipments']);
            Route::get('/pincode-serviceability', [DelhiveryServiceController::class, 'checkPincodeServiceability']);
            // Route::post('/delivery/shipping-cost', [DelhiveryServiceController::class, 'getShippingCost']);
            Route::post('/expected-time', [DelhiveryServiceController::class, 'getTat']);

            // optional separate setup api
            // Route::post('/auto_ship_setup/{orderId}', [DelhiveryServiceController::class, 'autoShipSetup']);

            // pickup crud
            Route::post('/pickup/create', [DelhiveryServiceController::class, 'createPickupLocation']); // for create
            Route::post('/pickup/fetch/{id?}', [DelhiveryServiceController::class, 'fetchPickupLocations']); // for fetch
            Route::delete('/pickup/delete/{id?}', [DelhiveryServiceController::class, 'deletePickupLocation']); // for fetch

        });
        // Ship Rocket
        Route::prefix('shiprocket')->group(function () {
            Route::post('/create-shipment', [ShiprocketController::class, 'createShipment']);
            Route::post('/punch-order/{order_id}', [ShiprocketController::class, 'punchOrderById']); // Puunch by order
            Route::post('/punch-by-payload', [ShiprocketController::class, 'punchByPayload']); // Punch by payload
            Route::post('/cancel', [ShiprocketController::class, 'cancelOrder']);  // Cancel order
            Route::post('/rates', [ShiprocketController::class, 'getShippingRates']); // get shipping cost
            Route::post('/tat', [ShiprocketController::class, 'getTat']); // get shipping cost            
            Route::post('/track', [ShiprocketController::class, 'trackShipment']);  // track 
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

    // Order Routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);            // List all orders for a user
        Route::get('/{id}', [OrderController::class, 'show']);         // Get details of a single order
        Route::post('/', [OrderController::class, 'store']);           // Create a new order
        Route::delete('/{id}', [OrderController::class, 'delete']);           // Create a new order
        Route::post('/{id}/update-status', [OrderController::class, 'statusUpdate']);
    });

    // Setting Routes
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index']);          // Retrieve all settings (Admin only)
        Route::put('/{key}', [SettingController::class, 'update']);    // Update a specific setting (Admin)
    });

    // Coupon Routes
    Route::prefix('coupons')->group(function () {
        Route::post('/fetch', [CouponController::class, 'fetchAll']);           // List all coupons
        Route::post('/create', [CouponController::class, 'create']);          // Add a new coupon
        Route::post('/update/{id}', [CouponController::class, 'update']);          // Add a new coupon
        Route::delete('/delete/{id}', [CouponController::class, 'delete']);  // Delete a coupon
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
Route::post('/razorpay/callback', [RazorpayController::class, 'handleCallback']);
