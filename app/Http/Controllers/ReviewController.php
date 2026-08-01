<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Booking;
use App\Services\NotificationService;
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

        // One review per booking (unique booking_id). Look at trashed rows too:
        // a live review blocks a second one; a soft-deleted one (the user removed
        // their old review) is restored and overwritten so they can review again.
        $existing = Review::withTrashed()
            ->where('booking_id', $booking->id)
            ->first();

        if ($existing && ! $existing->trashed()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You have already reviewed this booking',
            ], 409);
        }

        if ($existing) {
            // Re-review: bring the old (deleted) row back with the new content.
            $existing->restore();
            $existing->update([
                'rating'  => $request->rating,
                'comment' => $request->comment,
            ]);
            $review = $existing;
        } else {
            $review = Review::create([
                'booking_id' => $booking->id,
                'user_id'    => $user->id,
                'vendor_id'  => $booking->vendor_id,
                'rating'     => $request->rating,
                'comment'    => $request->comment,
            ]);
        }

        $vendor     = $booking->vendor;
        $avg        = Review::where('vendor_id', $vendor->id)->avg('rating');
        $vendor->update(['rating_avg' => round($avg, 2)]);

        (new NotificationService())->notifyVendor(
            $vendor,
            'New Review',
            "You received a {$request->rating}-star review.",
            ['booking_id' => $booking->id]
        );

        return response()->json([
            'status' => 'success',
            'review' => $review,
        ]);
    }

    // The user's own reviews (the ones THEY wrote) — Profile → Activity → Reviews.
    public function userReviews(Request $request)
    {
        $reviews = Review::where('user_id', $request->user()->id)
            ->with('vendor:id,business_name,profile_image')
            ->latest()
            ->get();

        return response()->json([
            'status'  => 'success',
            'reviews' => $reviews,
        ]);
    }

    // User edits their own review (rating and/or comment). Only the author can
    // edit it, and editing recomputes the vendor's rating average.
    public function update(Request $request, int $id)
    {
        $request->validate([
            'rating'  => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|nullable|string',
        ]);

        $review = Review::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $review->update($request->only(['rating', 'comment']));
        $review->refresh();

        // Rating may have changed → recompute the vendor's average.
        $avg = Review::where('vendor_id', $review->vendor_id)->avg('rating');
        $review->vendor->update(['rating_avg' => round($avg, 2)]);

        return response()->json([
            'status' => 'success',
            'review' => $review,
        ]);
    }

    // User deletes their own review. Soft delete — the row stays in the DB
    // (deleted_at set) but is hidden everywhere. The vendor's average is
    // recomputed over the remaining visible reviews.
    public function destroy(Request $request, int $id)
    {
        $review = Review::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $vendor = $review->vendor;
        $review->delete(); // soft delete — keeps the row, sets deleted_at

        // Recompute the average over the still-visible reviews (soft-deleted
        // ones are excluded automatically). No reviews left → average 0.
        $avg = Review::where('vendor_id', $vendor->id)->avg('rating');
        $vendor->update(['rating_avg' => round($avg ?? 0, 2)]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Review deleted',
        ]);
    }

    // Get all reviews for a vendor (public endpoint — used on the vendor's public profile page)
    public function vendorReviews(int $vendorId)
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

    // Vendor reviews summary — avg, total, star breakdown %, positive %, trend vs last period
    public function summary(Request $request)
    {
        $vendor = $request->user();

        $reviews = Review::where('vendor_id', $vendor->id)->get();
        $total   = $reviews->count();
        $avg     = $total > 0 ? round($reviews->avg('rating'), 2) : 0;

        // Count per star (1..5) + percentage of total
        $breakdown = [];
        foreach (range(5, 1) as $star) {
            $count = $reviews->where('rating', $star)->count();
            $breakdown[$star] = [
                'count'   => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        // Positive reviews = 4 and 5 stars
        $positiveCount   = $reviews->whereIn('rating', [4, 5])->count();
        $positivePercent = $total > 0 ? round(($positiveCount / $total) * 100, 1) : 0;

        // Trend: this month's avg vs last month's avg (rating up/down %)
        $startThisMonth = now()->startOfMonth();
        $startLastMonth = now()->subMonth()->startOfMonth();

        $thisMonthAvg = (float) Review::where('vendor_id', $vendor->id)
            ->where('created_at', '>=', $startThisMonth)
            ->avg('rating');

        $lastMonthAvg = (float) Review::where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$startLastMonth, $startThisMonth])
            ->avg('rating');

        $trend = $lastMonthAvg > 0
            ? round((($thisMonthAvg - $lastMonthAvg) / $lastMonthAvg) * 100, 1)
            : 0.0;

        return response()->json([
            'status'           => 'success',
            'rating_avg'       => (float) $avg,
            'total_reviews'    => $total,
            'star_breakdown'   => $breakdown,    // { "5": {count, percent}, ... }
            'positive_percent' => $positivePercent,
            'trend'            => $trend,         // percent vs last month, can be negative
        ]);
    }

    // Vendor sees their own reviews (private — auth:vendors)
    public function myReviews(Request $request)
    {
        $vendor  = $request->user();
        $reviews = Review::where('vendor_id', $vendor->id)
            ->with('user:id,first_name,last_name')
            ->latest()
            ->get();

        return response()->json([
            'status'        => 'success',
            'total_reviews' => $reviews->count(),
            'rating_avg'    => $vendor->rating_avg,
            'reviews'       => $reviews,
        ]);
    }
}
