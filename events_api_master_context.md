# EVENTS API — MASTER CONTEXT FILE

# حفلتي — Haflati Platform

# Complete Project Blueprint — UPDATED SESSION 2

# Generated: June 2026 | Version: 2.0 FINAL

---

> ## ⚠️ MESSAGE FOR AMER (read before you merge — رسالة لعامر قبل الدمج)
>
> **English (for Amer's Claude session):**
> Mohamad pushed a large batch of work to `dev` on 2026-07-30 (customer discovery, booking refactor, availability calendar, vendor online/offline toggle, cover image, escrow merge). It's been a while since Amer last pulled, so pulling `dev` will show **many merge conflicts** — this is expected and NOT a problem. **How to merge: take the SAME approach we used when we merged the admin branch — apply/keep BOTH sides on every conflict, do not discard Mohamad's changes.** Amer was working ONLY on admin; none of this batch touched admin, so there is no real conflict of intent — the conflicts are just because the two branches drifted. Every change Mohamad made to the user/vendor/booking side is intentional and must land exactly as written. So: resolve each conflict by keeping both, run `php artisan test` (expect 20 passing) + `php -l`, then continue. Do NOT revert user-side changes to make conflicts "go away."
>
> **بالعربي (لعامر):**
> محمد رفع دفعة كبيرة على `dev` بتاريخ 2026-07-30. صار وقت من آخر مرة سحبت التغييرات، فرح تشوف تعارضات (conflicts) كتيرة وقت تعمل pull — هاد طبيعي ومو مشكلة.
> طريقة الدمج: نفس الطريقة يلي دمجنا فيها فرع الأدمن — خلّي الطرفين (both sides) بكل تعارض، ولا تحذف تغييرات محمد.
> إنت كنت شغّال على الأدمن بس، وهي الدفعة ما لمست الأدمن أبداً، فالتعارضات سببها بس إنو الفرعين ابتعدوا عن بعض.
> كل تغيير عملو محمد على جهة اليوزر/الفيندور/الحجوزات مقصود ولازم ينزل متل ما هو. حل كل تعارض بإبقاء الطرفين، شغّل `php artisan test` (لازم 20 ينجحوا)، وبعدين كمّل. لا ترجّع (revert) تغييرات اليوزر عشان تتخلّص من التعارض.

---

# ═══════════════════════════════════════════════════════════════

# SECTION 1: THE MASTER PLAN & EXECUTIVE SUMMARY

# ═══════════════════════════════════════════════════════════════

## Project Identity

- **App Name:** حفلتي (Haflati)
- **Concept:** Two-sided marketplace for event services in the Arab world
- **Backend Developer:** Moh + Amer (Laravel)
- **Flutter Team:** 3 developers (Customer App + Vendor App)
- **Admin Dashboard:** React (web only)
- **Backend:** Laravel 12, PHP 8.2, MySQL
- **Live Server:** Railway.app
- **Repo:** GitHub (private) — `events-api`
- **API Testing:** Postman
- **Auth:** Laravel Sanctum + WhatsApp OTP via UltraMsg

---

## What This Platform Solves

A marketplace for event services where:

- **Customers** discover and book vendors for weddings, birthdays, graduations, etc.
- **Vendors** (wedding venues, photographers, cake shops, DJs, stores) list and manage their services
- **Admin** approves vendors and manages the platform via React dashboard

---

## Architecture Decision: Two Separate Flutter Apps

- **Customer App (Flutter):** Browse, search, book services
- **Vendor App (Flutter, SEPARATE):** Manage profile, products, bookings
- **Admin Dashboard (React Web):** Approve vendors, manage disputes — TWO ROLES: super_admin + support
- **Reason:** Two-sided market theory — each side has radically different UX needs. Security: separate Laravel Sanctum guards (auth:sanctum for users, auth:vendors for vendors).

---

## Team Split

| Person   | Role                             |
| -------- | -------------------------------- |
| Moh      | Laravel backend                  |
| Amer     | Laravel backend                  |
| 3 others | Flutter (customer + vendor apps) |
| 1 other  | React (admin dashboard)          |

---

## About the Developer (Mohamad) — for tailoring help / future Claude skills

This section captures how Mohamad works, so Claude (and future custom skills) can
assist him better. It is about **Mohamad**, the repo owner — not Amer.

- **Who:** Mohamad (Moh), repo owner (`Moh-05/events-api`), Laravel backend dev.
  Haflati is his graduation project (Damascus University, Faculty of Informatics Eng.).
- **Language:** Talks to Claude in **simple English** (switched from Arabic because the
  Claude Code chat panel has no RTL support and mixing scripts looked messy).
- **Teammate Amer:** Amer sometimes uses Mohamad's laptop. **Amer always talks in Arabic.**
  So: an Arabic message = Amer; an English message = Mohamad. Treat them as different people.
- **How he likes to work:**
  - Wants to understand the **why** before accepting a change (asks "do we actually need this?").
  - Prefers step-by-step guidance and concrete Postman routes when testing.
  - Likes clean JSON error responses over raw framework exceptions.
  - Still learning Laravel — explain concepts simply, avoid heavy jargon dumps.
- **Note:** This list will grow as we work, and Mohamad will use it to build tailored
  Claude skills later.

---

## Git Workflow

- `main` branch → connected to Railway auto-deploy
- `dev` branch → Moh + Amer work here
- Never work directly on `main`
- Merge dev → main to deploy:

```bash
git checkout main
git merge dev --no-edit
git push origin main
git checkout dev
```

---

## Railway DevOps

- **Platform:** Railway.app
- **Auto-deploy:** every `git push origin main`
- **Start Command:** `php artisan migrate --force && frankenphp run --config /etc/caddy/Caddyfile`
- **Live URL:** `https://events-api-production-138b.up.railway.app`

### Railway Variables (must be set manually — NOT in GitHub)

```
APP_KEY=base64:xxxx
APP_ENV=production
APP_DEBUG=false
DB_HOST=mysql.railway.internal
DB_PORT=3306
DB_DATABASE=railway
DB_USERNAME=root
DB_PASSWORD=xxxx
SESSION_DRIVER=file
ULTRAMSG_INSTANCE_ID=instance169953
ULTRAMSG_TOKEN=xxxx
SHAMCASH_API_TOKEN=xxxx
SHAMCASH_ACCOUNT_ID=acc_01ksvc53hwdpxm9cav1f9zbymj
FIREBASE_CREDENTIALS=storage/app/haflati-d14da-firebase-adminsdk-fbsvc-13bfc3d73d.json
```

**IMPORTANT — Firebase JSON on Railway:**
The JSON credentials file cannot be committed to GitHub (security risk).
On Railway, either:

1. Set `FIREBASE_CREDENTIALS` to the full path after manually uploading, OR
2. Store the entire JSON content as a Railway Variable and write a boot script to recreate the file.

---

## Vendor Types & Booking Styles

| vendor_type   | booking_style | Payment Type        |
| ------------- | ------------- | ------------------- |
| wedding_venue | appointment   | Deposit 20% (عربون) |
| photographer  | appointment   | Deposit 20% (عربون) |
| dj            | appointment   | Deposit 20% (عربون) |
| cake_shop     | order         | Full price          |
| store         | order         | Full price          |

`booking_style` is set **automatically** in `setType()` based on `vendor_type`.

---

## Payment Model — حفلتي (UPDATED & FINALIZED)

### Key Decision (UPDATED 2026-06-05 by Mohamad): PAY-FIRST

The flow was flipped from the earlier "approve-first" plan to **pay-first**.
This is what the code on the `dev` branch actually does now.

```
FLOW (pay-first — current implementation):
1. Customer creates booking      → status: awaiting_payment   (hidden from vendor)
2. Customer pays via ShamCash    → status: pending            (now visible to vendor)
3. Vendor approves               → status: approved
4. Event day / vendor marks done → status: completed
OR
3b. Vendor declines              → status: declined
```

**IMPORTANT — status meanings changed from the old plan. Read carefully:**

- `awaiting_payment` = brand-new UNPAID booking, hidden from the vendor
- `pending` = PAID booking, now visible to the vendor, waiting for approval

**WHY pay-first is OK for now (TEMPORARY — refund API arriving ~2026-06-12):**
ShamCash currently exposes only a VERIFY endpoint (read-only) — there is no
endpoint to send money out, so a paid booking that the vendor declines/cancels
would need a MANUAL refund today. We are waiting on a ShamCash API (expected
within ~1 week, around 2026-06-12) that lets us PAY / refund programmatically.
Until then we keep this pay-first flow as-is, and `cancel()` only allows
`awaiting_payment` (unpaid) bookings. Once the refund API lands, vendor
decline/cancel will trigger an AUTOMATIC refund to the user (no manual work),
and cancelling a paid booking can be enabled.

**Testing note:** review is allowed when a booking is `approved` OR `completed`
for now, so we don't have to wait for the event day. The real rule will be
`completed` only.

### Payment Amounts

```
Appointment vendors (wedding_venue, photographer, dj):
  amount_to_pay = product.price × deposit_percent / 100
  deposit_percent = 20% (fixed on vendor_products table)

Order vendors (cake_shop, store):
  amount_to_pay = product.price (full price)
```

### Commission Structure

```
Haflati takes: 15% of amount paid
Vendor receives: 85% of amount paid
```

### ShamCash Payment Flow (UI)

```
1. App shows: "Transfer X SYP to Haflati ShamCash account: [address]"
2. User opens ShamCash app manually
3. User transfers the amount
4. User returns to app and enters Transaction ID
5. Laravel calls ShamCash API to verify
6. If verified → booking status → pending (paid, now visible to vendor)
```

---

## Booking Status Flow (UPDATED 2026-06-05 — PAY-FIRST)

```
awaiting_payment  → pending    (user pays & ShamCash verified)
awaiting_payment  → cancelled  (user cancels an unpaid booking)
pending           → approved   (vendor approves)
pending           → declined   (vendor declines)
approved          → completed  (event day / vendor marks done)
```

---

## What Has Been Successfully Built ✅

### Authentication

- User auth: send-otp → verify-otp → complete-registration (name + birth_date)
- Vendor auth: same flow, separate controller (AuthVendorController), separate guard
- Both use UltraMsg WhatsApp OTP
- Token is in URL query parameter (not body)

### User Profile

- show, update (first_name, last_name, birth_date, latitude, longitude, address, profile_image, city), deleteImage
- GPS: latitude (decimal 10,8) + longitude (decimal 11,8) + address (string)

### Vendor Profile

- show, setType (auto-sets booking_style), update, deleteImage
- Same GPS structure as user
- Fields: first_name, last_name, phone, city, birth_date, business_name, vendor_type, booking_style, profile_image, latitude, longitude, address, bio, rating_avg, is_approved, is_active, fcm_token

### Vendor Products (VendorProductController)

- store, index, getVendorProducts, searchVendorProducts, show, update, destroy
- Products have: name, description, price, meta (JSON), is_available, deposit_percent

### Booking System (BookingController) — PARTIALLY BUILT

- store — customer sends booking request → status: pending
- update — customer edits pending booking
- cancel — customer cancels pending booking
- vendorBookings — vendor sees incoming bookings (Amer working on this)
- updateStatus — vendor approves (→ awaiting_payment) or declines (Amer working on this)

### Payment System (PaymentController) — BUILT

- verify — customer submits Transaction ID → ShamCash API verifies → booking → approved

### Notification System (NotificationService) — BUILT (not yet integrated)

- Firebase FCM HTTP v1 API
- send(), notifyUser(), notifyVendor()
- notifyAdmins() — commented out until Admin table is built

---

## UPDATES — 2026-06-06 (Mohamad session)

### Booking flow finalized (pay-first)
- `store()` creates a booking as `awaiting_payment` (hidden from vendor).
- `PaymentController::verify()` moves it to `pending` after ShamCash verifies.
- `approve()` → sets `approved` (was previously jumping straight to completed).
- `complete()` ADDED → moves `approved` → `completed` (vendor marks done / event day).
  This also fixed the `/vendor/bookings/{id}/complete` route, which had no method before.

### Reviews
- Review allowed when booking is `approved` OR `completed` (for now). Real rule later = `completed` only.
- Clean JSON errors instead of framework exceptions:
  - `404` Booking not found (not the caller's booking)
  - `422` "You can't review a booking with status '<status>'..." 
  - `409` already reviewed (unchanged)

### FCM device token (needed for real push later)
- `fcm_token` added to `$fillable` on User and Vendor models (column already existed).
- `updateFcmToken()` added to UserProfileController and VendorProfileController.
- Routes: `POST /fcm-token` (user) and `POST /vendor/fcm-token` (vendor).
- The Flutter app calls these on login to save the device token.

### Notification INBOX (bell icon history) — NEW
- New `notifications` table: `notifiable_type` ('user'|'vendor'), `notifiable_id`,
  `title`, `body`, `data` (json, nullable), `read_at` (nullable), timestamps.
- New `Notification` model.
- `NotificationService` now ALWAYS saves the notification to the DB (`saveToInbox`)
  in addition to sending the FCM push. So every notify is stored + pushed.
- New `NotificationController`:
  - `index()` → list (newest first) + `unread_count`
  - `markAsRead($id)` → set `read_at`
  - `markAllAsRead()` → set `read_at` for all unread
- Routes (both guards):
  - User:   `GET /notifications`, `POST /notifications/read-all`, `POST /notifications/{id}/read`
  - Vendor: `GET /vendor/notifications`, `POST /vendor/notifications/read-all`, `POST /vendor/notifications/{id}/read`
- `unread_count` = number of rows with `read_at = null`; the app uses it for the bell badge.

### Still deferred (not done yet)
- Notifications are only fired in `PaymentController::verify()` so far.
- Booking-event notifications (approve / decline / cancel) are NOT wired yet — to add next.
- `debug_notifications` field in the payment response is for testing; remove before production.

---

## UPDATES — 2026-06-06 (later: Amer's admin + vendor dashboard endpoints)

### Admin system — now BUILT by Amer (was "TO BE BUILT")
- New `auth:admins` Sanctum guard in `config/auth.php`; `role` middleware alias registered in `bootstrap/app.php`.
- Files: `AdminAuthController` (login/logout), `AdminController`, `EnsureAdminRole` middleware, `Admin` model, `admins` migration.
- Roles: `super_admin` and `support`.
- Admin routes: `POST /admin/login`, `POST /admin/logout`, KYC (`/admin/vendors/pending`, `/admin/vendors/{id}/approve`, `/admin/vendors/{id}/reject`), and super_admin-only: dashboard, vendors list, vendor toggle, users, bookings, payments.
- REVIEW + open decisions for this admin system are written up in `docs/admin-review.html`. Key pending decision: let `support` SEE the dashboard read-only but DENY bans/payments (see that file for the full permission matrix). Known HIGH bug: `rejectVendor` sets `is_active=false`, letting support effectively ban vendors.

### Vendor dashboard endpoints (Amer + this session)
The Vendor app has two dashboard styles (service providers vs store/order vendors). These endpoints back them (all under `auth:vendors`):
- `GET /vendor/bookings/recent-requests` — latest pending requests (Amer)
- `GET /vendor/bookings/upcoming-events` — approved future events (Amer)
- `GET /vendor/bookings/recent-orders` — latest orders for order vendors
- `GET /vendor/bookings/{id}` — full booking/order detail (user + product + payment)
- `GET /vendor/stats` — booking counts by status, earnings, rating (Amer)
- `GET /vendor/earnings` — this month vs last month payout + growth %
- `GET /vendor/reviews` — vendor's own reviews + average (Amer, `ReviewController::myReviews`)
- `GET /vendor/reviews/summary` — star breakdown %, positive %, trend vs last month
- `GET /vendor/products/best-sellers` — top products by number of orders
- `GET /vendor/products/low-stock` — inventory alerts (`?threshold=N`, default 5)

### Schema / model changes
- Added nullable `stock` (integer) to `vendor_products` (edited the original migration → needs `migrate:fresh`). Wired into `VendorProduct` `$fillable`/casts and into product store/update.
- Added `VendorProduct::bookings()` hasMany (was the one missing inverse relation).

### Decisions / fixes
- `deposit_percent` stays a FIXED platform rule (20%). It is deliberately NOT in `VendorProduct` `$fillable` — vendors cannot change it; it comes from the DB default. (A code review flagged it as a "bug"; that was a false positive given this rule.)
- Removed the dead `'name'` from `Vendor` `$fillable` (the `vendors` table has no `name` column).
- Comment cleanup pass: removed AI-style / Arabic comments across controllers/services/migrations, kept the real "why" comments.
- Full code/ERD review (relationship naming, missing inverses, many-to-many) is in `docs/erd-review.html`.

---

## UPDATES — 2026-06-10 (Mohamad session: live Railway deploy + relationship rename)

### 1. Relationship renamed: `vendor_product()` → `product()`
- The `Booking` relationship method is now **`product()`** (was `vendor_product()`). This matches `VendorProductImage::product()` — the naming inconsistency flagged in `docs/erd-review.html` is now fixed.
- The **DB column stays `vendor_product_id`** and the FK is still explicit: `belongsTo(VendorProduct::class, 'vendor_product_id')`.
- All call sites updated: `BookingController`, `PaymentController`, `AdminController`. Always eager-load with `$booking->load(['vendor', 'product'])`.
- ⚠️ **API impact (tell Flutter):** the booking RESPONSE key changed from `"vendor_product"` to `"product"`. The booking INPUT is unchanged — clients still POST `vendor_product_id` when creating a booking.
- This supersedes old BUG #6 and flips MUST-FOLLOW rule #1.

### 2. Edit-booking rule: only before payment
- `BookingController::update()` now allows editing a booking **only while `awaiting_payment`** (unpaid draft). Once paid (`pending`), the details are locked.
- Reason: once money is in, changing date/duration would break the deposit and the vendor's calendar. Changes after payment wait for a cancel/refund instead.
- `cancel()` is also `awaiting_payment`-only (unchanged).

### 3. Firebase credentials: env JSON (Railway) or file (local)
- `NotificationService` now reads credentials from **`FIREBASE_CREDENTIALS_JSON`** (a Railway variable holding the full service-account JSON) first, and falls back to the **`FIREBASE_CREDENTIALS`** file path (local).
- Local `.env` keeps `FIREBASE_CREDENTIALS=storage/app/...json`. Railway uses `FIREBASE_CREDENTIALS_JSON` (paste the whole JSON, braces included). You do NOT need both in the same place.
- Note: generating a new key in Firebase does NOT revoke old keys — to fully kill a leaked key, delete it in Google Cloud Console → IAM → Service Accounts → Keys.

### 4. Deployed to Railway (staging for the Flutter team)
- Live URL: `https://events-api-production-138b.up.railway.app`
- Start command is now **permanently** `php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT` (NOT `migrate:fresh`). `migrate --force` only applies new migrations and never wipes data.
- For one-off commands (`migrate:fresh`, `db:seed`, `tinker`) use the **Railway CLI**: `railway ssh php artisan ...`. It runs inside the container so it reaches `mysql.railway.internal`. Installed locally at `C:\xampp\php\railway.exe` (login with `railway login`, link with `railway link`).

### 5. Full end-to-end flow verified on LIVE ✅
- Flow: vendor signup → set type (photographer) → create service → user signup → booking → ShamCash payment (real 5 SYP deposit) → vendor approve → user review (5★) → vendor complete. All passed.
- Test data: vendor business_name "Opus 4.8", user "Opus 4.7", product "Wedding Photography Package" priced 25 SYP (20% deposit = 5 SYP). ShamCash confirmed a real transaction.
- 60-route sweep: every non-admin route responds correctly. Edit-booking verified after redeploy. Decline not re-tested (status guard confirmed; same code path as approve).
- Admin routes: only login (401 bad creds) + auth guard (401 no token) verified — **no admin account exists yet** (no seeder). Create the first admin via `railway ssh php artisan tinker`.

### 6. Known issues / reminders (NOT bugs — handle before real launch)
- **WhatsApp (UltraMsg) subscription STOPPED** for non-payment → real OTP messages not sending. OTP is still returned in the API response for testing. Renew UltraMsg before launch.
- **Images wiped on every Railway deploy** (ephemeral container disk). DB rows survive, but image files vanish → broken images until re-upload. Plan: move to **Cloudflare R2** (S3-compatible, ~10 GB always-free, no egress fees) or a Railway Volume before launch. Fine for testing — devs just re-upload.
- **`price_agreed`** (bookings) is reserved/unused (always null). To be **removed later**, or used when a custom-quote/negotiation feature is built.
- Testing-only leftovers to remove before real production: OTP in auth responses, `debug_notifications` in `PaymentController`, and set **`APP_DEBUG=false`** on Railway (it currently leaks stack traces on 404s).

---

## UPDATES — 2026-06-27 (Moh + Amer: wallet, portfolio, stock, categories, ban, notifications)

### 1. Vendor wallet system (NEW)
- Table `wallet_transactions`: `vendor_id`, `booking_id` (nullable), `type` (`credit|refund|withdrawal`), `amount` (signed decimal 10,2), timestamps. Model `WalletTransaction`; `Vendor::walletTransactions()`.
- `WalletController`:
  - `GET /vendor/wallet` (`show`) → `wallet` {`available_balance`, `pending_clearance`, `total_earned`, `currency`, `pending_note`} + `transactions` (each with `booking.user` + `booking.product`).
  - `POST /vendor/withdraw` → sweeps the full available balance to 0 (records a `withdrawal` row). Real bank payout deferred until ShamCash payout API exists. 422 if nothing available.
- Logic: on vendor **approve**, a `credit` of `vendor_payout` is added; **held 3 days** (`HOLD_DAYS`, the refund window) before it clears into `available`. Balances derived per-booking (group by `booking_id`, net credit+refund; cleared once the credit is >3 days old). Withdrawals reduce `available` immediately but never reduce `total_earned`.

### 2. Stock handling (NEW)
- Stock is taken at vendor **`approve()`** (NOT at booking) and returned when an **approved** booking is cancelled.
- `store()` blocks the booking if the product `is_available = false` (a product auto-hides when stock hits 0).
- `approve()` uses an **atomic conditional decrement** (`where('stock','>',0)->decrement`) → can never oversell; returns **409 "out of stock"** if none left; sets `is_available=false` when stock reaches 0.
- `cancel()` of an approved booking restores +1 and re-enables `is_available`. `decline()` and draft/pending cancels do NOT touch stock (it was never decremented). Refund tiers on approved-cancel: ≤24h=100%, ≤72h=50%, >72h=0% (negative `refund` row).
- Products with `stock = null` (appointment services) are untracked — all stock logic skips them. `approve()` and `cancel()` wrap stock + wallet + status in a DB transaction.

### 3. Vendor categories + `vendor_style` (CHANGED)
- `vendor_type` enum replaced with the new category list:
  - **Service** (`booking_style = appointment`): `photographer`, `makeupArtist`, `dj`, `weddingHall`
  - **Seller** (`booking_style = order`): `flowers`, `gifts`, `dresses`, `accessories`, `candles`, `cakes`
- `booking_style` auto-derives from `vendor_type` (seller list → order, else appointment) in both complete-registration and profile update. (Replaces the old `cake_shop` check.)
- NEW column **`vendor_style`** enum (`service_provider | seller`, nullable) — a **helper field for Flutter only, no backend logic**. Set wherever `vendor_type` is set (optional at registration, settable in profile); returned inside the `vendor` object on login/signup.
- **Registration flow changed:** `vendor_type` is now **required in `complete-registration`**; the old separate `POST /vendor/profile/type` (`setType`) endpoint was **REMOVED**.

### 4. Portfolio — "معرض الأعمال" (NEW)
- Like products but **no price**. Tables: `portfolio_items` (`vendor_id`, `title`, `description`) + `portfolio_item_images` (`portfolio_item_id`, `image_path`, `is_primary`). Models `PortfolioItem` / `PortfolioItemImage`; `Vendor::portfolioItems()`. Images → `storage/app/public/portfolio_images`.
- `PortfolioController` routes: `GET /vendor/portfolio` (own), `POST /vendor/portfolio` (create — `images[]` required, `primary_image_index`), `GET /vendor/portfolio/{id}` (detail), `POST /vendor/portfolio/{id}` (update — add `images[]` / `delete_image_ids[]`), `DELETE /vendor/portfolio/{id}`, and public `GET /vendors/{vendorId}/portfolio`.

### 5. Notifications wired into booking events (CHANGED)
- Previously only `PaymentController::verify` fired. Now also: **approve → user**, **decline → user**, **complete → user**, **cancel (pending/approved) → vendor**, **new review → vendor**.
- Each saves to the inbox + sends FCM (if device token) with `data: { booking_id }`. Notification titles are **plain text, no emojis**.

### 6. Admin role split + ban enforcement (Amer, NEW)
- New columns `users.is_active` and `vendors.rejection_reason`; new `admin_audit_logs` table; `AdminManagementController`; `EnsureActive` middleware (alias `active`) applied to the user and vendor route groups.
- Banned vendor: vendor login returns `status: suspended` (403); `BookingController::store` blocks booking a suspended vendor (403). `Vendor::scopeActive()` for public queries.
- Admin routes: `role:super_admin,support` for view + KYC; super_admin-only for management actions. `admin/login` throttled 5/min. First admin via `AdminSeeder` → `admin@haflati.com` / `0000` (change after first login).

### 7. Testing helpers — REMOVE before production
- **Payment bypass:** `transaction_id = "0000"` skips ShamCash verification and is reusable across bookings (stored internally as `0000-{bookingId}`).
- OTP still returned in auth responses; **UltraMsg subscription stopped** (trial not extended) → OTPs are not actually sent via WhatsApp during testing.
- ~~Images not persistent~~ — SOLVED 2026-07-03: moved to Supabase Storage (see UPDATES — 2026-07-03).

### Deferred by decision (fine for testing)
UltraMsg renewal · removing OTP/`0000`/`debug_notifications` · `APP_DEBUG=false` · ShamCash payout+refund API · reviews completed-only rule · pagination · Arabic translation · chat · favorites (Amer's task).

> **Docs:** `docs/vendor-api.html` is the up-to-date Vendor App API reference for the Flutter team (new categories, `vendor_style`, Portfolio section).

---

## UPDATES — 2026-07-03 (Supabase images + booking items / cart)

### 1. Supabase Storage — images now survive deploys
- Railway's container disk is wiped on every deploy, so locally-stored uploads kept disappearing. All image uploads/deletes moved from the local `public` disk to a new S3-compatible **`supabase` disk** (`config/filesystems.php`), backed by Supabase Storage (free tier, bucket `Haflati`, public reads).
- Package: `league/flysystem-aws-s3-v3`. Env vars (local `.env` + Railway): `SUPABASE_S3_ENDPOINT`, `SUPABASE_S3_REGION`, `SUPABASE_S3_ACCESS_KEY_ID`, `SUPABASE_S3_SECRET_ACCESS_KEY`, `SUPABASE_S3_BUCKET`, `SUPABASE_PUBLIC_URL`.
- New computed URL fields in every JSON response (Flutter uses these directly): `image_url` on `VendorProductImage` + `PortfolioItemImage`, `profile_image_url` on `User` + `Vendor`. DB still stores only the short path.
- Supabase free tier pauses after ~7 idle days (resume in dashboard). **Rotate the S3 secret key before launch.**

### 2. Booking items — cart-style orders (`booking_items` table)
- An order can now hold **multiple different products from the same vendor, each with its own quantity** (e.g. 1× Red Rose + 2× White Rose). New table `booking_items`: `booking_id`, `vendor_product_id`, `quantity`, `unit_price` (**price snapshot at booking time** — later vendor price changes don't alter what an existing order owes). Model `BookingItem`; `Booking::items()` hasMany.
- `POST /bookings` accepts either the old shape (`vendor_product_id` + optional `quantity`) or `items: [{vendor_product_id, quantity}, ...]`. Duplicate product ids are merged. Appointments unchanged: single package, quantity forced to 1 (`items`/qty>1 → 422). All items must be same vendor (422). Per-item availability + stock guards at booking (409 "Only N of 'X' left in stock").
- `bookings.vendor_product_id` still points to the first item (kept so existing vendor endpoints don't break).
- **Payment:** order total = sum of `unit_price × quantity` over items; appointment stays deposit% of package. Commission 15/85 applies to the total.
- **Stock:** approve decrements **every item by its quantity** atomically (`WHERE stock >= qty`), all-or-nothing in one transaction — if any item can't be covered the whole approval fails 409 and rolls back. Cancel of an approved booking restores every item's quantity. Sold-out auto-hide/un-hide unchanged.
- `PATCH`-style `POST /bookings/{id}` on an unpaid order draft can replace the items list (same guards, new snapshots).
- Bookings now return `items` (with product) everywhere: user store/update/index, vendor index/show/recentOrders, payment response, approve/decline/complete.
- Full flow verified locally: multi-item book → `0000` pay (exact total) → approve (stock −per item) → cancel (stock restored, wallet netted); guards: overbook, mixed vendors, appointment+qty, plain appointment regression.

---

## UPDATES — 2026-07-15 (booking refactor, Supabase verified live, design system, user-app roadmap)

### 1. Booking store() refactored — strict order/appointment split (BREAKING for Flutter)
- `store()` is now a small dispatcher: resolves the vendor from the request, runs the shared banned-vendor guard, then routes by `booking_style` to two private methods. One endpoint stays (`POST /bookings`) — the server decides the branch, Flutter can't call a "wrong" endpoint.
- `storeOrder()` (sellers): **`items[]` is now the ONLY shape** — the legacy `vendor_product_id + quantity` shape for orders was REMOVED. Even one product = `items: [{vendor_product_id, quantity}]`. Appointment fields (`event_date`, `event_location`, `duration_hours`) are `prohibited` → 422.
- `storeAppointment()` (services): `vendor_product_id` + event fields only; `items`/`details`/`delivery_*` are `prohibited` → 422. Also creates ONE `booking_items` row (qty 1, price snapshot) so every booking reads the same downstream.
- Validation rules moved to their own files (Laravel FormRequest pattern): `app/Http/Requests/StoreOrderBookingRequest.php` + `StoreAppointmentBookingRequest.php`; controller does `$request->validate((new StoreOrderBookingRequest())->rules())`.
- `update()` is strict per the booking's style too: order drafts can't send event fields, appointment drafts can't send cart fields.
- Cart merge is the named map `$quantityByProductId = collect(items)->groupBy('vendor_product_id')->map(sum quantities)` — same product twice in the request merges into one line (key = product id, value = total qty).
- Status at 2026-07-15: cart base commit `75a1e5a`→`75c1e5a` is pushed on `dev`; the refactor above is in the working tree, NOT yet committed — Mohamad reviewing before push. `main` does NOT have booking_items yet.

### 2. Supabase images — verified live on Railway production
- End-to-end proof: fresh vendor on prod → `POST /vendor/products` with a real 47KB jpg → response `image_url` on Supabase → GET on that URL = HTTP 200, identical byte size. Images now survive deploys for real.
- Supabase facts (Mohamad asked): 1 GB storage is PERMANENT total capacity (not monthly, never resets). The "paused after 7 idle days" email = warning only for zero-activity projects; pause deletes NOTHING, one-click restore within 90 days. Any dashboard visit / upload / image GET resets the timer. Keep-alive idea deferred; manual touching is fine during testing.
- Supabase org `Haflati`, project `events-api` (ref `hlgbsvyaoruetfrkvezb`), region eu-central-1, bucket `Haflati` (public). GitHub NOT connected to Supabase; RLS irrelevant (we only use Storage via S3 keys).
- Railway CLI note: link is per-machine and can drop — `railway link --project distinguished-imagination` then `railway ssh --service events-api "php artisan migrate:fresh --force"`.

### 3. Design system handoff (customer app blueprint)
- Full design system delivered: `Haflati Design System-handoff/` (tokens, components, user + partners UI kits). Untracked copy sits in repo root as `desgin system/`. Customer screens: Onboarding, EventSelect (occasions), Home, Explore (map/GPS), Filters, Vendor, ServiceVendor, Reviews, Booking, Bookings, CancelBooking, Saved, Messages, Profile.
- Designs are guidance, NOT contract — Mohamad decides what's worth building. Confirmed skips: "Cash at Venue" (breaks pay-first model — deliberate), delivery fee (fold into price v1), occasion selector backend (pure client-side mapping), Event Planner tab (already removed from design), AI UI (future).
- Design/schema gaps decided earlier: `quantity` → SOLVED via booking_items; guests count → pending small nullable column; pay-in-full vs 20% deposit → pending business decision (currently deposit forced for appointments).

### 4. USER APP — missing endpoints roadmap (rethought vs designs, ordered by importance)
The vendor app is essentially API-complete. The customer app can auth/book/pay/review but CANNOT DISCOVER vendors. Missing, in priority order:

**CRITICAL (one VendorBrowseController, unblocks Home/Explore/Filters/Vendor screens):**
1. `GET /vendors` — public browse. Query params carry ALL the Filters screen: `vendor_type`, `city`, `min_rating`, `min_price`/`max_price` (needs computed `from_price` = min product price), `verified` (is_approved), `available_on` (date → excludes vendors with a pending/approved booking that day), `search` (name), `sort` (top_rated | most_booked | nearest | newest), pagination REQUIRED from day one.
2. `GET /vendors/{id}` — public vendor detail header: name, bio, rating_avg, review count, city, verified, from_price, events-hosted count (completed bookings), joined date. (Products/portfolio/reviews sub-lists already exist.)
3. `GET /vendors/nearby?lat=&lng=&radius=` — Explore map tab. Haversine on the existing lat/lng columns; returns distance_km, sorted.
4. `GET /categories` — the 10 vendor_type tiles + vendor counts (or hardcode in Flutter — Mohamad decides).

**IMPORTANT (small, complete the core loops):**
5. `GET /bookings/{id}` — user booking detail (vendor side already has show()).
6. Favorites/Saved — `GET /saved`, `POST /saved/{productId}`, `DELETE /saved/{productId}` + `is_saved` flag in product JSON. **Assigned to Amer.** (Design: favorites are on PRODUCTS, not vendors.)
7. `GET /vendors/{id}/booked-dates` — public mirror of the vendor endpoint (booking calendar + "available on my date" filter).
8. `POST /logout` + `POST /vendor/logout` — DONE 2026-07-31 (revoke current token + null fcm_token). See the 2026-07-31 UPDATES section.
9. `GET /my-reviews` — user's own reviews (Profile → Activity shows Bookings/Reviews/Saved).

**NOT building:** review replies (vendor replying to reviews) — decided out 2026-07-15 even though the design shows "Replies."

**NICE-TO-HAVE / DECIDE LATER:**
11. `guests` nullable int on bookings (Booking screen "Number of Guests").
12. Pay-in-full option (`pay_full` flag changing expectedAmount) — needs business decision first.
13. `GET /home` aggregate (one call = top-rated + recommended + categories) — perf polish only.
14. Global `GET /search?q=` — v1 can lean on `GET /vendors?search=`.

### 5. Chat system — agreed simple plan (after user endpoints)
> **⚠️ SUPERSEDED 2026-08-14 — chat was BUILT backend-owned (MySQL), NOT Firestore. See "UPDATES — 2026-08-14 (user↔vendor chat)". The plan below is kept for history only.**
- **Messages live in Firebase Firestore, handled by the Flutter SDK client-side. The Laravel backend stores NO messages.** Free Spark tier, no card, realtime out of the box.
- Thread id convention: `chat_{userId}_{vendorId}` (deterministic — both sides derive it, no create-thread endpoint needed).
- Backend's only jobs: (a) `POST /chat/notify` — called by the sender's app after writing a message, backend fires FCM to the other party via existing NotificationService (Cloud Functions would need the paid Blaze plan/card — this avoids it); (b) optional later: mint Firebase custom auth tokens (we already have the service-account creds) so Firestore security rules can lock threads to their two participants.
- Design's Archived/Unread/Online states: all client-side Firestore fields, zero backend.

### 6. Arabic / translation — agreed simple plan
- **UI strings: 100% Flutter-side** — the design system already ships `assets/translations/ar.json` + `en.json`. Zero backend work. RTL is Flutter layout work; tokens are direction-ready.
- **API messages (validation/errors/notification titles): Laravel lang files** — `lang/ar/` + `lang/en/`, small middleware reads `Accept-Language` header and `App::setLocale()`. Wrap user-facing strings in `__()` progressively (notifications first, validation messages come translated by Laravel for free).
- **User-generated content (names, bios, product descriptions): NOT translated.** Syrian vendors type Arabic already; content stays as typed. (Optional far-future: dual name_ar/name_en fields.)

### 7. Smart Search API — separate FastAPI service (Moh + Amer, upcoming)
- New standalone **FastAPI (Python) service** connecting to the SAME MySQL DB, powering AI/semantic "smart search" features for the app. Integration model: Flutter (or Laravel proxy) calls the FastAPI endpoints; it reads vendors/products from the shared DB.
- Recommendations recorded for when it starts: give it a **read-only MySQL user** (it must never write app tables), host as a second Railway service in the same project (gets internal `mysql.railway.internal` access), keep it stateless, version its API (`/v1/...`), and don't block app flows on it (search degrades gracefully if the service is down).

### Standing corrections to older sections
- "FUTURE FEATURES → Cloudinary" is obsolete — persistent images are DONE via Supabase.
- "Browse/Search API" + "Unavailable dates endpoint" are now items 1–4 + 7 of the roadmap above.
- Old "IMMEDIATE (Current Session)" list (fix BookingController/PaymentController, admin table...) is all DONE long ago — kept below only as history.

---

## UPDATES — 2026-06-29 (Amer + Claude session: escrow fix + auto-complete)

> ⚠️ **Mohamad — this touches the wallet you built.** The change is intentional; please read before merging.

### 1. Vendor earnings now clear on COMPLETION, not 3 days after approval (ESCROW FIX)
- **Problem it fixes:** the old rule cleared a booking's payout **3 days after approval**. But an appointment (e.g. a wedding hall) can be booked months ahead, so the vendor could **withdraw money for a service that hadn't happened yet**. If the booking was then cancelled or the vendor banned before the event, the money was already gone — the platform couldn't refund the customer.
- **New rule:** a booking's earnings stay in **escrow (`pending_clearance`)** while the booking is still `pending`/`approved`, and only become **withdrawable (`available`)** once the booking is **`completed`** (service delivered) or **`cancelled`** (final). This keeps the money refundable right up until the service actually happens.
- **Where:** `WalletController::balances()` — the clearing test is now `in_array($booking->status, ['completed','cancelled'])` instead of the `created_at + 3 days` time check. `HOLD_DAYS` const removed. `withdraw()` now eager-loads `booking:id,status`. `pending_note` text updated.
- **No API shape change** — same `available_balance` / `pending_clearance` / `total_earned` keys; only *when* money moves between them changed.

### 2. Bookings auto-complete 1 day after the event/delivery date — NEW
- New command **`bookings:auto-complete`** (`app/Console/Commands/AutoCompleteBookings.php`): finds `approved` bookings whose service date has passed by 1 day and sets them `completed`. Appointment vendors are judged by `event_date`, order vendors by `delivery_date`.
- Scheduled **daily at 01:00** in `routes/console.php` (`Schedule::command('bookings:auto-complete')->dailyAt('01:00')`).
- **This is what makes escrow money clear on its own** — once a booking auto-completes, its earnings become withdrawable.
- ⚠️ **Railway:** the scheduler must actually run. Add a Railway **Cron** that runs `php artisan bookings:auto-complete` on `0 1 * * *` (or a cron running `php artisan schedule:run` every minute). Without it, bookings never auto-complete.

### 3. Vendors can't mark a booking complete before the date — guard added
- `BookingController::complete()` now rejects (422) if `now()` is before the booking's `event_date` (appointment) / `delivery_date` (order). Prevents a vendor from faking an early completion to cash out an escrowed booking. The manual "complete" button still works, but only on/after the real date; otherwise the daily auto-complete handles it.

### Not done yet (still open in this session)
- **Ban with active bookings** — deciding/implementing what happens to a banned vendor's in-flight bookings (winding-down vs immediate refund). Customer refund is **record-only** for now (real payout waits on ShamCash payout API).

---

## UPDATES — 2026-06-29 (Amer + Claude session: admin system COMPLETED)

The admin module is now feature-complete for everything possible pre-payout-API. Two-layer auth is unchanged (`auth:admins` + `role:` middleware). Role split: **support = view + KYC only**; **super_admin = everything** (bans, money, disputes, moderation, managing admins).

### 1. Shared cancellation service — `App\Services\BookingCancellationService`
- `cancelByPlatform(Booking, reason, notify=true)`: the single place that cancels a booking on the platform's behalf and settles money **fairly** — the customer always gets a **100% refund** (they did nothing wrong), unlike a customer self-cancellation which keeps BookingController's timed deposit tiers.
- What it does (all inside a DB transaction): idempotency guard (skips already-finished bookings) → for an `approved` booking it **reverses the vendor's wallet credit** (a negative `refund` row netting the credit to 0) and **restores stock** → sets status `cancelled` → notifies the customer.
- Reused by BOTH the ban flow and dispute resolution, so they can never drift apart.

### 2. Vendor account states — added `winding_down` (3-state model)
- New `vendors.winding_down` boolean. Combined with `is_active`, a vendor is now: **active** (`is_active=1`), **winding_down** (`is_active=0, winding_down=1`), or **banned** (`is_active=0, winding_down=0`).
- `Vendor::$appends` exposes **`account_status`** = `active | winding_down | banned` in JSON.
- `Vendor::finalizeBanIfCleared()` flips a winding-down vendor to fully banned once they have no more `pending`/`approved` bookings. Triggered by the `Booking` model's `updated` event (terminal status) **and** by the `bookings:auto-complete` command (which bulk-updates and so finalizes affected vendors itself).
- A **winding-down** vendor is hidden from search and can't take new bookings (both driven by `is_active=0`), but the `EnsureActive` middleware and vendor login now let them through so they can finish existing work.

### 3. Two ban modes + unban (super_admin ONLY) — replaced the old `toggleVendor`
- `POST /admin/vendors/{id}/ban` — **immediate**: cancels + refunds every in-flight booking via the service, then fully bans. For fraud/urgent.
- `POST /admin/vendors/{id}/ban-gradual` — **winding-down**: keeps only **committed** (`approved`) bookings so the vendor can finish them; drops unpaid drafts and **cancels + 100%-refunds any `pending`** booking (not yet approved = no commitment). Auto-finalizes to banned once the approved ones are done. (If there were no approved bookings, it bans immediately.)
- `POST /admin/vendors/{id}/unban` — reinstate to `active`.

### 4. Dispute resolution — `POST /admin/bookings/{id}/cancel` (super_admin)
- Cancels ONE booking and refunds the customer 100% (same shared service), **without** touching the vendor's account. For complaints ("vendor no-show / bad service").

### 5. Also added
- **Detail views:** `GET /admin/users/{id}` (user + their bookings), `GET /admin/bookings/{id}` (full booking incl. payment). `GET /admin/vendors/{id}` already existed.
- **Search / filters:** `GET /admin/vendors?search=&is_active=`, `GET /admin/users?search=`, `GET /admin/bookings?status=&vendor_id=&user_id=`.
- **Review moderation:** `GET /admin/reviews?vendor_id=` (both roles), `DELETE /admin/reviews/{id}` (super_admin) — recomputes the vendor's `rating_avg` after deletion.
- Every sensitive action writes to `admin_audit_logs`.

### 6. Financial statistics
- **`GET /admin/stats/financial`** (super_admin ONLY) — the full money picture from **verified** payments: `summary` (all-time `gross_volume` = customer paid, `platform_profit` = commission, `vendor_payouts`, `transactions`), plus `today` / `this_month` / `this_year` windows and a zero-filled **12-month `monthly_trend`** for charting.
- `dashboard()` reworked: fixed a bug where monthly revenue used `whereMonth` without a year (counted that month across all years) and didn't filter `status=verified`. Renamed the money keys to **`profit_today` / `profit_month` / `profit_all_time`** and added `banned_vendors`, `total_bookings`, `completed_bookings`. (Naming change — tell Ali; the admin frontend isn't built yet.)

### 7. Content moderation + money oversight
- New shared **`App\Services\WalletService::balances()`** — the balance math moved out of `WalletController` (which now delegates to it) so the vendor's own wallet and an admin viewing it always agree.
- **`GET /admin/vendors/{id}/wallet`** (super_admin) — any vendor's balances + full ledger, for money disputes.
- **`DELETE /admin/products/{id}`** (super_admin) — remove an inappropriate product listing (+ its images from storage).
- **`DELETE /admin/portfolio/{id}`** (super_admin) — remove an inappropriate portfolio item (+ its images).
- All audited (`product.delete`, `portfolio.delete`).

### 8. Money oversight — refunds due & withdrawals (closes the money loop)
- Added tracking columns: `bookings.refund_amount` + `bookings.refund_paid_at`, and `wallet_transactions.paid_at`.
- When a paid booking is cancelled, the amount owed to the customer is now **recorded** on the booking (`refund_amount`): 100% for a platform cancellation (ban/dispute), or the deposit-tier % for a customer self-cancellation. Both `BookingCancellationService` and `BookingController::cancel` set it.
- **`GET /admin/refunds-due`** (super_admin) — cancelled bookings still owed a refund (+ `total_due`). **`POST /admin/refunds/{id}/mark-paid`** — admin marks it paid after sending money manually.
- **`GET /admin/withdrawals?unpaid=1`** (super_admin) — vendor withdrawal requests (+ `total_unpaid`). **`POST /admin/withdrawals/{id}/mark-paid`** — mark a payout done.
- Gives the admin full money visibility now; the actual send stays manual until the ShamCash payout API.

### ⚠️ Notes / still open
- **Customer refunds are RECORD-ONLY** at the send step. The booking is cancelled and the vendor's credit reversed, but actually sending money back to the customer waits on the **ShamCash payout API** (not built) — admin does it manually for now. The refund intent is captured in the audit log + the cancelled booking + verified payment.
- Deferred (depend on future work): withdrawal/payout approval UI, complaints system, broadcast notifications.
- Local admin tests were removed at Amer's request; behaviour was verified via tinker (immediate ban, gradual ban + auto-finalize, dispute — all pass). NOTE: `tests/Feature/AdminFullTest.php` (19 tests) is now back in the repo and passing.

---

## UPDATES — 2026-08-01 (Amer + Claude session: admin final review — ban guard, refund waive, money consistency)

Final pre-hand-off pass over the admin module + a max-effort code review. Everything
verified through the REAL controllers via tinker on rolled-back throwaway rows.
(Test-harness note: three of the money tests first reported a false "ALL GREEN"
because their `$check` closure wrote to `$GLOBALS` instead of capturing `$pass/$fail`
by reference — 0 assertions actually ran. Fixed the closure and re-ran: clawback 11,
withdrawal-reject 12, net-profit 6 — all genuinely green. Lesson: a green with a
0-assertion count is not green.)

### 1. Code-review fixes (bugs found + fixed)
- **`account_status` fabricated on column-limited loads** — `Vendor::$appends`
  includes `account_status` (reads `is_active` + `winding_down`); admin lists that
  selected `vendor:id,business_name` made both null → every vendor serialized as
  `"banned"`. Fixed two ways: the accessor now returns `null` when its source columns
  aren't loaded, AND the moderation/support/saved selects carry `is_active,winding_down`
  (and `cover_image`, needed by the merged `cover_image_url` accessor — same class of bug).
- **Vendor status tabs didn't partition** — a banned-before-approval vendor matched
  BOTH `kyc_pending` and `banned`. `kyc_pending` (and `pendingVendors()` +
  dashboard `pending_vendors`) now also require `is_active = true`.
- **Vendor-side deletes orphaned content reports** — `VendorProductController::destroy`
  / `PortfolioController::destroy` now delete the item's `content_reports` rows (the
  admin-side deletes already did).

### 2. User ban — guarded, not a blind toggle
- `toggleUser` now REFUSES to ban a user who has a PAID active booking
  (pending/approved) → 422 with the count; the admin must let it complete or cancel it
  first (settlement can't be forgotten). Only unpaid `awaiting_payment` drafts → ban
  proceeds with a `warning` in the response. Ban is a pure lock-out; bookings/money are
  settled manually with the existing tools.

### 3. Refund waive — the third fate of a recorded refund
- `POST /admin/refunds/{id}/waive` (super_admin, reason required): the admin officially
  KEEPS the money (e.g. fraud). New `bookings.refund_waived_at`. Waived refunds leave
  `refunds-due` and its total; `markRefundPaid` refuses a waived one. So every recorded
  refund is now: paid / waived / still-pending.

### 4. Commission-clawback consistency (money logic fix)
- A FRAUD ban (`banVendor`) now charges the platform commission to the vendor on the
  bookings he was committed to (`approved` = already credited), exactly like a
  vendor-requested cancel — a fraudster shouldn't get off lighter than a vendor who
  politely asked. `cancelByPlatform` is called with `chargeCommission: true` only for
  approved bookings (pending/awaiting have no vendor credit to claw). Gradual ban and
  dispute cancel are unchanged (platform absorbs — not the vendor's fault). Ban response
  + audit now carry `commission_charged`.

### 5. Withdrawal rejection
- `POST /admin/withdrawals/{id}/reject` (super_admin, reason required). New
  `wallet_transactions.rejected_at`. A rejected withdrawal no longer counts against the
  wallet (`WalletService::balances()` ignores `rejected_at` rows) → the held amount
  returns to the vendor's `available` automatically, and it leaves the payout queue
  (`?unpaid=1` + `total_unpaid` exclude rejected). `markWithdrawalPaid` refuses a
  rejected one. Handles the case where a clawback makes a wallet negative after a pending
  withdrawal was requested.

### 6. Financial report — GROSS vs NET (honest profit)
- `financials()` gained a `net` block alongside the (unchanged, gross) `summary`:
  `gross_profit`, `refunded_to_customers`, `commission_lost` (refunds we absorbed —
  dispute/gradual-ban, no clawback, not waived), `commission_reclaimed` (clawed back
  from vendors), and `net_profit = gross − commission_lost`. Fixes the old overstatement
  where commission on refunded bookings still counted as profit. (Dashboard headline
  `profit_*` stays gross — it's a glance; the report is the source of truth.)

### 7. Dashboard sidebar badges (nav_badges) — BUILT
- `dashboard()` now returns a `nav_badges` object = live "needs attention" counts for
  the sidebar red dots: `pending_vendors` (KYC queue), `unread_support` (threads with an
  unread user/vendor message), `reported_content` (distinct content items with a pending
  report), `refunds_due`, `unpaid_withdrawals`. Verified 6/6.

### 8. Admin notification BELL (event feed) — BUILT
- The bell is an **event feed** (history you click), complementing the nav_badges (live
  counts). Reuses the existing `notifications` table with `notifiable_type = 'admin'` —
  **no new table**. Fan-out = one row per targeted admin → per-admin read state.
- `NotificationService::notifyAdmins(array $roles, title, body, data)` — role-targeted
  fan-out (inbox-only; admins have no device token). `NotificationController::owner()`
  now detects the admin guard, so the SAME controller powers the admin bell.
- Endpoints (every admin, under `auth:admins`): `GET /admin/notifications`,
  `POST /admin/notifications/read-all`, `POST /admin/notifications/{id}/read`.
- Events wired, each targeted at who can ACT on it:
  - New vendor KYC → both roles. Hooked on `Vendor::created` (model event) so the
    vendor-registration flow (Mohamad's) stays untouched — merge-safe.
  - New support ticket / user reply / vendor message → both roles (SupportController).
  - New content report → `super_admin` only (only they delete/dismiss). Fires once
    (dup re-report is a no-op).
  - New withdrawal request → `super_admin` (WalletController::withdraw).
  - Vendor declines a PAID booking (refund due) → `super_admin` (BookingController::decline).
- Each notification carries a `data.type` (`vendor_kyc | support | content_report |
  withdrawal | refund_due`) + the target id so the console can deep-link. Verified 18/18
  (role targeting, per-admin read state, can't read another admin's notification, dup
  suppression).

### Still deferred (admin) — flagged, not built
- Audit-log TARGET shows `type + id`, not a resolved display name.
- "Warn vendor" (lighter than ban) from a behaviour complaint.
- Minor: AdminSeeder password still `0000`; `docs/admin-system.html` stale (Ali's ref);
  no throttle on ticket/report creation; `firstOrCreate` races (reports / vendor thread).

---

## UPDATES — 2026-07-29 (Amer + Claude session: admin API ↔ React console alignment)

Audited the whole admin module against the actual React admin-console design (there is
now a screen for every page) and closed the gaps where the API didn't yet back a screen.
**No new tables** — every change is on existing `AdminController` endpoints, plus two new
list endpoints for moderation. Each change was verified through the **real controller
methods** via tinker (18 assertions, all green) on throwaway rows inside a rolled-back
transaction — nothing left in the DB.

### 1. Users list — status filter + bookings count
- `GET /admin/users` now takes `?is_active=0|1` (backs the All / Active / Banned tabs)
  and returns `bookings_count` per row (the list's "bookings" column). Was search-only.

### 2. Vendors list — 4-state account filter
- `GET /admin/vendors` now takes `?status=kyc_pending|active|winding_down|banned`,
  matching the vendor page's tabs. The old `?is_active=` boolean couldn't separate
  winding_down from banned (both are `is_active=false`). Mapping:
  - `kyc_pending`  → `is_approved = false`
  - `active`       → `is_active = true AND is_approved = true`
  - `winding_down` → `is_active = false AND winding_down = true`
  - `banned`       → `is_active = false AND winding_down = false`
- `?is_active=` and `?search=` still work.

### 3. Vendor detail — stat counts
- `GET /admin/vendors/{id}` now carries `bookings_count` + `reviews_count` (withCount)
  for the detail panel's "Bookings 41 / (128 reviews)" stats. Still eager-loads
  `products` for the KYC review + listings count.

### 4. Bookings list — search
- `GET /admin/bookings` now takes `?search=` matching booking id, customer name, or
  vendor business name (the page's "Search booking, user, vendor" box). The status /
  vendor_id / user_id filters are unchanged.

### 5. Audit log — search
- `GET /admin/audit-logs` now takes `?search=` matching the action name or the admin's
  name (the page's "Search actions" box).

### 6. Content moderation — browse endpoints (were delete-only)
- The moderation page has 3 tabs (Reviews / Products / Portfolio). Reviews already had a
  list; products & portfolio only had DELETE. Added the two missing lists:
  - `GET /admin/products`  → `AdminController::products`  (`?search=` `?vendor_id=`)
  - `GET /admin/portfolio` → `AdminController::portfolioItems` (`?vendor_id=`)
  - Both load `vendor:id,business_name` + `images`, paginated 20.
  - View is super_admin + support (same as the reviews list); DELETE stays super_admin.

### Still open / decided this session
- ~~Complaints system~~ → BUILT 2026-07-30 as the SUPPORT system (see that session below).
- ~~Content reporting~~ → BUILT 2026-07-30 (see that session below).
- **Admin notification bell** (top-bar count) — no backend yet.
- Minor, left as-is: dashboard `banned_vendors` counts winding_down vendors as banned;
  audit-log TARGET is stored as type+id (the frontend shows a resolved display name).

---

## UPDATES — 2026-07-30 (Amer + Claude session: Saved items / wishlist)

New customer-only feature backing the app's **"Saved"** screen (the heart / ♡ button).
A user saves a product to look at / book later. This is customer-side only —
vendors and admins are not involved.

### 1. What can be saved
- Only **products** (`vendor_products` rows) — NOT vendors. Both "packages" and
  "products" in the UI are the same `VendorProduct` entity; they only differ by
  the owning vendor's `booking_style` (`appointment` = package, `order` = product).
- Named **"saved"** (not "favorites") to match the frontend tab so the two sides
  don't drift on naming.

### 2. Schema — `saved_items` table
- Migration `2026_07_30_000000_create_saved_items_table.php`:
  `user_id` (FK, cascade), `vendor_product_id` (FK, cascade), timestamps.
- `unique(['user_id','vendor_product_id'])` — a user can save the same product
  only once. Cascade means deleting the user or the product auto-removes the row.

### 3. Model + relationships
- New `App\Models\SavedItem` — `user()`, `product()` (FK `vendor_product_id`,
  method named `product()` to match `Booking::product()`).
- `User::savedItems()` hasMany added.

### 4. Controller — `SavedItemController` (all under `auth:sanctum` + `active`)
- `index()` — returns the user's saved items **split into two tabs** the screen
  shows: `packages` (appointment vendors) and `products` (order vendors), plus a
  `counts` object (`{packages, products}`) that feeds the tab labels. A **banned
  vendor's** items are hidden (`whereHas('product.vendor', is_active=true)`),
  same rule as browse/search — the row stays in the DB, just isn't shown.
- `store()` — save a product. Uses `firstOrCreate`, so saving twice is a no-op
  (never a duplicate). Returns 201 on first save, 200 if already saved.
- `destroy($productId)` — remove by **product id** (convenient for the heart
  button, which knows the product id, not the saved-row id). Idempotent.
- `ids()` — lightweight helper for the browse / detail screens: returns ONLY the
  saved product ids (e.g. `[5,7,12]`) so the app can fill the heart on any card
  without downloading the full saved list. `GET /saved` already carries this info
  but ships full product data; `/saved/ids` is the tiny/fast version.

### 5. Routes (user group)
- `GET /saved` — index (packages + products + counts)
- `GET /saved/ids` — just the saved product ids
- `POST /saved` — save (`{vendor_product_id}`)
- `DELETE /saved/{productId}` — unsave

### 6. Bug found + fixed during end-to-end testing
- The `index()` vendor select was column-limited
  (`product.vendor:id,business_name,...`) and **omitted `is_active` / `winding_down`**.
  The `Vendor` model appends `account_status`, whose accessor reads those two
  columns — with them missing they were `null`, so every vendor wrongly serialized
  as `"account_status":"banned"`. Fixed by adding `is_active,winding_down` to the
  select. **Lesson:** an appended accessor needs its source columns present in any
  column-limited `select`, or it silently computes on `null`.

### 7. Testing
- Verified end-to-end over real HTTP (`php artisan serve`): no-token→401, empty
  list, save, idempotent re-save (200, no dup), correct package/product split +
  counts, invalid product→422, delete, and banned-vendor hiding — all pass.
  Test data was seeded then cleaned up (no leftover rows).
- Note: the `vendor_type` enum has been expanded (photographer, makeupArtist, dj,
  weddingHall / flowers, gifts, dresses, accessories, candles, cakes); the old
  `cake_shop` / `store` values are gone. Sellers (order) vs service providers
  (appointment) is derived from `vendor_type` in the auth/profile controllers.

---

## UPDATES — 2026-07-30 (Amer + Claude session: support system, content reporting, vendor-requested cancel)

Built after a full design discussion (plan agreed BEFORE coding). Three features + one
pre-existing money bug fixed. All verified through real controller calls in tinker on
throwaway rows inside rolled-back transactions: support 24/24, reporting 16/16,
money 21/21 — ALL GREEN, nothing left in the DB.

### 1. SUPPORT system (replaces the old "complaints" plan) — BUILT
The final model (decided with Amer):
- **User → tickets.** `POST /support/tickets` opens a ticket: `subject` + first message
  + optional `booking_id` (must be the user's own; gives the admin the full booking
  context) + optional `category` (`no_show|payment|behavior|other` — routes the admin
  to the right tool: money vs vendor behaviour). The user CANNOT write again until an
  admin replies — the ADMIN decides whether a ticket becomes a chat. `resolved` =
  closed for good; a new problem = a new ticket. Other user routes:
  `GET /support/tickets`, `GET /support/tickets/{id}` (marks admin replies read),
  `POST /support/tickets/{id}/messages` (422 while `open` or `resolved`).
- **Vendor → ONE persistent chat** (the SUPPORT button on the vendor home).
  Auto-created on first `GET /vendor/support`; `POST /vendor/support/messages` always
  works — writing to a resolved chat quietly reopens it.
- **Admin → one inbox for both**: `GET /admin/support` (filters `?owner_type=`,
  `?status=`, `?unread=1`; tab counts + total unread badge; owner name/phone attached
  with 2 queries, no N+1). `GET /admin/support/{id}` = conversation + booking context
  (vendor, product, payment) + marks owner messages read. `POST .../messages` = reply
  (moves an `open` user ticket to `in_review`, sets `handled_by`, notifies the owner).
  `POST .../resolve` = close (audited `support.resolve`, owner notified). **Both
  roles** handle support; money actions stay super_admin elsewhere.
- Tables: `support_threads` (`owner_type` user|vendor, `owner_id`, `booking_id` null,
  `subject` null, `category` null, `status` open|in_review|resolved, `handled_by`,
  `last_message_at`, `resolved_at`) + `support_messages` (`support_thread_id`,
  `sender_type` user|vendor|admin, `sender_id`, `body`, `read_at`). `read_at` = read
  by the OTHER side — drives every unread badge.
- Files: `SupportThread`/`SupportMessage` models, `SupportController` (user+vendor),
  `AdminSupportController`, migrations `2026_07_30_1000 00/01`.
- This is user/vendor ↔ ADMIN only — NOT the future customer↔vendor Firestore chat.

### 2. Content reporting (FLAGGED / REPORTED badges) — BUILT
- `content_reports`: `reporter_type` user|vendor, `reporter_id`, `reportable_type`
  review|product|portfolio_item, `reportable_id`, `reason` null, `status`
  pending|dismissed. Unique per reporter+item → re-reporting is a silent no-op (200).
- App routes: user `POST /reviews/{id}/report`, `POST /products/{id}/report`,
  `POST /portfolio/{id}/report`; vendor `POST /vendor/reviews/{id}/report`.
- The three admin moderation lists now carry **`reports_count`** (pending only) —
  badge shows when > 0. `POST /admin/reports/{type}/{id}/dismiss` (super_admin)
  clears a false flag without deleting (audited `report.dismiss`). Deleting content
  also deletes its report rows.
- Files: `ContentReport` model, `ContentReportController`, `reports()` relations on
  Review / VendorProduct / PortfolioItem, migration `2026_07_30_100002`.

### 3. Vendor-requested cancellation (money) — BUILT
`POST /admin/bookings/{id}/cancel-vendor-request` (super_admin). The vendor asked (via
support) to back out of a booking he already APPROVED. Amer's rule: **whoever causes
the cancellation bears the commission.**
- Customer: 100% refund recorded (`refund_amount` → refunds-due) + notified.
- Vendor: escrow credit reversed (−85) **AND** the platform commission charged to his
  wallet (−15, new `commission` ledger row) → he bears the full 100. His wallet can go
  **negative** (owes the platform); `withdraw()` already blocks at `available <= 0`.
  He also gets a notification stating the charge.
- Platform: keeps its 15. Audited `booking.cancel_vendor_request` with both amounts.
- Guard: `approved` bookings only (422 otherwise — a pending one the vendor can
  decline himself).
- The EXISTING dispute cancel (`/admin/bookings/{id}/cancel`) is UNCHANGED — no
  commission charge there (platform-initiated; the vendor didn't ask).
- Wallet plumbing: `wallet_transactions.type` enum now includes `commission`
  (migration `2026_07_30_100003`); `WalletService::balances()` nets commission rows
  into the booking group; `BookingCancellationService::cancelByPlatform()` gained a
  `$chargeCommission` flag (default false — ban/dispute flows untouched).

### 4. Pre-existing money BUG fixed — vendor decline of a PAID booking lost the refund
`BookingController::decline()` declined a `pending` (= PAID, pay-first) booking without
recording any refund or telling the user — the owed money silently disappeared from the
refunds-due loop. Now decline records `refund_amount` (100%, from the verified payment)
so it shows in `/admin/refunds-due`, and notifies the user. Note for the Flutter team:
decline now sends the user a push + inbox notification.

---

## UPDATES — 2026-07-30 (later: delivery review fixes + user-ban guard + refund waive)

A max-effort code review of the whole admin module ran before hand-off. It found 3
confirmed bugs (all fixed) and Amer added two deliberate features. Verified:
review-fixes 10/10, ban+waive 18/18 (controller) + 12/12 (real HTTP), full suite 20/20.

### Review fixes (3 confirmed bugs)
1. **`account_status` was fabricated as "banned" on column-limited loads.** The admin
   moderation lists and support views load `vendor:id,business_name` only; the
   `Vendor::$appends` `account_status` accessor reads `is_active`/`winding_down`, which
   came back null → an ACTIVE vendor serialized as `"banned"`. (Same class of bug the
   Saved-items work hit earlier.) Fix, two layers: the accessor now returns **null**
   when its source columns aren't loaded (never guesses), and the moderation/support
   selects now include `is_active,winding_down`.
2. **Vendors `?status=` tabs didn't partition.** `kyc_pending` filtered only
   `is_approved=false`, so a vendor banned before approval matched BOTH `kyc_pending`
   and `banned` → tab counts didn't add up. Fix: `kyc_pending` now also requires
   `is_active=true`; `pendingVendors()` + the dashboard `pending_vendors` stat match.
3. **Vendor-side deletes orphaned content-reports.** Admin deletes cleared
   `content_reports`, but `VendorProductController::destroy` / `PortfolioController::destroy`
   didn't → pending reports pointed at deleted content forever. Fixed both.

### Feature — user ban is a guarded LOCK-OUT (Amer's design)
Banning a user (`POST /admin/users/{id}/toggle`) never touched their bookings/money;
in-flight paid bookings silently rode to completion. New rule (agreed with Amer —
settlement stays a manual, per-case admin decision, banning must not FORGET it):
- **Paid active booking (`pending`/`approved`) → ban REFUSED (422)**, response names the
  count. The admin must let it complete or cancel it (refund recorded) first. No override.
- **Only unpaid drafts (`awaiting_payment`) → ban proceeds**, response carries a
  `warning` (nothing was paid; drafts just stay unpaid).
- Unban unchanged. Audit meta records `unpaid_drafts` on a ban.

### Feature — waive a recorded refund (the "keep the money" fate)
A recorded refund (`bookings.refund_amount`) had only two fates: paid, or pending
forever. Fraud cases (admin decides to KEEP the money) had no representation and clogged
`refunds-due`. New third fate:
- Column `bookings.refund_waived_at` (migration `2026_07_30_100004`).
- `POST /admin/refunds/{id}/waive` (super_admin) — **requires a reason**, audited
  `refund.waive` with amount + reason. Guards: can't waive an already-paid or
  already-waived refund.
- `refundsDue()` (list + `total_due`) now excludes waived; `markRefundPaid()` refuses a
  waived refund. So every recorded refund is now: **paid** / **waived (kept, with a
  logged reason)** / **still pending** — matching Amer's "either refund them, or keep it
  because they're a fraud" model.

### Still open (not blockers, flagged for hand-off)
- Admin **notification bell** — still no backend.
- **Financial report counts gross commission**, not net — commission on cancelled/
  refunded bookings is still counted as profit (overstates it; vendor-requested cancels
  are fine since the commission is really collected). Needs a short decision with Amer.
- Withdrawal requests can be marked paid but not **rejected**.
- `AdminSeeder` password is still `0000` — change before any real environment.
- `docs/admin-system.html` is now STALE (no support / reporting / new cancels / ban
  guard / waive) — Ali's reference needs regenerating.
- Review's "PLAUSIBLE" items left as-is: no throttle on ticket/report creation;
  `firstOrCreate` races on `content_reports` / vendor support thread.

### Merge with Mohamad's `dev` batch (2026-07-30, local only — NOT pushed)
Followed Mohamad's merge note (top of this file): kept BOTH sides on every conflict.
Conflicts were only from branch drift (his batch didn't touch admin):
- `Booking` casts / `BookingController::store` header / this file — resolved keeping both.
- **Dedup:** both Amer's decline-refund fix and Mohamad's booking-event wiring added a
  decline notification → kept as ONE (was double-notifying the customer).
- `composer install` for Mohamad's new `league/flysystem-aws-s3-v3` (Supabase disk).
- Added dummy `SUPABASE_S3_*` env to `phpunit.xml`: the `Vendor` image-URL accessors
  build an S3 client on every serialize, which threw "Missing required client
  configuration" in tests with no storage creds. (Tinker needs the same vars exported.)
- After merge: full suite 20/20, plus all session feature checks re-run green.

---

## UPDATES — 2026-07-28 (Moh session: admin merge, cover image deployed, booking review fixes)

### 1. Branch state / git
- Amer's admin work lived on branch **`origin/admin-complete`** (NOT `origin/dev`). Merged into local `dev` keeping BOTH sides on every conflict (Vendor `$appends`, BookingController cancel, master context). All 20 tests pass after merge. `dev` is LOCAL only — not pushed (unreviewed by choice).
- Amer's two commits: **escrow fix** (money clears on completion, not 3 days — see 2026-06-29 section) + **admin complete** (ban modes, disputes, moderation, financial stats, money oversight). He did NOT change our plan beyond the escrow rule Mohamad approved; everything else was additive.

### 2. Two Flutter-needed changes shipped to production via a `bug-fix` branch off `main`
Because `dev` had lots of unreviewed work, we did NOT merge dev→main. Instead branched `bug-fix` off `main`, added only the two items, merged to `main`, pushed (deployed). Then replicated on `dev`. (Workflow saved as a Claude memory.)
- **Customer identity in vendor booking responses:** the vendor-facing responses (`GET /vendor/bookings` list, approve, complete, decline) now eager-load the customer as **`user` { id, first_name, last_name, profile_image }** so the vendor app shows who booked instead of only a `user_id`. Customer-facing responses (store/update) deliberately unchanged.
- **Vendor cover image (NEW):** `vendors.cover_image` column; upload via `POST /vendor/profile` with a `cover_image` file (re-upload replaces, mirrors `profile_image`); **`DELETE /vendor/profile/cover`** to remove; **`cover_image_url`** computed in JSON (Supabase, stored under `vendor_covers/`). Verified live end-to-end on production (upload → Supabase URL 200 → delete → null).
- Production DB was reset with `migrate:fresh --force` (Option A — test data only) so the new column applied. `docs/vendor-api.html` updated (Arabic) for both.

### 3. Booking code review — logic fixes (dev, all 20 tests pass)
Reviewed the refactored `store()`/`storeOrder()`/`storeAppointment()` + FormRequests. Found and fixed:
- **REGRESSION fix (was BUG #15):** the date-conflict check had dropped `awaiting_payment`. Re-added to BOTH `storeAppointment()` and `update()` → conflict now blocks `['awaiting_payment','pending','approved']`. Without it, two users could book the same date while one was still an unpaid draft.
- **Appointment date now required:** `StoreAppointmentBookingRequest` `event_date` is now `required|date|after:now` (was `sometimes|nullable`). A service booking must have a future date.
- **Vendor guard hardened:** `store()` now blocks a booking unless the vendor is **`is_approved` AND `is_active`** (was only `is_active`). An unapproved/KYC-pending vendor can no longer be booked even by POSTing a product id directly.

### 4. Double-booking race condition — FIXED with a DB-level slot lock (NEW columns)
The `->exists()` check + `create()` weren't atomic → two simultaneous requests could both pass and double-book a date. Fixed the same way `transaction_id` is protected (DB constraint + catch):
- **`bookings.event_day`** — a STORED generated column = `DATE(event_date)` (NULL when `event_date` is NULL). **Unique index on `(vendor_id, event_day)`** → the database itself forbids two bookings for the same vendor on the same day (day-level, matching the `whereDate` logic). MySQL ignores NULLs, so orders and cancelled/declined appointments are exempt and never collide.
- **`bookings.old_event_date`** — preserves the date for display after cancel/decline. A `Booking::saving` model hook: when status becomes `cancelled`/`declined`, it copies `event_date` → `old_event_date` and nulls `event_date` (frees the slot + drops the row out of the unique index). One hook covers all 3 cancel paths + decline.
- `storeAppointment()` create wrapped in try/catch → a race that slips past the PHP check is caught as a clean `409 "This date is already booked"`.
- **Flutter display rule:** show `event_date ?? old_event_date` (live date, or the archived one for cancelled/declined bookings).
- Verified: book → double-book blocked by DB → cancel archives date → rebook same date succeeds.

### 5. Availability calendar — booked + manual blocks (NEW, appointment vendors only)
The old `GET /vendor/booked-dates` (bookings only, read-only) was replaced by a real availability system, because a vendor also gets booked OFFLINE (walk-in / phone) and must be able to block those days manually.
- **New table `vendor_blocked_dates`** (`vendor_id`, `date`, `reason` nullable, unique `(vendor_id, date)`). Model `VendorBlockedDate`; `Vendor::blockedDates()`. Day-level only (hours are a per-service detail — vendors put them in the product's `meta`).
- **New `AvailabilityController`:**
  - `GET /vendor/availability` (vendor) → `{ booked: [dates], blocked: [{id,date,reason}] }` — labeled so the app shows why each day is off.
  - `POST /vendor/blocked-dates` (vendor) → block a day (`date` required, `after_or_equal:today`; optional `reason`). 409 if the day already has a booking. Idempotent (firstOrCreate).
  - `DELETE /vendor/blocked-dates/{date}` (vendor) → unblock (404 if not blocked).
  - `GET /vendors/{id}/availability` (PUBLIC) → one flat `unavailable` list = booked + blocked merged, so the customer app greys out days BEFORE booking. Fills roadmap item #7.
- **Booking guard updated:** `storeAppointment()` now also rejects a manually-blocked date (409 "This date is not available"), so a manual block really prevents in-app bookings. Availability held-status set is `['awaiting_payment','pending','approved']` (matches the conflict guard).
- Order (seller) vendors have no calendar — they take many orders per day; all of this is appointment-only.

### 6. Vendor self online/offline toggle (NEW)
Separate from the admin ban (`is_active`) and the per-date blocks. A vendor can set themselves unavailable for NEW bookings while staying logged in and managing existing ones.
- **New column `vendors.is_accepting_bookings`** (boolean, default true). NOT the same as `is_active` — `is_active=false` is the ADMIN ban and blocks login; `is_accepting_bookings=false` is vendor-controlled and does NOT block login.
- **`POST /vendor/availability/toggle`** (`VendorProfileController::toggleAvailability`) — send `{ is_accepting_bookings: true|false }` or nothing to flip. Returns the new value.
- **Behavior (per Mohamad's decision):** an offline vendor STILL appears in browse — the flag `is_accepting_bookings` is returned in the vendor card + full vendor JSON so the customer app shows a "currently not accepting bookings" banner on the profile and disables the booking button. It is NOT hidden from browse.
- **Booking guard:** `store()` now also blocks if `!is_accepting_bookings` (403), so an offline vendor can't receive new bookings even by POSTing directly.
- Verified: default true; toggle off → still in browse (flag=false) → booking 403; toggle on → booking 200. All 20 tests pass.

> **FIXED 2026-08 (commit f3583c4)** — the public sub-resource endpoints now gate on vendor state. `GET /vendors/{id}/reviews`, `/portfolio`, `/products/search` require the vendor to be `is_approved` AND `is_active` (return empty otherwise) — a banned/unapproved vendor's data no longer leaks. `searchVendorProducts` also filters `is_available` + `is_hidden`. **Also FIXED same commit:** the `User` model now hides `fcm_token` in JSON (it was exposed on `GET /profile` + user-auth responses; `Vendor` already hid it).

### Still open / deferred
- `dev` not pushed (unreviewed booking refactor is the main reason — now reviewed + fixed, so closer to shippable).
- `update()`'s order-cart replacement path reviewed only lightly; PaymentController item-total sum not re-audited this session.
- Homepage `GET /products` (+ `discount_percent` for Best Offers) still the next customer-app build.

> **IMPORTANT — Home page redesigned (build to the NEW design, not the folder).** The Home screen in `desgin system/` (the static handoff copy) is OUTDATED. The current Home is the redesign Mohamad approved with Claude Design — **5 rails**: (1) **Top Rated Vendors** (vendors) → `GET /vendors?sort=top_rated`; (2) **Best Offers** (items with a discount badge + strikethrough price) → needs `discount_percent`; (3) **Discover Services** (service items, horizontal) → `GET /products?type=service`; (4) **Discover Products** (product items, horizontal) → `GET /products?type=product`; (5) **Recently Added** (items, NEW badge) → `GET /products?sort=newest`. Search is always over items (`GET /products?search=`). The occasion selector at the top only changes Top Rated Vendors + the Explore banner (client-side mapping) — it does NOT affect the item rails. Service vs product = the vendor's `booking_style` (`appointment` = service, `order` = product). Build backend to THIS, not the old Home HTML.

---

## ⭐⭐ UPDATES — 2026-08-19 (Moh + Claude session: product detail, saved-items leak fix, CORS gap, phone-normalization DEPLOYED + fixed outage, vendor location replaces city, ShamCash payout account)

> **Superseded within this same date** — §3 below originally said the phone fix was "written, NOT deployed." It was deployed later the same session, broke every existing login, got fixed, and got backfilled. See §5 and §6 for the real sequence — read those before assuming §3 alone is current.

### 1. `GET /products/{id}` — DEPLOYED (closes the roadmap item from 2026-08-16/17)
Public, no auth. One endpoint for every path into the product detail screen (Home
rail tap, search, or inside a vendor profile — same screen either way). Returns
**ALL images** (primary sorted to `images[0]`) + the **full vendor** object (with
`is_active`/`winding_down` in the select so `account_status` actually computes,
unlike the `null` you get from the `GET /products` list). Same visibility rule as
everywhere else — sold-out, hidden, or a not-approved/not-active vendor's product
all 404 identically to a missing id (`{"status":"error","message":"Product not
found"}`, en/ar), so nothing leaks by guessing an id. Verified against real
production data post-deploy (product id 1, a wedding hall package) — full shape,
correct `account_status:"active"`, no `fcm_token`/`remember_token` leak. Route
registered at `/products/{id}`, **after** the static `/products` route (same
"keep the specific route first" rule as `/vendors/nearby`). Commit `2139f7d`.

### 2. Saved-items visibility leak — FOUND AND FIXED
Audit (prompted by the customer visibility rule discussion) found `GET /saved`
filtered only `vendors.is_active`, and `GET /saved/ids` filtered **nothing**.
Result: a product the vendor had hidden (`is_hidden=1`), or one belonging to an
unapproved vendor, stayed in a user's Saved screen with the heart still lit —
even after it had already vanished from browse/search/detail. Both now apply the
full four-flag rule (`is_approved` + `is_active` on vendor, `is_available` +
`is_hidden` on product). Rows are filtered out, not deleted, so an item
reappears automatically if the vendor un-hides or restocks it. Proved with a
real seeded row before and after the fix (count went 1→0 on hide, back to 1 on
restore); 20/20 suite. Commit `2712541`.

Also folded into this commit: `smart-search.md` got a real update — the four-flag
visibility rule is now marked non-negotiable with a table of what each flag
leaks if dropped, and two stale claims were corrected (`lang/` now exists;
work goes to `main` not `dev`). The old note claiming `searchVendorProducts`
still ignored `is_available` was wrong — that one was already fixed earlier;
this settles that discrepancy for good. See `smart-search.md` § the visibility
rule / § 4 leak audit.

### 3. Phone-number login bug — root cause confirmed, fix WRITTEN, NOT yet deployed
Mohamad reported: a customer/vendor who signs up typing their phone one way
(e.g. `0933xxxxxx`) and later logs in typing it another way (`933xxxxxx`, no
leading trunk 0) gets silently treated as a brand-new account instead of logged
in, because `UserAuthController`/`VendorAuthController` used `$request->phone`
RAW everywhere — the OTP cache key, the `User`/`Vendor::where('phone', ...)`
lookup, and the `create()` write — with zero normalization. UltraMsg itself
tolerates all the shapes; only OUR matching was broken. Confirmed as a REAL,
already-happened bug, not hypothetical: production already has vendors stored
in different raw shapes side by side (`0949101231` vendor 1, `935983121` vendor
3 — no leading zero).

**Fix written** (NOT committed/pushed yet): new `App\Services\PhoneNumberService::normalize()`
— strips non-digits, then either strips the `963` country code prefix or a
leading trunk `0`, and rebuilds as canonical `+963XXXXXXXXX`. Wired into both
`sendOtp()` and `verifyOtp()` in both auth controllers, applied BEFORE the value
touches the cache key, the DB lookup, or the DB write, so all three input shapes
collapse to one account.

**Deliberately NOT done, per Mohamad's explicit instruction:** no backfill of
EXISTING phone rows (local or production) to the canonical format, and
**no `migrate:fresh`** — "leave the current account and dont ever migrate fresh
... later on when i tell u we can migrate fresh." So: new signups from the
moment this ships will normalize correctly and match on relogin; accounts that
already exist under an inconsistent raw phone stay exactly as they are (and
could still mismatch on a differently-typed relogin) until Mohamad explicitly
authorizes a backfill or a fresh migrate. **Next session: this needs testing,
committing, and deploying** — it was left mid-flow (code written, unlinted-in-CI,
untested) when the session moved to other tasks.

### 4. CORS — found wide open by default, decided to defer (NOT a bug needing an urgent fix)
Question from Mohamad: the React admin dashboard needs its own deploy (Vercel —
recommended, free Hobby tier, no expiry for a non-commercial student project;
the React dev owns that deploy, not the backend side) separate from just having
the Railway API URL. While checking whether that dashboard could reach the API
from a browser: **this repo has no `config/cors.php` and no CORS middleware
registered** — Laravel 12's `HandleCors` middleware IS in the global stack by
default (framework-level, not something we added), and with no published config
it falls back to the vendor default: `allowed_origins => ['*']`. Confirmed LIVE
against production with a real preflight `OPTIONS` request from a fake origin —
`access-control-allow-origin: *` came back. So: the React dashboard will connect
from any Vercel URL with zero config needed — CORS is NOT currently a blocker.
But `*` is wide open to any website, not just our own dashboard's domain — for
an admin panel specifically (not the Flutter apps, which ignore CORS entirely)
that's more exposure than it should have, especially combined with the still-
unrotated seeded admin password. **Decision: defer, not urgent** — same bucket
as the rest of the pre-launch kill list. Once the React dev has his real Vercel
URL, publish `config/cors.php` and lock `allowed_origins` to just that one
domain instead of `*`. Two-minute fix, deliberately not done yet because we
don't have the real URL to lock it to.

### 5. Phone fix DEPLOYED — then broke every existing login, then fixed (read this before touching phone auth again)
§3 above says the phone-normalization fix was "written, NOT deployed." That
changed later the same session: Mohamad approved shipping it, so `981566c`
went out (phone fix + the vendor-location work in §7 below, together).

**It broke login for every account that existed before the deploy.** Root
cause: login always normalizes the typed number to `+963{local}` before the DB
lookup, but — per Mohamad's explicit instruction — existing rows were left in
their ORIGINAL raw shape (`0949101231`, `935983121`, ...), never converted.
`+963949101231` never equals `0949101231`, so **no** pre-existing account could
log in with any input, not just a differently-typed one. Confirmed live —
Mohamad's own vendor login broke immediately, reported same session.

Fixed in `3b89d72`, two parts:
1. New command `php artisan phones:normalize` (`--force` to write, dry-run by
   default) backfills every existing `users`/`vendors` phone through the SAME
   `PhoneNumberService::normalize()` new signups use. No migration, no schema
   change — an `UPDATE` on the existing `phone` column.
2. Found a SECOND real bug while testing the backfill, before it ever touched
   production: `normalize()` was not idempotent. Re-running it on an
   already-canonical `+963900000001` produced `+963963900000001` (double
   country code) — the "already has 963" check had been dropped during an
   earlier simplification pass, so a value with no leading `0` just got `963`
   prepended a second time. Fixed: `normalize()` now checks for the country
   code FIRST, strips it if present, else strips a leading trunk `0`. Verified
   idempotent (`normalize(normalize(normalize(x))) === normalize(x)`) across
   all input shapes before running anywhere near real data.

**Backfill run for real** on both local and PRODUCTION (`railway ssh
--service events-api "php artisan phones:normalize --force"`, dry-run checked
first): 2 users + 5 vendors converted from raw local shape to `+963{local}`.
Reproduced Mohamad's exact bug report afterward on production — sent OTP to
`0949101231`, verified it, got `"status":"login"` with a real token. Confirmed
fixed, not assumed.

**State now:** every account, old and new, stores `+963{local}`. Login works
regardless of which shape (`09...` or `9...`, matches Mohamad's confirmation
that Flutter only ever sends one of those two — never `+963` itself) the
person types, for every account, old and new. The `phones:normalize` command
stays in the codebase (`app/Console/Commands/NormalizeExistingPhones.php`) —
safe to re-run anytime (idempotent, dry-run by default), useful if a future
merge or manual DB edit reintroduces a raw phone.

### 6. Vendor location REPLACES city (Mohamad's request, DEPLOYED same commit as §5's fix, `981566c`)
"There is no more city for vendor, there is location only." Applies to
VENDORS only — the user (`UserAuthController`/`UserProfileController`) side is
untouched, still has `city`.

- `POST /vendor/complete-registration` no longer accepts `city`. `latitude` +
  `longitude` are now REQUIRED (422 if missing); `address` optional.
- Every place vendor data is returned now carries `latitude`/`longitude`/
  `address` instead of `city`: `GET /vendors` (browse), `GET /vendors/{id}`
  (detail), `GET /vendors/nearby`, and the embedded vendor mini-object on both
  `GET /products` and `GET /products/{id}`.
- The `?city=` browse filter is REMOVED entirely (Mohamad's call — customers
  already have `sort=nearest&lat=&lng=`, the real replacement).
- `vendors.city` DB COLUMN IS UNTOUCHED — no migration, per "no migrate:fresh."
  Code just stops reading/writing it. The column sits dead in the schema.
- Verified against real inserted rows through the actual controllers: `city`
  genuinely absent (not null-but-present) from all five read paths;
  registration 422s on missing lat/lng; `?city=` is now a harmless no-op.

### 7. Vendor ShamCash payout account — NEW (Mohamad's request, `c19cf50`, DEPLOYED)
The withdraw flow (`POST /vendor/withdraw` → admin notified → admin sends
manually within 24h → `mark-paid`, see the 2026-06-29 admin session) had a real
hole: nothing anywhere captured WHERE a vendor's payout should go. The admin
got "vendor X wants Y SYP," no destination.

- New nullable `vendors.shamcash_account` column — additive migration only
  (`2026_08_19_102217_add_shamcash_account_to_vendors_table`), no data
  touched, no `migrate:fresh`. Just an id string, same shape as the platform's
  own `SHAMCASH_ACCOUNT_ID` env var (NOT the same account — customers pay INTO
  the platform's; this is where the platform pays OUT to the vendor).
- **`POST /vendor/shamcash-account`** — vendor sets/updates it (`{
  "shamcash_account": "acc_..." }`). Not a one-time lock — re-calling
  overwrites, for when a vendor changes accounts.
- **`POST /vendor/withdraw` now refuses (422, en/ar)** if no account is on
  file — a withdrawal can never reach the admin with nowhere to send money.
- **Fixed a real bug found while building this:** `GET /admin/withdrawals`
  column-limited the vendor select to `business_name,phone` — even after an
  account was set, the admin's payout list silently dropped it (same class of
  bug as the `account_status`-on-limited-select issue this codebase has hit
  more than once). Now includes `shamcash_account`, so the admin can act on a
  withdrawal straight from that list.
- Verified end-to-end with real data: blocked without account (422) → set
  succeeds (200) → withdraw succeeds once set, resets `available` to 0 →
  account visible on the admin list alongside name/phone. 20/20 suite.

### 8. Customer support routes — EXISTING, verified/explained (Mohamad asked how the Flutter dev should wire it), NOT new work
Mohamad described exactly what `SupportController`'s USER side already does —
open a ticket, admin sees it + gets notified, user CANNOT send a second message
until an admin replies (server-enforced 422, not a client-side rule), admin
reply turns it into a real back-and-forth, resolved = closed for good. Nothing
was built this session — confirmed against the real controller and routes,
then explained: `POST /support/tickets` (open, first message), `GET
/support/tickets` (list + `unread_count`), `GET /support/tickets/{id}`
(thread + marks admin replies read), `POST /support/tickets/{id}/messages`
(reply — 422 while `open`, 422 while `resolved`, 201 while `in_review`). No
polling/websocket needed — unlike `/conversations/*` (user↔vendor chat), this
is low-frequency; refetch-on-open is enough.

### Updated pre-launch kill list (supersedes the 2026-08-16/17 line below)
`0000` payment bypass · OTP in auth responses · `debug_notifications` in payment
response · `APP_DEBUG=false` on Railway · rotate the leaked Supabase S3 key ·
**lock `config/cors.php` `allowed_origins` to the real admin-dashboard domain
once it exists (currently defaults wide open to `*`)** · (UltraMsg was renewed
2026-08-17, instance `instance188530` — OTP may actually send via WhatsApp now,
worth a real-device check before launch) · ~~finish/deploy the phone-
normalization fix~~ DONE, see §5-6.

---

## ⭐⭐ UPDATES — 2026-08-16/17 (Moh session: nearby, user docs, security fixes, offer/notif/sort fixes — ALL DEPLOYED, app ~97% done)

> **CURRENT STATE — read this first.** Everything below is COMMITTED and LIVE on production. `main` = `origin/main` = deployed (latest commit `fed12b6`). Working tree clean. `dev` is BEHIND main (main is the source of truth now — we've been committing straight to main since the big merge; a `git checkout dev && git merge main` fast-forward catches it up whenever Amer needs it). Production DB was `migrate:fresh`ed at end of session (empty — teams make their own test data; admin reseeded `admin@haflati.com`/`0000`).

### What was built/fixed this session (all live)
1. **`GET /vendors/nearby`** — the Explore map endpoint. One endpoint, both Explore paths: no filter (nav-bar Explore) or `?vendor_type=` (tapped a Home category). `lat`/`lng` REQUIRED; returns approved+active vendors + `distance_km`, nearest first. Offline vendors (`is_accepting_bookings=false`) ARE shown (app shows a banner on tap). No prices — just vendor + distance. Route is registered BEFORE `/vendors/{id}` so "nearby" isn't captured as an id. (Decision: NO radius filter — we show the km, we don't filter by it; the design's "within X km" slider was dropped as low-value.)
2. **User (customer) API docs** — `docs/user-api.html` (43 endpoints, human reference) + `docs/user-api-testing.md` (33 endpoints, 19 test scenarios, machine-executable for the Flutter dev's Claude). Same quality/template as the vendor pair. Built by 2 subagents, verified by me.
3. **SECURITY fixes (were open gaps):** (a) public sub-resource endpoints now gate on vendor state — `vendorReviews`, `vendorPortfolio`, `searchVendorProducts` return EMPTY for a not-approved/not-active vendor (before, a banned/unapproved vendor's reviews/portfolio/products were publicly fetchable by id). `searchVendorProducts` also filters `is_hidden=false`. (b) `User::$hidden` now includes `fcm_token` (was exposed on the user object; Vendor already hid it).
4. **Earnings bug FIX:** `GET /vendor/earnings` + `GET /vendor/stats` were summing ALL verified payments — including paid-but-not-yet-approved (`pending`) bookings, i.e. money the vendor hadn't earned. Now both filter booking status to `approved`/`completed` (matches the wallet, which credits on approve).
5. **Offer-price display bug FIX (important):** vendor booking-list endpoints selected `product:id,name,price` only, which stripped `discount_percent`/`discount_ends_at` → the `is_on_offer`/`discounted_price` accessors returned wrong values (showed original price). Added the discount columns to ALL product eager-loads in BookingController, so the offer price shows everywhere the vendor sees a booked product. (Note: `item.unit_price` was always the correct charged price — the accessors were the broken part.)
6. **Notifications rewritten — no more dumb "booking #id":** all 6 notification bodies now use NAMES. Customer sees the vendor's `business_name` (accepted/declined/completed); vendor sees the customer's name (new booking/cancel/review). `booking_id` stays in the notification `data` payload for tap-navigation. en + ar both updated with `:name` placeholder. (This was Mohamad's "Option A".)
7. **`GET /products` sort/pagination refinements:** default sort is now **random** (was newest — "Recently Added" already has its own rail via `?sort=newest`, so default random feels varied). `top_rated` KEPT but explicitly sorts products by the OWNER VENDOR's `rating_avg` (products have no rating of their own). Added optional `?per_page=` (1–50, default 15) so the app controls page size: rail=15, the "show more" full vertical list can request more. KEEP the Laravel paginator shape (`current_page`/`last_page`/`total`/`next_page_url`) — it powers the "show more → load next page" flow (rail shows 15, tap show-more opens a paginated vertical list).
8. **Chat rule (from prior session, now confirmed live):** chat opens once the vendor APPROVES a paid booking (not just paid). Both directions: user `POST /conversations/vendor/{id}`, vendor `POST /vendor/conversations/user/{id}`. Decline accepts an optional free-text `reason` → becomes the customer's decline notification VERBATIM (vendor types it, not translated).

### Design reconciliation (the NEW design system)
- The correct current design is in the repo folder **`Haflati Design System (new)/`** (untracked; `ui_kits/user/Home.html`, `Filters.html`, `Explore.html`). The Home 5-rail layout + Filters (Category, Price min/max, Rating, Sort: random/nearest/top_rated/price_low/most_booked) MATCH what we built. Distance is DISPLAYED not filtered. Design is guidance, not contract — we drop things that don't add value (e.g. the radius filter).

### The customer app is now FEATURE-COMPLETE on the backend
Home (all 5 rails), Explore (map + nearest + category filter), vendor detail, product discovery + filters + search, booking (both shapes + selected_options), payment, offers/discounts, favorites, chat, reviews, notifications, support — ALL live.

### What's LEFT (~3%) — SUPERSEDED, see 2026-08-19 section above for current state
- ~~**Public single-product detail endpoint**~~ — BUILT + DEPLOYED 2026-08-19 (`GET /products/{id}`). See that section.
- **`GET /categories`** — the 10 category tiles + counts, OR hardcode in Flutter (undecided; leaning hardcode since the enum is fixed).
- **Smart Search (FastAPI)** — separate repo/service, Moh+Amer's final learning sprint. Groundwork in this repo: `smart-search.md`, `HaflatiDemoSeeder` (LOCAL ONLY seeder), factories.
- **Pre-launch kill list** — see the updated list in the 2026-08-19 section above (adds CORS + phone-normalization deploy to this same list).

### Ops notes learned this session
- Railway link drops per-machine + sometimes times out; retry `railway link --project distinguished-imagination --environment production --service events-api` then chain the `railway ssh` in the same call. Prod server is `php artisan serve` (single-threaded dev server, ~122 MB idle RAM) — FINE for a few testers, but before ~100 concurrent users switch to FrankenPHP for parallel requests. $5 Railway plan handles 100 test users on RAM/cost (lightweight API); concurrency is the only limit, not user count.
- **Firebase:** ONE project (`hafleti-80cf0`) for BOTH apps — the backend service-account JSON covers users AND vendors; nothing to change when the user app is added. Flutter adds each app (customer + vendor) to the SAME project with its own `google-services.json`/plist.
- **Terminal `????` on Arabic is a Windows/PowerShell display issue only** — data stores fine. To send Arabic via curl reliably, write the JSON body from a pure Python `urllib` script (raw UTF-8), not shell strings.

---

## ⭐ UPDATES — 2026-08-14/15 (Moh + Amer: BIG batch — offers, discovery, chat, merge, all DEPLOYED)

> **READ THIS FIRST — current state of the whole project.** Everything below is
> COMMITTED and LIVE on production (`main` = `origin/main` = deployed on Railway).
> Production DB was reset with `migrate:fresh` during deploy — it is EMPTY (teams
> create their own test data via the API). Admin reseeded: `admin@haflati.com` / `0000`.

### Git / branch state
- **We are on `main`.** `main`, `origin/main`, and `dev` are all aligned (this session did the reconcile). Latest commit: chat-approval-gate + decline-reason.
- **The big merge happened:** Amer's `origin/admin-complete` (chat, favorites/saved, support system, content reporting, vendor-requested cancel) was merged into our work. Conflicts (.gitignore, Booking model casts, BookingController decline, master context) were resolved KEEPING BOTH sides. All 20 tests pass after merge.
- Deploy flow used all session: commit → `git push origin main` (auto-deploys) → wait ~100s → `railway ssh --service events-api "php artisan migrate:fresh --force"` → reseed admin. Railway link drops per-machine: `railway link --project distinguished-imagination --environment production --service events-api` before any `railway ssh`.

### What WE (Moh) built this session — all live
1. **Offers / discounts (`vendor_products`):** vendor sets `discount_percent` on an EXISTING item, `discount_ends_at` (max 1 month), starts now. 1-week cooldown after an offer ends (`discount_last_ended_at`). Auto-revert: the `is_on_offer`/`discounted_price` accessors treat a past end date as no-offer instantly; `offers:expire` scheduled command (hourly, in `routes/console.php`) cleans up fields + stamps the cooldown. **Commission ALWAYS on the ORIGINAL price** — the vendor fully carries the discount; verified: 100 item, 25% off, 20% deposit → customer pays 15, commission 3 (on original 20), payout 12. Endpoints: `POST /vendor/products/{id}/discount`, `DELETE /vendor/products/{id}/discount`.
2. **Vendor hide/show product:** `POST /vendor/products/{id}/toggle-hidden` — new `is_hidden` column, SEPARATE from `is_available` (stock-controlled) so they never conflict. Hidden = gone from `GET /products`, public vendor search, and booking (409); still visible in the vendor's own `GET /vendor/products`. A product shows to customers only when `is_available=true AND is_hidden=false`.
3. **Booking selected_options (product meta picks):** the customer's chosen options from the product meta are saved — per BOOKING for appointments (`bookings.selected_options`), per ITEM for orders (`booking_items.selected_options`). Free-form JSON (Flutter validates against meta, backend just stores/relays). Same product + DIFFERENT options = separate cart lines; same options merge. Shared helpers `buildOrderLines()` / `createOrderItems()` used by store + update.
4. **`GET /products` — the ONE discovery endpoint** (new `ProductBrowseController`). Powers ALL Home item rails + the Filter screen + search: `?type=service|product` · `?category=` · `?on_offer=1` · `?min_price=&max_price=` · `?min_rating=` · `?search=` · `?sort=newest|top_rated|price_low|price_high|most_booked|nearest` (`lat`/`lng` for nearest). Returns each item + its vendor mini-object + primary image + `is_on_offer`/`discounted_price`. Leak-safe: only approved+active vendors' available, non-hidden items.
5. **`GET /vendors/{id}` — vendor detail header** (in VendorBrowseController). Returns rating_avg, reviews_count, events_hosted_count (completed bookings), bio, city, images, is_accepting_bookings. NO from_price here (prices live on the products below). `products_min_price` ("From X") stays only on the `GET /vendors` browse cards — the ONLY place vendors are displayed (Home "Top Rated Vendors").
6. **`GET /vendors/{id}` + `GET /products`** together fully serve the Home page: Top Rated Vendors + Best Offers + Discover Services + Discover Products + Recently Added + search + vendor profile on tap.
7. **Arabic/English localization (from 2026-08-07 session, now deployed):** `SetLocale` middleware reads `Accept-Language` (ar/en, default ar); all 45 messages + validation in `lang/ar` + `lang/en`; per-recipient notification language via `users.language`/`vendors.language`. Enum VALUES stay English keys (Flutter maps to Arabic labels). Admin messages kept English.
8. **Chat rule changes (on top of Amer's chat):** (a) chat now opens once the vendor **APPROVES** a paid booking (was: just paid) — `ChatController::hasPaidBooking` checks status `approved`/`completed` + verified payment; (b) the VENDOR can also start a chat: `POST /vendor/conversations/user/{userId}` (mirror of the user's open, same gate, `firstOrCreate` so no duplicate thread); (c) **decline with reason:** `POST /vendor/bookings/{id}/decline` accepts optional `reason` (free text ≤255) — if given it's the customer's decline-notification body VERBATIM (not translated), else the default translated message.

### What AMER built (merged in, live)
- **User↔vendor chat** (backend-owned MySQL, NOT Firestore — supersedes the old Firestore plan): `conversations` + `messages` tables; `GET /conversations`, `POST /conversations/vendor/{id}` (user opens), `GET/POST /conversations/{id}/messages` (poll with `?after=`), `POST /conversations/{id}/read`; vendor side under `/vendor/conversations/*`. FCM push to the other side per message (push-only, no inbox row).
- **Favorites/Saved:** `saved_items` table + `SavedItemController` (was assigned to Amer, now done).
- **Support system** (vendor↔admin + user tickets): `support_threads`/`support_messages`, `SupportController`, `AdminSupportController`.
- **Content reporting:** `content_reports` table + `ContentReportController` (report a product/review), admin `dismissReports`.
- **Admin additions:** vendor-requested cancel of an approved booking (`/admin/bookings/{id}/cancel-vendor-request`), user-ban guard, refund-waive (`bookings.refund_waived_at`), commission-type + rejected-at on wallet transactions.

### What's LEFT (roughly 5%)
- **`GET /vendors/nearby`** — the Explore MAP (vendors as pins, Haversine already exists in VendorBrowseController). The last real feature endpoint. NOT built yet.
- **`GET /categories`** — the 10 category tiles + counts, OR hardcode in Flutter (undecided).
- **Smart Search (FastAPI)** — separate repo/service, Moh+Amer's final learning sprint. Groundwork in this repo: `smart-search.md`, `HaflatiDemoSeeder` (LOCAL ONLY), factories.
- **Pre-launch kill list** (do LAST, before real launch — kept ON now for testing): `0000` payment bypass · OTP in auth responses · `debug_notifications` in payment response · set `APP_DEBUG=false` on Railway · renew UltraMsg · **rotate the leaked Supabase S3 key**.
- Open-for-discussion (pre-existing): public `products/reviews/portfolio` endpoints don't gate on `is_approved`/`is_active` (Mohamad wants to discuss before fixing).

### For the Flutter team (what to send them)
- **Translation:** send `Accept-Language: ar|en` on every request; map enum keys to Arabic labels app-side (`ar.json`); user-typed content is never translated.
- New endpoints they can use: `GET /products` (all Home rails + filters + search), `GET /vendors/{id}` (profile), `POST /vendor/products/{id}/discount` + `/toggle-hidden`, chat (`/conversations/*`, vendor `/vendor/conversations/user/{id}`), decline-with-reason.
- `docs/vendor-api-testing.md` updated with discount/toggle-hidden/localization + scenarios S18–S20.

---

## UPDATES — 2026-08-07 (Moh session: Arabic/English localization of all API text)

The whole API now responds in Arabic or English based on the `Accept-Language` header. NOT yet committed/deployed at time of writing — tested locally only.

### How it works
- **`SetLocale` middleware** (`app/Http/Middleware/SetLocale.php`), appended to the api middleware group in `bootstrap/app.php` → runs on every API request. Reads `Accept-Language`; `en` → English, anything else (or missing) → **Arabic** (default). The Flutter app sends `ar`/`en` (user choice or phone language).
- It also **persists the language** onto the authenticated account (`users.language` / `vendors.language`, new `string(2)` columns, default `ar`, in `$fillable`) when it changes — so push notifications (which have no request header) go out in each recipient's own language.

### What was translated
- **All 45 static API `message` strings** moved to `lang/en/messages.php` + `lang/ar/messages.php` (same keys), controllers now use `__('messages.<key>')`. 56 replacements across all controllers.
- **4 dynamic messages** with placeholders: `product_not_available` (`:name`), `only_n_in_stock` (`:count`,`:name`), `cancelled_partial_refund` (`:percent`), `cannot_review_status` (`:status`).
- **All 6 notifications** (payment received, new paid booking, approved, completed, declined, review, cancelled) → new `notifyUserTrans()` / `notifyVendorTrans()` in `NotificationService` render title+body in the RECIPIENT's language via `__($key, $params, $locale)` (forces a locale without touching the request's).
- **Laravel validation** → `lang/ar/validation.php` added (rules + an `attributes` map so field names read naturally, e.g. `phone` → `رقم الهاتف`). `lang/en/` published via `php artisan lang:publish`.
- **ADMIN messages kept in ENGLISH on purpose** (React dashboard / admins work in English). Keys exist in both files but the `ar` value is English. Translate later if the admin app needs Arabic.

### What is NOT translated by the API (by design)
- **Enum VALUES** (`vendor_type`, `booking_style`, `status`, `account_status`) always return the English KEY — it's the stable contract used in filters/WHERE. **Flutter maps the key to an Arabic label** (design system's `ar.json`). This is the correct separation; the backend never sends Arabic enum values.
- **User-generated content** (business_name, bio, product name/description, notes, address) — returned as typed, never translated.

### Verified locally
- Validation + messages return Arabic under `Accept-Language: ar`, English under `en`. Field names translate (`حقل رقم الهاتف مطلوب.`). Notifications render per-recipient language with placeholders filled. `lang/en` and `lang/ar` messages have identical key sets (62 each). All 20 tests pass.

### Docs updated
- `docs/vendor-api-testing.md` — new Localization section in §0 + scenario S18 (assert ar vs en text differs, enums stay English).

### Still open on translation
- The `debug_notifications` field in the payment response is still testing-only (kill before prod) — unaffected by translation.
- User-facing docs (`user-api.md`, not built yet) will get the same Localization note when created.

---

## UPDATES — 2026-07-31 (Moh session: logout, auto response-time, availability system, vendor toggle, Firebase fix, API docs)

### 1. Logout — NEW (was missing entirely)
- `POST /logout` (user) + `POST /vendor/logout` (vendor). Revokes ONLY the current request's Sanctum token (other devices stay logged in) and nulls that account's `fcm_token` so the device stops getting pushes. Returns `{status:success, message:"Logged out"}`.

### 2. Vendor response time — now AUTO-computed (manual enum DROPPED)
- The old vendor-chosen `response_time` enum (`within_1h`…) was **removed** from the vendors migration, Vendor model, profile-update validation, browse select, and VendorFactory.
- Replaced by `GET /vendor/response-time`: the **average gap** between when a booking was PAID (`payment.created_at`) and when the vendor RESPONDED (`responded_at`). Returned as a **moderated range** (`label`), never an exact number. 6 buckets: under 30 minutes / 30-60 minutes / 1-2 hours / 2-6 hours / 6-24 hours / over a day. Also returns `average_minutes` + `based_on`. A vendor who never responded → `{is_new:true, label:null}` (app shows "New").
- **New column `bookings.responded_at`** (timestamp, nullable), set in `approve()` and `decline()`. In BookingController.

### 3. Availability calendar (recap — built this session batch, appointment vendors only)
- Table `vendor_blocked_dates` (`vendor_id`, `date`, `reason`, unique per vendor+date). Model `VendorBlockedDate`; `Vendor::blockedDates()`.
- `AvailabilityController`: `GET /vendor/availability` (booked auto + blocked manual, labeled), `POST /vendor/blocked-dates` (block; 409 if the day has a booking), `DELETE /vendor/blocked-dates/{date}` (unblock; 404 if not blocked), public `GET /vendors/{id}/availability` (merged unavailable list). `storeAppointment()` also 409s on a manually-blocked date. Replaced the old read-only `/vendor/booked-dates`.
- Day-level only; per-service hours belong in the product's `meta`.

### 4. Vendor self online/offline toggle (recap)
- `vendors.is_accepting_bookings` (bool, default true) — vendor-controlled, SEPARATE from admin ban `is_active` (does not block login). `POST /vendor/availability/toggle`. Offline vendor STILL appears in browse (flag returned in JSON so the app shows "not accepting bookings" + disables booking); `store()` 403s a booking to an offline vendor.

### 5. Double-booking race fix (recap — BUG #16)
- `bookings.event_day` STORED generated column = `DATE(event_date)` + unique index `(vendor_id, event_day)`; `bookings.old_event_date` preserves the date after cancel/decline via a `Booking::saving` hook that nulls `event_date` to free the slot. try/catch on the create → clean 409.

### 6. Firebase project SWITCHED (notifications now work live)
- Push notifications were failing because the Flutter app and the backend were on **different Firebase projects**. Fixed by switching the BACKEND to the app's project. Railway `FIREBASE_CREDENTIALS_JSON` now holds the `hafleti-80cf0` service-account JSON (was `haflati-d14da`). Local `.env` `FIREBASE_CREDENTIALS` points to the new key file; `.gitignore` now uses `/storage/app/*firebase-adminsdk*.json` (wildcard) so any key name is ignored.
- Verified LIVE on production: paying a booking for a vendor with a real device token returns `debug_notifications.vendor.sent = true` (two consecutive live pushes confirmed). User side stays `sent:false / no_device_token` only because test users are curl-created with no device. **The Firebase service-account key is a BACKEND secret — never share it with Flutter; the app uses its own `google-services.json` / `GoogleService-Info.plist` from the SAME project.**
- Reminder: `debug_notifications` in the payment response is a TESTING field — remove before production (kill list).

### 7. Vendor API docs — full response reference + machine-readable test spec (NEW files, committed `305719f` + this session's logout/response-time additions)
- `docs/vendor-api.html`: every vendor endpoint now shows all its responses (success + errors) with real example JSON, color-coded by status, bilingual. 44 endpoints fully documented (incl. the new logout + response-time).
- `docs/vendor-api-testing.md`: a machine-consumable spec for the Flutter dev's Claude session — auth bootstrap, every endpoint's exact request/response, invariants as assertions, and 15 ready-to-run end-to-end test scenarios. Built so his Claude can autonomously verify his integration.

### Still open / deferred
- `dev` local commits + this batch NOT yet pushed/deployed. When deploying: push dev → merge main → **`migrate:fresh --force` on Railway** (new columns: `responded_at`, dropped `response_time`, plus prior `event_day`/`old_event_date`/`is_accepting_bookings`/`cover_image` and tables `booking_items`/`vendor_blocked_dates`) → re-seed admin (`db:seed --class=AdminSeeder`).
- Homepage `GET /products` (+ `discount_percent`) still the next customer-app build.
- PRE-EXISTING gap still open (Mohamad wants to discuss): public `products/reviews/portfolio` endpoints don't gate on `is_approved`/`is_active`.
- Smart Search groundwork (`smart-search.md`, `HaflatiDemoSeeder`, factories) added — see that file; local-only seeder, never run on Railway.

---

## UPDATES — 2026-08-14 (Amer + Claude session: user↔vendor chat — backend-owned)

WhatsApp-style chat between a customer and a vendor, unlocked ONLY after the customer
has PAID for a booking with that vendor. **Built backend-owned (MySQL), NOT Firestore** —
this **SUPERSEDES** the old "chat via Firestore" plan (see 2026-07-15 §5 and the
"Chat System — Future" section, both now flagged). Decision still needs confirmation
with Mohamad + the Flutter team, because Flutter now integrates via **REST + polling**
instead of the Firestore SDK.

### Why backend-owned (not Firestore)
- **The gate lives where the data lives.** "Chat only after paying" is a fact in our DB
  (booking + verified payment) → the server enforces it in one line. Firestore would need
  custom tokens + security rules to approximate the same gate.
- **Disputes.** Admin dispute resolution already exists (no-show / bad service). Backend
  messages are evidence the admin can read; Firestore chats would be invisible to us.
- **Consistency.** The project already stores messages in MySQL for the support chat
  (`support_threads`/`support_messages`) — this reuses the SAME pattern, no new tech.
- **Cost of the choice:** realtime isn't free like Firestore. v1 uses **polling**; upgrade
  to **Laravel Reverb** (websockets) later with ZERO schema change.

### Schema — 2 new tables
- **`conversations`**: `user_id` (FK), `vendor_id` (FK), `last_message_at` (nullable —
  drives list order), timestamps. **`unique(user_id, vendor_id)`** = exactly ONE
  conversation per pair (WhatsApp-style, NOT per booking). Indexes `(user_id,
  last_message_at)` + `(vendor_id, last_message_at)`. Unlike `support_threads` (whose
  other side is always the admin → `owner_type` trick), here the two sides are fixed roles
  → two explicit columns.
- **`messages`**: `conversation_id` (FK cascade), `sender_type` enum(`user`|`vendor`),
  `sender_id`, `body` (text), `read_at` (nullable — seen by the OTHER side, drives unread
  badges), timestamps. Index `(conversation_id, created_at)`. Mirrors `support_messages`
  minus `admin`.
- Models `Conversation` (`user()`, `vendor()`, `messages()`, `latestMessage()` for the
  list preview) + `Message` (`conversation()`).

### The gate + lifecycle
- A conversation is only CREATED once the user has a booking with that vendor carrying a
  **`verified` payment** (`ChatController::hasPaidBooking()` = `whereHas('payment',
  status='verified')`). An unpaid draft never unlocks chat; a booking cancelled while still
  `awaiting_payment` (never paid) does NOT unlock it either.
- Created **lazily** via `firstOrCreate` when the user first opens it → the payment flow is
  untouched (merge-safe). Once created it **stays open for good** — the two keep talking
  across future bookings, even after `completed`/`cancelled` (good for follow-up + disputes).

### Endpoints — ONE `ChatController` serves both sides (caller resolved from the guard, like `NotificationController::owner()`)
User (`auth:sanctum` + `active`):
- `GET /conversations` — chat list (other party + `latest_message` + `unread_count`),
  newest activity first.
- `POST /conversations/vendor/{vendorId}` — open/create (**the gate**: 403 if no paid
  booking; 201 new / 200 existing).
- `GET /conversations/{id}/messages?after={id}` — messages oldest-first; `after` returns
  ONLY newer ones (**this is the polling mechanism**).
- `POST /conversations/{id}/messages` — send (`body` required, max 5000).
- `POST /conversations/{id}/read` — mark the other side's messages read.

Vendor (`auth:vendors` + `active`) — SAME controller methods, no "open" route (the user
always initiates): `GET /vendor/conversations`, `GET /vendor/conversations/{id}/messages`,
`POST /vendor/conversations/{id}/messages`, `POST /vendor/conversations/{id}/read`.

Ownership: every message action goes through `authorizedConversation()` — the caller must
be one of the conversation's two participants (else 404), so nobody can read a chat that
isn't theirs.

### Notifications (the WhatsApp principle)
- Every message pushes an **FCM notification to the OTHER party** via the existing
  `NotificationService::send()` (title = sender's first/last name or `business_name`, body
  = message preview, `data: { type: 'chat', conversation_id }` for deep-linking).
- **Push-only — NO inbox/bell row.** The chat itself is the history; we don't flood the
  bell with one row per message. Skipped gracefully if the recipient has no `fcm_token`.
- Push is synchronous like the rest of the app; queueing it is a production nicety (deferred).

### Testing (tinker, throwaway rows in a rolled-back transaction — nothing persisted)
- Full flow 8/8: gate opens for a paid vendor (201) / blocks unpaid (403); send both ways;
  `unread_count` 1→0 on read; polling returns only new; unrelated vendor blocked (404).
- Notification path proven with a spy `NotificationService`: user→vendor pushes to the
  vendor's token (title = user name); vendor→user pushes to the user's token (title =
  business_name); 2 pushes total; a no-token recipient is skipped.

### Still open / deferred
- Realtime is **polling**; Reverb upgrade later (no schema change).
- `GET messages` with no `after` returns ALL messages — add pagination for long chats.
- No Flutter chat UI yet — test via Postman.
- Vendor-initiated chat not built (user always initiates) — trivial to add if wanted.
- **Latent (PRE-EXISTING): `User` model does NOT `$hidden` its `fcm_token`.** ChatController
  avoids leaking it by column-limiting the user select, but the token is still exposed by
  other endpoints that serialize a full `User`. Worth adding to `User::$hidden` (`Vendor`
  already hides it).
- Confirm the backend-vs-Firestore decision with Mohamad + the Flutter team.

---

## ADMIN DASHBOARD — COMPLETE ENDPOINT & SCREEN MAP (for the React design/build)

The React admin dashboard is web-only. Auth = two layers: `auth:admins` guard + `role:` middleware.
**Roles:** `super_admin` = everything. `support` = view + KYC only (blocked from bans, money, audit, managing admins).
All lists are paginated (Laravel paginator JSON: `data`, `current_page`, `last_page`, `total`, ...).
Every response is `{ "status": "success", ... }`. Every sensitive super_admin action writes an `admin_audit_logs` row.

### Auth
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| POST | `/admin/login` | public (throttled 5/min) | Login screen. Returns admin + token. First admin seeded: `admin@haflati.com` / `0000` (change after first login). |
| POST | `/admin/logout` | any admin | Revoke current token. |

### Dashboard home
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| GET | `/admin/dashboard` | super_admin + support | Landing screen headline cards: `total_users`, `total_vendors`, `approved_vendors`, `pending_vendors`, `banned_vendors`, `total_bookings`, `active_bookings`, `completed_bookings`, `profit_today`, `profit_month`, `profit_all_time`. |

### Vendors (KYC + management)
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| GET | `/admin/vendors?search=&is_active=` | both | Vendors list (searchable, filter by active). |
| GET | `/admin/vendors/pending` | both | KYC queue — vendors awaiting approval. |
| GET | `/admin/vendors/{id}` | both | Vendor detail. |
| GET | `/admin/vendors/{id}/wallet` | super_admin | A vendor's balances + full ledger (money disputes). |
| POST | `/admin/vendors/{id}/approve` | both | Approve KYC. |
| POST | `/admin/vendors/{id}/reject` | both | Reject KYC (reason shown to vendor). |
| POST | `/admin/vendors/{id}/ban` | super_admin | Immediate ban — cancels + 100%-refunds every in-flight booking, then fully bans. |
| POST | `/admin/vendors/{id}/ban-gradual` | super_admin | Winding-down ban — keeps `approved` bookings to finish, cancels+refunds `pending`, auto-finalizes when done. |
| POST | `/admin/vendors/{id}/unban` | super_admin | Reinstate to active. |

Vendor `account_status` in JSON = `active | winding_down | banned` — use it for a status pill.

### Users
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| GET | `/admin/users?search=` | both | Users list (searchable). |
| GET | `/admin/users/{id}` | both | User detail + their bookings. |
| POST | `/admin/users/{id}/toggle` | super_admin | Ban / unban a user (`is_active`). |

### Bookings + disputes
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| GET | `/admin/bookings?status=&vendor_id=&user_id=` | both | Bookings list (filterable). |
| GET | `/admin/bookings/{id}` | both | Booking detail incl. payment. |
| POST | `/admin/bookings/{id}/cancel` | super_admin | Dispute resolution — cancel ONE booking + 100% refund the customer, vendor account untouched. |

### Money oversight
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| GET | `/admin/payments` | super_admin | All payments (with booking.user + booking.vendor). |
| GET | `/admin/stats/financial` | super_admin | Full money report: `summary` (gross_volume, platform_profit, vendor_payouts, transactions) + today/this_month/this_year + 12-month `monthly_trend` for charts. |
| GET | `/admin/refunds-due` | super_admin | Cancelled bookings still owed a customer refund (+ `total_due`). |
| POST | `/admin/refunds/{id}/mark-paid` | super_admin | Mark a refund paid after sending manually. |
| GET | `/admin/withdrawals?unpaid=1` | super_admin | Vendor withdrawal requests (+ `total_unpaid`). |
| POST | `/admin/withdrawals/{id}/mark-paid` | super_admin | Mark a payout done. |

(Real send of both refunds and payouts stays manual until the ShamCash payout API exists.)

### Content moderation
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| GET | `/admin/reviews?vendor_id=` | both | Reviews list. |
| DELETE | `/admin/reviews/{id}` | super_admin | Remove a review (recomputes vendor `rating_avg`). |
| DELETE | `/admin/products/{id}` | super_admin | Remove an inappropriate product listing (+ its images). |
| DELETE | `/admin/portfolio/{id}` | super_admin | Remove an inappropriate portfolio item (+ its images). |

### Audit + managing admins
| Method | Endpoint | Role | Screen / purpose |
| --- | --- | --- | --- |
| GET | `/admin/audit-logs` | super_admin | Audit log viewer (who did what, with admin name/role). |
| GET | `/admin/admins` | super_admin | List admin accounts. |
| POST | `/admin/admins` | super_admin | Create an admin (hire support). |
| DELETE | `/admin/admins/{id}` | super_admin | Remove an admin. |

---

## Next Steps To Build (IN ORDER) — historical (2026-06-06), superseded by the 2026-07-15 roadmap above

### IMMEDIATE (Current Session)

1. **Fix BookingController** — correct the full flow with new statuses + add notifications
2. **Fix PaymentController** — correct status flow (pending → approved after payment)
3. **Integrate NotificationService** into BookingController and PaymentController
4. **Admin table + migration** — with roles (super_admin, support) + fcm_token
5. **Admin auth** — separate guard
6. **Uncomment notifyAdmins()** after admin table is ready

### AMER IS WORKING ON

- vendorBookings() endpoint
- updateStatus() — vendor approves (→ awaiting_payment) or declines
- Mark booking as completed

### FUTURE FEATURES

- Reviews & Ratings system (after booking completed)
- Browse/Search API (public, no auth)
- Unavailable dates endpoint
- Push Notifications fully integrated with FCM
- Cloudinary for persistent image storage
- AI features: Event Planner chatbot, Smart search
- Chat system via Firebase Firestore

---

# ═══════════════════════════════════════════════════════════════

# SECTION 2: DATABASE SCHEMA & MIGRATIONS

# ═══════════════════════════════════════════════════════════════

## Table: users (UPDATED — added fcm_token, first_name, last_name, city)

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('first_name')->nullable();
    $table->string('last_name')->nullable();
    $table->string('phone')->unique();
    $table->string('city')->nullable();
    $table->decimal('latitude', 10, 8)->nullable();
    $table->decimal('longitude', 11, 8)->nullable();
    $table->string('address')->nullable();
    $table->string('profile_image')->nullable();
    $table->date('birth_date')->nullable();
    $table->string('fcm_token')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
```

---

## Table: vendors (UPDATED — added fcm_token, first_name, last_name, city)

```php
Schema::create('vendors', function (Blueprint $table) {
    $table->id();
    $table->string('first_name')->nullable();
    $table->string('last_name')->nullable();
    $table->string('phone')->unique();
    $table->string('city')->nullable();
    $table->date('birth_date')->nullable();
    $table->string('business_name')->nullable();
    $table->enum('vendor_type', [
        'wedding_venue',
        'photographer',
        'dj',
        'cake_shop',
        'store',
    ])->nullable();
    $table->enum('booking_style', [
        'appointment',
        'order'
    ])->nullable();
    $table->string('profile_image')->nullable();
    $table->decimal('latitude', 10, 8)->nullable();
    $table->decimal('longitude', 11, 8)->nullable();
    $table->string('address')->nullable();
    $table->text('bio')->nullable();
    $table->decimal('rating_avg', 3, 2)->default(0);
    $table->boolean('is_approved')->default(false);
    $table->boolean('is_active')->default(true);
    $table->string('fcm_token')->nullable();
    $table->timestamps();
});
```

---

## Table: vendor_products (UPDATED — added deposit_percent)

```php
Schema::create('vendor_products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
    $table->string('name')->nullable();
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2)->nullable();
    $table->decimal('deposit_percent', 5, 2)->default(20)->nullable();
    $table->json('meta')->nullable();
    $table->boolean('is_available')->default(true);
    $table->timestamps();
});
```

**Notes:**

- deposit_percent: only used for appointment vendors (20% by default)
- For order vendors (cake_shop, store): full price is charged, deposit_percent ignored
- meta JSON stores type-specific details flexibly

---

## Table: vendor_product_images

```php
Schema::create('vendor_product_images', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vendor_product_id')->constrained()->cascadeOnDelete();
    $table->string('image_path');
    $table->boolean('is_primary')->default(false);
    $table->timestamps();
});
```

---

## Table: bookings (UPDATED — new status values)

```php
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
    $table->foreignId('vendor_product_id')->constrained('vendor_products')->cascadeOnDelete();
    $table->enum('booking_style', ['appointment', 'order']);
    $table->enum('status', [
        'pending',
        'awaiting_payment',
        'approved',
        'declined',
        'completed',
        'cancelled'
    ])->default('pending');

    // Appointment fields
    $table->dateTime('event_date')->nullable();
    $table->string('event_type')->nullable();
    $table->string('event_location')->nullable();
    $table->integer('duration_hours')->nullable();

    // Order fields
    $table->json('details')->nullable();
    $table->dateTime('delivery_date')->nullable();
    $table->string('delivery_address')->nullable();

    // Shared
    $table->text('notes')->nullable();
    $table->decimal('price_agreed', 10, 2)->nullable();
    $table->timestamps();
});
```

**CRITICAL NOTE on status flow (UPDATED 2026-06-05 — PAY-FIRST):**

```
awaiting_payment  → pending    (user pays & ShamCash verified)
awaiting_payment  → cancelled  (user cancels an unpaid booking)
pending           → approved   (vendor approves)
pending           → declined   (vendor declines)
approved          → completed  (event day / vendor marks done)
```

---

## Table: payments (NEW)

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount_paid', 10, 2);
    $table->decimal('commission', 10, 2);    // 15% for Haflati
    $table->decimal('vendor_payout', 10, 2); // 85% for vendor
    $table->string('currency')->default('SYP');
    $table->string('transaction_id')->unique(); // from ShamCash
    $table->string('sender_name')->nullable();  // from ShamCash API
    $table->enum('status', [
        'pending',
        'verified',
        'failed'
    ])->default('pending');
    $table->timestamps();
});
```

---

## Table: admins (BUILT 2026-06-06 by Amer — see actual migration for the final columns)

```php
Schema::create('admins', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->enum('role', ['super_admin', 'support']);
    $table->string('fcm_token')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
```

**Roles:**

- `super_admin`: Full access — users, vendors, payments, reports, audit log
- `support`: KYC review, complaints only

---

## Migration Order (important for FK constraints)

1. `create_users_table`
2. `create_vendors_table`
3. `create_personal_access_tokens_table` (Sanctum)
4. `create_vendor_products_table`
5. `create_vendor_product_images_table`
6. `create_bookings_table`
7. `create_payments_table`
8. `create_admins_table` (when built)

---

# ═══════════════════════════════════════════════════════════════

# SECTION 3: ELOQUENT MODELS & FULL RELATIONSHIPS

# ═══════════════════════════════════════════════════════════════

## Model: User (UPDATED)

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'city',
        'birth_date',
        'profile_image',
        'latitude',
        'longitude',
        'address',
        'fcm_token',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'latitude'   => 'decimal:8',
            'longitude'  => 'decimal:8',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'user_id');
    }
}
```

---

## Model: Vendor (UPDATED)

```php
<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'city',
        'birth_date',
        'business_name',
        'vendor_type',
        'booking_style',
        'profile_image',
        'latitude',
        'longitude',
        'address',
        'bio',
        'rating_avg',
        'is_approved',
        'is_active',
        'fcm_token',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_active'   => 'boolean',
            'rating_avg'  => 'decimal:2',
            'birth_date'  => 'date',
            'latitude'    => 'decimal:8',
            'longitude'   => 'decimal:8',
        ];
    }

    public function products()
    {
        return $this->hasMany(VendorProduct::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
```

---

## Model: VendorProduct (UPDATED — added deposit_percent)

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProduct extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'description',
        'price',
        'deposit_percent',
        'meta',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'meta'            => 'array',
            'is_available'    => 'boolean',
            'price'           => 'decimal:2',
            'deposit_percent' => 'decimal:2',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function images()
    {
        return $this->hasMany(VendorProductImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(VendorProductImage::class)->where('is_primary', true);
    }
}
```

---

## Model: VendorProductImage

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProductImage extends Model
{
    protected $fillable = [
        'vendor_product_id',
        'image_path',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(VendorProduct::class, 'vendor_product_id');
    }
}
```

---

## Model: Booking (UPDATED — new statuses, product() method — renamed from vendor_product() 2026-06-10)

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'vendor_product_id',
        'booking_style',
        'status',
        'event_date',
        'event_type',
        'event_location',
        'duration_hours',
        'details',
        'delivery_date',
        'delivery_address',
        'notes',
        'price_agreed',
    ];

    protected function casts(): array
    {
        return [
            'details'       => 'array',
            'event_date'    => 'datetime',
            'delivery_date' => 'datetime',
            'price_agreed'  => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Relationship to the booked product. Renamed from vendor_product() to
    // product() on 2026-06-10 (matches VendorProductImage::product()).
    // The FK column stays vendor_product_id.
    public function product()
    {
        return $this->belongsTo(VendorProduct::class, 'vendor_product_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
```

---

## Model: Payment (NEW)

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'amount_paid',
        'commission',
        'vendor_payout',
        'currency',
        'transaction_id',
        'sender_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid'   => 'decimal:2',
            'commission'    => 'decimal:2',
            'vendor_payout' => 'decimal:2',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
```

---

## Model: Admin (BUILT 2026-06-06 by Amer — see app/Models/Admin.php for the final version)

```php
<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'fcm_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
    ];
}
```

---

## Relationship Summary

```
User         → hasMany   → Booking (via user_id)
Vendor       → hasMany   → VendorProduct
Vendor       → hasMany   → Booking
VendorProduct → hasMany  → VendorProductImage
VendorProduct → hasOne   → VendorProductImage (primaryImage)
Booking      → belongsTo → User (user_id)
Booking      → belongsTo → Vendor
Booking      → belongsTo → VendorProduct (via vendor_product_id) — method named 'product()' (renamed from 'vendor_product()' 2026-06-10)
Booking      → hasOne    → Payment
Payment      → belongsTo → Booking
VendorProductImage → belongsTo → VendorProduct
VendorProduct → belongsTo → Vendor
```

---

# ═══════════════════════════════════════════════════════════════

# SECTION 4: API ROUTING ARCHITECTURE

# ═══════════════════════════════════════════════════════════════

## Complete api.php (UPDATED)

```php
<?php

use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\AuthVendorController;
use App\Http\Controllers\VendorProfileController;
use App\Http\Controllers\VendorProductController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────
// PUBLIC ROUTES — No auth required
// ─────────────────────────────────────────────────────────

// User Auth
Route::post('/send-otp', [UserAuthController::class, 'sendOtp']);
Route::post('/verify-otp', [UserAuthController::class, 'verifyOtp']);
Route::post('/complete-registration', [UserAuthController::class, 'completeRegistration']);

// Vendor Auth
Route::post('/vendor/send-otp', [AuthVendorController::class, 'sendOtp']);
Route::post('/vendor/verify-otp', [AuthVendorController::class, 'verifyOtp']);
Route::post('/vendor/complete-registration', [AuthVendorController::class, 'completeRegistration']);

// Public browse
Route::get('/vendors/{vendorId}/products', [VendorProductController::class, 'getVendorProducts']);
Route::get('/vendors/{vendorId}/products/search', [VendorProductController::class, 'searchVendorProducts']);

// ─────────────────────────────────────────────────────────
// USER PROTECTED ROUTES — auth:sanctum
// ─────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::post('/profile', [UserProfileController::class, 'update']);
    Route::delete('/profile/image', [UserProfileController::class, 'deleteImage']);

    // Bookings — user side
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'userBookings']);
    Route::put('/bookings/{id}', [BookingController::class, 'update']);
    Route::patch('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    // Payments
    Route::post('/payments/verify', [PaymentController::class, 'verify']);

    // FCM Token update
    Route::post('/fcm-token', [UserProfileController::class, 'updateFcmToken']);
});

// ─────────────────────────────────────────────────────────
// VENDOR PROTECTED ROUTES — auth:vendors
// IMPORTANT: This group is SEPARATE from auth:sanctum group
// DO NOT nest auth:vendors inside auth:sanctum
// ─────────────────────────────────────────────────────────
Route::middleware('auth:vendors')->group(function () {
    // Vendor Profile
    Route::get('/vendor/profile', [VendorProfileController::class, 'show']);
    Route::post('/vendor/profile/type', [VendorProfileController::class, 'setType']);
    Route::post('/vendor/profile', [VendorProfileController::class, 'update']);
    Route::delete('/vendor/profile/image', [VendorProfileController::class, 'deleteImage']);

    // Vendor Products
    Route::get('/vendor/products', [VendorProductController::class, 'index']);
    Route::post('/vendor/products', [VendorProductController::class, 'store']);
    Route::get('/vendor/products/{id}', [VendorProductController::class, 'show']);
    Route::post('/vendor/products/{id}', [VendorProductController::class, 'update']); // POST not PUT — form-data bug fix
    Route::delete('/vendor/products/{id}', [VendorProductController::class, 'destroy']);

    // Bookings — vendor side
    Route::get('/vendor/bookings', [BookingController::class, 'vendorBookings']);
    Route::patch('/vendor/bookings/{id}/status', [BookingController::class, 'updateStatus']);

    // FCM Token update
    Route::post('/vendor/fcm-token', [VendorProfileController::class, 'updateFcmToken']);
});
```

---

# ═══════════════════════════════════════════════════════════════

# SECTION 5: SERVICES — FULL CODE

# ═══════════════════════════════════════════════════════════════

## ShamCashService (app/Services/ShamCashService.php)

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShamCashService
{
    private string $token;
    private string $accountId;
    private string $baseUrl = 'https://api.shamcash-api.com/v1';

    public function __construct()
    {
        $this->token     = env('SHAMCASH_API_TOKEN');
        $this->accountId = env('SHAMCASH_ACCOUNT_ID');
    }

    public function verifyTransaction(
        string $transactionId,
        float  $expectedAmount,
        string $currency = 'SYP'
    ): array {

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ])->get("{$this->baseUrl}/transactions", [
            'account_id'      => $this->accountId,
            'transaction_ids' => $transactionId,
        ]);

        if (!$response->successful()) {
            return ['verified' => false, 'reason' => 'API_ERROR'];
        }

        $data         = $response->json();
        $transactions = $data['data']['transactions'] ?? [];

        if (empty($transactions)) {
            return ['verified' => false, 'reason' => 'NOT_FOUND'];
        }

        $tx = $transactions[0];

        if ((float) $tx['amount'] !== (float) $expectedAmount) {
            return [
                'verified' => false,
                'reason'   => 'AMOUNT_MISMATCH',
                'expected' => $expectedAmount,
                'received' => $tx['amount'],
            ];
        }

        if ($tx['currency']['code'] !== strtoupper($currency)) {
            return ['verified' => false, 'reason' => 'CURRENCY_MISMATCH'];
        }

        $occurredAt = \Carbon\Carbon::parse($tx['occurred_at']);
        if ($occurredAt->diffInMinutes(now()) > 60) {
            return ['verified' => false, 'reason' => 'TRANSACTION_EXPIRED'];
        }

        return [
            'verified'    => true,
            'amount'      => $tx['amount'],
            'sender_name' => $tx['sender_name'],
            'occurred_at' => $tx['occurred_at'],
        ];
    }
}
```

**ShamCash API Details:**

- Base URL: `https://api.shamcash-api.com/v1`
- Auth: Bearer token in Authorization header
- Account ID: `acc_01ksvc53hwdpxm9cav1f9zbymj`
- Endpoints used:
  - `GET /accounts` — get account_id (one-time setup)
  - `GET /transactions` — verify payments (used every payment)
  - `GET /balances` — not used currently

**Transaction verification checks:**

1. Transaction exists (NOT_FOUND check)
2. Amount matches expected (AMOUNT_MISMATCH check)
3. Currency is SYP (CURRENCY_MISMATCH check)
4. Not older than 60 minutes (TRANSACTION_EXPIRED check)
5. Transaction ID not used before (DUPLICATE check — via DB unique constraint)

**ShamCash transaction[0] explanation:**
When we send `transaction_ids=333`, API returns array with only that transaction.
`$transactions[0]` is the single matching transaction we requested — not the latest overall.

---

## NotificationService (app/Services/NotificationService.php) — NEW

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Auth\Credentials\ServiceAccountCredentials;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Admin;

class NotificationService
{
    protected string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = base_path(env('FIREBASE_CREDENTIALS'));
    }

    private function getAccessToken(): ?string
    {
        if (!file_exists($this->credentialsPath)) {
            Log::error("Firebase credentials file not found at: {$this->credentialsPath}");
            return null;
        }

        $scopes = ['https://www.googleapis.com/auth/cloud-platform'];

        try {
            $creds = new ServiceAccountCredentials($scopes, $this->credentialsPath);
            $authToken = $creds->fetchAuthToken();
            return $authToken['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error("Failed to generate Firebase Access Token: " . $e->getMessage());
            return null;
        }
    }

    public function send(string $fcmToken, string $title, string $body, array $data = []): void
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return;
        }

        $config    = json_decode(file_get_contents($this->credentialsPath), true);
        $projectId = $config['project_id'] ?? null;

        if (!$projectId) {
            Log::error("Firebase Project ID could not be extracted.");
            return;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            'message' => [
                'token'        => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
            ]
        ];

        if (!empty($data)) {
            // Google requires all data values to be strings
            $payload['message']['data'] = array_map('strval', $data);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error("FCM Notification failed to send", $response->json());
        }
    }

    public function notifyUser(User $user, string $title, string $body): void
    {
        if ($user->fcm_token) {
            $this->send($user->fcm_token, $title, $body);
        }
    }

    public function notifyVendor(Vendor $vendor, string $title, string $body): void
    {
        if ($vendor->fcm_token) {
            $this->send($vendor->fcm_token, $title, $body);
        }
    }

    // Commented until Admin table is built
    /*
    public function notifyAdmins(string $role, string $title, string $body): void
    {
        Admin::where('role', $role)
            ->whereNotNull('fcm_token')
            ->each(fn($admin) => $this->send($admin->fcm_token, $title, $body));
    }
    */
}
```

**IMPORTANT Notes on NotificationService:**

1. **Why Log not JSON response for errors:**
   Notifications are background tasks. If Firebase fails, we don't want to break
   the main API response to the user. Log silently, fix later.

2. **Why project_id from JSON not .env:**
   DRY principle — project_id already exists in the JSON file.
   No need to duplicate in .env.

3. **FCM Token vs Sanctum Token — COMPLETELY DIFFERENT:**
   - Sanctum Token: identifies user to Laravel backend
   - FCM Token: identifies the physical device to Firebase
     Flutter team generates FCM token on device and sends to backend on every login.

4. **Google/auth package used instead of kreait/laravel-firebase:**
   kreait requires PHP 8.3+ but project uses PHP 8.2
   google/auth works with PHP 8.2 and handles OAuth2 for FCM HTTP v1 API

5. **Installed package:** `composer require google/auth`

6. **Firebase project:** `haflati-d14da`
   Credentials file: `storage/app/haflati-d14da-firebase-adminsdk-fbsvc-13bfc3d73d.json`
   File is in .gitignore — must be shared manually between team members

---

## Notifications Planned — Full List

```
For User:
├── Vendor accepted booking ✅ → "تم قبول حجزك!"
├── Vendor declined booking ❌ → "تم رفض حجزك"
├── Payment confirmed ✅ → "تم تأكيد دفعك"
└── Booking completed ✅ → "اكتملت الخدمة"

For Vendor:
├── New booking request 🔔 → "حجز جديد وصلك"
├── User cancelled booking ❌ → "المستخدم إلغى الحجز"
└── Payment received 💰 → "وصل دفع جديد"

For Super Admin:
├── New vendor pending KYC → "فيندور جديد بانتظار الموافقة"
├── New complaint → "شكوى جديدة وصلت"
└── Daily report → automated daily summary

For Support Admin:
├── New complaint assigned → "شكوى جديدة"
└── Reply on complaint → "رد جديد على شكوى"
```

**Where notifications are triggered:**

```
BookingController::store()        → notifyVendor (new booking)
BookingController::cancel()       → notifyVendor (booking cancelled)
BookingController::updateStatus() → notifyUser (accepted/declined) [Amer]
PaymentController::verify()       → notifyVendor (payment received)
```

---

## Chat System — Future (Firebase Firestore)

> **⚠️ SUPERSEDED 2026-08-14 — chat is now BACKEND-OWNED (MySQL). See "UPDATES — 2026-08-14 (user↔vendor chat)". Kept for history only.**

```
Notifications → FCM (already set up)
Chat messages → Firebase Firestore (same Firebase project)
Both use the same Firebase project — no extra setup needed
```

---

# ═══════════════════════════════════════════════════════════════

# SECTION 6: CONTROLLERS — FULL CODE

# ═══════════════════════════════════════════════════════════════

## UserAuthController

```php
<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UserAuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:15|regex:/^\+?[0-9]+$/'
        ]);

        $otp = rand(100000, 999999);
        Cache::put('otp_'.$request->phone, $otp, now()->addMinutes(5));

        $response = Http::asForm()->post(
            "https://api.ultramsg.com/".env('ULTRAMSG_INSTANCE_ID')."/messages/chat?token=".env('ULTRAMSG_TOKEN'),
            ['to' => $request->phone, 'body' => "Verification Code: $otp"]
        );

        return response()->json([
            'message'           => 'OTP sent',
            'otp'               => $otp, // REMOVE IN PRODUCTION
            'ultramsg_status'   => $response->status(),
            'ultramsg_response' => $response->json(),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:15|regex:/^\+?[0-9]+$/',
            'otp'   => 'required|integer|digits:6',
        ]);

        $cachedOtp = Cache::get('otp_'.$request->phone);
        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        Cache::forget('otp_'.$request->phone);
        $user = User::where('phone', $request->phone)->first();

        if ($user) {
            return response()->json([
                'status' => 'login',
                'token'  => $user->createToken('auth_token')->plainTextToken,
                'user'   => $user
            ]);
        }

        $regToken = Str::random(64);
        Cache::put('reg_token_'.$regToken, $request->phone, now()->addMinutes(15));

        return response()->json(['status' => 'new_user', 'registration_token' => $regToken]);
    }

    public function completeRegistration(Request $request)
    {
        $request->validate([
            'registration_token' => 'required|string|size:64',
            'name'               => 'required|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'birth_date'         => 'required|date|before:today|after:1900-01-01',
        ]);

        $phone = Cache::get('reg_token_'.$request->registration_token);
        if (!$phone) return response()->json(['message' => 'Expired or invalid token'], 403);

        $user = User::create([
            'phone'      => $phone,
            'name'       => $request->name,
            'birth_date' => $request->birth_date,
        ]);

        Cache::forget('reg_token_'.$request->registration_token);

        return response()->json([
            'status' => 'success',
            'token'  => $user->createToken('auth_token')->plainTextToken,
            'user'   => $user
        ]);
    }
}
```

---

## BookingController (UPDATED — correct load, new status flow, notifications)

```php
<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\VendorProduct;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'vendor_product_id' => 'required|exists:vendor_products,id',
            'notes'             => 'sometimes|nullable|string',
            'event_date'        => 'sometimes|nullable|date',
            'event_location'    => 'sometimes|nullable|string',
            'duration_hours'    => 'sometimes|nullable|integer',
            'details'           => 'sometimes|nullable|array',
            'delivery_date'     => 'sometimes|nullable|date',
            'delivery_address'  => 'sometimes|nullable|string',
        ]);

        $user    = $request->user();
        $product = VendorProduct::with('vendor')->findOrFail($request->vendor_product_id);
        $vendor  = $product->vendor;

        if ($vendor->booking_style === 'appointment' && $request->event_date) {
            $conflict = Booking::where('vendor_id', $vendor->id)
                ->whereIn('status', ['pending', 'awaiting_payment', 'approved'])
                ->whereDate('event_date', $request->event_date)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This date is already booked',
                ], 409);
            }
        }

        $booking = Booking::create([
            'user_id'           => $user->id,
            'vendor_id'         => $vendor->id,
            'vendor_product_id' => $product->id,
            'booking_style'     => $vendor->booking_style,
            'event_type'        => $vendor->vendor_type,
            'status'            => 'pending',
            'notes'             => $request->notes,
            'event_date'        => $request->event_date,
            'event_location'    => $request->event_location,
            'duration_hours'    => $request->duration_hours,
            'details'           => $request->details,
            'delivery_date'     => $request->delivery_date,
            'delivery_address'  => $request->delivery_address,
        ]);

        // Notify vendor of new booking
        $notification = new NotificationService();
        $notification->notifyVendor(
            $vendor,
            'حجز جديد! 🔔',
            'وصلك طلب حجز جديد من ' . $user->first_name
        );

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'product']),
        ]);
    }

    public function userBookings(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with(['vendor', 'product'])
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'bookings' => $bookings]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'notes'            => 'sometimes|nullable|string',
            'event_date'       => 'sometimes|nullable|date',
            'event_location'   => 'sometimes|nullable|string',
            'duration_hours'   => 'sometimes|nullable|integer',
            'details'          => 'sometimes|nullable|array',
            'delivery_date'    => 'sometimes|nullable|date',
            'delivery_address' => 'sometimes|nullable|string',
        ]);

        $user    = $request->user();
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($booking->booking_style === 'appointment' && $request->event_date) {
            $conflict = Booking::where('vendor_id', $booking->vendor_id)
                ->where('id', '!=', $id)
                ->whereIn('status', ['pending', 'awaiting_payment', 'approved'])
                ->whereDate('event_date', $request->event_date)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This date is already booked',
                ], 409);
            }
        }

        $booking->update($request->only([
            'notes', 'event_date', 'event_location',
            'duration_hours', 'details', 'delivery_date', 'delivery_address',
        ]));

        $booking->refresh();

        return response()->json([
            'status'  => 'success',
            'booking' => $booking->load(['vendor', 'product']),
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $booking = Booking::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $booking->update(['status' => 'cancelled']);

        // Notify vendor of cancellation
        $notification = new NotificationService();
        $notification->notifyVendor(
            $booking->vendor,
            'إلغاء حجز ❌',
            'قام المستخدم بإلغاء الحجز'
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Booking cancelled successfully',
        ]);
    }

    // AMER IS BUILDING THIS
    public function vendorBookings(Request $request)
    {
        $bookings = Booking::where('vendor_id', $request->user()->id)
            ->with(['user', 'product'])
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'bookings' => $bookings]);
    }

    // AMER IS BUILDING THIS — vendor approves (→ awaiting_payment) or declines
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:awaiting_payment,declined',
        ]);

        $vendor  = $request->user();
        $booking = Booking::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $booking->update(['status' => $request->status]);
        $booking->refresh();

        $notification = new NotificationService();

        if ($request->status === 'awaiting_payment') {
            $notification->notifyUser(
                $booking->user,
                'تم قبول حجزك! ✅',
                'قبل ' . $vendor->business_name . ' حجزك — يرجى إتمام الدفع'
            );
        } else {
            $notification->notifyUser(
                $booking->user,
                'تم رفض حجزك ❌',
                'رفض ' . $vendor->business_name . ' طلب حجزك'
            );
        }

        return response()->json(['status' => 'success', 'booking' => $booking]);
    }
}
```

---

## PaymentController (UPDATED — correct status flow + notification)

```php
<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\ShamCashService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'booking_id'     => 'required|exists:bookings,id',
            'transaction_id' => 'required|string',
        ]);

        $user    = $request->user();
        $booking = Booking::where('id', $request->booking_id)
            ->where('user_id', $user->id)
            ->where('status', 'awaiting_payment') // UPDATED: must be awaiting_payment
            ->firstOrFail();

        if (Payment::where('booking_id', $booking->id)
            ->where('status', 'verified')
            ->exists()
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This booking is already paid',
            ], 409);
        }

        if (Payment::where('transaction_id', $request->transaction_id)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This transaction has already been used',
            ], 409);
        }

        $product        = $booking->product;
        $vendor         = $booking->vendor;
        $expectedAmount = $vendor->booking_style === 'appointment'
            ? round($product->price * ($product->deposit_percent / 100), 2)
            : $product->price;

        $shamCash = new ShamCashService();
        $result   = $shamCash->verifyTransaction(
            $request->transaction_id,
            $expectedAmount
        );

        if (!$result['verified']) {
            return response()->json([
                'status'  => 'error',
                'message' => match ($result['reason']) {
                    'NOT_FOUND'           => 'Transaction not found',
                    'AMOUNT_MISMATCH'     => 'Amount does not match. Expected: ' . $expectedAmount,
                    'CURRENCY_MISMATCH'   => 'Wrong currency',
                    'TRANSACTION_EXPIRED' => 'Transaction is too old',
                    default               => 'Payment verification failed',
                },
            ], 422);
        }

        $commission   = round($expectedAmount * 0.15, 2);
        $vendorPayout = round($expectedAmount * 0.85, 2);

        try {
            $payment = Payment::create([
                'booking_id'     => $booking->id,
                'amount_paid'    => $expectedAmount,
                'commission'     => $commission,
                'vendor_payout'  => $vendorPayout,
                'currency'       => 'SYP',
                'transaction_id' => $request->transaction_id,
                'sender_name'    => $result['sender_name'],
                'status'         => 'verified',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This transaction has already been used',
            ], 409);
        }

        // UPDATED: status → approved (payment confirms the booking)
        $booking->update(['status' => 'approved']);
        $booking->refresh();

        // Notify vendor that payment was received
        $notification = new NotificationService();
        $notification->notifyVendor(
            $vendor,
            'وصل دفع جديد! 💰',
            'دفع ' . $user->first_name . ' العربون للحجز رقم #' . $booking->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment verified successfully',
            'booking' => $booking->load(['vendor', 'product']),
            'payment' => $payment,
        ]);
    }
}
```

---

## VendorProfileController

```php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['status' => 'success', 'vendor' => $request->user()]);
    }

    public function setType(Request $request)
    {
        $request->validate([
            'vendor_type' => 'required|in:wedding_venue,photographer,dj,cake_shop,store',
        ]);

        $vendor = $request->user();

        $bookingStyle = in_array($request->vendor_type, ['cake_shop', 'store'])
            ? 'order'
            : 'appointment';

        $vendor->update([
            'vendor_type'   => $request->vendor_type,
            'booking_style' => $bookingStyle,
        ]);

        return response()->json([
            'status'        => 'success',
            'vendor_type'   => $vendor->vendor_type,
            'booking_style' => $vendor->booking_style,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'bio'           => 'sometimes|string|max:1000',
            'birth_date'    => 'sometimes|date',
            'latitude'      => 'sometimes|numeric|between:-90,90',
            'longitude'     => 'sometimes|numeric|between:-180,180',
            'address'       => 'sometimes|string|max:255',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $vendor = $request->user();
        $data   = $request->only(['business_name', 'bio', 'birth_date', 'latitude', 'longitude', 'address']);

        if ($request->hasFile('profile_image')) {
            if ($vendor->profile_image) {
                Storage::disk('public')->delete($vendor->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')
                ->store('vendor_images', 'public');
        }

        $vendor->update($data);

        return response()->json(['status' => 'success', 'vendor' => $vendor]);
    }

    public function deleteImage(Request $request)
    {
        $vendor = $request->user();

        if ($vendor->profile_image) {
            Storage::disk('public')->delete($vendor->profile_image);
            $vendor->update(['profile_image' => null]);
        }

        return response()->json(['status' => 'success', 'message' => 'Profile image removed']);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['status' => 'success']);
    }
}
```

---

# ═══════════════════════════════════════════════════════════════

# SECTION 7: DEVOPS & COMPLETE BUG FIXES LEDGER

# ═══════════════════════════════════════════════════════════════

## Production Environment

### Railway Configuration

- **Auth:** GitHub OAuth
- **MySQL:** Managed MySQL service (mysql.railway.internal)
- **DB Name:** `railway` (NOT `events_api`)
- **PHP Version:** 8.2.x
- **Server:** FrankenPHP
- **Auto-deploy:** Every push to `main` branch

### Local vs Railway Environment

| Setting        | Local      | Railway                |
| -------------- | ---------- | ---------------------- |
| DB_HOST        | 127.0.0.1  | mysql.railway.internal |
| DB_DATABASE    | events_api | railway                |
| APP_ENV        | local      | production             |
| APP_DEBUG      | true       | false                  |
| SESSION_DRIVER | database   | file                   |

---

## BUG FIXES LEDGER

### BUG #1: Procfile Conflict with FrankenPHP

**Problem:** Procfile with `php artisan serve` conflicted with Railway's FrankenPHP.
**Fix:** Delete the Procfile entirely.

### BUG #2: Wrong DB_DATABASE on Railway

**Problem:** Local uses `events_api`, Railway uses `railway`.
**Fix:** Set `DB_DATABASE=railway` in Railway Variables.

### BUG #3: UltraMsg Token Position

**Problem:** Token in body returned "Wrong token" error.
**Fix:** Move token to URL query parameter.

```php
Http::asForm()->post(
    "https://api.ultramsg.com/".env('ULTRAMSG_INSTANCE_ID')."/messages/chat?token=".env('ULTRAMSG_TOKEN'),
    ['to' => $phone, 'body' => "Verification Code: $otp"]
);
```

### BUG #4: Primary Image Always False

**Problem:** `is_primary` always false even when `primary_image_index` sent correctly.
**Root Cause:** `$request->primary_image_index` comes as string, `$index` is int — strict comparison fails.
**Fix:** `$primaryIndex = (int) ($request->primary_image_index ?? 0);`

### BUG #5: Nested Middleware Bug in api.php

**Problem:** `auth:vendors` group nested inside `auth:sanctum` group — vendor routes blocked.
**Fix:** Keep two middleware groups completely separate at same level.

### BUG #6: RelationNotFoundException — vendor_product vs product (RESOLVED 2026-06-10)

**Problem (historical):** `$booking->load(['vendor', 'product'])` threw RelationNotFoundException because the relationship method was named `vendor_product()`.
**Resolution:** The method was renamed to `product()` on 2026-06-10 (to match `VendorProductImage::product()`), so the inconsistency is gone. **Now always use `$booking->load(['vendor', 'product'])`.** The FK column stays `vendor_product_id`.

### BUG #7: PUT/PATCH Fails on Railway with form-data

**Problem:** PUT/PATCH with form-data arrives empty on Railway/PHP.
**Root Cause:** PHP only parses form-data body for POST requests natively.
**Fix:** Method spoofing — send POST with `_method = PATCH` in form-data.

```
Method: POST
URL: /api/vendor/products/{id}
Body: _method=PATCH, name=updated, images[]=file
```

### BUG #8: Stale Data in Response After Update

**Problem:** After `$booking->update([...])`, response shows old values.
**Root Cause:** Eloquent caches attributes in memory. `update()` modifies DB but in-memory object not refreshed.
**Fix:** Call `$booking->refresh()` after every update.

### BUG #9: customer_id vs user_id Mismatch

**Problem:** `$fillable` had `customer_id` but DB column is `user_id`.
**Fix:** Use `user_id` consistently everywhere.

### BUG #10: booking_style Cannot Be Null

**Problem:** Vendor hadn't set their type via `setType()` before testing bookings.
**Fix:** Ensure vendor calls `POST /api/vendor/profile/type` before booking tests.

### BUG #11: .env Comment Syntax Error

**Problem:** `//// Ultramsg` comment caused "Failed to parse dotenv file" error.
**Root Cause:** .env comments must start with `#` not `//`.
**Fix:** Change to `# Ultramsg`.

### BUG #12: kreait/laravel-firebase PHP Version Conflict

**Problem:** `kreait/laravel-firebase` requires PHP 8.3+ but project uses PHP 8.2.
**Fix:** Use `composer require google/auth` instead and call FCM HTTP v1 API directly via `Http::`.

### BUG #13: Payment Status Flow — LOGIC BUG

**Problem:** Original PaymentController had `$booking->update(['status' => 'pending'])` after payment verified.
**Root Cause:** Copy-paste error — booking should be `approved` after payment.
**Fix:** Change to `$booking->update(['status' => 'approved'])`.
**ALSO:** PaymentController now checks for `status = 'awaiting_payment'` (not 'pending')
because new flow is: vendor approves first (→ awaiting_payment), then user pays (→ approved).

### BUG #14: Race Condition on Transaction ID

**Problem:** Two requests with same transaction_id could both pass if processed simultaneously.
**Fix (3 layers):**

1. Controller checks `Payment::where('transaction_id')->exists()` before creating
2. DB unique constraint: `$table->string('transaction_id')->unique()`
3. try/catch on `Payment::create()` to handle QueryException if race condition occurs

### BUG #15: Booking Conflict Check — Missing awaiting_payment Status

**Problem:** Date conflict check only blocked `pending` and `approved` but not `awaiting_payment`.
**Fix:** Add `awaiting_payment` to conflict check:

```php
->whereIn('status', ['pending', 'awaiting_payment', 'approved'])
```

**Note (2026-07-28):** the booking refactor accidentally dropped `awaiting_payment` again; re-added to both `storeAppointment()` and `update()`. See BUG #16 for the DB-level guard that now backs it up.

### BUG #16: Double-Booking Race Condition (2026-07-28)

**Problem:** the appointment date guard is `->exists()` then `create()` — not atomic. Two simultaneous requests both pass the "date free?" check before either inserts, so both book the same date.
**Fix (DB is the final judge, like BUG #14 for transaction_id):**

1. STORED generated column `bookings.event_day` = `DATE(event_date)` (NULL when `event_date` is NULL).
2. Unique index on `(vendor_id, event_day)` — DB physically forbids two bookings per vendor per day. NULLs are ignored, so orders + cancelled/declined rows never collide.
3. `bookings.old_event_date` keeps the date for display; a `Booking::saving` hook moves `event_date` → `old_event_date` and nulls `event_date` on cancel/decline (frees the slot, drops out of the index).
4. `storeAppointment()` create wrapped in try/catch → duplicate-key error becomes a clean 409.

Flutter display: `event_date ?? old_event_date`.

---

## Auth Config (config/auth.php)

```php
'guards' => [
    'web' => [
        'driver'   => 'session',
        'provider' => 'users',
    ],
    'vendors' => [
        'driver'   => 'sanctum',
        'provider' => 'vendors',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model'  => App\Models\User::class,
    ],
    'vendors' => [
        'driver' => 'eloquent',
        'model'  => App\Models\Vendor::class,
    ],
],
```

---

## Installed Packages

```
composer require laravel/sanctum        ← Auth tokens
composer require google/auth            ← Firebase FCM (PHP 8.2 compatible)
```

**NOT installed (requires PHP 8.3+):**

```
kreait/laravel-firebase ← DO NOT INSTALL on PHP 8.2
```

---

## Image Storage

- **Local:** `storage/app/public/product_images/`, `vendor_images/`, `profile_images/`
- **Access URL:** `http://localhost:8000/storage/product_images/filename.jpg`
- **Railway Issue:** Images WIPED on every deploy
- **Future fix:** Cloudinary for persistent storage
- **Command after clone:** `php artisan storage:link`

---

## Postman Testing Guide

### Headers for all authenticated requests

```
Authorization: Bearer {token}
Accept: application/json
```

### Method Spoofing for product updates

```
Method: POST
URL: /api/vendor/products/{id}
Body (form-data): _method=PATCH, name=updated, images[]=file
```

### Test booking (appointment)

```json
POST /api/bookings
{
  "vendor_product_id": 1,
  "event_date": "2026-09-15 18:00:00",
  "event_location": "Damascus, Syria",
  "duration_hours": 6,
  "notes": "Wedding for 200 guests"
}
```

### Test payment verification

```json
POST /api/payments/verify
{
  "booking_id": 1,
  "transaction_id": "184627893"
}
```

### Update FCM token (Flutter sends on login)

```json
POST /api/fcm-token
Authorization: Bearer {user_token}
{
  "fcm_token": "device_fcm_token_here"
}
```

---

# ═══════════════════════════════════════════════════════════════

# SECTION 8: PAYMENT SYSTEM — FULL DOCUMENTATION

# ═══════════════════════════════════════════════════════════════

## ShamCash API (shamcash-api.com)

- **Base URL:** `https://api.shamcash-api.com/v1`
- **Auth:** `Authorization: Bearer {api_token}`
- **Account:** Muhammad Jamal (mohamad.jamal.mohamad2@gmail.com)
- **Account ID:** `acc_01ksvc53hwdpxm9cav1f9zbymj`
- **Subscription expires:** 2026-06-29

**Available Endpoints:**

```
GET /accounts      → list linked ShamCash accounts
GET /balances      → get balance (account_id required)
GET /transactions  → get incoming transactions (account_id required)
```

**Transaction verification logic:**

```
GET /transactions?account_id=xxx&transaction_ids=184627893
Returns array with that specific transaction (not all transactions)
$transactions[0] = the one transaction we requested
```

**Why transaction[0]:**
We filter by specific transaction_id so API returns array with 1 item.
`[0]` gets that single item. NOT the latest transaction overall.

**Error codes:**

```
SUCCESS                → 200
VALIDATION_ERROR       → 400
AUTH_MISSING           → 401
AUTH_INVALID           → 401
SUBSCRIPTION_UNAVAILABLE → 403
NOT_FOUND              → 404
RATE_LIMIT_EXCEEDED    → 429
FETCH_FAILED           → 502
INTERNAL_ERROR         → 500
```

---

## Why Errors are Logged (not returned as JSON)

**For ShamCash errors:** Returned as array because user is waiting for the result.
User needs to know "Transaction not found" or "Amount mismatch" to fix it.

**For FCM notification errors:** Logged silently because:

- Notifications are background tasks
- If Firebase fails, main API response should still succeed
- User doesn't know/care about background notification
- Developer checks logs to debug

**Rule:** Does the user need to know? → Return error. Background task? → Log it.

---

# ═══════════════════════════════════════════════════════════════

# SECTION 9: FIREBASE & NOTIFICATION SYSTEM SETUP

# ═══════════════════════════════════════════════════════════════

## Firebase Project Details

- **Project Name:** Haflati
- **Project ID:** `haflati-d14da`
- **Console:** `console.firebase.google.com/u/0/project/haflati-d14da`
- **Service Account:** `firebase-adminsdk-fbsvc@haflati-d14da.iam.gserviceaccount.com`
- **Credentials File:** `haflati-d14da-firebase-adminsdk-fbsvc-13bfc3d73d.json`
- **File Location:** `storage/app/haflati-d14da-firebase-adminsdk-fbsvc-13bfc3d73d.json`
- **In .gitignore:** YES — must share manually between team members

## Services Enabled

- Cloud Messaging (FCM) — for push notifications
- Firestore — planned for chat system

## FCM HTTP v1 API

- **Endpoint:** `POST https://fcm.googleapis.com/v1/projects/{projectId}/messages:send`
- **Auth:** OAuth2 Bearer token (generated from Service Account JSON)
- **Scope:** `https://www.googleapis.com/auth/cloud-platform`

## Token Types — CRITICAL DISTINCTION

```
Sanctum Token:
  - Created by: Laravel when user logs in
  - Identifies: User to Laravel backend
  - Sent in: Authorization header of every API request
  - Stored in: personal_access_tokens table

FCM Token (Device Token):
  - Created by: Firebase SDK on the device
  - Identifies: Physical device to Firebase
  - Different per device (same user, different phone = different token)
  - Sent in: POST /api/fcm-token after every login by Flutter team
  - Stored in: users.fcm_token or vendors.fcm_token column
```

## Flutter Team Responsibilities

```
1. Add firebase_messaging package
2. On app start: get FCM token from Firebase
3. On login: send FCM token to backend (POST /api/fcm-token)
4. Handle incoming notifications (foreground + background + killed)
5. Add google-services.json (Android) from Firebase console
6. Add GoogleService-Info.plist (iOS) from Firebase console
```

## React Admin Team Responsibilities

```
1. Add firebase package
2. Get FCM token from browser
3. Send to backend on admin login
4. Handle notification display
5. Get Firebase config object from console
```

## Amer's Responsibilities (Backend)

```
1. Get Firebase JSON file from Moh
2. Place in storage/app/
3. Add FIREBASE_CREDENTIALS to .env
4. Add SHAMCASH credentials to .env
5. Build vendorBookings() and updateStatus() in BookingController
6. Build Admin table and auth when ready
```

---

# ═══════════════════════════════════════════════════════════════

# SECTION 10: ADMIN SYSTEM PLAN (TO BE BUILT)

# ═══════════════════════════════════════════════════════════════

## Two Admin Roles

| Role        | Permissions                                           |
| ----------- | ----------------------------------------------------- |
| super_admin | Everything — users, vendors, payments, reports, audit |
| support     | KYC review, complaints only                           |

## Admin Notifications

```
super_admin receives:
  - New vendor pending KYC
  - New complaint (serious disputes)
  - Daily analytics report

support receives:
  - New complaint assigned
  - Reply on active complaint
```

## Uncomment notifyAdmins() after Admin table is built:

```php
public function notifyAdmins(string $role, string $title, string $body): void
{
    Admin::where('role', $role)
        ->whereNotNull('fcm_token')
        ->each(fn($admin) => $this->send($admin->fcm_token, $title, $body));
}
```

---

# ═══════════════════════════════════════════════════════════════

# SECTION 11: CRITICAL RULES FOR NEW CLAUDE SESSION

# ═══════════════════════════════════════════════════════════════

## MUST FOLLOW — Zero Exceptions

1. **Relationship Loading:** ALWAYS `$booking->load(['vendor', 'product'])` — the method was renamed from `vendor_product()` to `product()` on 2026-06-10. The DB column is still `vendor_product_id`.
2. **After Updates:** ALWAYS call `$booking->refresh()` before returning JSON response
3. **Form-data Updates:** ALWAYS use POST with `_method=PATCH` spoofing for file uploads
4. **primary_image_index:** ALWAYS cast to `(int)` — `(int) ($request->primary_image_index ?? 0)`
5. **Middleware Groups:** NEVER nest `auth:vendors` inside `auth:sanctum` — always separate
6. **DB Names:** Local = `events_api`, Railway = `railway`
7. **Booking Status:** Flow = awaiting_payment → pending (after pay) → approved → completed
8. **Payment:** User can only pay when booking is `awaiting_payment` (not `pending`)
9. **Date Conflicts:** Block `pending`, `awaiting_payment`, AND `approved` statuses
10. **FCM Errors:** Log silently — never return as API error response
11. **ShamCash Errors:** Return as JSON — user needs to know

## Language Convention (UPDATED 2026-07-15)

- Two people use this repo with Claude: **Mohamad → English**, **Amer → Arabic** (identify the speaker by the language they write in)
- When answering Amer in Arabic: keep Arabic and English on separate lines, never mixed inline
- No emojis/icons in code comments or user-facing strings (notification titles/bodies)

## Architecture Reminders

- Two separate Flutter apps (Customer + Vendor) — different guards
- Admin is React web only — separate from Flutter apps
- All services in `app/Services/` folder
- All notifications are background tasks — never block main flow

---

# END OF MASTER CONTEXT FILE v2.0

# Generated: June 2026

# This file contains ALL knowledge from Session 1 + Session 2
