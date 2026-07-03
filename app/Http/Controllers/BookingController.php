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
    // Customer sends a booking request.
    // Appointment: one package (vendor_product_id), quantity is always 1.
    // Order: cart-style — either a single vendor_product_id (+ optional
    // quantity) or an items[] list of different products from the same vendor.
    public function store(Request $request)
    {
        $request->validate([
            'vendor_product_id' => 'required_without:items|exists:vendor_products,id',
            'notes'             => 'sometimes|nullable|string',

            // Appointment fields
            'event_date'     => 'sometimes|nullable|date',
            'event_location' => 'sometimes|nullable|string',
            'duration_hours' => 'sometimes|nullable|integer',

            // Order fields
            'quantity'                   => 'sometimes|integer|min:1',
            'items'                      => 'sometimes|array|min:1',
            'items.*.vendor_product_id'  => 'required_with:items|exists:vendor_products,id',
            'items.*.quantity'           => 'sometimes|integer|min:1',
            'details'                    => 'sometimes|nullable|array',
            'delivery_date'              => 'sometimes|nullable|date',
            'delivery_address'           => 'sometimes|nullable|string',
        ]);

        $user = $request->user();

        // Normalize both input shapes into one cart list: product_id => quantity.
        // Duplicate product ids are merged by summing their quantities.
        $cart = collect($request->items ?: [[
                'vendor_product_id' => $request->vendor_product_id,
                'quantity'          => $request->quantity ?? 1,
            ]])
            ->groupBy('vendor_product_id')
            ->map(fn ($rows) => $rows->sum(fn ($row) => (int) ($row['quantity'] ?? 1)));

        $products = VendorProduct::with('vendor')->findMany($cart->keys());
        $vendor   = $products->first()?->vendor;

        // All items must belong to one vendor — a booking has exactly one vendor.
        if ($products->pluck('vendor_id')->unique()->count() > 1) {
            return response()->json([
                'status'  => 'error',
                'message' => 'All items must belong to the same vendor',
            ], 422);
        }

        // A banned (suspended) vendor can't receive new bookings.
        if (!$vendor || !$vendor->is_active) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This vendor is currently unavailable',
            ], 403);
        }

        // Multiple items only make sense for order vendors — an appointment
        // books one package, quantity 1.
        if ($vendor->booking_style !== 'order' && ($products->count() > 1 || $cart->max() > 1)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Appointment bookings take a single package',
            ], 422);
        }

        foreach ($products as $product) {
            // Can't book an unavailable product. A product auto-hides
            // (is_available = false) the moment its stock hits 0, so this also
            // blocks booking a sold-out product.
            if ($product->is_available === false) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "'{$product->name}' is not available",
                ], 409);
            }

            // UX guard: can't ask for more units than are in stock right now.
            // The real oversell defense stays the atomic decrement at approve.
            if ($product->stock !== null && $cart[$product->id] > $product->stock) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Only {$product->stock} of '{$product->name}' left in stock",
                ], 409);
            }
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

        // Booking + its item rows are created together or not at all.
        $booking = DB::transaction(function () use ($request, $user, $vendor, $products, $cart) {
            $booking = Booking::create([
                'user_id'           => $user->id,
                'vendor_id'         => $vendor->id,
                'vendor_product_id' => $products->first()->id, // primary product (kept for existing endpoints)
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

            foreach ($products as $product) {
                $booking->items()->create([
                    'vendor_product_id' => $product->id,
                    'quantity'          => $cart[$product->id],
                    'unit_price'        => $product->price, // price snapshot at booking time
                ]);
            }

            return $booking;
        });

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
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
            'items'                     => 'sometimes|array|min:1',
            'items.*.vendor_product_id' => 'required_with:items|exists:vendor_products,id',
            'items.*.quantity'          => 'sometimes|integer|min:1',
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


        // Replace the cart (order drafts only) — new items must be this
        // vendor's, available, and within stock; old rows are swapped out.
        if ($request->has('items') && $booking->booking_style === 'order') {
            $cart = collect($request->items)
                ->groupBy('vendor_product_id')
                ->map(fn ($rows) => $rows->sum(fn ($row) => (int) ($row['quantity'] ?? 1)));

            $products = VendorProduct::findMany($cart->keys());

            foreach ($products as $product) {
                if ($product->vendor_id !== $booking->vendor_id) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'All items must belong to the same vendor',
                    ], 422);
                }

                if ($product->is_available === false) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => "'{$product->name}' is not available",
                    ], 409);
                }

                if ($product->stock !== null && $cart[$product->id] > $product->stock) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => "Only {$product->stock} of '{$product->name}' left in stock",
                    ], 409);
                }
            }

            DB::transaction(function () use ($booking, $products, $cart) {
                $booking->items()->delete();

                foreach ($products as $product) {
                    $booking->items()->create([
                        'vendor_product_id' => $product->id,
                        'quantity'          => $cart[$product->id],
                        'unit_price'        => $product->price,
                    ]);
                }

                $booking->update(['vendor_product_id' => $products->first()->id]);
            });
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
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
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

        // Approval takes the ordered stock AND credits the vendor's wallet.
        // Both run in one transaction so they can never half-apply.
        $approved = DB::transaction(function () use ($booking, $vendor) {
            // Atomic, oversell-safe decrement of every item by its quantity.
            // Returns false when any item can't be fully covered — the whole
            // approval fails together (a vendor can't half-fulfill an order)
            // and the transaction rolls back what was already taken.
            if (! $this->decrementStock($booking)) {
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
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
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
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
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
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
        ]);
    }

    // Take the ordered stock when a vendor approves: every item is decremented
    // by its quantity with an atomic conditional UPDATE (stock >= quantity), so
    // two approvals racing for the last units can't push stock negative —
    // exactly one wins. Returns false the moment any item can't be covered;
    // the caller's transaction then rolls back the items already taken.
    // Untracked products (stock = null, e.g. appointment services) always pass.
    private function decrementStock(Booking $booking): bool
    {
        foreach ($booking->items()->with('product')->get() as $item) {
            $product = $item->product;

            if (! $product || $product->stock === null) {
                continue;
            }

            $taken = VendorProduct::where('id', $product->id)
                ->where('stock', '>=', $item->quantity)
                ->decrement('stock', $item->quantity);

            if ($taken === 0) {
                return false; // not enough left — lost the race for these units
            }

            // Auto-hide the product the moment it sells out.
            VendorProduct::where('id', $product->id)
                ->where('stock', '<=', 0)
                ->update(['is_available' => false]);
        }

        return true;
    }

    // Give the ordered stock back when an approved booking is cancelled.
    // Mirror image of decrementStock — atomic increment per item, and each
    // product becomes visible again once it's back in stock. No-op for
    // untracked products (stock = null).
    private function restoreStock(Booking $booking): void
    {
        foreach ($booking->items()->with('product')->get() as $item) {
            $product = $item->product;

            if (! $product || $product->stock === null) {
                continue;
            }

            VendorProduct::where('id', $product->id)->increment('stock', $item->quantity);

            VendorProduct::where('id', $product->id)
                ->where('stock', '>', 0)
                ->update(['is_available' => true]);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user instanceof \App\Models\Vendor) {
            $bookings = Booking::where('vendor_id', $user->id)
                ->with(['product', 'items.product:id,name,price'])
                ->latest()
                ->get();
        } else {
            $bookings = Booking::where('user_id', $user->id)
                ->with(['vendor', 'product', 'items.product:id,name,price'])
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
            ->with(['user:id,first_name,last_name,phone,profile_image', 'product.images', 'items.product:id,name,price', 'payment'])
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
            ->with(['user:id,first_name,last_name', 'product:id,name,price', 'items.product:id,name,price'])
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
