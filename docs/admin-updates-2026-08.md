# Admin Notes — What's New (August 2026 session)

For the admin/React team. Covers everything added or changed this session that
touches the admin side. Older reference docs (`admin-api.html`,
`admin-system.html`) still cover the base admin system — this file is the
delta on top of that.

---

## 1. Vendor withdrawal requests — the ShamCash account gap is now closed

**The problem this fixes:** a vendor could request a withdrawal, the admin got
notified, but there was nowhere on the vendor's record saying *where* to send
the money. The admin had to chase the vendor separately every time.

### What a vendor does first
Before a vendor can request any withdrawal, they must set their ShamCash
account:

```
POST /vendor/shamcash-account
{ "shamcash_account": "acc_..." }
```

This is just a string — same shape as the platform's own ShamCash account id.
It can be changed anytime (not a one-time lock).

### The guard
`POST /vendor/withdraw` now **refuses (422)** if the vendor has no ShamCash
account on file. A withdrawal request can no longer reach the admin queue with
nowhere to send the money.

### What you see as admin
`GET /admin/withdrawals` (optionally `?unpaid=1` for only outstanding ones) —
each row now includes the vendor's `shamcash_account` directly, alongside their
name and phone:

```json
{
  "id": 9,
  "vendor_id": 4,
  "amount": "-97500.00",
  "paid_at": null,
  "vendor": {
    "id": 4,
    "business_name": "حلويات الوسام",
    "phone": "+963945745973",
    "shamcash_account": "5566"
  }
}
```

You no longer need a separate lookup to know where to send the payout.

### Sort order — read this before acting on the queue
**The list is currently sorted newest-first** (`->latest()`), not oldest-first.
If Mohamad wants "the vendor who's waited longest gets paid first" as the
actual queue order, that still needs to change server-side — flag it if this
matters for your workflow, since right now the newest request shows at the top.

### Marking a withdrawal handled
- `POST /admin/withdrawals/{id}/mark-paid` — once you've actually sent the
  money via ShamCash, mark it done. Refuses if already paid or already
  rejected.
- `POST /admin/withdrawals/{id}/reject` — needs a `reason`. Use this for a
  suspicious request. **The held amount automatically returns to the vendor's
  available balance** — nothing manual needed on your end, `WalletService`
  ignores rejected withdrawal rows when computing balances.

### Known gap from before this fix
Some withdrawal requests made **before** the ShamCash requirement existed have
`shamcash_account: null` — there's a real one sitting in production right now
(Velora Boutique, 212.50 SYP, no account on file). For those, you'll need to
ask the vendor for their account manually, or reject the request so they can
re-submit properly through the new flow.

---

## 2. Order completion — a customer can now confirm receipt directly

New customer-facing endpoint: `POST /bookings/{id}/received`. When a customer
taps "I received it" on an order (flowers, cakes, gifts, etc.), the booking
completes **immediately** and the vendor's payout clears from escrow right
then — they don't have to wait for the vendor's own delivery-date estimate to
pass.

**What this means for you as admin:** a booking's `delivery_date` field may now
reflect the **actual** confirmed delivery moment rather than the vendor's
original estimate — it gets overwritten the instant the customer confirms.
This is intentional, so booking history reflects reality. The fallback is
unchanged: if the customer never confirms, `bookings:auto-complete` still
completes the order automatically one day after the vendor's original delivery
estimate.

---

## 3. Two new public "free slots this month" stats

Not admin-only, but useful for a dashboard tile if you want one:

- `GET /vendors/wedding-halls/free-slots` — free days this month across
  approved wedding halls specifically.
- `GET /vendors/appointment/free-slots` (optional `?vendor_type=`) — same idea,
  generalized to every appointment-style category (photographer, makeupArtist,
  dj, weddingHall).

Both return a `total_free_slots` number plus the actual vendor list (with
images, location, rating) for any vendor that has at least one free day left
this month. A fully-booked vendor still counts toward the total but isn't
included in the list.

---

## 4. Vendor location replaces "city"

Vendors no longer have a `city` field anywhere in the API. Registration now
requires `latitude`/`longitude` (optional `address`), and every vendor object
you see — admin lists included — carries real coordinates instead. The
`vendors.city` column still physically exists in the database (nothing was
migrated away), it's just unused going forward.

---

## 5. New vendor notifications (in case a vendor asks why they got a push)

Two new automated notifications, both scheduled daily:

- **Upcoming event reminder** (appointment vendors only) — sent 3 days before
  an approved event, once per booking.
- **Low stock alert** (seller vendors only) — sent when a product hits 3 units
  or fewer, once per "low stretch" (won't repeat daily while it sits low, fires
  again if it dips low a second time after being restocked).

Neither needs any admin action — purely FYI if a vendor mentions receiving one.

---

## 6. Notification inbox now follows the app's current language

Previously, a notification received in English stayed in English forever, even
after the user switched their app to Arabic. Fixed — the inbox now re-renders
each entry in whichever language the app is currently asking for. Doesn't
affect anything on the admin side directly, but worth knowing if a
support/complaint mentions "my old notifications are in the wrong language."

---

## 7. Public product detail endpoint

`GET /products/{id}` — didn't exist before this session. Returns a single
product's full image gallery and vendor info. Same visibility rule as
everywhere else: a hidden, sold-out, or banned/unapproved vendor's product
returns an identical 404 to a non-existent id, so nothing leaks by guessing.

