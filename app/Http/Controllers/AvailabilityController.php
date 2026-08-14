<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Vendor;
use App\Models\VendorBlockedDate;
use Illuminate\Http\Request;

// Calendar availability for appointment (service) vendors.
// A date is unavailable if it is either:
//   - booked   -> an active booking sits on it (in-app), or
//   - blocked  -> the vendor manually marked it off (offline booking / day off).
// Order (seller) vendors have no calendar — they take many orders per day.
class AvailabilityController extends Controller
{
    // The two active-booking states that hold a date. Same set the booking
    // conflict guard uses, so what the calendar shows and what booking allows
    // never disagree. (declined/cancelled null out event_date, so they drop out.)
    private const HELD_STATUSES = ['awaiting_payment', 'pending', 'approved'];

    // Vendor's own calendar — booked dates + manually blocked dates, labeled so
    // the app can show why each day is unavailable.
    public function vendorAvailability(Request $request)
    {
        $vendor = $request->user();

        return response()->json([
            'status'  => 'success',
            'booked'  => $this->bookedDates($vendor->id),
            'blocked' => $vendor->blockedDates()
                ->orderBy('date')
                ->get(['id', 'date', 'reason']),
        ]);
    }

    // Vendor manually blocks a date (an offline booking or a day off).
    public function block(Request $request)
    {
        $request->validate([
            'date'   => 'required|date|after_or_equal:today',
            'reason' => 'sometimes|nullable|string|max:255',
        ]);

        $vendor = $request->user();

        // Can't block a day that already has an in-app booking.
        $hasBooking = Booking::where('vendor_id', $vendor->id)
            ->whereIn('status', self::HELD_STATUSES)
            ->whereDate('event_date', $request->date)
            ->exists();

        if ($hasBooking) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This date already has a booking',
            ], 409);
        }

        // Unique (vendor_id, date) means blocking an already-blocked day is a no-op
        // rather than an error — firstOrCreate keeps it idempotent.
        $blocked = VendorBlockedDate::firstOrCreate(
            ['vendor_id' => $vendor->id, 'date' => $request->date],
            ['reason' => $request->reason],
        );

        return response()->json([
            'status'  => 'success',
            'blocked' => $blocked->only(['id', 'date', 'reason']),
        ]);
    }

    // Vendor frees a date they had blocked.
    public function unblock(Request $request, string $date)
    {
        $vendor  = $request->user();
        $deleted = VendorBlockedDate::where('vendor_id', $vendor->id)
            ->whereDate('date', $date)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This date is not blocked',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Date unblocked',
        ]);
    }

    // Public — the customer sees which dates are unavailable BEFORE booking, so
    // the app can grey them out. One flat list (booked + blocked merged); the
    // customer doesn't need to know the reason.
    public function publicAvailability(int $vendorId)
    {
        $vendor = Vendor::where('is_approved', true)
            ->where('is_active', true)
            ->findOrFail($vendorId);

        $booked  = $this->bookedDates($vendor->id);
        $blocked = $vendor->blockedDates()->pluck('date')
            ->map(fn ($d) => $d->toDateString());

        // Merge, de-dupe, sort — a single "these days are taken" list.
        $unavailable = $booked->merge($blocked)->unique()->sort()->values();

        return response()->json([
            'status'      => 'success',
            'unavailable' => $unavailable,
        ]);
    }

    // Day strings (YYYY-MM-DD) that have an active booking for this vendor.
    private function bookedDates(int $vendorId)
    {
        return Booking::where('vendor_id', $vendorId)
            ->whereIn('status', self::HELD_STATUSES)
            ->whereNotNull('event_date')
            ->pluck('event_date')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->values();
    }
}
