<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use Illuminate\Http\Request;

// Support conversations with the admin team, from the customer/vendor side.
//
// Two different behaviours by design:
//   USER   → tickets. Opening one = subject + first message (+ optional
//            booking + category). The user can only keep writing after an
//            admin replies (chat opened). Resolved = closed for good; a new
//            problem = a new ticket.
//   VENDOR → ONE persistent chat (the SUPPORT button). Always writable;
//            if an admin resolved it, a new vendor message reopens it.
class SupportController extends Controller
{
    // ── User: tickets ────────────────────────────────────────────

    // Open a ticket (creates the thread + its first message).
    public function storeTicket(Request $request)
    {
        $data = $request->validate([
            'subject'    => 'required|string|max:255',
            'message'    => 'required|string|max:5000',
            'booking_id' => 'sometimes|nullable|integer',
            'category'   => 'sometimes|nullable|in:no_show,payment,behavior,other',
        ]);

        $user = $request->user();

        // A ticket may point at one of the user's own bookings (gives the
        // admin the full context: vendor, product, payment).
        if (!empty($data['booking_id'])) {
            $owns = Booking::where('id', $data['booking_id'])
                ->where('user_id', $user->id)
                ->exists();

            if (! $owns) {
                return response()->json(['status' => 'error', 'message' => 'Booking not found'], 404);
            }
        }

        $thread = SupportThread::create([
            'owner_type'      => 'user',
            'owner_id'        => $user->id,
            'booking_id'      => $data['booking_id'] ?? null,
            'subject'         => $data['subject'],
            'category'        => $data['category'] ?? null,
            'status'          => 'open',
            'last_message_at' => now(),
        ]);

        $message = $thread->messages()->create([
            'sender_type' => 'user',
            'sender_id'   => $user->id,
            'body'        => $data['message'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Ticket opened — support will get back to you.',
            'ticket'  => $thread,
            'first_message' => $message,
        ], 201);
    }

    // The user's tickets, most recently active first.
    public function myTickets(Request $request)
    {
        $tickets = SupportThread::where('owner_type', 'user')
            ->where('owner_id', $request->user()->id)
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_type', 'admin')->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return response()->json(['status' => 'success', 'tickets' => $tickets]);
    }

    // One ticket + its conversation. Opening it marks admin replies as read.
    public function showTicket(Request $request, int $id)
    {
        $thread = SupportThread::where('owner_type', 'user')
            ->where('owner_id', $request->user()->id)
            ->with([
                'messages' => fn ($q) => $q->oldest(),
                'booking:id,vendor_id,vendor_product_id,booking_style,status',
                'booking.vendor:id,business_name,is_active,winding_down',
                'booking.product:id,name,price',
            ])
            ->findOrFail($id);

        SupportMessage::where('support_thread_id', $thread->id)
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'success', 'ticket' => $thread]);
    }

    // Reply on a ticket — only once support has replied (the admin decides
    // whether a ticket becomes a conversation).
    public function replyTicket(Request $request, int $id)
    {
        $data = $request->validate(['message' => 'required|string|max:5000']);

        $thread = SupportThread::where('owner_type', 'user')
            ->where('owner_id', $request->user()->id)
            ->findOrFail($id);

        if ($thread->status === 'resolved') {
            return response()->json([
                'status'  => 'error',
                'message' => 'This ticket is resolved. Open a new ticket if you need more help.',
            ], 422);
        }

        if ($thread->status === 'open') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Support has not replied yet — please wait for their response.',
            ], 422);
        }

        $message = $thread->messages()->create([
            'sender_type' => 'user',
            'sender_id'   => $request->user()->id,
            'body'        => $data['message'],
        ]);

        $thread->update(['last_message_at' => now()]);

        return response()->json(['status' => 'success', 'sent' => $message], 201);
    }

    // ── Vendor: one persistent chat ──────────────────────────────

    // The vendor's support chat (SUPPORT button). Created on first open.
    public function vendorThread(Request $request)
    {
        $thread = $this->getOrCreateVendorThread($request->user()->id);

        $thread->load(['messages' => fn ($q) => $q->oldest()]);

        SupportMessage::where('support_thread_id', $thread->id)
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'success', 'thread' => $thread]);
    }

    // Vendor sends a message. Writing to a resolved chat reopens it.
    public function vendorSend(Request $request)
    {
        $data = $request->validate(['message' => 'required|string|max:5000']);

        $thread = $this->getOrCreateVendorThread($request->user()->id);

        if ($thread->status === 'resolved') {
            $thread->status      = 'open';
            $thread->resolved_at = null;
        }

        $message = $thread->messages()->create([
            'sender_type' => 'vendor',
            'sender_id'   => $request->user()->id,
            'body'        => $data['message'],
        ]);

        $thread->last_message_at = now();
        $thread->save();

        return response()->json(['status' => 'success', 'sent' => $message], 201);
    }

    private function getOrCreateVendorThread(int $vendorId): SupportThread
    {
        return SupportThread::firstOrCreate(
            ['owner_type' => 'vendor', 'owner_id' => $vendorId],
            ['status' => 'open', 'last_message_at' => now()]
        );
    }
}
