<?php

namespace App\Http\Controllers;

use App\Models\VendorProduct;
use Illuminate\Http\Request;

// Public item discovery. ONE endpoint powers every product/service rail on the
// Home page (Discover Services, Discover Products, Best Offers, Recently Added),
// the Filter screen, and search — the "rails" are just preset query params.
//
//   ?type=service|product        service = appointment vendors, product = order
//   ?category=photographer|...   a specific vendor_type
//   ?on_offer=1                  only items with a live discount (Best Offers)
//   ?min_price= &max_price=      price range on the item's own price
//   ?min_rating=                 vendor rating >=
//   ?search=                     item name contains
//   ?sort=newest|top_rated|price_low|price_high|most_booked|nearest
//   ?lat= &lng=                  required only for sort=nearest (vendor distance)
class ProductBrowseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type'       => 'sometimes|in:service,product',
            'category'   => 'sometimes|string',
            'on_offer'   => 'sometimes|boolean',
            'min_price'  => 'sometimes|numeric|min:0',
            'max_price'  => 'sometimes|numeric|min:0',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'search'     => 'sometimes|string',
            'sort'       => 'sometimes|in:random,newest,top_rated,price_low,price_high,most_booked,nearest',
            'lat'        => 'required_if:sort,nearest|numeric|between:-90,90',
            'lng'        => 'required_if:sort,nearest|numeric|between:-180,180',
            'per_page'   => 'sometimes|integer|min:1|max:50', // rail = 15; "show more" list can ask for more
        ]);

        // Only show items from approved + active vendors, and only available items.
        // The item carries its own vendor (mini object) + primary image; discount
        // fields drive is_on_offer / discounted_price (added by the model).
        $query = VendorProduct::query()
            ->where('is_available', true)
            ->where('is_hidden', false) // vendor manually hidden -> not shown to customers
            ->whereHas('vendor', fn ($q) => $q->where('is_approved', true)->where('is_active', true))
            ->with([
                'vendor:id,business_name,vendor_type,booking_style,city,rating_avg,profile_image',
                'primaryImage',
            ]);

        // type -> the vendor's booking_style (service = appointment, product = order)
        if ($request->filled('type')) {
            $style = $request->type === 'service' ? 'appointment' : 'order';
            $query->whereHas('vendor', fn ($q) => $q->where('booking_style', $style));
        }

        // category -> a specific vendor_type
        if ($request->filled('category')) {
            $query->whereHas('vendor', fn ($q) => $q->where('vendor_type', $request->category));
        }

        // on_offer -> live discount only (Best Offers rail)
        if ($request->boolean('on_offer')) {
            $query->whereNotNull('discount_percent')
                ->where('discount_ends_at', '>', now());
        }

        // rating filter is on the vendor
        if ($request->filled('min_rating')) {
            $query->whereHas('vendor', fn ($q) => $q->where('rating_avg', '>=', $request->min_rating));
        }

        // price range on the item's own price
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // search on the item name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $this->applySort($query, $request);

        return response()->json([
            'status'   => 'success',
            'products' => $query->paginate((int) $request->input('per_page', 15))->withQueryString(),
        ]);
    }

    // Sorting. Rating/most_booked/nearest depend on the vendor, so they join it.
    private function applySort($query, Request $request): void
    {
        switch ($request->input('sort', 'random')) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;

            case 'price_high':
                $query->orderBy('price', 'desc');
                break;

            case 'top_rated':
                // "Top rated" for a product = its VENDOR's rating (we rate vendors,
                // not products). Order by the owner vendor's rating_avg.
                $query->orderByDesc(
                    \App\Models\Vendor::select('rating_avg')
                        ->whereColumn('vendors.id', 'vendor_products.vendor_id')
                );
                break;

            case 'most_booked':
                $query->withCount('bookings')->orderByDesc('bookings_count');
                break;

            case 'newest':
                $query->latest();
                break;

            case 'nearest':
                // Distance to the item's vendor (Haversine). Items whose vendor has
                // no saved location sort last.
                $query->select('vendor_products.*')
                    ->join('vendors', 'vendors.id', '=', 'vendor_products.vendor_id')
                    ->selectRaw(
                        '(6371 * acos(
                            cos(radians(?)) * cos(radians(vendors.latitude)) *
                            cos(radians(vendors.longitude) - radians(?)) +
                            sin(radians(?)) * sin(radians(vendors.latitude))
                        )) as distance_km',
                        [$request->lat, $request->lng, $request->lat]
                    )
                    ->orderByRaw('distance_km is null, distance_km asc');
                break;

            case 'random':
            default:
                // Default browsing feels fresh/varied. "Recently Added" has its own
                // rail via ?sort=newest, so the default is deliberately NOT newest.
                $query->inRandomOrder();
                break;
        }
    }
}
