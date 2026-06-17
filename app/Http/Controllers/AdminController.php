<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ── Dashboard ────────────────────────────────────────────────
    // view — super_admin + support

    public function dashboard()
    {
        return response()->json([
            'status' => 'success',
            'stats'  => [
                'total_users'      => User::count(),
                'total_vendors'    => Vendor::count(),
                'approved_vendors' => Vendor::where('is_approved', true)->count(),
                'pending_vendors'  => Vendor::where('is_approved', false)->count(),
                'active_bookings'  => Booking::whereIn('status', ['pending', 'approved'])->count(),
                'revenue_today'    => Payment::whereDate('created_at', today())->sum('commission'),
                'revenue_month'    => Payment::whereMonth('created_at', now()->month)->sum('commission'),
            ],
        ]);
    }

    // ── Vendors ──────────────────────────────────────────────────

    // All vendors (paginated) — view: super_admin + support
    public function vendors(Request $request)
    {
        $query = Vendor::latest();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'status'  => 'success',
            'vendors' => $query->paginate(20),
        ]);
    }

    // Single vendor detail (for KYC review) — view: super_admin + support
    public function vendorDetail($id)
    {
        $vendor = Vendor::with('products')->findOrFail($id);

        return response()->json(['status' => 'success', 'vendor' => $vendor]);
    }

    // Pending KYC (paginated) — view: super_admin + support
    public function pendingVendors()
    {
        $vendors = Vendor::where('is_approved', false)->latest()->paginate(20);

        return response()->json(['status' => 'success', 'vendors' => $vendors]);
    }

    // Approve KYC — super_admin + support
    public function approveVendor(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_approved' => true, 'rejection_reason' => null]);

        AdminAuditLog::record($request->user()->id, 'vendor.approve', 'vendor', $vendor->id);

        return response()->json(['status' => 'success', 'message' => 'Vendor approved']);
    }

    // Reject KYC — super_admin + support
    // NOTE: this is KYC rejection only (is_approved = false). It does NOT ban an
    // already-active vendor — banning is a separate super_admin action (toggleVendor).
    public function rejectVendor(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $vendor = Vendor::findOrFail($id);
        $vendor->update([
            'is_approved'      => false,
            'rejection_reason' => $request->reason,
        ]);

        AdminAuditLog::record(
            $request->user()->id,
            'vendor.reject',
            'vendor',
            $vendor->id,
            ['reason' => $request->reason]
        );

        return response()->json(['status' => 'success', 'message' => 'Vendor KYC rejected']);
    }

    // Ban / unban a vendor (toggle is_active) — super_admin ONLY
    public function toggleVendor(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_active' => !$vendor->is_active]);

        AdminAuditLog::record(
            $request->user()->id,
            $vendor->is_active ? 'vendor.unban' : 'vendor.ban',
            'vendor',
            $vendor->id
        );

        return response()->json([
            'status'    => 'success',
            'is_active' => $vendor->is_active,
        ]);
    }

    // ── Users ────────────────────────────────────────────────────

    // All users (paginated) — view: super_admin + support
    public function users()
    {
        $users = User::latest()->paginate(20);

        return response()->json(['status' => 'success', 'users' => $users]);
    }

    // Ban / unban a user (toggle is_active) — super_admin ONLY
    public function toggleUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);

        AdminAuditLog::record(
            $request->user()->id,
            $user->is_active ? 'user.unban' : 'user.ban',
            'user',
            $user->id
        );

        return response()->json([
            'status'    => 'success',
            'is_active' => $user->is_active,
        ]);
    }

    // ── Bookings ─────────────────────────────────────────────────
    // view: super_admin + support

    public function bookings(Request $request)
    {
        $query = Booking::with(['user', 'vendor', 'vendor_product'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status'   => 'success',
            'bookings' => $query->paginate(20),
        ]);
    }

    // ── Payments ─────────────────────────────────────────────────
    // super_admin ONLY (money)

    public function payments()
    {
        $payments = Payment::with(['booking.user', 'booking.vendor'])->latest()->paginate(20);

        return response()->json(['status' => 'success', 'payments' => $payments]);
    }

    // ── Audit log ────────────────────────────────────────────────
    // super_admin ONLY — who did what, when

    public function auditLogs()
    {
        $logs = AdminAuditLog::with('admin:id,name,email,role')->latest()->paginate(30);

        return response()->json(['status' => 'success', 'logs' => $logs]);
    }
}
