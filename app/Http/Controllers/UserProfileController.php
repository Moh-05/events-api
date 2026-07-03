<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user'   => $request->user()
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'first_name'    => 'sometimes|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'last_name'     => 'sometimes|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'city'          => 'sometimes|nullable|string|min:2|max:100',
            'birth_date'    => 'sometimes|date',
            'latitude'      => 'sometimes|numeric|between:-90,90',
            'longitude'     => 'sometimes|numeric|between:-180,180',
            'address'       => 'sometimes|string|max:255',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();
        $data = $request->only(['first_name', 'last_name', 'city', 'birth_date', 'latitude', 'longitude', 'address']);

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('supabase')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')
                ->store('profile_images', 'supabase');
        }

        $user->update($data);

        return response()->json([
            'status' => 'success',
            'user'   => $user
        ]);
    }

    public function deleteImage(Request $request)
    {
        $user = $request->user();

        if ($user->profile_image) {
            Storage::disk('supabase')->delete($user->profile_image);
            $user->update(['profile_image' => null]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile image removed'
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
