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
                'message' => __('messages.date_has_booking'),
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
                'message' => __('messages.date_not_blocked'),
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.date_unblocked'),
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

    // Public — total count of free wedding-hall DAYS across THIS calendar month,
    // summed over every approved+active weddingHall vendor. "Free" = today or
    // later, not already booked, and not manually blocked by that vendor — same
    // definition publicAvailability() uses per vendor, just counted and summed
    // across all of them instead of listed per one.
    //
    // Example: 3 approved wedding halls, 30-day month, today is the 10th ->
    // 21 remaining days each (30 - 9 elapsed) = 63 slots before subtracting
    // anything booked/blocked. Each hall's own booked+blocked days are removed
    // from ITS OWN 21, then the three counts are summed.
    public function freeWeddingHallSlotsThisMonth()
    {
        $today      = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        // Remaining days in the month, today inclusive — the pool each hall's
        // count is drawn from. Halls never rented for a day already past.
        // diffInDays() on two Carbon instances that aren't both exact midnight
        // returns a float (e.g. 12.999...); comparing whole DAYS, so use the
        // date part only and round to a clean integer count.
        $remainingDaysInMonth = $today->copy()->startOfDay()->diffInDays($monthEnd->copy()->startOfDay()) + 1;

        $vendorIds = Vendor::where('is_approved', true)
            ->where('is_active', true)
            ->where('vendor_type', 'weddingHall')
            ->pluck('id');

        $totalFree = 0;
        $halls     = [];

        foreach ($vendorIds as $vendorId) {
            // Booked or blocked days for THIS hall, clipped to today..end-of-month
            // (a booking earlier this month, before today, doesn't cost a "free"
            // slot — that day is simply gone, not something to subtract twice).
            $bookedThisMonth = Booking::where('vendor_id', $vendorId)
                ->whereIn('status', self::HELD_STATUSES)
                ->whereNotNull('event_date')
                ->whereBetween('event_date', [$today, $monthEnd])
                ->distinct()
                ->count('event_date');

            $blockedThisMonth = VendorBlockedDate::where('vendor_id', $vendorId)
                ->whereBetween('date', [$today->toDateString(), $monthEnd->toDateString()])
                ->count();

            $free = max(0, $remainingDaysInMonth - $bookedThisMonth - $blockedThisMonth);

            $halls[]    = ['vendor_id' => $vendorId, 'free_days' => $free];
            $totalFree += $free;
        }

        return response()->json([
            'status'                  => 'success',
            'month'                   => $monthStart->format('Y-m'),
            'remaining_days_in_month' => $remainingDaysInMonth,
            'wedding_halls_counted'   => $vendorIds->count(),
            'total_free_slots'        => $totalFree,
            'halls'                   => $halls,
        ]);
    }
}
