<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentBookingRequest;
use App\Http\Requests\StoreOrderBookingRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\WalletTransaction;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    // Customer sends a booking request. One endpoint — the vendor's
    // booking_style (server-side truth) decides which shape applies:
    //   order       -> storeOrder():       items[] cart, delivery fields
    //   appointment -> storeAppointment(): one package + event fields
    public function store(Request $request)
    {
        // Just enough validation to know which product (and so which vendor)
        // the request is about — each branch does its own strict validation.
        $request->validate([
            'vendor_product_id'         => 'required_without:items|exists:vendor_products,id',
            'items'                     => 'sometimes|array|min:1',
            'items.*.vendor_product_id' => 'required_with:items|exists:vendor_products,id',
        ]);

        $firstId = $request->vendor_product_id ?? $request->items[0]['vendor_product_id'];
        $vendor  = VendorProduct::with('vendor')->findOrFail($firstId)->vendor;

        // A vendor can only receive bookings if they are approved (KYC done),
        // active (not banned), and currently accepting bookings (not self-offline).
        if (!$vendor || !$vendor->is_approved || !$vendor->is_active || !$vendor->is_accepting_bookings) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.vendor_unavailable'),
            ], 403);
        }

        return $vendor->booking_style === 'order'
            ? $this->storeOrder($request, $vendor)
            : $this->storeAppointment($request, $vendor);
    }

    // Order vendors (sellers): cart-style — items[] is the only shape, even
    // for a single product. Appointment fields are rejected outright.
    private function storeOrder(Request $request, Vendor $vendor)
    {
        // Rules live in their own file: app/Http/Requests/StoreOrderBookingRequest.php
        $request->validate((new StoreOrderBookingRequest())->rules());

        $lines          = $this->buildOrderLines($request->items);
        $qtyByProductId = $lines->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));
        $products       = VendorProduct::findMany($qtyByProductId->keys())->keyBy('id');

        // Every product in the cart must pass every rule.
        foreach ($products as $product) {
            // A booking has exactly one vendor — no mixing vendors in one order.
            if ($product->vendor_id !== $vendor->id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('messages.items_same_vendor'),
                ], 422);
            }

            // Can't book an unavailable product. A product auto-hides
            // (is_available = false) the moment its stock hits 0, so this also
            // blocks booking a sold-out product.
            if ($product->is_available === false || $product->is_hidden) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('messages.product_not_available', ['name' => $product->name]),
                ], 409);
            }

            // UX guard: can't ask for more units than are in stock right now.
            // The real oversell defense stays the atomic decrement at approve.
            if ($product->stock !== null && $qtyByProductId[$product->id] > $product->stock) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('messages.only_n_in_stock', ['count' => $product->stock, 'name' => $product->name]),
                ], 409);
            }
        }

        // Booking + its item rows are created together or not at all.
        $booking = DB::transaction(function () use ($request, $vendor, $products, $lines) {
            $booking = Booking::create([
                'user_id'           => $request->user()->id,
                'vendor_id'         => $vendor->id,
                'vendor_product_id' => $lines->first()['product_id'], // primary product (kept for existing endpoints)
                'booking_style'     => 'order',
                'event_type'        => $vendor->vendor_type,
                'status'            => 'awaiting_payment', // hidden from vendor until payment is confirmed
                'notes'             => $request->notes,
                'details'           => $request->details,
                'delivery_date'     => $request->delivery_date,
                'delivery_address'  => $request->delivery_address,
            ]);

            $this->createOrderItems($booking, $products, $lines);

            return $booking;
        });

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
        ]);
    }

    // Appointment vendors (services): one package, quantity is always 1.
    // Cart/delivery fields are rejected outright.
    private function storeAppointment(Request $request, Vendor $vendor)
    {
        // Rules live in their own file: app/Http/Requests/StoreAppointmentBookingRequest.php
        $request->validate((new StoreAppointmentBookingRequest())->rules());

        $product = VendorProduct::findOrFail($request->vendor_product_id);

        if ($product->is_available === false || $product->is_hidden) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.product_not_available', ['name' => $product->name]),
            ], 409);
        }

        // Check if date is already booked
        if ($request->event_date) {
            $conflict = Booking::where('vendor_id', $vendor->id)
                ->whereIn('status', ['awaiting_payment', 'pending', 'approved'])
                ->whereDate('event_date', $request->event_date)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('messages.date_already_booked'),
                ], 409);
            }

            // Also respect a date the vendor manually blocked (offline booking).
            // Compare on the date part only — event_date carries a time.
            $blocked = \App\Models\VendorBlockedDate::where('vendor_id', $vendor->id)
                ->whereDate('date', \Illuminate\Support\Carbon::parse($request->event_date)->toDateString())
                ->exists();

            if ($blocked) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('messages.date_not_available'),
                ], 409);
            }
        }

        // Booking + its single item row are created together or not at all.
        // The (vendor_id, event_day) unique index is the real double-booking
        // guard: if two requests race past the check above, the DB rejects the
        // second insert and we turn that into the same clean 409.
        try {
            $booking = DB::transaction(function () use ($request, $vendor, $product) {
                $booking = Booking::create([
                    'user_id'           => $request->user()->id,
                    'vendor_id'         => $vendor->id,
                    'vendor_product_id' => $product->id,
                    'booking_style'     => 'appointment',
                    'event_type'        => $vendor->vendor_type,
                    'status'            => 'awaiting_payment', // hidden from vendor until payment is confirmed
                    'notes'             => $request->notes,
                    'selected_options'  => $request->selected_options, // customer's picks from the product meta
                    'event_date'        => $request->event_date,
                    'event_location'    => $request->event_location,
                    'duration_hours'    => $request->duration_hours,
                ]);

                // One item row (quantity 1) so orders and appointments read the same.
                $booking->items()->create([
                    'vendor_product_id'   => $product->id,
                    'quantity'            => 1,
                    'unit_price'          => $product->discounted_price, // discounted if on offer
                    'original_unit_price' => $product->price,            // pre-discount, for commission
                ]);

                return $booking;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.date_already_booked'),
            ], 409);
        }

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
        ]);
    }




    // Customer updates a pending booking
    public function update(Request $request, int $id)
    {
        $user    = $request->user();
        // Only an unpaid draft can be edited. Once the booking is paid (pending)
        // the details are locked, so changes wait for a cancel/refund instead.
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'awaiting_payment')
            ->firstOrFail();

        // Strict per style, same as store(): an order edits its cart/delivery,
        // an appointment edits its event fields — never the other way around.
        $request->validate($booking->booking_style === 'order'
            ? [
                'notes'                     => 'sometimes|nullable|string',
                'items'                     => 'sometimes|array|min:1',
                'items.*.vendor_product_id' => 'required_with:items|exists:vendor_products,id',
                'items.*.quantity'          => 'sometimes|integer|min:1',
                'details'                   => 'sometimes|nullable|array',
                'delivery_date'             => 'sometimes|nullable|date',
                'delivery_address'          => 'sometimes|nullable|string',
                'event_date'                => 'prohibited',
                'event_location'            => 'prohibited',
                'duration_hours'            => 'prohibited',
            ]
            : [
                'notes'            => 'sometimes|nullable|string',
                'event_date'       => 'sometimes|nullable|date',
                'event_location'   => 'sometimes|nullable|string',
                'duration_hours'   => 'sometimes|nullable|integer',
                'items'            => 'prohibited',
                'details'          => 'prohibited',
                'delivery_date'    => 'prohibited',
                'delivery_address' => 'prohibited',
            ]);

        // Check date conflict if date changed (appointment only)
        if ($booking->booking_style === 'appointment' && $request->event_date) {
            $conflict = Booking::where('vendor_id', $booking->vendor_id)
                ->where('id', '!=', $id)
                ->whereIn('status', ['awaiting_payment', 'pending', 'approved'])
                ->whereDate('event_date', $request->event_date)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('messages.date_already_booked'),
                ], 409);
            }
        }


        // Replace the cart (order drafts only — validation already guarantees
        // that) — new items must be this vendor's, available, and within
        // stock; old rows are swapped out.
        if ($request->has('items')) {
            $lines          = $this->buildOrderLines($request->items);
            $qtyByProductId = $lines->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));
            $products       = VendorProduct::findMany($qtyByProductId->keys())->keyBy('id');

            foreach ($products as $product) {
                if ($product->vendor_id !== $booking->vendor_id) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => __('messages.items_same_vendor'),
                    ], 422);
                }

                if ($product->is_available === false || $product->is_hidden) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => __('messages.product_not_available', ['name' => $product->name]),
                    ], 409);
                }

                if ($product->stock !== null && $qtyByProductId[$product->id] > $product->stock) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => __('messages.only_n_in_stock', ['count' => $product->stock, 'name' => $product->name]),
                    ], 409);
                }
            }

            DB::transaction(function () use ($booking, $products, $lines) {
                $booking->items()->delete();
                $this->createOrderItems($booking, $products, $lines);
                $booking->update(['vendor_product_id' => $lines->first()['product_id']]);
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
                'message' => __('messages.booking_cancelled'),
            ]);
        }

        // Paid but not yet approved — money is still held by the platform and
        // was never credited to the vendor. Full refund to the user; the
        // vendor's wallet is untouched.
        if ($booking->status === 'pending') {
            $paid = (float) Payment::where('booking_id', $booking->id)
                ->where('status', 'verified')->value('amount_paid');

            $booking->update([
                'status'        => 'cancelled',
                'refund_amount' => $paid > 0 ? round($paid, 2) : null, // 100% owed to the user
            ]);

            (new NotificationService())->notifyVendorTrans(
                $booking->vendor,
                'messages.notif_cancelled_title',
                'messages.notif_cancelled_body',
                ['id' => $booking->id],
                ['booking_id' => $booking->id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => __('messages.booking_cancelled_refund'),
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

            // What the customer gets back = that % of what they paid.
            $paid         = (float) Payment::where('booking_id', $booking->id)
                ->where('status', 'verified')->value('amount_paid');
            $refundAmount = round($paid * $percent / 100, 2);

            // Debit the vendor's wallet by the refunded share of their payout.
            // It nets against the original credit (same booking_id), so it
            // clears on the same 3-day schedule as that credit.
            // Refund row + status change + stock restore happen together.
            DB::transaction(function () use ($booking, $credit, $percent, $refundAmount) {
                if ($credit && $percent > 0) {
                    WalletTransaction::create([
                        'vendor_id'  => $booking->vendor_id,
                        'booking_id' => $booking->id,
                        'type'       => 'refund',
                        'amount'     => -1 * round($credit->amount * $percent / 100, 2),
                    ]);
                }

                $booking->update([
                    'status'        => 'cancelled',
                    'refund_amount' => $refundAmount > 0 ? $refundAmount : null,
                ]);

                // The approved booking had its stock decremented — give the unit back.
                $this->restoreStock($booking);
            });

            (new NotificationService())->notifyVendorTrans(
                $booking->vendor,
                'messages.notif_cancelled_title',
                'messages.notif_cancelled_body',
                ['id' => $booking->id],
                ['booking_id' => $booking->id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => __('messages.cancelled_partial_refund', ['percent' => $percent]),
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

            $booking->update(['status' => 'approved', 'responded_at' => now()]);

            // Credit the vendor's payout. The money stays in escrow (pending)
            // until the booking is completed — see WalletController::balances().
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
                'message' => __('messages.out_of_stock_approve'),
            ], 409);
        }

        $booking->refresh();

        (new NotificationService())->notifyUserTrans(
            $booking->user,
            'messages.notif_approved_title',
            'messages.notif_approved_body',
            ['id' => $booking->id],
            ['booking_id' => $booking->id]
        );

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['user:id,first_name,last_name,profile_image', 'vendor', 'product', 'items.product']),
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

        // Can't mark complete before the service actually happened — otherwise a
        // vendor could complete early to cash out an escrowed booking. Bookings
        // also auto-complete one day after this date (bookings:auto-complete).
        $serviceDate = $booking->booking_style === 'order'
            ? $booking->delivery_date
            : $booking->event_date;

        if ($serviceDate && now()->lt($serviceDate)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.complete_before_date'),
            ], 422);
        }

        $booking->update(['status' => 'completed']);
        $booking->refresh();

        (new NotificationService())->notifyUserTrans(
            $booking->user,
            'messages.notif_completed_title',
            'messages.notif_completed_body',
            ['id' => $booking->id],
            ['booking_id' => $booking->id]
        );

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['user:id,first_name,last_name,profile_image', 'vendor', 'product', 'items.product']),
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

        // A pending booking is PAID (pay-first flow). Declining it means the
        // customer is owed everything back — record it so the refund shows up
        // in the admin's refunds-due list instead of silently vanishing.
        $paid = (float) Payment::where('booking_id', $booking->id)
            ->where('status', 'verified')->value('amount_paid');

        // No stock change: a pending booking never decremented stock (stock is
        // only taken at approve), so there is nothing to restore here.
        // responded_at powers response-time; refund_amount records the refund the
        // customer is owed (both changes kept from the merge).
        $booking->update([
            'status'        => 'declined',
            'responded_at'  => now(),
            'refund_amount' => $paid > 0 ? round($paid, 2) : null,
        ]);

        // One translated decline notification to the customer (Arabic/English by
        // their language). :id fills the booking number.
        (new NotificationService())->notifyUserTrans(
            $booking->user,
            'messages.notif_declined_title',
            'messages.notif_declined_body',
            ['id' => $booking->id],
            ['booking_id' => $booking->id]
        );

        // A declined PAID booking creates a refund the admin must pay out.
        if ($paid > 0) {
            $this->notifications->notifyAdmins(
                ['super_admin'],
                'Refund due',
                "A declined booking (#{$booking->id}) owes the customer a {$paid} SYP refund.",
                ['type' => 'refund_due', 'booking_id' => (string) $booking->id]
            );
        }

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['user:id,first_name,last_name,profile_image', 'vendor', 'product', 'items.product']),
        ]);
    }

    // Take the ordered stock when a vendor approves: every item is decremented
    // Turn the raw items[] request into cart lines. Two rows merge into ONE line
    // only when they share the same product AND the same selected_options — the
    // same product with different options (white rose vs red rose) stays separate.
    // Each line: { product_id, quantity, options }.
    private function buildOrderLines(array $items): \Illuminate\Support\Collection
    {
        return collect($items)
            ->groupBy(fn ($row) => $row['vendor_product_id'] . '|' . json_encode($row['selected_options'] ?? null))
            ->map(fn ($rows) => [
                'product_id' => (int) $rows->first()['vendor_product_id'],
                'quantity'   => $rows->sum(fn ($row) => (int) ($row['quantity'] ?? 1)),
                'options'    => $rows->first()['selected_options'] ?? null,
            ])
            ->values();
    }

    // Create the booking_items for a set of cart lines (price snapshot + the
    // customer's per-line selected options). $products is keyed by id.
    private function createOrderItems(Booking $booking, $products, \Illuminate\Support\Collection $lines): void
    {
        foreach ($lines as $line) {
            $product = $products[$line['product_id']];
            $booking->items()->create([
                'vendor_product_id'   => $product->id,
                'quantity'            => $line['quantity'],
                'unit_price'          => $product->discounted_price, // what the customer pays (discounted if on offer)
                'original_unit_price' => $product->price,            // pre-discount, for commission
                'selected_options'    => $line['options'],          // the customer's picks for this line
            ]);
        }
    }

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
                ->with(['user:id,first_name,last_name,profile_image', 'product', 'items.product:id,name,price'])
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

    // Vendor's average response time — automatically computed as the average gap
    // between when a booking was PAID (payment.created_at, the moment it became
    // visible to the vendor) and when the vendor RESPONDED (responded_at, set on
    // approve/decline). Returned as a moderated range, never an exact figure.
    // A vendor who has never responded to a booking is marked as new.
    public function responseTime(Request $request)
    {
        $vendor = $request->user();

        // Only bookings the vendor actually acted on, and that have a verified
        // payment (so there's a real "paid at" moment to measure from).
        $bookings = Booking::where('vendor_id', $vendor->id)
            ->whereNotNull('responded_at')
            ->whereHas('payment', fn ($query) => $query->where('status', 'verified'))
            ->with('payment:id,booking_id,created_at')
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'status'        => 'success',
                'response_time' => [
                    'is_new' => true, // no bookings answered yet — app shows "New"
                    'label'  => null,
                ],
            ]);
        }

        // Average gap in minutes between paid time and vendor response time.
        $avgMinutes = $bookings->avg(function (Booking $booking) {
            return $booking->payment->created_at->diffInMinutes($booking->responded_at);
        });

        return response()->json([
            'status'        => 'success',
            'response_time' => [
                'is_new'          => false,
                'label'           => $this->responseTimeLabel($avgMinutes),
                'average_minutes' => (int) round($avgMinutes), // raw value if the app wants it
                'based_on'        => $bookings->count(),       // how many responses it averages
            ],
        ]);
    }

    // Moderate a raw minute average into a human range, so the app shows
    // "usually replies in 1-2 hours" instead of "43 minutes".
    private function responseTimeLabel(float $minutes): string
    {
        return match (true) {
            $minutes < 30      => 'under 30 minutes',
            $minutes < 60      => '30-60 minutes',
            $minutes < 120     => '1-2 hours',
            $minutes < 360     => '2-6 hours',
            $minutes < 1440    => '6-24 hours',
            default            => 'over a day',
        };
    }
}
