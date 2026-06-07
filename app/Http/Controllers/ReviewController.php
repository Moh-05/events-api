<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Booking;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Customer submits a review
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'sometimes|nullable|string',
        ]);

        $user = $request->user();

        // Must be the user's own booking
        $booking = Booking::where('id', $request->booking_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Booking not found',
            ], 404);
        }

        // Review is allowed only after the service is done.
        // Real rule: 'completed'. For now 'approved' is also accepted for testing.
        if (!in_array($booking->status, ['approved', 'completed'])) {
            return response()->json([
                'status'  => 'error',
                'message' => "You can't review a booking with status '{$booking->status}'. It must be completed first (approved is allowed for testing).",
            ], 422);
        }

        // Check if already reviewed
        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You have already reviewed this booking',
            ], 409);
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'user_id'    => $user->id,
            'vendor_id'  => $booking->vendor_id,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
        ]);

        // Update vendor rating_avg
        $vendor     = $booking->vendor;
        $avg        = Review::where('vendor_id', $vendor->id)->avg('rating');
        $vendor->update(['rating_avg' => round($avg, 2)]);

        return response()->json([
            'status' => 'success',
            'review' => $review,
        ]);
    }

    // Get all reviews for a vendor
    public function vendorReviews($vendorId)
    {
        $reviews = Review::where('vendor_id', $vendorId)
            ->with('user:id,first_name,last_name')
            ->latest()
            ->get();

        return response()->json([
            'status'  => 'success',
            'reviews' => $reviews,
        ]);
    }
}
