<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorProfileController extends Controller
{
    use StoresImages;

    public function show(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'vendor' => $request->user()
        ]);
    }

    // Complete profile setup
    public function update(Request $request)
    {
        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'bio'           => 'sometimes|string|max:1000',
            'birth_date'    => 'sometimes|date',
            'latitude'      => 'sometimes|numeric|between:-90,90',
            'longitude'     => 'sometimes|numeric|between:-180,180',
            'address'       => 'sometimes|string|max:255',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
            'cover_image'   => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
            'vendor_type'   => 'sometimes|in:photographer,makeupArtist,dj,weddingHall,flowers,gifts,dresses,accessories,candles,cakes',
            'vendor_style'  => 'sometimes|in:service_provider,seller', // helper for Flutter; no backend logic
            'response_time' => 'sometimes|in:within_1h,within_2h,within_3h,within_24h',
        ]);

        $vendor = $request->user();
        $data   = $request->only(['business_name', 'bio', 'birth_date', 'latitude', 'longitude', 'address', 'vendor_type', 'vendor_style', 'response_time']);

        if ($request->filled('vendor_type')) {
            // Seller categories are order-based; every other (service) category is appointment-based.
            $sellerTypes = ['flowers', 'gifts', 'dresses', 'accessories', 'candles', 'cakes'];
            $data['booking_style'] = in_array($request->vendor_type, $sellerTypes) ? 'order' : 'appointment';
        }

        if ($request->hasFile('profile_image')) {
            if ($vendor->profile_image) {
                Storage::disk('supabase')->delete($vendor->profile_image);
            }
            $data['profile_image'] = $this->storeImageOrFail(
                $request->file('profile_image'),
                'vendor_images'
            );
        }

        if ($request->hasFile('cover_image')) {
            if ($vendor->cover_image) {
                Storage::disk('supabase')->delete($vendor->cover_image);
            }
            $data['cover_image'] = $this->storeImageOrFail(
                $request->file('cover_image'),
                'vendor_covers'
            );
        }

        $vendor->update($data);

        return response()->json([
            'status' => 'success',
            'vendor' => $vendor
        ]);
    }

    public function deleteImage(Request $request)
    {
        $vendor = $request->user();

        if ($vendor->profile_image) {
            Storage::disk('supabase')->delete($vendor->profile_image);
            $vendor->update(['profile_image' => null]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile image removed'
        ]);
    }

    public function deleteCover(Request $request)
    {
        $vendor = $request->user();

        if ($vendor->cover_image) {
            Storage::disk('supabase')->delete($vendor->cover_image);
            $vendor->update(['cover_image' => null]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Cover image removed'
        ]);
    }

    // Save / update the device FCM token (the app sends it on login)
    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['status' => 'success']);
    }
}