<?php

namespace App\Http\Controllers;
use App\Models\Vendor;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
class AuthVendorController extends Controller
{
    //
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:15|regex:/^\+?[0-9]+$/'
        ]);

        $otp = rand(100000, 999999);
        Cache::put('otp_'.$request->phone, $otp, now()->addMinutes(5));

        $response = Http::asForm()->post("https://api.ultramsg.com/".env('ULTRAMSG_INSTANCE_ID')."/messages/chat", [
            'token' => env('ULTRAMSG_TOKEN'),
            'to'    => $request->phone,
            'body'  => "Verification Code: $otp",
        ]);

        $respData = $response->json();

        return response()->json([
            'message'           => 'OTP sent',
            'otp'               => $otp, // remove in production
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

        $cachedOtp = Cache::get('otp_'.$request->phone);
        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        Cache::forget('otp_'.$request->phone);
        $user = Vendor::where('phone', $request->phone)->first();

        if ($user) {
            return response()->json([
                'status' => 'login',
                'token'  => $user->createToken('auth_token')->plainTextToken,
                'user'   => $user
            ]);
        }

        $regToken = Str::random(64);
        Cache::put('reg_token_'.$regToken, $request->phone, now()->addMinutes(15));

        return response()->json(['status' => 'new_user', 'registration_token' => $regToken]);
    }

    public function completeRegistration(Request $request)
    {
        $request->validate([
            'registration_token' => 'required|string|size:64',
            'name'               => 'required|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'birth_date'         => 'required|date|before:today|after:1900-01-01',
        ]);

        $phone = Cache::get('reg_token_'.$request->registration_token);
        if (!$phone) return response()->json(['message' => 'Expired'], 403);

        $user = Vendor::create([
            'phone'      => $phone,
            'name'       => $request->name,
            'birth_date' => $request->birth_date,
        ]);

        Cache::forget('reg_token_'.$request->registration_token);

        return response()->json([
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user'  => $user
        ]);
    }
}
