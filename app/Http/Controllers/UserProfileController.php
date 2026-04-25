<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    // Get profile
    public function show(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user'   => $request->user()
        ]);
    }

    // Update profile
    public function update(Request $request)
    {
        $request->validate([
            'name'          => 'sometimes|string|max:255',
            'location'      => 'sometimes|string|max:255',
            'birth_date'    => 'sometimes|date',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();
        $data = $request->only(['name', 'location', 'birth_date']);

        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')
                ->store('profile_images', 'public');
        }

        $user->update($data);

        return response()->json([
            'status' => 'success',
            'user'   => $user
        ]);
    }

    // Delete profile image
    public function deleteImage(Request $request)
    {
        $user = $request->user();

        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
            $user->update(['profile_image' => null]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile image removed'
        ]);
    }
}