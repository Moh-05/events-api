<?php

namespace App\Http\Controllers;

use App\Models\VendorProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
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