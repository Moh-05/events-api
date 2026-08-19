# Vendor App — Notifications Guide

Every notification the **vendor app** can receive, and where tapping it should go.

---

## How to read a notification

Every push carries a `data` object. **Route on `data`, never on the title text** —
titles are translated (Arabic/English) and will change; `data` will not.

```json
{
  "notification": { "title": "New Booking", "body": "You have a new paid booking from Lina Omar." },
  "data": { "booking_id": "55" }
}
```

Most vendor notifications carry only `booking_id`. The newer ones also carry an
explicit `type` — check `type` first, and fall back to "it's a booking" when
`type` is absent.

```dart
String routeFor(Map data) {
  switch (data['type']) {
    case 'event_reminder': return '/bookings/${data['booking_id']}';
    case 'low_stock':      return '/products/${data['product_id']}';
    case 'chat':           return '/chat/${data['conversation_id']}';
    default:
      // no type -> booking event (new booking, cancelled, review)
      if (data['booking_id'] != null) return '/bookings/${data['booking_id']}';
      return '/';
  }
}
```

> **All `data` values arrive as strings**, even ids. Parse with `int.parse()`.

---

## The notifications

| # | When it fires | Title | `data` | Tap goes to |
|---|---|---|---|---|
| 1 | A customer **paid** for a booking with you — it now needs your accept/decline | New Booking | `booking_id` | That booking's detail (with Accept / Decline) |
| 2 | A customer **cancelled** a booking | Booking Cancelled | `booking_id` | That booking's detail |
| 3 | A customer **left you a review** | New Review | `booking_id` | Your reviews screen, or that booking |
| 4 | **3 days before** an approved event *(appointment vendors only)* | Upcoming Event | `type: event_reminder`, `booking_id` | That booking's detail |
| 5 | A product is **nearly sold out** *(seller vendors only)* | Low Stock | `type: low_stock`, `product_id` | That product's edit screen (to restock) |
| 6 | The customer **sent you a chat message** | *(sender's name)* | `type: chat`, `conversation_id`, `message_id`, `sender_type`, `sender_id`, `body`, `created_at` | That conversation |
| 7 | **Support replied** to your message | *(from admin)* | — | Your support chat screen |

### Notes per notification

**1 — New Booking.** The most important one. The booking is paid and waiting;
you must accept or decline. Deep-link straight to the accept/decline buttons.

**4 — Upcoming Event.** Only appointment vendors (photographer, makeup, DJ,
wedding hall). Sent once per booking — never repeats. Sellers never get this.

**5 — Low Stock.** Only seller vendors (flowers, gifts, dresses, accessories,
candles, cakes). Fires at **3 or fewer** units left. Sent **once per low
episode**: if the vendor restocks above 3 and later dips low again, they are
warned again — but they are not nagged daily while sitting at 2.
Note this carries `product_id`, **not** `booking_id`.

**6 — Chat.** The push carries the **whole message**, not just an id. If the
user already has that conversation open, append the message from the payload
immediately and **suppress the banner** — otherwise the notification appears
before the bubble does. Dedupe against the next poll using `message_id`.

---

## The notification inbox (bell icon)

Separate from push. Notifications 1–5 are also **saved to an inbox** the vendor
can browse; chat messages (6) are **push-only** and are not stored there.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/vendor/notifications` | List, newest first, plus `unread_count` for the badge |
| POST | `/vendor/notifications/{id}/read` | Mark one read |
| POST | `/vendor/notifications/read-all` | Mark all read |

Each inbox row has the same `data` object, so `routeFor()` above works for
inbox taps too.

---

## Setup checklist

1. On login, send the device token: `POST /vendor/fcm-token` `{ "fcm_token": "..." }`
2. On logout the server clears it automatically — no action needed.
3. Language follows the vendor's account setting, from the `Accept-Language`
   header on their requests. Send `ar` or `en` consistently.
4. A vendor with no device token simply receives no push; the inbox still fills.
