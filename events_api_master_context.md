# EVENTS API — MASTER CONTEXT FILE

# حفلتي — Haflati Platform

# Complete Project Blueprint — UPDATED SESSION 2

# Generated: June 2026 | Version: 2.0 FINAL

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
- **Images not persistent** — wiped on every Railway deploy. Acceptable while testing; move to Cloudflare R2 before launch.

### Deferred by decision (fine for testing)
Persistent images (R2) · UltraMsg renewal · removing OTP/`0000`/`debug_notifications` · `APP_DEBUG=false` · ShamCash payout+refund API · reviews completed-only rule · pagination · Arabic translation · chat · favorites (Amer's task).

> **Docs:** `docs/vendor-api.html` is the up-to-date Vendor App API reference for the Flutter team (new categories, `vendor_style`, Portfolio section).

---

## Next Steps To Build (IN ORDER)

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

## Language Convention

- Moh speaks Arabic and English mixed
- Claude responds in Arabic with English technical terms
- Example: "الـ controller بيعمل الـ validation أول"

## Architecture Reminders

- Two separate Flutter apps (Customer + Vendor) — different guards
- Admin is React web only — separate from Flutter apps
- All services in `app/Services/` folder
- All notifications are background tasks — never block main flow

---

# END OF MASTER CONTEXT FILE v2.0

# Generated: June 2026

# This file contains ALL knowledge from Session 1 + Session 2
