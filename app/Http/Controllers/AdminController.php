<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ── Dashboard ────────────────────────────────────────────────
    // super_admin only

    public function dashboard()
    {
        return response()->json([
            'status' => 'success',
            'stats'  => [
                'total_users'     => User::count(),
                'total_vendors'   => Vendor::where('is_approved', true)->count(),
                'active_bookings' => Booking::whereIn('status', ['pending', 'approved'])->count(),
                'revenue_today'   => Payment::whereDate('created_at', today())->sum('commission'),
                'revenue_month'   => Payment::whereMonth('created_at', now()->month)->sum('commission'),
            ],
        ]);
    }

    // ── Vendors ──────────────────────────────────────────────────

    // All vendors — super_admin only
    public function vendors()
    {
        $vendors = Vendor::latest()->get();

        return response()->json(['status' => 'success', 'vendors' => $vendors]);
    }

    // Pending KYC — super_admin + support
    public function pendingVendors()
    {
        $vendors = Vendor::where('is_approved', false)->latest()->get();

        return response()->json(['status' => 'success', 'vendors' => $vendors]);
    }

    // Approve vendor — super_admin + support
    public function approveVendor($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_approved' => true]);

        return response()->json(['status' => 'success', 'message' => 'Vendor approved']);
    }


    // Reject vendor — super_admin + support
    public function rejectVendor(Request $request, $id)
    {
        $request->validate(['reason' => 'sometimes|nullable|string']);

        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_approved' => false, 'is_active' => false]);

        return response()->json(['status' => 'success', 'message' => 'Vendor rejected']);
    }

    // Toggle active — super_admin only
    public function toggleVendor($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_active' => !$vendor->is_active]);

        return response()->json([
            'status'    => 'success',
            'is_active' => $vendor->is_active,
        ]);
    }

    // ── Users ────────────────────────────────────────────────────
    // super_admin only

    public function users()
    {
        $users = User::latest()->get();

        return response()->json(['status' => 'success', 'users' => $users]);
    }

    // ── Bookings ─────────────────────────────────────────────────
    // super_admin only

    public function bookings(Request $request)
    {
        $query = Booking::with(['user', 'vendor', 'product'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['status' => 'success', 'bookings' => $query->get()]);
    }

    // ── Payments ─────────────────────────────────────────────────
    // super_admin only

    public function payments()
    {
        $payments = Payment::with(['booking.user', 'booking.vendor'])->latest()->get();

        return response()->json(['status' => 'success', 'payments' => $payments]);
    }
}
