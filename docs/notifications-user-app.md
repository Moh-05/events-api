# Customer App — Notifications Guide

Every notification the **customer app** can receive, and where tapping it should go.

---

## How to read a notification

Every push carries a `data` object. **Route on `data`, never on the title text** —
titles are translated (Arabic/English) and will change; `data` will not.

```json
{
  "notification": { "title": "Booking Accepted", "body": "Studio Nour accepted your booking." },
  "data": { "booking_id": "55" }
}
```

Most customer notifications carry only `booking_id`. Chat also carries a `type`.

```dart
String routeFor(Map data) {
  if (data['type'] == 'chat') return '/chat/${data['conversation_id']}';
  if (data['booking_id'] != null) return '/bookings/${data['booking_id']}';
  return '/';
}
```

> **All `data` values arrive as strings**, even ids. Parse with `int.parse()`.

---

## The notifications

| # | When it fires | Title | `data` | Tap goes to |
|---|---|---|---|---|
| 1 | Your **payment was confirmed** | Payment Received | `booking_id` | That booking's detail |
| 2 | The vendor **accepted** your booking | Booking Accepted | `booking_id` | That booking's detail |
| 3 | The vendor **declined** your booking | Booking Declined | `booking_id` | That booking's detail |
| 4 | The vendor **marked the service complete** | Service Completed | `booking_id` | That booking — prompt for a review |
| 5 | The vendor **sent you a chat message** | *(vendor's business name)* | `type: chat`, `conversation_id`, `message_id`, `sender_type`, `sender_id`, `body`, `created_at` | That conversation |
| 6 | **Support replied** to your ticket | *(from admin)* | — | That support ticket |

### Notes per notification

**1 — Payment Received.** Confirms the money went through. **This does not mean
the booking is confirmed** — the vendor still has to accept. The body says so;
make sure the UI does too, or customers will think they're booked when they
aren't.

**2 — Booking Accepted.** The real confirmation. This is also the moment **chat
unlocks** with that vendor — if your chat button was disabled, enable it now.

**3 — Booking Declined.** A refund is owed. Refunds are processed manually by
the admin, so don't promise an instant return.

**4 — Service Completed.** Best moment to ask for a review — deep-link straight
into the review screen for that booking.

**5 — Chat.** The push carries the **whole message**, not just an id. If the
user already has that conversation open, append the message from the payload
immediately and **suppress the banner** — otherwise the notification appears
before the bubble does. Dedupe against the next poll using `message_id`.

---

## The notification inbox (bell icon)

Separate from push. Notifications 1–4 are also **saved to an inbox** the
customer can browse; chat messages (5) are **push-only** and are not stored
there.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/notifications` | List, newest first, plus `unread_count` for the badge |
| POST | `/notifications/{id}/read` | Mark one read |
| POST | `/notifications/read-all` | Mark all read |

Each inbox row has the same `data` object, so `routeFor()` above works for
inbox taps too.

---

## Setup checklist

1. On login, send the device token: `POST /fcm-token` `{ "fcm_token": "..." }`
2. On logout the server clears it automatically — no action needed.
3. Language follows the customer's account setting, from the `Accept-Language`
   header on their requests. Send `ar` or `en` consistently.
4. A customer with no device token simply receives no push; the inbox still fills.
