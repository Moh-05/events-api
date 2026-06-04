<?php

use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VendorProfileController;
use App\Http\Controllers\VendorProductController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public Routes — no auth required
// ─────────────────────────────────────────────

// User Auth
Route::post('/send-otp', [UserAuthController::class, 'sendOtp']);
Route::post('/verify-otp', [UserAuthController::class, 'verifyOtp']);
Route::post('/complete-registration', [UserAuthController::class, 'completeRegistration']);

// Vendor Auth
Route::post('/vendor/send-otp', [VendorAuthController::class, 'sendOtp']);
Route::post('/vendor/verify-otp', [VendorAuthController::class, 'verifyOtp']);
Route::post('/vendor/complete-registration', [VendorAuthController::class, 'completeRegistration']);

// Public browse
Route::get('/vendors/{vendorId}/products/search', [VendorProductController::class, 'searchVendorProducts']);
Route::get('/vendors/{vendorId}/reviews', [ReviewController::class, 'vendorReviews']);

// ─────────────────────────────────────────────
// User Protected Routes — auth:sanctum
// ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Profile
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::post('/profile', [UserProfileController::class, 'update']);
    Route::delete('/profile/image', [UserProfileController::class, 'deleteImage']);

    // Bookings (User)
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}', [BookingController::class, 'update']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Payments
    Route::post('/payments/verify', [PaymentController::class, 'verify']);
});


// ─────────────────────────────────────────────
// Vendor Protected Routes — auth:vendors
// ─────────────────────────────────────────────
Route::middleware('auth:vendors')->group(function () {

    // Profile
    Route::get('/vendor/profile', [VendorProfileController::class, 'show']);
    Route::post('/vendor/profile/type', [VendorProfileController::class, 'setType']);
    Route::post('/vendor/profile', [VendorProfileController::class, 'update']);
    Route::delete('/vendor/profile/image', [VendorProfileController::class, 'deleteImage']);

    // Products
    Route::get('/vendor/products', [VendorProductController::class, 'getVendorProducts']);
    Route::post('/vendor/products', [VendorProductController::class, 'store']);
    Route::get('/vendor/products/{id}', [VendorProductController::class, 'show']);
    Route::post('/vendor/products/{id}', [VendorProductController::class, 'update']);
    Route::delete('/vendor/products/{id}', [VendorProductController::class, 'destroy']);

    // Bookings (Vendor)
    Route::get('/vendor/bookings', [BookingController::class, 'index']);
    Route::post('/vendor/bookings/{id}/approve', [BookingController::class, 'approve']);
    Route::post('/vendor/bookings/{id}/decline', [BookingController::class, 'decline']);
    Route::post('/vendor/bookings/{id}/complete', [BookingController::class, 'complete']);
    Route::get('/vendor/booked-dates', [BookingController::class, 'bookedDates']);
});
