<?php

namespace App\Http\Controllers;

use App\Models\SavedItem;
use Illuminate\Http\Request;

class SavedItemController extends Controller
{
    // List the current user's saved items, split into the two tabs the "Saved"
    // screen shows: Packages (appointment vendors — venues, photographers, DJs)
    // and Products (order vendors — cake shops, stores).
    //
    // Applies the FULL customer visibility rule, the same one browse/search use:
    // the vendor must be approved AND active, and the product available AND not
    // hidden. Anything failing that drops out of the list but stays saved in the
    // DB, so it reappears if the vendor un-hides it or restocks. Checking only
    // is_active here used to leak a vendor-hidden product into every user's
    // Saved screen.
    public function index(Request $request)
    {
        $saved = SavedItem::where('user_id', $request->user()->id)
            ->whereHas('product', fn ($q) => $q->where('is_available', true)->where('is_hidden', false))
            ->whereHas('product.vendor', fn ($q) => $q->where('is_approved', true)->where('is_active', true))
            ->with([
                'product.images',
                // The Vendor model appends account_status (needs is_active +
                // winding_down) and cover_image_url (needs cover_image); a
                // column-limited select must carry those source columns, or the
                // appended attributes silently compute on null (wrong data).
                'product.vendor:id,business_name,profile_image,cover_image,vendor_type,booking_style,is_active,winding_down',
            ])
            ->latest()
            ->get();

        $packages = $saved->filter(
            fn ($item) => $item->product->vendor->booking_style === 'appointment'
        )->values();

        $products = $saved->filter(
            fn ($item) => $item->product->vendor->booking_style === 'order'
        )->values();

        return response()->json([
            'status' => 'success',
            'counts' => [
                'packages' => $packages->count(),
                'products' => $products->count(),
            ],
            'packages' => $packages,
            'products' => $products,
        ]);
    }

    // Lightweight helper for the BROWSE / SEARCH / product-detail screens (not
    // the Saved screen itself). Returns ONLY the ids of the products this user
    // has saved, e.g. [5, 7, 12]. The app fetches this once, then fills the
    // heart icon "dark" on any product card whose id is in the list — so the
    // heart shows the correct state everywhere, not just inside the Saved tab.
    // Why a separate endpoint: GET /saved already returns this info, but it
    // ships every product's full data; this one is tiny and fast when all you
    // need is "which hearts are on".
    // Same visibility rule as index(), so a heart can never point at a product
    // the customer can no longer see anywhere else.
    public function ids(Request $request)
    {
        $ids = SavedItem::where('user_id', $request->user()->id)
            ->whereHas('product', fn ($q) => $q->where('is_available', true)->where('is_hidden', false))
            ->whereHas('product.vendor', fn ($q) => $q->where('is_approved', true)->where('is_active', true))
            ->pluck('vendor_product_id');

        return response()->json([
            'status' => 'success',
            'ids'    => $ids,
        ]);
    }

    // Save a product. Idempotent — saving twice is a no-op, never a duplicate
    // row (guarded by the unique constraint).
    public function store(Request $request)
    {
        $request->validate([
            'vendor_product_id' => 'required|exists:vendor_products,id',
        ]);

        $item = SavedItem::firstOrCreate([
            'user_id'           => $request->user()->id,
            'vendor_product_id' => $request->vendor_product_id,
        ]);

        return response()->json([
            'status' => 'success',
            'item'   => $item->load('product.images'),
        ], $item->wasRecentlyCreated ? 201 : 200);
    }

    // Remove a saved product by product id. DELETE is idempotent — removing
    // something that isn't saved still returns success.
    public function destroy(Request $request, $productId)
    {
        SavedItem::where('user_id', $request->user()->id)
            ->where('vendor_product_id', $productId)
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Removed from saved',
        ]);
    }
}
