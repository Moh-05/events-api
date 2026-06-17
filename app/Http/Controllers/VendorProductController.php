<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Support\Facades\Storage;

class VendorProductController extends Controller
{
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
        // A banned vendor's work is hidden from customers (data stays in the DB).
        if (!Vendor::active()->whereKey($vendorId)->exists()) {
            return response()->json([
                'status'   => 'success',
                'products' => [],
                'note'     => 'Vendor unavailable',
            ]);
        }

        $query = VendorProduct::where('vendor_id', $vendorId)
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
                $path = $image->store('product_images', 'public');

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
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            }
        }

        // Upload and attach new images
        if ($request->hasFile('images')) {
            $primaryIndex = $request->primary_image_index ?? null;

            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('product_images', 'public');

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
            ->with('images')
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
            ->with('images')
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
            Storage::disk('public')->delete($image->image_path);
        }

        $product->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Product deleted successfully',
        ]);
    }
}
