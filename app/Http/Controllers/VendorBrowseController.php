<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

// Public customer-facing vendor discovery. Powers the Home lists, the Explore
// map, and the Filters screen. Returns lightweight cards only — the full
// profile (products, reviews, portfolio) loads from its own endpoints when
// the user taps into a vendor.
class VendorBrowseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'vendor_type' => 'sometimes|string',
            'city'        => 'sometimes|string',
            'min_rating'  => 'sometimes|numeric|min:0|max:5',
            'min_price'   => 'sometimes|numeric|min:0',
            'max_price'   => 'sometimes|numeric|min:0',
            'search'      => 'sometimes|string',
            'sort'        => 'sometimes|in:top_rated,most_booked,newest,nearest',

            // Only needed for sort=nearest — the phone's GPS position, so we
            // can measure distance to each vendor. Never sent otherwise.
            'lat' => 'required_if:sort,nearest|numeric|between:-90,90',
            'lng' => 'required_if:sort,nearest|numeric|between:-180,180',
        ]);

        // Customers only ever see approved + active vendors.
        // select   -> card fields only; full profile comes from GET /vendors/{id}
        //             (profile_image feeds the image URL; lat/lng feed map pins)
        // withMin  -> adds products_min_price  (the "From 180,000 SYP" on cards)
        // withCount-> adds bookings_count      (for the most_booked sort)
        // Offline vendors (is_accepting_bookings = false) still appear in browse —
        // the profile shows a "not accepting bookings" banner and disables booking.
        $query = Vendor::where('is_approved', true)
            ->where('is_active', true)
            ->select([
                'id', 'business_name', 'vendor_type', 'city', 'rating_avg',
                'profile_image', 'latitude', 'longitude',
                'is_accepting_bookings',
            ])
            ->withMin('products', 'price')
            ->withCount('bookings');

        // Filters — each applies only if Flutter sent it.
        if ($request->filled('vendor_type')) {
            $query->where('vendor_type', $request->vendor_type);
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->filled('min_rating')) {
            $query->where('rating_avg', '>=', $request->min_rating);
        }

        if ($request->filled('search')) {
            $query->where('business_name', 'like', '%' . $request->search . '%');
        }

        // Price range runs against the cheapest product (products_min_price).
        if ($request->filled('min_price')) {
            $query->having('products_min_price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->having('products_min_price', '<=', $request->max_price);
        }

        // Sort — plain browsing (no sort param) just shows the newest vendors.
        // Home's rails ask explicitly: ?sort=top_rated / ?sort=most_booked.
        $sort = $request->input('sort', 'newest');

        if ($sort === 'top_rated') {
            $query->orderByDesc('rating_avg');
        } elseif ($sort === 'most_booked') {
            $query->orderByDesc('bookings_count');
        } elseif ($sort === 'nearest') {
            $this->sortByDistance($query, $request->lat, $request->lng);
        } else {
            $query->latest();
        }

        return response()->json([
            'status'  => 'success',
            'vendors' => $query->paginate(15)->withQueryString(),
        ]);
    }

    // Adds distance_km to each vendor and sorts closest-first.
    // This is the standard Haversine formula (distance between two GPS points
    // on a sphere) — the math looks scary but it's a well-known copy-paste
    // formula, not custom logic. Vendors with no saved location sort last.
    private function sortByDistance($query, $lat, $lng): void
    {
        // selectRaw APPENDS distance_km to the card fields selected in index().
        $query->selectRaw(
            '(6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )) as distance_km',
            [$lat, $lng, $lat]
        )->orderByRaw('distance_km is null, distance_km asc');
    }
}
