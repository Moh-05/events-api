<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VendorAuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:15|regex:/^\+?[0-9]+$/'
        ]);

        // Normalize BEFORE it becomes a cache key, so "0933..." and "933..."
        // hit the same OTP entry and the same account on verify.
        $phone = PhoneNumberService::normalize($request->phone);

        $otp = rand(100000, 999999);
        Cache::put('otp_' . $phone, $otp, now()->addMinutes(5));

        $response = Http::asForm()->post("https://api.ultramsg.com/" . env('ULTRAMSG_INSTANCE_ID') . "/messages/chat", [
            'token' => env('ULTRAMSG_TOKEN'),
            'to'    => $phone,
            'body'  => "Verification Code: $otp",
        ]);

        $respData = $response->json();

        return response()->json([
            'message'           => __('messages.otp_sent'),
            'otp'               => $otp,
            'ultramsg_status'   => $response->status(),
            'ultramsg_response' => $respData,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:15|regex:/^\+?[0-9]+$/',
            'otp'   => 'required|integer|digits:6',
        ]);

        $phone = PhoneNumberService::normalize($request->phone);

        $cachedOtp = Cache::get('otp_' . $phone);
        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => __('messages.invalid_otp')], 400);
        }

        Cache::forget('otp_' . $phone);
        $vendor = Vendor::where('phone', $phone)->first();

        if ($vendor) {
            // Fully banned vendors can't log in. Data stays intact; unbanning
            // restores access. A winding-down vendor (banned but still finishing
            // existing bookings) can still log in to complete their obligations.
            if (!$vendor->is_active && !$vendor->winding_down) {
                return response()->json([
                    'status'  => 'suspended',
                    'message' => __('messages.account_suspended'),
                ], 403);
            }

            return response()->json([
                'status' => 'login',
                'token'  => $vendor->createToken('auth_token')->plainTextToken,
                'vendor' => $vendor
            ]);
        }

        $regToken = Str::random(64);
        Cache::put('reg_token_' . $regToken, $phone, now()->addMinutes(15));

        return response()->json([
            'status'             => 'new_vendor',
            'registration_token' => $regToken
        ]);
    }

    public function completeRegistration(Request $request)
    {
        $request->validate([
            'registration_token' => 'required|string|size:64',
            'first_name'         => 'required|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'last_name'          => 'required|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            // No more city for vendors — a vendor's location IS lat/lng. Both
            // required at registration (customers need to find vendors near
            // them from day one, not after a later profile edit).
            'latitude'           => 'required|numeric|between:-90,90',
            'longitude'          => 'required|numeric|between:-180,180',
            'address'            => 'sometimes|nullable|string|max:255',
            'birth_date'         => 'required|date|before:today|after:1900-01-01',
            'vendor_type'        => 'required|in:photographer,makeupArtist,dj,weddingHall,flowers,gifts,dresses,accessories,candles,cakes',
            'vendor_style'       => 'sometimes|in:service_provider,seller', // helper for Flutter; no backend logic
        ]);

        $phone = Cache::get('reg_token_' . $request->registration_token);
        if (!$phone) return response()->json(['message' => __('messages.expired')], 403);

        // Seller categories are order-based; every other (service) category is appointment-based.
        $sellerTypes  = ['flowers', 'gifts', 'dresses', 'accessories', 'candles', 'cakes'];
        $bookingStyle = in_array($request->vendor_type, $sellerTypes) ? 'order' : 'appointment';

        $vendor = Vendor::create([
            'phone'         => $phone,
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'latitude'      => $request->latitude,
            'longitude'     => $request->longitude,
            'address'       => $request->address,
            'birth_date'    => $request->birth_date,
            'vendor_type'   => $request->vendor_type,
            'booking_style' => $bookingStyle,
            'vendor_style'  => $request->vendor_style,
        ]);

        Cache::forget('reg_token_' . $request->registration_token);

        return response()->json([
            'status' => 'success',
            'token'  => $vendor->createToken('auth_token')->plainTextToken,
            'vendor' => $vendor,
        ]);
    }
}
