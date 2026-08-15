<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// WhatsApp-style chat between a user and a vendor. One conversation per pair.
// The SAME controller serves both sides: the caller (user or vendor) is resolved
// from the auth guard, exactly like NotificationController does. The gate — you
// can only chat a vendor you have PAID — lives in openWithVendor().
class ChatController extends Controller
{
    // List the caller's conversations, most recent activity first (the chat list).
    public function index(Request $request)
    {
        [$side, $id] = $this->actor($request);
        $otherSide   = $side === 'user' ? 'vendor' : 'user';

        $conversations = Conversation::query()
            ->where($side === 'user' ? 'user_id' : 'vendor_id', $id)
            // load the OTHER party for the row header. Vendor is safe whole
            // (fcm_token is $hidden); User is column-limited on purpose because
            // User does NOT hide fcm_token.
            ->when($side === 'user', fn ($q) => $q->with('vendor'))
            ->when($side === 'vendor', fn ($q) => $q->with('user:id,first_name,last_name,profile_image'))
            ->with('latestMessage')
            // unread = messages the OTHER side sent that I haven't read yet
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_type', $otherSide)
                ->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'status'        => 'success',
            'conversations' => $conversations,
        ]);
    }

    // USER only: open (or create) the conversation with a given vendor. This is
    // the gate — a conversation can only come into existence once the user has a
    // verified payment with that vendor. After that it stays open for good.
    public function openWithVendor(Request $request, $vendorId)
    {
        $userId = $request->user()->id;

        $vendor = Vendor::find($vendorId);
        if (! $vendor) {
            return response()->json(['status' => 'error', 'message' => 'Vendor not found'], 404);
        }

        if (! $this->hasPaidBooking($userId, $vendor->id)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You can chat a vendor only after paying for a booking with them.',
            ], 403);
        }

        $conversation = Conversation::firstOrCreate([
            'user_id'   => $userId,
            'vendor_id' => $vendor->id,
        ]);

        $conversation->load('vendor');

        return response()->json([
            'status'       => 'success',
            'conversation' => $conversation,
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    // Fetch a conversation's messages, oldest first. Pass ?after={lastMessageId}
    // to get ONLY newer messages — this is how the app polls for new ones.
    public function messages(Request $request, $id)
    {
        $conversation = $this->authorizedConversation($request, $id);
        if (! $conversation) {
            return response()->json(['status' => 'error', 'message' => 'Conversation not found'], 404);
        }

        $messages = $conversation->messages()
            ->when($request->query('after'), fn ($q, $after) => $q->where('id', '>', $after))
            ->orderBy('id')
            ->get();

        return response()->json([
            'status'   => 'success',
            'messages' => $messages,
        ]);
    }

    // Send a message into a conversation the caller belongs to.
    public function send(Request $request, $id)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        [$side, $senderId] = $this->actor($request);

        $conversation = $this->authorizedConversation($request, $id);
        if (! $conversation) {
            return response()->json(['status' => 'error', 'message' => 'Conversation not found'], 404);
        }

        $message = $conversation->messages()->create([
            'sender_type' => $side,
            'sender_id'   => $senderId,
            'body'        => $data['body'],
        ]);

        // bump the conversation so it jumps to the top of both sides' lists
        $conversation->update(['last_message_at' => now()]);

        $this->pushToOtherSide($conversation, $side, $message);

        return response()->json([
            'status'  => 'success',
            'message' => $message,
        ], 201);
    }

    // Mark the OTHER side's messages in this conversation as read (clears my badge).
    public function markRead(Request $request, $id)
    {
        [$side] = $this->actor($request);

        $conversation = $this->authorizedConversation($request, $id);
        if (! $conversation) {
            return response()->json(['status' => 'error', 'message' => 'Conversation not found'], 404);
        }

        $otherSide = $side === 'user' ? 'vendor' : 'user';

        $conversation->messages()
            ->where('sender_type', $otherSide)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'success', 'message' => 'Marked as read']);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    // Who is calling — ['user'|'vendor', id] — resolved from the guard.
    private function actor(Request $request): array
    {
        $caller = $request->user();

        return $caller instanceof Vendor
            ? ['vendor', $caller->id]
            : ['user', $caller->id];
    }

    // The gate: does the user have at least one booking with this vendor that was
    // actually paid (a 'verified' payment)? An unpaid draft never unlocks chat.
    private function hasPaidBooking(int $userId, int $vendorId): bool
    {
        return Booking::where('user_id', $userId)
            ->where('vendor_id', $vendorId)
            ->whereHas('payment', fn ($q) => $q->where('status', 'verified'))
            ->exists();
    }

    // Fetch conversation $id ONLY if the caller is one of its two participants —
    // this is the ownership check for every message action.
    private function authorizedConversation(Request $request, $id): ?Conversation
    {
        [$side, $actorId] = $this->actor($request);

        return Conversation::where('id', $id)
            ->where($side === 'user' ? 'user_id' : 'vendor_id', $actorId)
            ->first();
    }

    // Push an FCM notification to whichever side did NOT send the message. This
    // is push-only (no inbox row) — the chat itself is the history, so we don't
    // want to flood the notification bell with one row per message.
    private function pushToOtherSide(Conversation $conversation, string $senderSide, Message $message): void
    {
        $service = app(NotificationService::class);
        $preview = Str::limit($message->body, 80);
        $data    = ['type' => 'chat', 'conversation_id' => (string) $conversation->id];

        if ($senderSide === 'user') {
            $vendor = Vendor::find($conversation->vendor_id);
            if ($vendor && $vendor->fcm_token) {
                $sender = User::find($conversation->user_id);
                $title  = trim("{$sender?->first_name} {$sender?->last_name}") ?: 'Customer';
                $service->send($vendor->fcm_token, $title, $preview, $data);
            }
        } else {
            $user = User::find($conversation->user_id);
            if ($user && $user->fcm_token) {
                $vendor = Vendor::find($conversation->vendor_id);
                $title  = $vendor?->business_name ?: 'Vendor';
                $service->send($user->fcm_token, $title, $preview, $data);
            }
        }
    }
}
