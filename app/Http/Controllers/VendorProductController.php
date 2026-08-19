<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\StoresImages;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Support\Facades\Storage;

class VendorProductController extends Controller
{
    use StoresImages;

    // Get all products across all vendors
    public function index()
    {
        $products = VendorProduct::with('images')->get();

        return response()->json([
            'status'   => 'success',
            'products' => $products,
        ]);
    }

    // Get all available products for a specific vendor
    public function getVendorProducts(Request $request)
    {
        $user = $request->user();

        $products = VendorProduct::where('vendor_id', $user->id)
            ->where('is_available', true)
            ->with('images')
            ->get();

        return response()->json([
            'status'   => 'success',
            'products' => $products,
        ]);
    }

    // Search products by name within a specific vendor
    public function searchVendorProducts(Request $request, $vendorId)
    {
        // A banned OR not-yet-approved vendor's work is hidden (data stays in DB).
        if (!Vendor::whereKey($vendorId)->where('is_approved', true)->where('is_active', true)->exists()) {
            return response()->json([
                'status'   => 'success',
                'products' => [],
                'note'     => 'Vendor unavailable',
            ]);
        }

        // Public list — show only available, non-hidden products (same visibility
        // rule as GET /products).
        $query = VendorProduct::where('vendor_id', $vendorId)
            ->where('is_available', true)
            ->where('is_hidden', false)
            ->with('images');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        return response()->json([
            'status'   => 'success',
            'products' => $query->get(),
        ]);
    }

