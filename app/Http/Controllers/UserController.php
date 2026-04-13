<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function register(Request $request)
    {
        // 1. التحقق من البيانات
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users,phone', // التأكد أن الرقم فريد
        ]);

        // 2. إنشاء الزبون (بدون كلمة سر وبدور زبون تلقائي)
        $user = User::create([
            'name' => $fields['name'],
            'phone' => $fields['phone'],
            'role' => 'customer', // التثبيت على دور زبون
        ]);

        // 3. إصدار التوكن
        $token = $user->createToken('customerToken')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }
}