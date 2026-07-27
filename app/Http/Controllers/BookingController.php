<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\VendorProduct;
use App\Models\WalletTransaction;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    // Customer sends a booking request
    public function store(Request $request)
    {
        $request->validate([
            'vendor_product_id' => 'required|exists:vendor_products,id',
            'notes'             => 'sometimes|nullable|string',

            // Appointment fields
            'event_date'     => 'sometimes|nullable|date',
            'event_location' => 'sometimes|nullable|string',
            'duration_hours' => 'sometimes|nullable|integer',

            // Order fields
            'details'          => 'sometimes|nullable|array',
            'delivery_date'    => 'sometimes|nullable|date',
            'delivery_address' => 'sometimes|nullable|string',
        ]);

        $user    = $request->user();
        $product = VendorProduct::with('vendor')->findOrFail($request->vendor_product_id);
        $vendor  = $product->vendor;

        // A banned (suspended) vendor can't receive new bookings.
        if (!$vendor || !$vendor->is_active) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This vendor is currently unavailable',
            ], 403);
        }

        // Can't book an unavailable product. A product auto-hides
        // (is_available = false) the moment its stock hits 0, so this also
        // blocks booking a sold-out product.
        if ($product->is_available === false) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This product is not available',
            ], 409);
        }

        // Check if date is already booked (appointment only)
        if ($vendor->booking_style === 'appointment' && $request->event_date) {
            $conflict = Booking::where('vendor_id', $vendor->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('event_date', $request->event_date)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This date is already booked',
                ], 409);
            }
        }

        $booking = Booking::create([
            'user_id'           => $user->id,
            'vendor_id'         => $vendor->id,
            'vendor_product_id' => $product->id,
            'booking_style'     => $vendor->booking_style,
            'event_type'        => $vendor->vendor_type,
            'status'            => 'awaiting_payment', // hidden from vendor until payment is confirmed
            'notes'             => $request->notes,

            // Appointment
            'event_date'     => $request->event_date,
            'event_location' => $request->event_location,
            'duration_hours' => $request->duration_hours,

            // Order
            'details'          => $request->details,
            'delivery_date'    => $request->delivery_date,
            'delivery_address' => $request->delivery_address,
        ]);

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'product']),
        ]);
    }




    // Customer updates a pending booking
    public function update(Request $request, int $id)
    {
        $request->validate([
            'notes'            => 'sometimes|nullable|string',

            // Appointment fields
            'event_date'       => 'sometimes|nullable|date',
            'event_location'   => 'sometimes|nullable|string',
            'duration_hours'   => 'sometimes|nullable|integer',

            // Order fields
            'details'          => 'sometimes|nullable|array',
            'delivery_date'    => 'sometimes|nullable|date',
            'delivery_address' => 'sometimes|nullable|string',
        ]);

        $user    = $request->user();
        // Only an unpaid draft can be edited. Once the booking is paid (pending)
        // the details are locked, so changes wait for a cancel/refund instead.
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'awaiting_payment')
            ->firstOrFail();

        // Check date conflict if date changed (appointment only)
        if ($booking->booking_style === 'appointment' && $request->event_date) {
            $conflict = Booking::where('vendor_id', $booking->vendor_id)
                ->where('id', '!=', $id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('event_date', $request->event_date)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This date is already booked',
                ], 409);
            }
        }


        $booking->update($request->only([
            'notes',
            'event_date',
            'event_location',
            'duration_hours',
            'details',
            'delivery_date',
            'delivery_address',
        ]));

        $booking->refresh();

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'product']),
        ]);
    }

    // Customer cancels a booking.
    // Refund depends on the status and (for approved bookings) how long ago the
    // vendor approved. The real money back to the user waits on the ShamCash
    // refund API — here we only adjust the vendor's wallet bookkeeping.
    public function cancel(Request $request, int $id)
    {
        $user    = $request->user();
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Unpaid draft — nothing was paid, just drop it.
        if ($booking->status === 'awaiting_payment') {
            $booking->update(['status' => 'cancelled']);
            return response()->json([
                'status'  => 'success',
                'message' => 'Booking cancelled',
            ]);
        }

        // Paid but not yet approved — money is still held by the platform and
        // was never credited to the vendor. Full refund to the user; the
        // vendor's wallet is untouched.
        if ($booking->status === 'pending') {
            $booking->update(['status' => 'cancelled']);

            (new NotificationService())->notifyVendor(
                $booking->vendor,
                'Booking Cancelled',
                "The customer cancelled booking #{$booking->id}.",
                ['booking_id' => $booking->id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Booking cancelled — full refund due to the user',
                'refund'  => ['percent' => 100, 'note' => 'real refund pending ShamCash API'],
            ]);
        }

        // Approved — refund tier based on hours since approval (clock started
        // when the credit was created).
        if ($booking->status === 'approved') {
            $credit = WalletTransaction::where('booking_id', $booking->id)
                ->where('type', 'credit')
                ->first();

            $hours = $credit
                ? (now()->timestamp - $credit->created_at->timestamp) / 3600
                : PHP_INT_MAX;

            $percent = $hours <= 24 ? 100 : ($hours <= 72 ? 50 : 0);

            // Debit the vendor's wallet by the refunded share of their payout.
            // It nets against the original credit (same booking_id), so it
            // clears on the same 3-day schedule as that credit.
            // Refund row + status change + stock restore happen together.
            DB::transaction(function () use ($booking, $credit, $percent) {
                if ($credit && $percent > 0) {
                    WalletTransaction::create([
                        'vendor_id'  => $booking->vendor_id,
                        'booking_id' => $booking->id,
                        'type'       => 'refund',
                        'amount'     => -1 * round($credit->amount * $percent / 100, 2),
                    ]);
                }

                $booking->update(['status' => 'cancelled']);

                // The approved booking had its stock decremented — give the unit back.
                $this->restoreStock($booking);
            });

            (new NotificationService())->notifyVendor(
                $booking->vendor,
                'Booking Cancelled',
                "The customer cancelled booking #{$booking->id}.",
                ['booking_id' => $booking->id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => "Booking cancelled — {$percent}% refund due to the user",
                'refund'  => ['percent' => $percent, 'note' => 'real refund pending ShamCash API'],
            ]);
        }

        // completed / already cancelled / declined — cannot cancel.
        return response()->json([
            'status'  => 'error',
            'message' => "Cannot cancel a booking with status '{$booking->status}'",
        ], 422);
    }



    // Vendor approves a paid booking → approved.
    // Later (event day / vendor mark) it moves to completed via complete().
    public function approve(Request $request, int $id)
    {
        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $product = $booking->product;

        // Approval takes one unit of stock AND credits the vendor's wallet.
        // Both run in one transaction so they can never half-apply.
        $approved = DB::transaction(function () use ($booking, $vendor, $product) {
            // Atomic, oversell-safe decrement. Returns false only when the
            // product tracks stock and there's none left — i.e. another approval
            // already grabbed the last unit. Untracked products always pass.
            if (! $this->decrementStock($product)) {
                return false;
            }

            $booking->update(['status' => 'approved']);

            // Credit the vendor's payout. This row's created_at is the approval
            // time — money is held 3 days (refund window) before it's withdrawable.
            $payout = (float) Payment::where('booking_id', $booking->id)
                ->where('status', 'verified')
                ->value('vendor_payout');

            if ($payout > 0) {
                WalletTransaction::create([
                    'vendor_id'  => $vendor->id,
                    'booking_id' => $booking->id,
                    'type'       => 'credit',
                    'amount'     => $payout,
                ]);
            }

            return true;
        });

        if (! $approved) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This product is out of stock — cannot approve',
            ], 409);
        }

        $booking->refresh();

        (new NotificationService())->notifyUser(
            $booking->user,
            'Booking Approved',
            "Your booking #{$booking->id} has been approved by the vendor.",
            ['booking_id' => $booking->id]
        );

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['user:id,first_name,last_name,profile_image', 'vendor', 'product']),
        ]);
    }

    // Vendor marks an approved booking as completed (e.g. on the event day)
    public function complete(Request $request, int $id)
    {
        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'approved')
            ->firstOrFail();

        $booking->update(['status' => 'completed']);
        $booking->refresh();

        (new NotificationService())->notifyUser(
            $booking->user,
            'Service Completed',
            "Your booking #{$booking->id} is complete. Don't forget to leave a review!",
            ['booking_id' => $booking->id]
        );

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['user:id,first_name,last_name,profile_image', 'vendor', 'product']),
        ]);
    }

    // Vendor declines a booking
    public function decline(Request $request, int $id)
    {
        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->firstOrFail();

        // No stock change: a pending booking never decremented stock (stock is
        // only taken at approve), so there is nothing to restore here.
        $booking->update(['status' => 'declined']);

        (new NotificationService())->notifyUser(
            $booking->user,
            'Booking Declined',
            "Your booking #{$booking->id} was declined. Your payment will be refunded.",
            ['booking_id' => $booking->id]
        );

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['user:id,first_name,last_name,profile_image', 'vendor', 'product']),
        ]);
    }

    // Take one unit of stock when a vendor approves a stock-tracked product.
    // The decrement is an atomic conditional UPDATE (stock > 0), so two approvals
    // racing for the last unit can't push stock negative — exactly one wins.
    // Returns false when there's nothing left to take. Untracked products
    // (stock = null, e.g. appointment services) always pass.
    private function decrementStock(?VendorProduct $product): bool
    {
        if (! $product || $product->stock === null) {
            return true;
        }

        $taken = VendorProduct::where('id', $product->id)
            ->where('stock', '>', 0)
            ->decrement('stock');

        if ($taken === 0) {
            return false; // sold out — lost the race for the last unit
        }

        // Auto-hide the product the moment it sells out.
        VendorProduct::where('id', $product->id)
            ->where('stock', '<=', 0)
            ->update(['is_available' => false]);

        return true;
    }

    // Give one unit back when an approved booking is cancelled. Mirror image of
    // decrementStock — atomic increment, and the product becomes visible again
    // once it's back in stock. No-op for untracked products (stock = null).
    private function restoreStock(Booking $booking): void
    {
        $product = $booking->product;

        if (! $product || $product->stock === null) {
            return;
        }

        VendorProduct::where('id', $product->id)->increment('stock');

        VendorProduct::where('id', $product->id)
            ->where('stock', '>', 0)
            ->update(['is_available' => true]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user instanceof \App\Models\Vendor) {
            $bookings = Booking::where('vendor_id', $user->id)
                ->with(['user:id,first_name,last_name,profile_image', 'product'])
                ->latest()
                ->get();
        } else {
            $bookings = Booking::where('user_id', $user->id)
                ->with(['vendor', 'product'])
                ->latest()
                ->get();
        }

        return response()->json([
            'status'   => 'success',
            'bookings' => $bookings,
        ]);
    }


    // Recent booking requests — latest pending bookings waiting for vendor action
    public function recentRequests(Request $request)
    {
        $vendor   = $request->user();
        $bookings = Booking::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->with(['user:id,first_name,last_name', 'product:id,name,price'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'status'   => 'success',
            'bookings' => $bookings,
        ]);
    }

    // Upcoming events — approved bookings with a future event_date (appointment vendors only)
    public function upcomingEvents(Request $request)
    {
        $vendor = $request->user();

        if ($vendor->booking_style !== 'appointment') {
            return response()->json([
                'status'   => 'success',
                'bookings' => [],
                'note'     => 'Only appointment vendors have upcoming events',
            ]);
        }

        $bookings = Booking::where('vendor_id', $vendor->id)
            ->where('status', 'approved')
            ->where('event_date', '>=', now())
            ->with(['user:id,first_name,last_name', 'product:id,name'])
            ->orderBy('event_date', 'asc')
            ->get();

        return response()->json([
            'status'   => 'success',
            'bookings' => $bookings,
        ]);
    }

    public function stats(Request $request)
    {
        $vendor = $request->user();

        $bookings = Booking::where('vendor_id', $vendor->id);

        $totalEarnings = Payment::whereHas('booking', fn($q) => $q->where('vendor_id', $vendor->id))
            ->where('status', 'verified')
            ->sum('vendor_payout');

        return response()->json([
            'status' => 'success',
            'stats'  => [
                'bookings' => [
                    'total'     => (clone $bookings)->count(),
                    'pending'   => (clone $bookings)->where('status', 'pending')->count(),
                    'approved'  => (clone $bookings)->where('status', 'approved')->count(),
                    'completed' => (clone $bookings)->where('status', 'completed')->count(),
                    'declined'  => (clone $bookings)->where('status', 'declined')->count(),
                    'cancelled' => (clone $bookings)->where('status', 'cancelled')->count(),
                ],
                'earnings'       => (float) $totalEarnings,
                'rating_avg'     => (float) $vendor->rating_avg,
                'total_reviews'  => \App\Models\Review::where('vendor_id', $vendor->id)->count(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->with(['user:id,first_name,last_name,phone,profile_image', 'product.images', 'payment'])
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'booking' => $booking,
        ]);
    }

    // Recent orders — latest orders for order vendors (cake_shop, store).
    // An order is just a booking with booking_style='order'. We skip drafts (awaiting_payment).
    public function recentOrders(Request $request)
    {
        $vendor   = $request->user();
        $bookings = Booking::where('vendor_id', $vendor->id)
            ->where('booking_style', 'order')
            ->where('status', '!=', 'awaiting_payment')
            ->with(['user:id,first_name,last_name', 'product:id,name,price'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'orders' => $bookings,
        ]);
    }

    // Earnings/revenue with month-over-month growth %
    public function earnings(Request $request)
    {
        $vendor = $request->user();

        $startThisMonth = now()->startOfMonth();
        $startLastMonth = now()->subMonth()->startOfMonth();

        // Sum vendor_payout from verified payments for this vendor's bookings
        $base = Payment::whereHas('booking', fn($q) => $q->where('vendor_id', $vendor->id))
            ->where('status', 'verified');

        $thisMonth = (float) (clone $base)
            ->where('created_at', '>=', $startThisMonth)
            ->sum('vendor_payout');

        $lastMonth = (float) (clone $base)
            ->whereBetween('created_at', [$startLastMonth, $startThisMonth])
            ->sum('vendor_payout');

        // Growth % vs last month. If last month was 0, treat any income as 100%.
        $growth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : ($thisMonth > 0 ? 100.0 : 0.0);

        return response()->json([
            'status'   => 'success',
            'earnings' => [
                'this_month' => $thisMonth,
                'last_month' => $lastMonth,
                'growth'     => $growth, // percent, can be negative
            ],
        ]);
    }

    // Booked dates for appointment vendors (used to block the calendar)
    public function bookedDates(Request $request)
    {
        $vendor = $request->user();

        $dates = Booking::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotNull('event_date')
            ->pluck('event_date');

        return response()->json([
            'status' => 'success',
            'dates'  => $dates,
        ]);
    }
}
