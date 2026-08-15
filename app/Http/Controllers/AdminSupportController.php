<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Http\Request;

// The admin side of support — one inbox for BOTH user tickets and vendor
// chats. Both roles handle support (it's the support staff's main job);
// money actions stay super_admin-only elsewhere.
class AdminSupportController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    // Inbox — ?owner_type=user|vendor , ?status=open|in_review|resolved ,
    // ?unread=1 (only threads with unread messages). Sorted by latest activity.
    public function index(Request $request)
    {
        $query = SupportThread::query()
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_type', '!=', 'admin')->whereNull('read_at')])
            ->orderByDesc('last_message_at');

        if ($request->filled('owner_type')) {
            $query->where('owner_type', $request->owner_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('unread')) {
            $query->whereHas('messages', fn ($q) => $q
                ->where('sender_type', '!=', 'admin')->whereNull('read_at'));
        }

        $threads = $query->paginate(20);
        $this->attachOwners($threads->getCollection());

        return response()->json([
            'status'  => 'success',
            'counts'  => [
                'open'      => SupportThread::where('status', 'open')->count(),
                'in_review' => SupportThread::where('status', 'in_review')->count(),
                'resolved'  => SupportThread::where('status', 'resolved')->count(),
                'unread'    => SupportMessage::where('sender_type', '!=', 'admin')
                    ->whereNull('read_at')->count(),
            ],
            'threads' => $threads,
        ]);
    }

    // One thread: conversation + owner + booking context (user tickets).
    // Opening it marks the owner's messages as read.
    public function show(int $id)
    {
        $thread = SupportThread::with([
            'messages' => fn ($q) => $q->oldest(),
            'handler:id,name',
            'booking:id,user_id,vendor_id,vendor_product_id,booking_style,status,event_date,delivery_date,refund_amount',
            'booking.vendor:id,business_name,phone,is_active,winding_down',
            'booking.product:id,name,price',
            'booking.payment:id,booking_id,amount_paid,commission,vendor_payout,status',
        ])->findOrFail($id);

        SupportMessage::where('support_thread_id', $thread->id)
            ->where('sender_type', '!=', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $owner = $thread->owner();

        return response()->json([
            'status' => 'success',
            'thread' => $thread,
            'owner'  => $owner ? [
                'id'    => $owner->id,
                'type'  => $thread->owner_type,
                'name'  => $thread->owner_type === 'vendor'
                    ? ($owner->business_name ?? trim("{$owner->first_name} {$owner->last_name}"))
                    : trim("{$owner->first_name} {$owner->last_name}"),
                'phone' => $owner->phone,
            ] : null,
        ]);
    }

    // Admin replies. On a fresh user ticket this is the "open the chat"
    // decision — the ticket moves to in_review and the user can write back.
    public function reply(Request $request, int $id)
    {
        $data = $request->validate(['message' => 'required|string|max:5000']);

        $thread = SupportThread::findOrFail($id);

        if ($thread->status === 'resolved') {
            return response()->json([
                'status'  => 'error',
                'message' => 'This thread is resolved.',
            ], 422);
        }

        if ($thread->status === 'open') {
            $thread->status = 'in_review';
        }
        if (! $thread->handled_by) {
            $thread->handled_by = $request->user()->id;
        }

        $message = $thread->messages()->create([
            'sender_type' => 'admin',
            'sender_id'   => $request->user()->id,
            'body'        => $data['message'],
        ]);

        $thread->last_message_at = now();
        $thread->save();

        $this->notifyOwner($thread, 'Support replied', $data['message']);

        return response()->json(['status' => 'success', 'sent' => $message, 'thread_status' => $thread->status], 201);
    }

    // Close the thread. User tickets stay closed (new problem = new ticket);
    // a vendor chat quietly reopens if the vendor writes again.
    public function resolve(Request $request, int $id)
    {
        $thread = SupportThread::findOrFail($id);

        if ($thread->status === 'resolved') {
            return response()->json(['status' => 'error', 'message' => 'Already resolved'], 422);
        }

        $thread->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
            'handled_by'  => $thread->handled_by ?? $request->user()->id,
        ]);

        AdminAuditLog::record($request->user()->id, 'support.resolve', 'support_thread', $thread->id, [
            'owner_type' => $thread->owner_type,
            'owner_id'   => $thread->owner_id,
        ]);

        $this->notifyOwner(
            $thread,
            'Support ticket resolved',
            $thread->subject ? "Your ticket \"{$thread->subject}\" has been resolved." : 'Your support conversation has been resolved.'
        );

        return response()->json(['status' => 'success', 'message' => 'Thread resolved']);
    }

    // ── helpers ──────────────────────────────────────────────────

    // Attach owner name/phone to a page of threads (2 queries, no N+1).
    private function attachOwners($threads): void
    {
        $userIds   = $threads->where('owner_type', 'user')->pluck('owner_id');
        $vendorIds = $threads->where('owner_type', 'vendor')->pluck('owner_id');

        $users   = User::whereIn('id', $userIds)->get(['id', 'first_name', 'last_name', 'phone'])->keyBy('id');
        $vendors = Vendor::whereIn('id', $vendorIds)->get(['id', 'business_name', 'first_name', 'last_name', 'phone'])->keyBy('id');

        $threads->transform(function ($t) use ($users, $vendors) {
            $owner = $t->owner_type === 'vendor' ? $vendors->get($t->owner_id) : $users->get($t->owner_id);

            $t->owner_name = $owner
                ? ($t->owner_type === 'vendor'
                    ? ($owner->business_name ?? trim("{$owner->first_name} {$owner->last_name}"))
                    : trim("{$owner->first_name} {$owner->last_name}"))
                : null;
            $t->owner_phone = $owner?->phone;

            return $t;
        });
    }

    private function notifyOwner(SupportThread $thread, string $title, string $body): void
    {
        $owner = $thread->owner();

        if (! $owner) {
            return;
        }

        $data = ['thread_id' => (string) $thread->id];

        $thread->owner_type === 'vendor'
            ? $this->notifications->notifyVendor($owner, $title, $body, $data)
            : $this->notifications->notifyUser($owner, $title, $body, $data);
    }
}
