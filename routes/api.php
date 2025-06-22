<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController; 
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Mail;

 
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


 
Route::get("/",[WelcomeController::class,'welcome']);
Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']); 
    Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
    Route::post('/remove-avatar', [AuthController::class, 'removeAvatar']);
    Route::post('/update',[AuthController::class,'updateProfile']);
    Route::post('/google',[GoogleAuthController::class,'googleSign']);  
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']); 
    }); 
    Route::middleware(['auth:api', 'verified'])->group(function () {
    // Verified-only routes
    });
});
 

Route::get('/email/verify', [VerificationController::class, 'verify'])->name('verification.verify');
Route::post('/email/resend', [VerificationController::class, 'resend']); 
//Send reset password link and reset password 
Route::post('/password/email', [PasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])
    ->name('password.reset');  


//Addresses
Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'addresses'
], function ($router) {
    Route::get('/', [AddressController::class, 'index']);
    Route::post('/', [AddressController::class, 'store']);
    Route::post('/update/{id}', [AddressController::class, 'update']);
    Route::post('/delete/{id}', [AddressController::class, 'destroy']);
    Route::post('/{id}/set-default', [AddressController::class, 'setDefault']);
});




// Endpoints for admin to use and manage users
Route::group([
    'middleware' => ['auth:api', 'role:admin'],
    'prefix' => 'admin/users'
], function () {
    Route::get('/', [AdminUserController::class, 'index']);
    Route::post('/', [AdminUserController::class, 'store']);
    Route::get('/{id}', [AdminUserController::class, 'show']);
    Route::post('/update/{id}', [AdminUserController::class, 'update']);
    Route::delete('/{id}', [AdminUserController::class, 'destroy']);
});
 
//Categories
Route::group([
    'prefix' => 'categories'
], function ($router) {
    // Public routes (no auth required)
    Route::get('/', [CategoryController::class, 'index']); 
    Route::get('/{id}', [CategoryController::class, 'show']);
    
    // Protected admin-only routes
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::post('/update/{id}', [CategoryController::class, 'update']);
        Route::post('/delete/{id}', [CategoryController::class, 'destroy']);
        Route::post('/{id}/icon', [CategoryController::class, 'uploadIconImage']);
        Route::delete('/{id}/icon', [CategoryController::class, 'deleteIconImage']);
    });
});

//Products
Route::get('/products/get-by-ids', [ProductController::class,'getProductsByIds']);
Route::group(['prefix' => 'products'], function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']); 
    Route::get('/{id}/related', [ProductController::class, 'getRelatedProducts']);

    

    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::post('/update/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        
        // Image upload routes
        Route::post('/{id}/main-image', [ProductController::class, 'uploadMainImage']);
        Route::post('/{id}/additional-images', [ProductController::class, 'uploadAdditionalImages']);
        Route::delete('/{productId}/additional-images/{imageId}', [ProductController::class, 'deleteAdditionalImage']);
    });
}); 

Route::group(['prefix' => 'tables'], function () {
    Route::get('/', [TableController::class, 'index']);
    Route::get('/{id}', [TableController::class, 'show']);
    
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::post('/', [TableController::class, 'store']);
        Route::post('/update/{id}', [TableController::class, 'update']);
        Route::delete('/{id}', [TableController::class, 'destroy']);
    });
});  




Route::group([ 
    'prefix'=>'orders'
],function(){
    
    // Customer routes
    Route::get('/', [OrderController::class, 'index'])->middleware('role:customer');
    Route::post('/', [OrderController::class, 'store'])->middleware('role:customer');
    Route::get('/{order}', [OrderController::class, 'show']); 
    
    Route::post('/{order}/cancel', [OrderController::class, 'customerCancelOrder']) 
    ->name('orders.customer-cancel');
    
    Route::middleware(['role:admin'])->group(function () { 
        // Admin routes
        Route::get('/admin/orders', [OrderController::class, 'index']); 
        Route::get('/admin/orders/{order}', [OrderController::class, 'show']); 
        Route::post('/admin/orders/{order}', [OrderController::class, 'update']); 

    });

});
  
 
Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']);

Route::middleware('auth:api')->group(function () {
    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::patch('/{payment}', [PaymentController::class, 'update'])->middleware('role:admin');
    });
});
 
 

// Admin settings
Route::group([
    'prefix' => 'settings'
], function () { 
    Route::get('/', [SettingsController::class, 'index']);
    Route::group([
        'middleware' => ['auth:api', 'role:admin'],
    ], function(){
        Route::post('/', [SettingsController::class, 'update']);
        Route::post('/payment-gateways/{id}/toggle', [SettingsController::class, 'togglePaymentGateway']);
        Route::post('/upload-company-logo', [SettingsController::class, 'uploadCompanyLogo']);
        Route::post('/upload-gateway-logo/{gatewayId}', [SettingsController::class, 'uploadGatewayLogo']);

    });
});
 

Route::prefix('admin/dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/sales-chart', [DashboardController::class, 'salesChart']);
    Route::get('/top-selling', [DashboardController::class, 'topSellingItems']);
    Route::get('/recent-orders', [DashboardController::class, 'recentOrders']);
});

Route::get('/test-email', function() {
    try {
        Mail::raw('Test email', function($message) {
            $message->to('emma234eze@gmail.com')->subject('Test Email');
        });
        return 'Email sent';
    } catch (\Exception $e) {
        return 'Error: '.$e->getMessage();
    }
});