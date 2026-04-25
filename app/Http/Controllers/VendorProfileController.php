<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorProfileController extends Controller
{
    // Get profile
    public function show(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'vendor' => $request->user()
        ]);
    }

    // Setup vendor type (first step after register)
    public function setType(Request $request)
    {
        $request->validate([
            'vendor_type' => 'required|in:wedding_venue,photographer,cake_shop,dj,catering,beauty,decor,accessories',
        ]);

        $vendor = $request->user();

        $bookingStyle = in_array($request->vendor_type, ['cake_shop', 'decor', 'accessories', 'catering'])
            ? 'order'
            : 'appointment';

        $vendor->update([
            'vendor_type'   => $request->vendor_type,
            'booking_style' => $bookingStyle,
        ]);

        return response()->json([
            'status'        => 'success',
            'vendor_type'   => $vendor->vendor_type,
            'booking_style' => $vendor->booking_style,
        ]);
    }

    // Complete profile setup
    public function  update(Request $request)
    {
        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'location'      => 'sometimes|string|max:255',
            'bio'           => 'sometimes|string|max:1000',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
            'birth_date'=>'sometimes|date',
        ]);

        $vendor = $request->user();
        $data   = $request->only(['business_name', 'location', 'bio','birth_date']);

        if ($request->hasFile('profile_image')) {
            if ($vendor->profile_image) {
                Storage::disk('public')->delete($vendor->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')
                ->store('vendor_images', 'public');
        }

        $vendor->update($data);

        return response()->json([
            'status' => 'success',
            'vendor' => $vendor
        ]);
    }

    // Delete profile image
    public function deleteImage(Request $request)
    {
        $vendor = $request->user();

        if ($vendor->profile_image) {
            Storage::disk('public')->delete($vendor->profile_image);
            $vendor->update(['profile_image' => null]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile image removed'
        ]);
    }
}