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
        ]);

        $vendor = $request->user();
        $data   = $request->only(['business_name', 'bio', 'birth_date', 'latitude', 'longitude', 'address', 'vendor_type', 'vendor_style']);

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
            'message' => __('messages.profile_image_removed')
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
            'message' => __('messages.cover_image_removed')
        ]);
    }

    // Vendor sets themselves online / offline for new bookings. Offline = hidden
    // from browse + can't receive new bookings, but stays logged in and manages
    // existing ones. Send { is_accepting_bookings: true|false }, or send nothing
    // to flip the current value.
    public function toggleAvailability(Request $request)
    {
        $request->validate([
            'is_accepting_bookings' => 'sometimes|boolean',
        ]);

        $vendor = $request->user();

        $vendor->update([
            'is_accepting_bookings' => $request->has('is_accepting_bookings')
                ? $request->boolean('is_accepting_bookings')
                : ! $vendor->is_accepting_bookings,
        ]);

        return response()->json([
            'status'                => 'success',
            'is_accepting_bookings' => $vendor->is_accepting_bookings,
        ]);
    }

    // Log out: revoke ONLY the token used for this request (other devices stay
    // logged in). Also clears the device's FCM token so it stops getting pushes.
    public function logout(Request $request)
    {
        $vendor = $request->user();
        $vendor->update(['fcm_token' => null]);
        $vendor->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.logged_out'),
        ]);
    }

    // Save / update the device FCM token (the app sends it on login)
    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['status' => 'success']);
    }

    // Set / update the vendor's OWN ShamCash account — just an id string, the
    // same shape as the platform's own SHAMCASH_ACCOUNT_ID env var. This is
    // where a payout goes when the vendor withdraws; it is NOT the platform's
    // account (customers pay INTO the platform's account, never the vendor's
    // directly). Not tied to registration — a vendor can work for a while
    // before ever setting this, but POST /vendor/withdraw refuses (422) until
    // it's set, so admin never gets a payout request with nowhere to send it.
    // Re-calling this endpoint overwrites the previous value (e.g. the vendor
    // changed accounts) — it is not a one-time-only lock.
    public function updateShamcashAccount(Request $request)
    {
        $request->validate([
            'shamcash_account' => 'required|string|max:255',
        ]);

        $vendor = $request->user();
        $vendor->update(['shamcash_account' => $request->shamcash_account]);

        return response()->json([
            'status'           => 'success',
            'message'          => __('messages.shamcash_account_saved'),
            'shamcash_account' => $vendor->shamcash_account,
        ]);
    }
}