    // Create a new product — name, price, description are optional, images are required
    public function store(Request $request)
    {
        $request->validate([
            'name'                => 'sometimes|nullable|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'price'               => 'sometimes|nullable|numeric|min:0',
            'stock'               => 'sometimes|nullable|integer|min:0',
            'meta'                => 'sometimes|array',
            'images'              => 'required|array',
            'images.*'            => 'image|mimes:jpg,jpeg,png|max:2048',
            'primary_image_index' => 'sometimes|integer',
        ]);

        $vendor = $request->user();

        $product = VendorProduct::create([
            'vendor_id'   => $vendor->id,
            'name'        => $request->name ?? null,
            'description' => $request->description ?? null,
            'price'       => $request->price ?? null,
            'stock'       => $request->stock ?? null,
            'meta'        => $request->meta ?? [],
        ]);

        if ($request->hasFile('images')) {
            $primaryIndex = (int) ($request->primary_image_index ?? 0);
            foreach ($request->file('images') as $index => $image) {
                $path = $this->storeImageOrFail($image, 'product_images');

                $product->images()->create([
                    'image_path' => $path,
                    'is_primary' => $index === $primaryIndex,

                ]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'product' => $product->load('images'),
        ]);
    }

    // Get a single product — vendor can only access their own
    public function show(Request $request, $id)
    {
        $vendor = $request->user();

        $product = VendorProduct::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->with('images')
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'product' => $product,
        ]);
    }

    // Update product data and manage images (add new / delete existing)
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                => 'sometimes|nullable|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'price'               => 'sometimes|nullable|numeric|min:0',
            'stock'               => 'sometimes|nullable|integer|min:0',
            'meta'                => 'sometimes|array',
            'images'              => 'sometimes|array',
            'images.*'            => 'image|mimes:jpg,jpeg,png|max:2048',
            'primary_image_index' => 'sometimes|integer',
            'delete_image_ids'    => 'sometimes|array',
            'delete_image_ids.*'  => 'integer',
        ]);

        $vendor = $request->user();

        $product = VendorProduct::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $product->update($request->only([
            'name',
            'description',
            'price',
            'stock',
            'meta',
        ]));

        // Delete selected images from storage and DB
        if ($request->has('delete_image_ids')) {
            $imagesToDelete = $product->images()
                ->whereIn('id', $request->delete_image_ids)
                ->get();

            foreach ($imagesToDelete as $image) {
                Storage::disk('supabase')->delete($image->image_path);
                $image->delete();
            }
        }

        // Upload and attach new images
        if ($request->hasFile('images')) {
            $primaryIndex = $request->primary_image_index ?? null;

            foreach ($request->file('images') as $index => $image) {
                $path = $this->storeImageOrFail($image, 'product_images');

                $product->images()->create([
                    'image_path' => $path,
                    'is_primary' => $index === $primaryIndex,
                ]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'product' => $product->load('images'),
        ]);
    }

    // Best sellers — top products by number of bookings/orders (order vendors dashboard)
    public function bestSellers(Request $request)
    {
        $vendor = $request->user();

        // Count bookings per product, only paid/active ones count as a sale
        $products = VendorProduct::where('vendor_id', $vendor->id)
            ->withCount(['bookings' => function ($q) {
                $q->whereIn('status', ['pending', 'approved', 'completed']);
            }])
            ->with('primaryImage')
            ->orderByDesc('bookings_count')
            ->take(5)
            ->get();

        return response()->json([
            'status'   => 'success',
            'products' => $products,
        ]);
    }

    // Low-stock alerts — products at or below the threshold (order vendors inventory)
    public function lowStock(Request $request)
    {
        $vendor    = $request->user();
        $threshold = (int) $request->query('threshold', 5);

        $products = VendorProduct::where('vendor_id', $vendor->id)
            ->whereNotNull('stock') // only products that track stock
            ->where('stock', '<=', $threshold)
            ->with('primaryImage')
            ->orderBy('stock', 'asc')
            ->get();

        return response()->json([
            'status'    => 'success',
            'threshold' => $threshold,
            'count'     => $products->count(),
            'products'  => $products,
        ]);
    }

    // Delete product and remove all associated images from storage
    public function destroy(Request $request, $id)
    {
        $vendor = $request->user();

        $product = VendorProduct::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        foreach ($product->images as $image) {
            Storage::disk('supabase')->delete($image->image_path);
        }

        $product->delete();

        // The content is gone — drop any moderation reports pointing at it
        // (same cleanup the admin-side delete does).
        \App\Models\ContentReport::where('reportable_type', 'product')
            ->where('reportable_id', $product->id)
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.product_deleted'),
        ]);
    }

    // Vendor puts an existing product/service on offer. Rules:
    //  - percent 1..90, on an item the vendor already owns
    //  - starts NOW, ends on a date the vendor picks, at most 1 month out
    //  - can't start a new offer within 1 week of the previous one ending
    // Haflati's commission is still taken on the ORIGINAL price at payment time
    // (handled in PaymentController) — the vendor carries the whole discount.
    public function setDiscount(Request $request, $id)
    {
        $request->validate([
            'discount_percent' => 'required|numeric|min:1|max:90',
            'ends_at'          => 'required|date|after:now|before_or_equal:' . now()->addMonth()->toDateTimeString(),
        ]);

        $vendor  = $request->user();
        $product = VendorProduct::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        // Block a new offer while one is still live.
        if ($product->is_on_offer) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.offer_already_active'),
            ], 409);
        }

        // 1-week cooldown after the previous offer ended (anti-abuse: stops a
        // vendor faking a permanent "discount" off an inflated original price).
        if ($product->discount_last_ended_at && $product->discount_last_ended_at->gt(now()->subWeek())) {
            $availableAt = $product->discount_last_ended_at->copy()->addWeek();
            return response()->json([
                'status'       => 'error',
                'message'      => __('messages.offer_cooldown'),
                'available_at' => $availableAt->toIso8601String(),
            ], 409);
        }

        $product->update([
            'discount_percent' => $request->discount_percent,
            'discount_ends_at' => $request->ends_at,
        ]);
        $product->refresh();

        return response()->json([
            'status'  => 'success',
            'product' => $product,
        ]);
    }

    // Vendor ends an offer early. Records when it ended so the 1-week cooldown
    // starts from now (can't instantly re-add).
    public function removeDiscount(Request $request, $id)
    {
        $vendor  = $request->user();
        $product = VendorProduct::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        if ($product->discount_percent === null) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.no_active_offer'),
            ], 404);
        }

        $product->update([
            'discount_percent'       => null,
            'discount_ends_at'       => null,
            'discount_last_ended_at' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.offer_removed'),
        ]);
    }

    // Vendor hides / shows a product. Hidden = the item stays in the vendor's own
    // list but is not shown to customers (browse, search, discovery) and can't be
    // booked, until the vendor shows it again. Separate from is_available, which
    // the stock system controls automatically. Send { is_hidden: true|false } or
    // nothing to flip.
    public function toggleHidden(Request $request, $id)
    {
        $request->validate([
            'is_hidden' => 'sometimes|boolean',
        ]);

        $vendor  = $request->user();
        $product = VendorProduct::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $product->update([
            'is_hidden' => $request->has('is_hidden')
                ? $request->boolean('is_hidden')
                : ! $product->is_hidden,
        ]);

        return response()->json([
            'status'    => 'success',
            'is_hidden' => $product->is_hidden,
        ]);
    }
}
