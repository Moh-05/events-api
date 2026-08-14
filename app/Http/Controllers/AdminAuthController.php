<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Normalize email so "Admin@Haflati.com " and "admin@haflati.com" match.
        $email = strtolower(trim($request->email));
        $admin = Admin::where('email', $email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        // Track the last successful login.
        $admin->update(['last_login_at' => now()]);

        return response()->json([
            'status' => 'success',
            'token'  => $admin->createToken('admin_token', ['admin'])->plainTextToken,
            'admin'  => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
                'role'  => $admin->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.logged_out'),
        ]);
    }
}
