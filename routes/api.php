<?php

use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VendorProfileController;
use App\Http\Controllers\VendorProductController;
use App\Http\Controllers\VendorBrowseController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PortfolioController;
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
Route::get('/vendors', [VendorBrowseController::class, 'index']); // discovery: Home / Explore / Filters
Route::get('/vendors/{vendorId}/products/search', [VendorProductController::class, 'searchVendorProducts']);
Route::get('/vendors/{vendorId}/reviews', [ReviewController::class, 'vendorReviews']);
Route::get('/vendors/{vendorId}/portfolio', [PortfolioController::class, 'vendorPortfolio']);

// ─────────────────────────────────────────────
// User Protected Routes — auth:sanctum
// ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'active'])->group(function () {

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
Route::middleware(['auth:vendors', 'active'])->group(function () {

    // Profile
    Route::get('/vendor/profile', [VendorProfileController::class, 'show']);
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

    // Portfolio (Vendor)
    Route::get('/vendor/portfolio', [PortfolioController::class, 'index']);
    Route::post('/vendor/portfolio', [PortfolioController::class, 'store']);
    Route::get('/vendor/portfolio/{id}', [PortfolioController::class, 'show']);      // detail: primary + other images
    Route::post('/vendor/portfolio/{id}', [PortfolioController::class, 'update']);   // POST (form-data) to add/replace images
    Route::delete('/vendor/portfolio/{id}', [PortfolioController::class, 'destroy']);

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

// Public — no auth. Throttled to 5 attempts/min per IP against brute force.
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:admins')->group(function () {

    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

    // ── View + KYC — super_admin AND support (read-only + approve/reject KYC) ──
    Route::middleware('role:super_admin,support')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/admin/vendors', [AdminController::class, 'vendors']);                    // ?search= ?is_active=
        Route::get('/admin/vendors/pending', [AdminController::class, 'pendingVendors']);
        Route::get('/admin/vendors/{id}', [AdminController::class, 'vendorDetail']);
        Route::post('/admin/vendors/{id}/approve', [AdminController::class, 'approveVendor']);
        Route::post('/admin/vendors/{id}/reject', [AdminController::class, 'rejectVendor']);

        Route::get('/admin/users', [AdminController::class, 'users']);                        // ?search=
        Route::get('/admin/users/{id}', [AdminController::class, 'userDetail']);              // user + their bookings

        Route::get('/admin/bookings', [AdminController::class, 'bookings']);                  // ?status= ?vendor_id= ?user_id=
        Route::get('/admin/bookings/{id}', [AdminController::class, 'bookingDetail']);

        Route::get('/admin/reviews', [AdminController::class, 'reviews']);                    // ?vendor_id=
    });

    // ── Sensitive actions — super_admin ONLY (ban, money, disputes, audit, admins) ──
    Route::middleware('role:super_admin')->group(function () {
        // Vendor account state
        Route::post('/admin/vendors/{id}/ban', [AdminController::class, 'banVendor']);           // immediate: cancel + refund all
        Route::post('/admin/vendors/{id}/ban-gradual', [AdminController::class, 'banVendorGradual']); // winding-down
        Route::post('/admin/vendors/{id}/unban', [AdminController::class, 'unbanVendor']);

        // User account state
        Route::post('/admin/users/{id}/toggle', [AdminController::class, 'toggleUser']);         // ban/unban user

        // Dispute resolution — cancel + refund a single booking
        Route::post('/admin/bookings/{id}/cancel', [AdminController::class, 'cancelBooking']);

        // Content moderation — remove abusive review / product / portfolio item
        Route::delete('/admin/reviews/{id}', [AdminController::class, 'deleteReview']);
        Route::delete('/admin/products/{id}', [AdminController::class, 'deleteProduct']);
        Route::delete('/admin/portfolio/{id}', [AdminController::class, 'deletePortfolioItem']);

        // Money oversight
        Route::get('/admin/vendors/{id}/wallet', [AdminController::class, 'vendorWallet']); // a vendor's earnings/ledger
        Route::get('/admin/payments', [AdminController::class, 'payments']);
        Route::get('/admin/stats/financial', [AdminController::class, 'financials']); // income/profit report

        // Refunds owed to customers (pay out manually, then mark paid)
        Route::get('/admin/refunds-due', [AdminController::class, 'refundsDue']);
        Route::post('/admin/refunds/{id}/mark-paid', [AdminController::class, 'markRefundPaid']);

        // Vendor withdrawal payouts
        Route::get('/admin/withdrawals', [AdminController::class, 'withdrawals']);          // ?unpaid=1
        Route::post('/admin/withdrawals/{id}/mark-paid', [AdminController::class, 'markWithdrawalPaid']);

        Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs']);

        // Manage admin accounts (hire/remove support)
        Route::get('/admin/admins', [AdminManagementController::class, 'index']);
        Route::post('/admin/admins', [AdminManagementController::class, 'store']);
        Route::delete('/admin/admins/{id}', [AdminManagementController::class, 'destroy']);
    });
});
