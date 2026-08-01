<?php

namespace App\Http\Controllers;

use App\Models\PortfolioItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortfolioController extends Controller
{
    // Vendor's own portfolio (auth:vendors)
    public function index(Request $request)
    {
        $items = PortfolioItem::where('vendor_id', $request->user()->id)
            ->with('images')
            ->latest()
            ->get();

        return response()->json([
            'status'    => 'success',
            'portfolio' => $items,
        ]);
    }

    // Public — any vendor's portfolio
    public function vendorPortfolio(Request $request, $vendorId)
    {
        $items = PortfolioItem::where('vendor_id', $vendorId)
            ->with('images')
            ->latest()
            ->get();

        return response()->json([
            'status'    => 'success',
            'portfolio' => $items,
        ]);
    }

    // Single item with all its images (detail view: one primary + the rest)
    public function show(Request $request, $id)
    {
        $item = PortfolioItem::where('id', $id)
            ->where('vendor_id', $request->user()->id)
            ->with('images')
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'item'   => $item,
        ]);
    }

    // Create an item — title/description optional, images required
    public function store(Request $request)
    {
        $request->validate([
            'title'               => 'sometimes|nullable|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'images'              => 'required|array',
            'images.*'            => 'image|mimes:jpg,jpeg,png|max:2048',
            'primary_image_index' => 'sometimes|integer',
        ]);

        $vendor = $request->user();

        $item = PortfolioItem::create([
            'vendor_id'   => $vendor->id,
            'title'       => $request->title,
            'description' => $request->description,
        ]);

        if ($request->hasFile('images')) {
            $primaryIndex = (int) ($request->primary_image_index ?? 0);
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('portfolio_images', 'public');

                $item->images()->create([
                    'image_path' => $path,
                    'is_primary' => $index === $primaryIndex,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'item'   => $item->load('images'),
        ]);
    }

    // Update item data and manage images (add new / delete existing)
    public function update(Request $request, $id)
    {
        $request->validate([
            'title'               => 'sometimes|nullable|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'images'              => 'sometimes|array',
            'images.*'            => 'image|mimes:jpg,jpeg,png|max:2048',
            'primary_image_index' => 'sometimes|integer',
            'delete_image_ids'    => 'sometimes|array',
            'delete_image_ids.*'  => 'integer',
        ]);

        $vendor = $request->user();

        $item = PortfolioItem::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $item->update($request->only(['title', 'description']));

        // Delete selected images from storage and DB
        if ($request->has('delete_image_ids')) {
            $imagesToDelete = $item->images()
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
                $path = $image->store('portfolio_images', 'public');

                $item->images()->create([
                    'image_path' => $path,
                    'is_primary' => $index === $primaryIndex,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'item'   => $item->load('images'),
        ]);
    }

    // Delete an item and all its images from storage
    public function destroy(Request $request, $id)
    {
        $vendor = $request->user();

        $item = PortfolioItem::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        foreach ($item->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $item->delete();

        // The content is gone — drop any moderation reports pointing at it
        // (same cleanup the admin-side delete does).
        \App\Models\ContentReport::where('reportable_type', 'portfolio_item')
            ->where('reportable_id', $item->id)
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Portfolio item deleted',
        ]);
    }
}
