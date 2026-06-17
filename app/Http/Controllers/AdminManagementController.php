<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Managing admin accounts — super_admin ONLY.
// The super_admin hires/removes support staff here.
class AdminManagementController extends Controller
{
    // List all admin accounts
    public function index()
    {
        $admins = Admin::select('id', 'name', 'email', 'role', 'last_login_at', 'created_at')
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'admins' => $admins]);
    }

    // Create a new admin (normally a support account)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
            'role'     => ['required', Rule::in(['super_admin', 'support'])],
        ]);

        $data['email'] = strtolower(trim($data['email']));

        $admin = Admin::create($data);

        AdminAuditLog::record(
            $request->user()->id,
            'admin.create',
            'admin',
            $admin->id,
            ['role' => $admin->role]
        );

        return response()->json([
            'status' => 'success',
            'admin'  => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
                'role'  => $admin->role,
            ],
        ], 201);
    }

    // Delete an admin account
    public function destroy(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);

        // A super_admin can't delete their own account (avoid locking yourself out).
        if ($admin->id === $request->user()->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $admin->delete();

        AdminAuditLog::record($request->user()->id, 'admin.delete', 'admin', $admin->id);

        return response()->json(['status' => 'success', 'message' => 'Admin deleted']);
    }
}
