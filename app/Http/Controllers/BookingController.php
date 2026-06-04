<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\VendorProduct;
use Illuminate\Http\Request;

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
            'status'            => 'pending',
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
            'booking' => $booking->load(['vendor', 'vendor_product']),
        ]);
    }




    // Customer updates a pending booking
    public function update(Request $request, $id)
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
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
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
            'booking' => $booking->load(['vendor', 'vendor_product']),
        ]);
    }

    // Customer cancels a booking
    public function cancel(Request $request, $id)
    {
        $user    = $request->user();
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending'])
            ->firstOrFail();

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Booking cancelled successfully',
        ]);
    }



    // Vendor approves a booking
    public function approve(Request $request, $id)
    {
        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $booking->update(['status' => 'approved']);

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'vendor_product']),
        ]);
    }

    // Vendor declines a booking
    public function decline(Request $request, $id)
    {
        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $booking->update(['status' => 'declined']);

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'vendor_product']),
        ]);
    }

    // Vendor marks booking as complete
    public function complete(Request $request, $id)
    {
        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'approved')
            ->firstOrFail();

        $booking->update(['status' => 'completed']);

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'vendor_product']),
        ]);
    }
    // Get all bookings (user or vendor)
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user instanceof \App\Models\Vendor) {
            $bookings = Booking::where('vendor_id', $user->id)
                ->with(['vendor_product'])
                ->latest()
                ->get();
        } else {
            $bookings = Booking::where('user_id', $user->id)
                ->with(['vendor', 'vendor_product'])
                ->latest()
                ->get();
        }

        return response()->json([
            'status'   => 'success',
            'bookings' => $bookings,
        ]);
    }


    //الايام المحجوزه للمواعيد
    // Vendor gets their booked dates
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
