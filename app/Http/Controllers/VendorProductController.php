<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VendorProduct;
use Illuminate\Support\Facades\Storage;

class VendorProductController extends Controller
{

    public function index()
    {
        $products = VendorProduct::with('images')->get();

        return response()->json([
            'status'   => 'success',
            'products' => $products,
        ]);
    }




    public function getVendorProducts($vendorId)
    {
        $products = VendorProduct::where('vendor_id', $vendorId)
            ->where('is_available', true)
            ->with('images')
            ->get();

        return response()->json([
            'status'   => 'success',
            'products' => $products,
        ]);
    }

    public function searchVendorProducts(Request $request, $vendorId)
    {
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
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'sometimes|string',
            'price'       => 'required|numeric|min:0',
            'meta'        => 'sometimes|array',
            'images'      => 'sometimes|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png|max:2048',
            'primary_image_index' => 'sometimes|integer',
        ]);

        $vendor = $request->user();

        $product = VendorProduct::create([
            'vendor_id'   => $vendor->id,
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'meta'        => $request->meta ?? [],
        ]);

        if ($request->hasFile('images')) {
            $primaryIndex = $request->primary_image_index ?? 0;

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


    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                => 'sometimes|string|max:255',
            'description'         => 'sometimes|string',
            'price'               => 'sometimes|numeric|min:0',
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

        // تحديث البيانات الأساسية
        $product->update($request->only([
            'name',
            'description',
            'price',
            'meta'
        ]));

        // حذف صور محددة
        if ($request->has('delete_image_ids')) {
            $imagesToDelete = $product->images()
                ->whereIn('id', $request->delete_image_ids)
                ->get();

            foreach ($imagesToDelete as $image) {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            }
        }

        // إضافة صور جديدة
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
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $vendor = request()->user();

        $product = VendorProduct::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        // حذف الصور من السيرفر
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        // حذف المنتج (الصور بتتحذف تلقائياً بسبب cascadeOnDelete)
        $product->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'تم حذف المنتج'
        ]);
    }
}
