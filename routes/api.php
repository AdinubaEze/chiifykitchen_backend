<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WelcomeController;  
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