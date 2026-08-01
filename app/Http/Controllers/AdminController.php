<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\VendorProduct;
use App\Models\PortfolioItem;
use App\Models\ContentReport;
use App\Models\WalletTransaction;
use App\Models\AdminAuditLog;
use App\Services\BookingCancellationService;
use App\Services\NotificationService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function __construct(
        private BookingCancellationService $cancellation,
        private WalletService $wallet,
        private NotificationService $notifications,
    ) {
    }

    // ── Dashboard ────────────────────────────────────────────────
    // view — super_admin + support

    public function dashboard()
    {
        // Platform profit = commission on verified payments. The /admin/stats/financial
        // endpoint has the full money breakdown; this is just the headline.
        $profit = fn ($q) => round((float) $q->where('status', 'verified')->sum('commission'), 2);

        return response()->json([
            'status' => 'success',
            'stats'  => [
                'total_users'        => User::count(),
                'total_vendors'      => Vendor::count(),
                'approved_vendors'   => Vendor::where('is_approved', true)->count(),
                'pending_vendors'    => Vendor::where('is_approved', false)->where('is_active', true)->count(),
                'banned_vendors'     => Vendor::where('is_active', false)->count(),
                'total_bookings'     => Booking::count(),
                'active_bookings'    => Booking::whereIn('status', ['pending', 'approved'])->count(),
                'completed_bookings' => Booking::where('status', 'completed')->count(),
                'profit_today'       => $profit(Payment::whereDate('created_at', today())),
                'profit_month'       => $profit(Payment::whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)),
                'profit_all_time'    => $profit(Payment::query()),
            ],
        ]);
    }

    // ── Vendors ──────────────────────────────────────────────────

    // All vendors (paginated, searchable) — view: super_admin + support
    // ?status=kyc_pending|active|winding_down|banned matches the list's tabs.
    // ?is_active=0|1 still works. ?search= matches name / business / phone.
    public function vendors(Request $request)
    {
        $query = Vendor::latest();

        // Account-state tabs — a true PARTITION (each vendor matches exactly
        // one, so the tab counts add up):
        //   kyc_pending  → not yet approved (and not banned)
        //   active       → approved and running
        //   winding_down → banned but finishing existing bookings
        //   banned       → fully blocked (approved or not)
        switch ($request->status) {
            case 'kyc_pending':
                $query->where('is_approved', false)->where('is_active', true);
                break;
            case 'active':
                $query->where('is_active', true)->where('is_approved', true);
                break;
            case 'winding_down':
                $query->where('is_active', false)->where('winding_down', true);
                break;
            case 'banned':
                $query->where('is_active', false)->where('winding_down', false);
                break;
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('business_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%");
            });
        }

        return response()->json([
            'status'  => 'success',
            'vendors' => $query->paginate(20),
        ]);
    }

    // Single vendor detail (for KYC review) — view: super_admin + support
    // Carries bookings_count + reviews_count for the detail panel's stats.
    public function vendorDetail(int $id)
    {
        $vendor = Vendor::with('products')
            ->withCount(['bookings', 'reviews'])
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'vendor' => $vendor]);
    }

    // Pending KYC (paginated) — view: super_admin + support
    // Banned vendors are excluded: there is nothing to review on a blocked
    // account (same rule as the vendors list's kyc_pending tab).
    public function pendingVendors()
    {
        $vendors = Vendor::where('is_approved', false)
            ->where('is_active', true)
            ->latest()
            ->paginate(20);

        return response()->json(['status' => 'success', 'vendors' => $vendors]);
    }

    // Approve KYC — super_admin + support
    public function approveVendor(Request $request, int $id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_approved' => true, 'rejection_reason' => null]);

        AdminAuditLog::record($request->user()->id, 'vendor.approve', 'vendor', $vendor->id);

        return response()->json(['status' => 'success', 'message' => 'Vendor approved']);
    }

    // Reject KYC — super_admin + support
    // NOTE: this is KYC rejection only (is_approved = false). It does NOT ban an
    // already-active vendor — banning is a separate super_admin action (banVendor).
    public function rejectVendor(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $vendor = Vendor::findOrFail($id);
        $vendor->update([
            'is_approved'      => false,
            'rejection_reason' => $request->reason,
        ]);

        AdminAuditLog::record(
            $request->user()->id,
            'vendor.reject',
            'vendor',
            $vendor->id,
            ['reason' => $request->reason]
        );

        return response()->json(['status' => 'success', 'message' => 'Vendor KYC rejected']);
    }

    // Immediate ban — super_admin ONLY.
    // Blocks the vendor now and cancels + refunds every in-flight booking.
    // Use for fraud / urgent cases where the vendor must be stopped at once.
    public function banVendor(Request $request, int $id)
    {
        $request->validate(['reason' => 'sometimes|nullable|string|max:1000']);

        $vendor = Vendor::findOrFail($id);

        if (! $vendor->is_active && ! $vendor->winding_down) {
            return response()->json(['status' => 'error', 'message' => 'Vendor is already banned'], 422);
        }

        $reason = $request->input('reason', 'Banned by admin');

        // Cancel + refund every in-flight booking through the shared service.
        $active = $vendor->bookings()
            ->whereIn('status', ['awaiting_payment', 'pending', 'approved'])
            ->get();

        $cancelled = 0;
        foreach ($active as $booking) {
            if ($this->cancellation->cancelByPlatform($booking, $reason)['cancelled']) {
                $cancelled++;
            }
        }

        $vendor->update(['is_active' => false, 'winding_down' => false]);

        AdminAuditLog::record($request->user()->id, 'vendor.ban', 'vendor', $vendor->id, [
            'mode'               => 'immediate',
            'reason'             => $reason,
            'cancelled_bookings' => $cancelled,
        ]);

        return response()->json([
            'status'             => 'success',
            'account_status'     => 'banned',
            'cancelled_bookings' => $cancelled,
            'message'            => "Vendor banned. {$cancelled} active booking(s) cancelled — refunds are due to the customers.",
        ]);
    }

    // Ban after winding down — super_admin ONLY.
    // The vendor is hidden and can't take new bookings, but keeps access to
    // finish existing ones. The ban finalizes automatically once the last active
    // booking is done. Use for normal removals that shouldn't hurt customers who
    // already booked.
    public function banVendorGradual(Request $request, int $id)
    {
        $request->validate(['reason' => 'sometimes|nullable|string|max:1000']);

        $vendor = Vendor::findOrFail($id);

        if (! $vendor->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Vendor is already banned or winding down'], 422);
        }

        $reason = $request->input('reason', 'Banned by admin (winding down)');

        // Winding down only keeps bookings the vendor has actually COMMITTED to
        // (approved). Everything else is settled now:
        //   - awaiting_payment → nothing paid, just dropped
        //   - pending → paid but not yet approved (no commitment) → cancel + 100% refund
        $vendor->bookings()->where('status', 'awaiting_payment')->update(['status' => 'cancelled']);

        $refundedPending = 0;
        $pending = $vendor->bookings()->where('status', 'pending')->get();
        foreach ($pending as $booking) {
            if ($this->cancellation->cancelByPlatform($booking, $reason)['cancelled']) {
                $refundedPending++;
            }
        }

        // Remaining obligations = approved bookings the vendor must still deliver.
        $activeCount = $vendor->bookings()->where('status', 'approved')->count();

        // Nothing left to finish → this is just an immediate full ban.
        if ($activeCount === 0) {
            $vendor->update(['is_active' => false, 'winding_down' => false]);

            AdminAuditLog::record($request->user()->id, 'vendor.ban', 'vendor', $vendor->id, [
                'mode'             => 'gradual_no_active',
                'reason'           => $reason,
                'refunded_pending' => $refundedPending,
            ]);

            return response()->json([
                'status'           => 'success',
                'account_status'   => 'banned',
                'refunded_pending' => $refundedPending,
                'message'          => 'Vendor had no committed bookings — banned immediately.',
            ]);
        }

        $vendor->update(['is_active' => false, 'winding_down' => true]);

        AdminAuditLog::record($request->user()->id, 'vendor.ban_gradual', 'vendor', $vendor->id, [
            'reason'           => $reason,
            'active_bookings'  => $activeCount,
            'refunded_pending' => $refundedPending,
        ]);

        return response()->json([
            'status'           => 'success',
            'account_status'   => 'winding_down',
            'active_bookings'  => $activeCount,
            'refunded_pending' => $refundedPending,
            'message'          => "Vendor is hidden and can't take new bookings. They keep access to finish {$activeCount} committed booking(s); the ban completes automatically once those are done.",
        ]);
    }

    // Reinstate a banned or winding-down vendor — super_admin ONLY.
    public function unbanVendor(Request $request, int $id)
    {
        $vendor = Vendor::findOrFail($id);

        if ($vendor->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Vendor is already active'], 422);
        }

        $vendor->update(['is_active' => true, 'winding_down' => false]);

        AdminAuditLog::record($request->user()->id, 'vendor.unban', 'vendor', $vendor->id);

        return response()->json([
            'status'         => 'success',
            'account_status' => 'active',
            'message'        => 'Vendor reinstated.',
        ]);
    }

    // ── Users ────────────────────────────────────────────────────

    // All users (paginated, searchable) — view: super_admin + support
    // ?search= matches name / phone. ?is_active=0|1 filters banned/active.
    // Each row carries bookings_count for the list's "bookings" column.
    public function users(Request $request)
    {
        $query = User::withCount('bookings')->latest();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        return response()->json(['status' => 'success', 'users' => $query->paginate(20)]);
    }

    // Single user detail + their bookings — view: super_admin + support
    public function userDetail(int $id)
    {
        $user = User::with(['bookings' => fn ($q) => $q->with(['vendor:id,business_name', 'product:id,name'])->latest()])
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'user' => $user]);
    }

    // Ban / unban a user (toggle is_active) — super_admin ONLY.
    // Banning is a pure LOCK-OUT: bookings and money are settled manually,
    // case by case, with the existing tools (cancel booking / refunds-due).
    // Guard rules (so settlement can't be forgotten):
    //   - PAID active bookings (pending/approved) → ban REFUSED, no override.
    //     Either let them complete or cancel them first.
    //   - only unpaid drafts (awaiting_payment) → ban proceeds, but the
    //     response carries a small warning so the admin knows.
    public function toggleUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $unpaidDrafts = 0;

        if ($user->is_active) { // this call is a BAN
            $paidActive = Booking::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            if ($paidActive > 0) {
                return response()->json([
                    'status'          => 'error',
                    'active_bookings' => $paidActive,
                    'message'         => "Cannot ban: this user has {$paidActive} paid active booking(s)."
                        . ' Let them complete, or cancel them (with the refund recorded) first.',
                ], 422);
            }

            $unpaidDrafts = Booking::where('user_id', $user->id)
                ->where('status', 'awaiting_payment')
                ->count();
        }

        $user->update(['is_active' => !$user->is_active]);

        AdminAuditLog::record(
            $request->user()->id,
            $user->is_active ? 'user.unban' : 'user.ban',
            'user',
            $user->id,
            $user->is_active ? [] : ['unpaid_drafts' => $unpaidDrafts]
        );

        $response = [
            'status'    => 'success',
            'is_active' => $user->is_active,
        ];

        if (! $user->is_active && $unpaidDrafts > 0) {
            $response['warning'] = "Note: this user had {$unpaidDrafts} unpaid draft booking(s)."
                . ' Nothing was paid on them; they will simply stay unpaid.';
        }

        return response()->json($response);
    }

    // ── Bookings ─────────────────────────────────────────────────
    // view: super_admin + support
    // ?status= , ?vendor_id= , ?user_id= to filter (e.g. all bookings of a vendor).
    public function bookings(Request $request)
    {
        $query = Booking::with(['user', 'vendor', 'product'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // ?search= matches booking id, customer name, or vendor business name.
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('id', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%"))
                    ->orWhereHas('vendor', fn ($v) => $v
                        ->where('business_name', 'like', "%{$term}%"));
            });
        }

        return response()->json([
            'status'   => 'success',
            'bookings' => $query->paginate(20),
        ]);
    }

    // Single booking detail (full: customer, vendor, product, payment) — both roles
    public function bookingDetail(int $id)
    {
        $booking = Booking::with(['user', 'vendor', 'product', 'payment'])->findOrFail($id);

        return response()->json(['status' => 'success', 'booking' => $booking]);
    }

    // Dispute resolution — super_admin ONLY.
    // Cancels ONE booking and refunds the customer 100% (reuses the shared
    // platform-cancellation service), without touching the vendor's account.
    public function cancelBooking(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $booking = Booking::findOrFail($id);
        $result  = $this->cancellation->cancelByPlatform($booking, $request->reason);

        if (! $result['cancelled']) {
            return response()->json([
                'status'  => 'error',
                'message' => "Cannot cancel a booking with status '{$booking->status}'",
            ], 422);
        }

        AdminAuditLog::record($request->user()->id, 'booking.cancel', 'booking', $booking->id, [
            'reason'         => $request->reason,
            'refund_percent' => $result['refund_percent'],
            'from_status'    => $result['from_status'],
        ]);

        return response()->json([
            'status'         => 'success',
            'message'        => "Booking cancelled — {$result['refund_percent']}% refund is due to the customer.",
            'refund_percent' => $result['refund_percent'],
        ]);
    }

    // Vendor-requested cancellation — super_admin ONLY.
    // The vendor asked (via support) to back out of a booking he already
    // APPROVED. The customer did nothing wrong, so:
    //   - customer gets a 100% refund (recorded in refunds-due)
    //   - the vendor's escrow credit is reversed AND the platform's commission
    //     is charged to his wallet — he bears the full cost, the platform
    //     keeps its cut. His wallet may go negative (owes the platform).
    // A pending booking doesn't belong here: the vendor can decline it himself.
    public function cancelVendorRequest(Request $request, int $id)
    {
        $request->validate(['reason' => 'sometimes|nullable|string|max:1000']);

        $booking = Booking::findOrFail($id);

        if ($booking->status !== 'approved') {
            return response()->json([
                'status'  => 'error',
                'message' => "Only an approved booking can be cancelled at the vendor's request"
                    . " (a pending one the vendor can decline himself). This booking is '{$booking->status}'.",
            ], 422);
        }

        $reason = $request->input('reason', 'Cancelled at the vendor\'s request');
        $result = $this->cancellation->cancelByPlatform($booking, $reason, notify: true, chargeCommission: true);

        // Tell the vendor the outcome too (the user was already notified).
        $this->notifications->notifyVendor(
            $booking->vendor,
            'Booking cancelled on your request',
            "Booking #{$booking->id} was cancelled. The platform commission of {$result['commission_charged']} SYP was charged to your wallet.",
            ['booking_id' => (string) $booking->id]
        );

        AdminAuditLog::record($request->user()->id, 'booking.cancel_vendor_request', 'booking', $booking->id, [
            'reason'             => $reason,
            'refund_amount'      => $result['refund_amount'],
            'commission_charged' => $result['commission_charged'],
            'vendor_id'          => $booking->vendor_id,
        ]);

        return response()->json([
            'status'             => 'success',
            'message'            => "Booking cancelled — {$result['refund_amount']} SYP refund is due to the customer;"
                . " {$result['commission_charged']} SYP commission was charged to the vendor's wallet.",
            'refund_amount'      => $result['refund_amount'],
            'commission_charged' => $result['commission_charged'],
        ]);
    }

    // ── Payments ─────────────────────────────────────────────────
    // super_admin ONLY (money)

    public function payments()
    {
        $payments = Payment::with(['booking.user', 'booking.vendor'])->latest()->paginate(20);

        return response()->json(['status' => 'success', 'payments' => $payments]);
    }

    // ── Financial statistics ─────────────────────────────────────
    // super_admin ONLY (money). Full income/profit picture.
    //   gross_volume    = what customers paid (amount_paid)
    //   platform_profit = the platform's cut (commission)  ← our profit
    //   vendor_payouts  = what vendors earned (vendor_payout)
    // All figures use verified payments only.
    public function financials()
    {
        return response()->json([
            'status'        => 'success',
            'currency'      => 'SYP',
            'summary'       => $this->paymentTotals(Payment::query()),
            'today'         => $this->paymentTotals(Payment::whereDate('created_at', today())),
            'this_month'    => $this->paymentTotals(
                Payment::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)
            ),
            'this_year'     => $this->paymentTotals(Payment::whereYear('created_at', now()->year)),
            'monthly_trend' => $this->monthlyTrend(),
        ]);
    }

    // Aggregate a payments query into the four money figures.
    private function paymentTotals($query): array
    {
        $row = $query->where('status', 'verified')->selectRaw('
            COALESCE(SUM(amount_paid), 0)   as volume,
            COALESCE(SUM(commission), 0)    as profit,
            COALESCE(SUM(vendor_payout), 0) as payouts,
            COUNT(*)                        as transactions
        ')->first();

        return [
            'gross_volume'    => round((float) $row->volume, 2),
            'platform_profit' => round((float) $row->profit, 2),
            'vendor_payouts'  => round((float) $row->payouts, 2),
            'transactions'    => (int) $row->transactions,
        ];
    }

    // Last 12 months of profit/volume, zero-filled so a chart has no gaps.
    private function monthlyTrend(): array
    {
        $rows = Payment::where('status', 'verified')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month,
                         SUM(amount_paid) as volume,
                         SUM(commission)  as profit,
                         COUNT(*)         as transactions")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        return collect(range(11, 0))->map(function ($back) use ($rows) {
            $key = now()->subMonths($back)->format('Y-m');
            $row = $rows->get($key);

            return [
                'month'           => $key,
                'gross_volume'    => round((float) ($row->volume ?? 0), 2),
                'platform_profit' => round((float) ($row->profit ?? 0), 2),
                'transactions'    => (int) ($row->transactions ?? 0),
            ];
        })->all();
    }

    // ── Audit log ────────────────────────────────────────────────
    // super_admin ONLY — who did what, when

    // ?search= matches the action name or the admin's name.
    public function auditLogs(Request $request)
    {
        $query = AdminAuditLog::with('admin:id,name,email,role')->latest();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', "%{$term}%")
                    ->orWhereHas('admin', fn ($a) => $a->where('name', 'like', "%{$term}%"));
            });
        }

        return response()->json(['status' => 'success', 'logs' => $query->paginate(30)]);
    }

    // ── Reviews moderation ───────────────────────────────────────
    // view: super_admin + support ; delete: super_admin ONLY (in routes)

    // All reviews (paginated, filterable by vendor) — ?vendor_id=
    // reports_count > 0 → the FLAGGED badge.
    public function reviews(Request $request)
    {
        $query = Review::with(['user:id,first_name,last_name', 'vendor:id,business_name,is_active,winding_down'])
            ->withCount(['reports as reports_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest();

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        return response()->json(['status' => 'success', 'reviews' => $query->paginate(20)]);
    }

    // Dismiss the flag on a reported item — the admin looked and the content
    // is fine. Clears every pending report so the badge disappears.
    public function dismissReports(Request $request, string $type, int $id)
    {
        if (! in_array($type, ['review', 'product', 'portfolio_item'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unknown content type'], 422);
        }

        $cleared = ContentReport::where('reportable_type', $type)
            ->where('reportable_id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'dismissed']);

        if ($cleared === 0) {
            return response()->json(['status' => 'error', 'message' => 'No pending reports on this item'], 422);
        }

        AdminAuditLog::record($request->user()->id, 'report.dismiss', $type, $id, [
            'cleared_reports' => $cleared,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Reports dismissed', 'cleared' => $cleared]);
    }

    // Delete an abusive review, then recompute the vendor's rating average.
    public function deleteReview(Request $request, int $id)
    {
        $review   = Review::findOrFail($id);
        $vendorId = $review->vendor_id;

        $review->delete();
        ContentReport::where('reportable_type', 'review')->where('reportable_id', $id)->delete();

        // Keep the vendor's rating_avg honest after removing a review.
        $avg = Review::where('vendor_id', $vendorId)->avg('rating');
        Vendor::where('id', $vendorId)->update(['rating_avg' => round($avg ?? 0, 2)]);

        AdminAuditLog::record($request->user()->id, 'review.delete', 'review', $id, ['vendor_id' => $vendorId]);

        return response()->json(['status' => 'success', 'message' => 'Review deleted']);
    }

    // ── Vendor wallet (money oversight) ──────────────────────────
    // super_admin ONLY — view any vendor's balances + ledger (for disputes).
    // Uses the SAME WalletService as the vendor's own wallet, so figures agree.
    public function vendorWallet(int $id)
    {
        $vendor = Vendor::findOrFail($id);

        $transactions = WalletTransaction::where('vendor_id', $vendor->id)
            ->with([
                'booking:id,user_id,vendor_product_id,booking_style,status',
                'booking.user:id,first_name,last_name',
                'booking.product:id,name,price',
            ])
            ->latest()
            ->get();

        $balances = $this->wallet->balances($transactions);

        return response()->json([
            'status' => 'success',
            'vendor' => ['id' => $vendor->id, 'business_name' => $vendor->business_name],
            'wallet' => [
                'available_balance' => $balances['available'],
                'pending_clearance' => $balances['pending'],
                'total_earned'      => $balances['total_earned'],
                'currency'          => 'SYP',
            ],
            'transactions' => $transactions,
        ]);
    }

    // ── Money oversight: refunds due & withdrawals ───────────────
    // super_admin ONLY. Real payouts are manual until the ShamCash payout API;
    // these endpoints let the admin see what's owed and mark it paid.

    // Refunds owed to customers (cancelled bookings not yet paid back).
    // Excludes waived ones — money the admin officially decided to keep.
    public function refundsDue()
    {
        $bookings = Booking::whereNotNull('refund_amount')
            ->whereNull('refund_paid_at')
            ->whereNull('refund_waived_at')
            ->with(['user:id,first_name,last_name,phone', 'vendor:id,business_name'])
            ->latest()
            ->paginate(20);

        $totalDue = round((float) Booking::whereNotNull('refund_amount')
            ->whereNull('refund_paid_at')
            ->whereNull('refund_waived_at')
            ->sum('refund_amount'), 2);

        return response()->json([
            'status'         => 'success',
            'currency'       => 'SYP',
            'total_due'      => $totalDue,
            'refunds'        => $bookings,
        ]);
    }

    // Mark a customer refund as paid (after the admin sent the money manually).
    public function markRefundPaid(Request $request, int $id)
    {
        $booking = Booking::whereNotNull('refund_amount')->findOrFail($id);

        if ($booking->refund_paid_at) {
            return response()->json(['status' => 'error', 'message' => 'Refund already marked paid'], 422);
        }

        if ($booking->refund_waived_at) {
            return response()->json(['status' => 'error', 'message' => 'This refund was waived — it cannot be paid'], 422);
        }

        $booking->update(['refund_paid_at' => now()]);

        AdminAuditLog::record($request->user()->id, 'refund.mark_paid', 'booking', $booking->id, [
            'amount' => (float) $booking->refund_amount,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Refund marked as paid']);
    }

    // Waive a recorded refund — the admin officially decides to KEEP the money
    // (e.g. fraud). Requires a reason; the decision is audited. This is the
    // third fate of a recorded refund: paid / waived / still pending.
    public function waiveRefund(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $booking = Booking::whereNotNull('refund_amount')->findOrFail($id);

        if ($booking->refund_paid_at) {
            return response()->json(['status' => 'error', 'message' => 'Refund already paid — it cannot be waived'], 422);
        }

        if ($booking->refund_waived_at) {
            return response()->json(['status' => 'error', 'message' => 'Refund already waived'], 422);
        }

        $booking->update(['refund_waived_at' => now()]);

        AdminAuditLog::record($request->user()->id, 'refund.waive', 'booking', $booking->id, [
            'amount' => (float) $booking->refund_amount,
            'reason' => $request->reason,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Refund waived — the money stays with the platform']);
    }

    // Vendor withdrawal requests (money leaving the platform to vendors).
    public function withdrawals(Request $request)
    {
        $query = WalletTransaction::where('type', 'withdrawal')
            ->with('vendor:id,business_name,phone')
            ->latest();

        if ($request->filled('unpaid')) {
            $query->whereNull('paid_at'); // ?unpaid=1 → only outstanding payouts
        }

        $totalUnpaid = round((float) abs(WalletTransaction::where('type', 'withdrawal')
            ->whereNull('paid_at')->sum('amount')), 2);

        return response()->json([
            'status'        => 'success',
            'currency'      => 'SYP',
            'total_unpaid'  => $totalUnpaid,
            'withdrawals'   => $query->paginate(20),
        ]);
    }

    // Mark a vendor withdrawal as paid out.
    public function markWithdrawalPaid(Request $request, int $id)
    {
        $withdrawal = WalletTransaction::where('type', 'withdrawal')->findOrFail($id);

        if ($withdrawal->paid_at) {
            return response()->json(['status' => 'error', 'message' => 'Withdrawal already marked paid'], 422);
        }

        $withdrawal->update(['paid_at' => now()]);

        AdminAuditLog::record($request->user()->id, 'withdrawal.mark_paid', 'wallet_transaction', $withdrawal->id, [
            'vendor_id' => $withdrawal->vendor_id,
            'amount'    => (float) abs($withdrawal->amount),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Withdrawal marked as paid']);
    }

    // ── Content moderation ───────────────────────────────────────
    // Listing (view) — super_admin + support. Deleting — super_admin ONLY.
    // Lets the admin browse products/portfolio and remove abusive listings.

    // Products for the moderation grid — ?search= (name) , ?vendor_id=
    // reports_count > 0 → the REPORTED badge.
    public function products(Request $request)
    {
        $query = VendorProduct::with(['vendor:id,business_name,is_active,winding_down', 'images'])
            ->withCount(['reports as reports_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest();

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        return response()->json(['status' => 'success', 'products' => $query->paginate(20)]);
    }

    // Portfolio items for the moderation grid — ?vendor_id=
    // reports_count > 0 → the REPORTED badge.
    public function portfolioItems(Request $request)
    {
        $query = PortfolioItem::with(['vendor:id,business_name,is_active,winding_down', 'images'])
            ->withCount(['reports as reports_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest();

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        return response()->json(['status' => 'success', 'portfolio' => $query->paginate(20)]);
    }

    // Delete a product and its images from storage.
    public function deleteProduct(Request $request, int $id)
    {
        $product  = VendorProduct::with('images')->findOrFail($id);
        $vendorId = $product->vendor_id;

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }
        $product->delete();
        ContentReport::where('reportable_type', 'product')->where('reportable_id', $id)->delete();

        AdminAuditLog::record($request->user()->id, 'product.delete', 'product', $id, ['vendor_id' => $vendorId]);

        return response()->json(['status' => 'success', 'message' => 'Product deleted']);
    }

    // Delete a portfolio item and its images from storage.
    public function deletePortfolioItem(Request $request, int $id)
    {
        $item     = PortfolioItem::with('images')->findOrFail($id);
        $vendorId = $item->vendor_id;

        foreach ($item->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }
        $item->delete();
        ContentReport::where('reportable_type', 'portfolio_item')->where('reportable_id', $id)->delete();

        AdminAuditLog::record($request->user()->id, 'portfolio.delete', 'portfolio_item', $id, ['vendor_id' => $vendorId]);

        return response()->json(['status' => 'success', 'message' => 'Portfolio item deleted']);
    }
}