---

## 8. Order delivery deadlines are now vendor policy, not a customer date

Previously the customer picked a delivery date at checkout. Now the **seller
sets a policy per product** — `max_delivery_days` ("I deliver this within N
days") — and the backend computes the order's `delivery_date` from it at order
time. The customer can no longer send a date; it is rejected outright.

What this means when you're looking at an order:

- `delivery_date` is a **computed deadline**, derived from the vendor's own
  promise — not something the customer requested.
- On a mixed cart, it uses the **longest** promise among the products ordered
  (the order isn't late until its slowest item is).
- `max_delivery_days` is **optional**. If no product in the order has one set,
  `delivery_date` is `null` — that order has no deadline and will never
  auto-complete on a date. It relies on the customer confirming receipt or the
  vendor completing it manually.
- If a customer edits an unpaid draft and swaps in slower products, the
  deadline **recomputes** rather than keeping the old date.

---

## 9. Terms & Privacy documents (for the legal/consent screens)

Eight documents now live in `docs/`, replacing the earlier fragmented set:

| | Terms | Privacy |
|---|---|---|
| **Customer** | `terms-user-en.md` · `terms-user-ar.md` | `privacy-user-en.md` · `privacy-user-ar.md` |
| **Vendor** | `terms-vendor-en.md` · `terms-vendor-ar.md` | `privacy-vendor-en.md` · `privacy-vendor-ar.md` |

Both vendor types (service provider and store owner) are covered in a single
vendor document, with the two payment models presented side by side.

**Two commitments in these documents that the admin team actually has to
honour:**

1. **"We will review your account within 48 hours."** Nothing in the backend
   enforces or tracks this — KYC approval is a manual admin action with no
   timer or SLA alert. If the KYC queue isn't checked, this stated term is
   broken. Watch `GET /admin/vendors/pending`.
2. **"Withdrawals are remitted manually, ordinarily within 24 hours."** Same
   situation — the queue is `GET /admin/withdrawals?unpaid=1`, and nothing
   chases it for you.

The privacy policies also state that **support staff may read customer↔vendor
messages when resolving a dispute**. That is true and permitted, but it should
only happen for an actual dispute, not casually.

---

## 10. Vendor rating growth — explicit field name

`GET /vendor/reviews/summary` now returns **`rating_growth_percent`** (this
month's average rating vs last month's, as a percentage, can be negative). The
old `trend` field is kept as an alias with the identical value, so nothing
breaks — but `rating_growth_percent` is the one to use.

---

## Quick reference — everything new this session

| What | Endpoint |
|---|---|
| Vendor sets payout account | `POST /vendor/shamcash-account` |
| Admin sees withdrawal queue (now with account) | `GET /admin/withdrawals?unpaid=1` |
| Admin marks a payout sent | `POST /admin/withdrawals/{id}/mark-paid` |
| Admin rejects a payout (needs `reason`) | `POST /admin/withdrawals/{id}/reject` |
| Customer confirms order received | `POST /bookings/{id}/received` |
| Free wedding-hall days this month | `GET /vendors/wedding-halls/free-slots` |
| Free days, all appointment vendors | `GET /vendors/appointment/free-slots` |
| Single product detail (public) | `GET /products/{id}` |
| Vendor rating growth | `GET /vendor/reviews/summary` → `rating_growth_percent` |

---

## Daily admin checklist

Nothing in the system chases these for you — they are all manual queues.

1. **KYC queue** — `GET /admin/vendors/pending`. The vendor terms promise a
   review **within 48 hours**, so this needs checking daily. Approve with
   `POST /admin/vendors/{id}/approve`, or reject with
   `POST /admin/vendors/{id}/reject` (the reason is shown to the vendor, who
   fixes it and gets re-reviewed — there is no separate "reapply" action).
2. **Withdrawal queue** — `GET /admin/withdrawals?unpaid=1`. The terms promise
   payout **within 24 hours**. Each row now shows the vendor's
   `shamcash_account` — send the money there, then `mark-paid`. If a row shows
   `shamcash_account: null` (a pre-fix request), ask the vendor for it or
   reject so they can re-submit.
3. **Support inbox** — `GET /admin/support`. User tickets **cannot be replied
   to by the customer until you answer first**, so an unanswered ticket blocks
   that customer entirely. Vendor chats are always open.
4. **Refunds due** — `GET /admin/refunds-due`. Refunds are recorded
   automatically but sent manually.
5. **Dashboard badges** — `GET /admin/dashboard` → `nav_badges` gives live
   counts for all of the above in one call.

---

## Things that still need a decision

Flagged honestly rather than left to be discovered later:

- **Withdrawal queue sort order.** Currently newest-first. If "whoever waited
  longest gets paid first" is the intended workflow, this needs a server change
  — it has not been made.
- **The scheduler must actually be running.** Four commands are registered
  (`bookings:auto-complete`, `offers:expire`, `bookings:remind-upcoming`,
  `products:alert-low-stock`), but they only fire if Railway invokes
  `schedule:run` on a cron. If that is not configured, orders never
  auto-complete, offers never expire, and no reminders are sent. Worth
  confirming in the Railway dashboard.
- **Pre-launch items still open**: the `0000` payment test bypass, OTP returned
  in auth responses, `debug_notifications` in the payment response,
  `APP_DEBUG=false` on Railway, rotating the leaked Supabase key, and locking
  CORS to the real admin-dashboard domain once it exists.
