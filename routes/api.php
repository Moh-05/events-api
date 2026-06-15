<?php

use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VendorProfileController;
use App\Http\Controllers\VendorProductController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\WalletController;
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

    // Device FCM token (sent by the Flutter app)
    Route::post('/fcm-token', [UserProfileController::class, 'updateFcmToken']);

    // Bookings (User)
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}', [BookingController::class, 'update']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Payments
    Route::post('/payments/verify', [PaymentController::class, 'verify']);

    // Notifications (inbox / bell icon)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
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

    // Device FCM token (sent by the Flutter app)
    Route::post('/vendor/fcm-token', [VendorProfileController::class, 'updateFcmToken']);

    // Products
    Route::get('/vendor/products', [VendorProductController::class, 'getVendorProducts']);
    Route::post('/vendor/products', [VendorProductController::class, 'store']);
    Route::get('/vendor/products/best-sellers', [VendorProductController::class, 'bestSellers']); // top products by orders
    Route::get('/vendor/products/low-stock', [VendorProductController::class, 'lowStock']);       // inventory alerts
    Route::get('/vendor/products/{id}', [VendorProductController::class, 'show']);
    Route::post('/vendor/products/{id}', [VendorProductController::class, 'update']);
    Route::delete('/vendor/products/{id}', [VendorProductController::class, 'destroy']);

    // Bookings (Vendor)
    Route::get('/vendor/bookings', [BookingController::class, 'index']);
    Route::get('/vendor/bookings/recent-requests', [BookingController::class, 'recentRequests']);
    Route::get('/vendor/bookings/recent-orders', [BookingController::class, 'recentOrders']);     // order vendors
    Route::get('/vendor/bookings/upcoming-events', [BookingController::class, 'upcomingEvents']);
    Route::get('/vendor/bookings/{id}', [BookingController::class, 'show']);                       // full booking/order detail
    Route::post('/vendor/bookings/{id}/approve', [BookingController::class, 'approve']);
    Route::post('/vendor/bookings/{id}/decline', [BookingController::class, 'decline']);
    Route::post('/vendor/bookings/{id}/complete', [BookingController::class, 'complete']);
    Route::get('/vendor/booked-dates', [BookingController::class, 'bookedDates']);

    // Reviews (Vendor)
    Route::get('/vendor/reviews', [ReviewController::class, 'myReviews']);
    Route::get('/vendor/reviews/summary', [ReviewController::class, 'summary']); // star breakdown, positive %, trend

    // Stats (Vendor)
    Route::get('/vendor/stats', [BookingController::class, 'stats']);
    Route::get('/vendor/earnings', [BookingController::class, 'earnings']); // month-over-month growth

    // Wallet (Vendor)
    Route::get('/vendor/wallet', [WalletController::class, 'show']);       // balances + transactions
    Route::post('/vendor/withdraw', [WalletController::class, 'withdraw']); // withdraw all available

    // Notifications (inbox / bell icon)
    Route::get('/vendor/notifications', [NotificationController::class, 'index']);
    Route::post('/vendor/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/vendor/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
});

// ─────────────────────────────────────────────
// Admin Routes
// ─────────────────────────────────────────────

// Public — no auth
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// All admins (super_admin + support)
Route::middleware('auth:admins')->group(function () {

    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

    // KYC — both roles
    Route::get('/admin/vendors/pending', [AdminController::class, 'pendingVendors']);
    Route::post('/admin/vendors/{id}/approve', [AdminController::class, 'approveVendor']);
    Route::post('/admin/vendors/{id}/reject', [AdminController::class, 'rejectVendor']);

    // Super admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/admin/vendors', [AdminController::class, 'vendors']);
        Route::post('/admin/vendors/{id}/toggle', [AdminController::class, 'toggleVendor']);
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::get('/admin/bookings', [AdminController::class, 'bookings']);
        Route::get('/admin/payments', [AdminController::class, 'payments']);
    });
});
