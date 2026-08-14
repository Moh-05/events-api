# Haflati Vendor API — Machine-Executable Integration Test Spec

> **Reading agent: this file is your instruction set.** You are verifying that the Flutter app's linking to the backend is correct by running real HTTP calls against the live production API. Execute the **Auth Bootstrap** first, hold the token, then run the **Ready-to-run Test Scenarios** top to bottom. For every call, assert the HTTP status code AND the JSON shape against the "Endpoint Reference". Report any mismatch (wrong status, missing field, extra field, wrong type) with the endpoint, what you sent, what you expected, and what you got. Do not assume — verify against what is written here, which was derived directly from the controller source.

This spec covers **vendor endpoints only** (vendor auth, profile, products, portfolio, vendor-side bookings, availability, reviews, stats, earnings, wallet, notifications). User/admin endpoints appear only as much as needed to set up test data.

---

## 0. Global conventions

- **Base URL:** `https://events-api-production-138b.up.railway.app/api`
- **Auth header:** `Authorization: Bearer {token}` on every protected call.
- **ALWAYS send `Accept: application/json`** on every request. Without it, auth/validation failures return an HTML redirect or a 500 instead of clean JSON — you will misread the result. This is mandatory.
- **Content types:**
  - JSON bodies: `Content-Type: application/json`.
  - Any call that uploads files (product/portfolio/profile images) MUST be `multipart/form-data`. See the multipart note in §4.
- **Two production test helpers (live right now):**
  1. **OTP is returned in the send-otp response body** in the `otp` field. No WhatsApp needed — read it straight from the JSON.
  2. **Payment bypass:** send `transaction_id: "0000"` to `POST /payments/verify`. It skips ShamCash and is reusable across bookings. This is how you fund a booking so it becomes visible to the vendor.
- **`fcm_token` and `remember_token` NEVER appear in any response.** Do not assert their presence; assert their ABSENCE on vendor/user objects.
- **Auth guards:** vendor routes use guard `auth:vendors`; user routes use `auth:sanctum`. A user token cannot call a vendor route and vice-versa (returns `401`). Both are Sanctum plain-text tokens; they are not interchangeable across guards.
- **Standard error envelopes** you will see repeatedly:
  - Laravel validation error → **422** with `{ "message": "...", "errors": { "field": ["..."] } }`.
  - Missing/invalid token (with `Accept: application/json`) → **401** `{ "message": "Unauthenticated." }`.
  - `firstOrFail()` / `findOrFail()` miss (wrong id, or a resource that isn't yours) → **404** `{ "message": "..." }` (Laravel `ModelNotFoundException`).
  - Banned account hitting a protected route → **403** `{ "status": "error", "message": "Your account has been suspended. Please contact support." }`.

### Localization — Arabic / English (what the Flutter app must do)

The API returns human-facing text (success `message`, error `message`, validation `errors`, and push-notification title/body) in **Arabic or English**, chosen by the **`Accept-Language`** request header.

- **Send `Accept-Language: ar` or `Accept-Language: en` on EVERY request.** The app picks it from the user's chosen language, or the phone's native language if they haven't chosen. If the header is missing or anything other than `en`, the API defaults to **Arabic**.
- Example: `POST /vendor/send-otp` with no `phone` → with `Accept-Language: ar` returns `{"message":"حقل رقم الهاتف مطلوب.", "errors":{"phone":["حقل رقم الهاتف مطلوب."]}}`; with `Accept-Language: en` returns the English equivalent. (The raw JSON escapes Arabic as `\uXXXX` — that is normal; the app decodes it to Arabic automatically.)
- **Notifications follow the recipient's stored language, not the request header** (a push has no request). The backend remembers each account's language from the `Accept-Language` header on their authenticated requests (column `vendors.language` / `users.language`, default `ar`). So: keep sending the header on normal calls and the vendor's notifications will arrive in that language automatically. No separate "set language" endpoint.
- **What the app translates itself (NOT the API):** the app must show Arabic labels for enum VALUES — `vendor_type` (`photographer`, `weddingHall`, `dj`, `makeupArtist`, `flowers`, `gifts`, `dresses`, `accessories`, `candles`, `cakes`), `booking_style` (`appointment`/`order`), `status` (`awaiting_payment`, `pending`, `approved`, `declined`, `completed`, `cancelled`), `account_status`. The API always returns the English enum KEY (it's the stable contract used in filters); mapping the key to an Arabic display label is the app's job (use the design system's `ar.json`). Never expect the API to send an Arabic enum value.
- **User-generated content is never translated:** `business_name`, `bio`, product `name`/`description`, `notes`, `address`, etc. come back exactly as the vendor typed them (usually Arabic already).

**Assertion for the testing agent:** repeat any message-returning call with `Accept-Language: ar` then `Accept-Language: en`; assert the `message`/`errors` text differs between the two (Arabic vs English) for the SAME status code and JSON shape.

---

## 1. Auth Bootstrap (do this first)

### 1.1 Vendor login / registration flow

Vendors authenticate by phone + OTP. A phone that already exists logs in; a new phone must complete registration.

**Step 1 — Send OTP**

```http
POST /vendor/send-otp
Content-Type: application/json
Accept: application/json

{ "phone": "+963900000001" }
```

`phone` rule: `required|string|min:7|max:15|regex:/^\+?[0-9]+$/`.

Response `200`:
```json
{
  "message": "OTP sent",
  "otp": 123456,
  "ultramsg_status": 200,
  "ultramsg_response": { }
}
```
**Extract `otp`** (integer). (`ultramsg_status`/`ultramsg_response` reflect the WhatsApp gateway and may show an error in test — ignore them; the OTP is still valid because it is cached server-side.)

**Step 2 — Verify OTP**

```http
POST /vendor/verify-otp
Content-Type: application/json
Accept: application/json

{ "phone": "+963900000001", "otp": 123456 }
```
Rules: `phone` same as above; `otp` = `required|integer|digits:6`.

Three possible `status` values:

- Existing, active vendor → **200**
  ```json
  { "status": "login", "token": "…plainTextToken…", "vendor": { /* Vendor object */ } }
  ```
  **Extract `token`.** Done — skip Step 3.
- New phone → **200**
  ```json
  { "status": "new_vendor", "registration_token": "…64-char string…" }
  ```
  **Extract `registration_token`**, go to Step 3.
- Suspended (banned, not winding down) → **403**
  ```json
  { "status": "suspended", "message": "Your account has been suspended. Please contact support." }
  ```
- Wrong/expired OTP → **400** `{ "message": "Invalid OTP" }`.

**Step 3 — Complete registration (new vendor only)**

```http
POST /vendor/complete-registration
Content-Type: application/json
Accept: application/json

{
  "registration_token": "…64 chars…",
  "first_name": "Test",
  "last_name": "Vendor",
  "city": "Damascus",
  "birth_date": "1995-05-20",
  "vendor_type": "photographer",
  "vendor_style": "service_provider"
}
```
Rules:
| field | rule |
|---|---|
| `registration_token` | required, string, size:64 |
| `first_name` | required, string, 2–50, letters+spaces only (`/^[\p{L}\s]+$/u`) |
| `last_name` | required, string, 2–50, letters+spaces only |
| `city` | required, string, 2–100 |
| `birth_date` | required, date, before today, after 1900-01-01 |
| `vendor_type` | required, `in:photographer,makeupArtist,dj,weddingHall,flowers,gifts,dresses,accessories,candles,cakes` |
| `vendor_style` | optional, `in:service_provider,seller` (Flutter helper only; no backend effect) |

Response **200**:
```json
{ "status": "success", "token": "…plainTextToken…", "vendor": { /* Vendor object */ } }
```
**Extract `token`.**

Expired/invalid registration_token → **403** `{ "message": "Expired" }`.

### 1.2 `vendor_type` enum → booking_style mapping

The server sets `booking_style` from `vendor_type` (the client's `vendor_style` does NOT drive logic). Assert the returned vendor's `booking_style`:

| vendor_type | category | booking_style | booking shape |
|---|---|---|---|
| `photographer` | service | `appointment` | one package + event fields |
| `makeupArtist` | service | `appointment` | one package + event fields |
| `dj` | service | `appointment` | one package + event fields |
| `weddingHall` | service | `appointment` | one package + event fields |
| `flowers` | seller | `order` | items[] cart + delivery fields |
| `gifts` | seller | `order` | items[] cart + delivery fields |
| `dresses` | seller | `order` | items[] cart + delivery fields |
| `accessories` | seller | `order` | items[] cart + delivery fields |
| `candles` | seller | `order` | items[] cart + delivery fields |
| `cakes` | seller | `order` | items[] cart + delivery fields |

To exercise BOTH booking shapes end-to-end, bootstrap **two** vendors: one `photographer` (appointment) and one `cakes` (order).

### 1.3 CRITICAL approval caveat (read before booking scenarios)

A freshly registered vendor has **`is_approved = false`** by default (KYC pending). **A booking against an unapproved vendor returns `403` "This vendor is currently unavailable"** (see §5). Therefore:

- **Self-service vendor endpoints** (profile, products, portfolio, availability, wallet, notifications, stats, earnings, reviews list) **work immediately** with a fresh vendor token — no approval needed. Test these first and fully.
- **Vendor-side booking scenarios** need at least one booking to exist, which requires an **approved, active, accepting** vendor. Getting `is_approved = true` requires an admin `POST /admin/vendors/{id}/approve` — there is NO self-approve endpoint. 

  If you have admin credentials, approve your test vendor. **If you do not, use a pre-existing approved vendor** whose phone you can OTP into (its bookings/products already exist), and run the booking scenarios against that account. Note this limitation in your report rather than inventing an approval path.

---

## 2. Cross-role setup helpers (create booking data for the vendor to act on)

Vendor-side booking endpoints (`approve`, `decline`, `complete`, `show`, lists, availability conflicts, wallet credits) need a **paid** booking to exist. A booking is created and paid on the **user** side. Minimal flow:

### 2.1 Get a user token
Same 3-step flow as the vendor but on the user routes: `POST /send-otp` → read `otp` → `POST /verify-otp` → (new) `POST /complete-registration` (no `vendor_type`/`vendor_style` fields). Extract `token`.

### 2.2 Discover the vendor's product id
As the vendor, `POST /vendor/products` to create a product (see §4), or `GET /vendor/products` to read existing ones. Capture a `vendor_product_id` (the `product.id`). Order vendors need `stock` set to test stock rules; appointment services can leave `stock` null.

### 2.3 Create a booking (user token) — shape depends on the vendor's booking_style

**Appointment vendor** (photographer, etc.):
```http
POST /bookings
Authorization: Bearer {USER_TOKEN}
Content-Type: application/json
Accept: application/json

{
  "vendor_product_id": 12,
  "event_date": "2026-09-15 18:00:00",
  "event_location": "Damascus",
  "duration_hours": 4,
  "notes": "outdoor shoot"
}
```
`event_date` is `required|date|after:now`. `items/details/delivery_date/delivery_address` are **prohibited** here (sending them → 422).

**Order vendor** (cakes, etc.):
```http
POST /bookings
Authorization: Bearer {USER_TOKEN}
Content-Type: application/json
Accept: application/json

{
  "items": [ { "vendor_product_id": 20, "quantity": 2 } ],
  "delivery_date": "2026-09-20",
  "delivery_address": "Mazzeh, Damascus",
  "notes": "chocolate"
}
```
`items` is `required|array|min:1`; `event_date/event_location/duration_hours` are **prohibited** here (→ 422).

Success **200**: `{ "status": "success", "booking": { …, "status": "awaiting_payment", … } }`. **Extract `booking.id`.** The booking is `awaiting_payment` and is **invisible to the vendor** until paid.

### 2.4 Pay the booking (user token) — makes it visible to the vendor
```http
POST /payments/verify
Authorization: Bearer {USER_TOKEN}
Content-Type: application/json
Accept: application/json

{ "booking_id": 55, "transaction_id": "0000" }
```
Success **200**:
```json
{
  "status": "success",
  "message": "Payment verified successfully",
  "booking": { "…": "…", "status": "pending" },
  "payment": {
    "amount_paid": "…", "commission": "…", "vendor_payout": "…",
    "currency": "SYP", "transaction_id": "0000-55", "status": "verified"
  },
  "debug_notifications": { "user": "…", "vendor": "…" }
}
```
Now the booking is `pending` and **visible to the vendor**. Amounts:
- **Appointment:** `amount_paid = product.price × deposit_percent/100` (deposit_percent default **20**).
- **Order:** `amount_paid = Σ(unit_price × quantity)` over the cart (full price).
- `commission = 15%` of amount_paid, `vendor_payout = 85%`.

Now switch to the **vendor token** and run vendor-side booking scenarios.

---

## 3. Vendor object shape (reference)

Returned by vendor auth + `GET /vendor/profile`. Serialized straight from the `Vendor` model.

Present fields: `id, first_name, last_name, phone, city, birth_date, business_name, vendor_type, booking_style, vendor_style, profile_image, cover_image, latitude, longitude, address, bio, response_time, rating_avg, is_approved, is_active, winding_down, is_accepting_bookings, rejection_reason, created_at, updated_at`.

Appended (computed) fields — **always present**:
- `profile_image_url` — full Supabase public URL or `null`.
- `cover_image_url` — full Supabase public URL or `null`.
- `account_status` — one of `active` / `winding_down` / `banned` (derived from `is_active` + `winding_down`).

**Hidden — assert ABSENT:** `fcm_token`, `remember_token`.

Casts: `is_approved/is_active/winding_down/is_accepting_bookings` are booleans; `rating_avg` is a 2-decimal string; `birth_date` is a date; `latitude/longitude` are 8-decimal strings.

---

## 4. Endpoint Reference

Every path below is under the base URL and requires `Authorization: Bearer {VENDOR_TOKEN}` + `Accept: application/json` unless marked public.

> **Multipart note (products, portfolio, profile images):** these are `multipart/form-data`. Arrays go as repeated keys: `images[]` (one part per file), `delete_image_ids[]`. `meta` (product) is an array — send `meta[key]=value` form fields. HTTP clients cannot send a body on a true `POST`-over-PUT, so **Laravel uses POST for updates** (no `_method` spoofing is required here because the routes are already declared as `POST`). Just POST multipart to the update path.

### 4.1 Profile

#### `GET /vendor/profile`
Returns the authenticated vendor.
**200:** `{ "status": "success", "vendor": { /* Vendor object, §3 */ } }`

#### `POST /vendor/profile`  (multipart if sending images, else JSON)
Update profile fields.
| field | type | required | rule |
|---|---|---|---|
| business_name | string | no | max:255 |
| bio | string | no | max:1000 |
| birth_date | date | no | date |
| latitude | number | no | between -90,90 |
| longitude | number | no | between -180,180 |
| address | string | no | max:255 |
| profile_image | file | no | image jpg/jpeg/png, max 2048 KB |
| cover_image | file | no | image jpg/jpeg/png, max 2048 KB |
| vendor_type | string | no | enum (see §1.2) — also recomputes booking_style |
| vendor_style | string | no | in:service_provider,seller |
| response_time | string | no | in:within_1h,within_2h,within_3h,within_24h |

**200:** `{ "status": "success", "vendor": { /* updated Vendor */ } }`
Setting `vendor_type` to a seller type flips `booking_style` to `order` (and vice-versa) — assert it.

#### `DELETE /vendor/profile/image`
**200:** `{ "status": "success", "message": "Profile image removed" }` (idempotent — succeeds even if none set).

#### `DELETE /vendor/profile/cover`
**200:** `{ "status": "success", "message": "Cover image removed" }`

#### `POST /vendor/availability/toggle`
Vendor sets self online/offline for NEW bookings.
| field | type | required | rule |
|---|---|---|---|
| is_accepting_bookings | boolean | no | boolean — omit to flip the current value |

**200:** `{ "status": "success", "is_accepting_bookings": true|false }`

#### `POST /vendor/fcm-token`
| field | type | required |
|---|---|---|
| fcm_token | string | yes |

**200:** `{ "status": "success" }` — response never echoes the token.

#### `POST /vendor/logout`
No body. Revokes ONLY the token used for this request (other devices stay logged in) and nulls this account's `fcm_token`. After calling it, reusing the same token must return **401**.
**200:** `{ "status": "success", "message": "Logged out" }`

### 4.2 Products

#### `GET /vendor/products`
Vendor's own products where `is_available = true`, each with `images`.
**200:** `{ "status": "success", "products": [ { /* product + images[] */ } ] }`
> Note: this list **excludes** unavailable/sold-out products (`is_available=false`). To see all (incl. sold-out), use `show` by id or the best-sellers/low-stock endpoints.

#### `POST /vendor/products`  (multipart — images required)
| field | type | required | rule |
|---|---|---|---|
| name | string | no | max:255, nullable |
| description | string | no | nullable |
| price | number | no | numeric, min:0, nullable |
| stock | integer | no | min:0, nullable |
| meta | array | no | array |
| images | file[] | **yes** | `images[]`, each image jpg/jpeg/png max 2048 |
| primary_image_index | integer | no | which uploaded image is primary (default 0) |

**200:** `{ "status": "success", "product": { …, "images": [ { "id", "vendor_product_id", "image_path", "is_primary", "image_url", … } ] } }`
Missing `images` → **422**.

#### `GET /vendor/products/best-sellers`
Top 5 of the vendor's products by count of bookings in status `pending|approved|completed`, with `images`, ordered by `bookings_count` desc.
**200:** `{ "status": "success", "products": [ { …, "bookings_count": N, "images": [...] } ] }`

#### `GET /vendor/products/low-stock?threshold=5`
Products with non-null `stock <= threshold` (default 5), ascending stock.
**200:** `{ "status": "success", "threshold": 5, "count": N, "products": [ … ] }`

#### `GET /vendor/products/{id}`
Own product only (else 404).
**200:** `{ "status": "success", "product": { …, "images": [...] } }`
Not yours / missing → **404**.

#### `POST /vendor/products/{id}`  (multipart or JSON)
Update fields + manage images.
| field | type | required | rule |
|---|---|---|---|
| name / description / price / stock / meta | — | no | same rules as create |
| images | file[] | no | new images to ADD (`images[]`) |
| primary_image_index | integer | no | index into the newly-uploaded images |
| delete_image_ids | integer[] | no | `delete_image_ids[]` — image ids to remove |

**200:** `{ "status": "success", "product": { …, "images": [...] } }`
Not yours → **404**.

#### `DELETE /vendor/products/{id}`
**200:** `{ "status": "success", "message": "Product deleted successfully" }`. Not yours → **404**.

**Product object fields:** `id, vendor_id, name, description, price (2-dec string|null), stock (int|null), meta (array), is_available (bool), deposit_percent, created_at, updated_at, images[]`. Each image: `id, vendor_product_id, image_path, is_primary (bool), image_url (full URL), created_at, updated_at`.

### 4.3 Portfolio

#### `GET /vendor/portfolio`
Own items, newest first, with images.
**200:** `{ "status": "success", "portfolio": [ { "id","vendor_id","title","description","created_at","updated_at","images":[…] } ] }`

#### `POST /vendor/portfolio`  (multipart — images required)
| field | type | required | rule |
|---|---|---|---|
| title | string | no | max:255, nullable |
| description | string | no | nullable |
| images | file[] | **yes** | `images[]` jpg/jpeg/png max 2048 |
| primary_image_index | integer | no | default 0 |

**200:** `{ "status": "success", "item": { …, "images": [ { …, "image_url" } ] } }`. Missing images → **422**.

#### `GET /vendor/portfolio/{id}`
Own item (else 404). **200:** `{ "status": "success", "item": { …, "images":[…] } }`

#### `POST /vendor/portfolio/{id}`  (multipart or JSON)
Update title/description, add `images[]`, remove `delete_image_ids[]`.
**200:** `{ "status": "success", "item": { …, "images":[…] } }`. Not yours → **404**.

#### `DELETE /vendor/portfolio/{id}`
**200:** `{ "status": "success", "message": "Portfolio item deleted" }`. Not yours → **404**.

Portfolio image object: `id, portfolio_item_id, image_path, is_primary, image_url, created_at, updated_at`.

### 4.4 Bookings (vendor-side)

Booking object (vendor context) includes: `id, user_id, vendor_id, vendor_product_id, booking_style, status, event_date, old_event_date, event_type, event_location, duration_hours, details (array|null), delivery_date, delivery_address, notes, price_agreed, refund_amount, refund_paid_at, created_at, updated_at`, plus eager-loaded relations noted per endpoint. `event_date`/`delivery_date` are datetime; `details` is an array.

#### `GET /vendor/bookings`
ALL of this vendor's bookings, newest first. **Includes `awaiting_payment`?** — index does NOT filter status, so unpaid drafts CAN appear here (it filters only by `vendor_id`). The *filtered* dashboard endpoints below exclude drafts. Eager loads: `user:{id,first_name,last_name,profile_image}`, `product`, `items.product:{id,name,price}`.
**200:** `{ "status": "success", "bookings": [ { …, "user": {…}, "items": [ { …, "product": {…} } ] } ] }`

#### `GET /vendor/bookings/recent-requests`
Latest 10 bookings with `status = pending` (paid, awaiting vendor action). Loads `user:{id,first_name,last_name}`, `product:{id,name,price}`.
**200:** `{ "status": "success", "bookings": [ … ] }`

#### `GET /vendor/bookings/recent-orders`
Latest 10 where `booking_style='order'` AND `status != 'awaiting_payment'`. Loads `user`, `product`, `items.product`.
**200:** `{ "status": "success", "orders": [ … ] }`  ← key is **`orders`**, not `bookings`.

#### `GET /vendor/bookings/upcoming-events`
Appointment vendors only. `status='approved'` AND `event_date >= now()`, ascending. For an order vendor returns:
**200:** `{ "status": "success", "bookings": [], "note": "Only appointment vendors have upcoming events" }`
Appointment vendor **200:** `{ "status": "success", "bookings": [ … ] }`.

#### `GET /vendor/bookings/{id}`
Full detail for own booking. Loads `user:{id,first_name,last_name,phone,profile_image}`, `product.images`, `items.product:{id,name,price}`, `payment`.
**200:** `{ "status": "success", "booking": { …, "user": {…}, "payment": {…}, "items": […] } }`. Not yours → **404**.

#### `POST /vendor/bookings/{id}/approve`
Only a **`pending`** booking (else 404). Atomically decrements stock for every item and credits the vendor wallet (escrow/pending until completed).
**200:** `{ "status": "success", "booking": { …, "status": "approved", "user": {id,first_name,last_name,profile_image}, … } }`
Out of stock (any item can't be fully covered) → **409** `{ "status": "error", "message": "This product is out of stock — cannot approve" }`.
Not pending / not yours → **404**.

#### `POST /vendor/bookings/{id}/decline`
Only a `pending` booking (else 404). No stock change (stock is only taken at approve).
**200:** `{ "status": "success", "booking": { …, "status": "declined", … } }`. (Declining nulls `event_date` and moves it to `old_event_date`, freeing the calendar slot.)

#### `POST /vendor/bookings/{id}/complete`
Only an `approved` booking (else 404). **Cannot complete before the service date:** if the service date (`delivery_date` for orders, `event_date` for appointments) is in the future → **422** `{ "status": "error", "message": "You can only mark this completed on or after the event/delivery date" }`. On/after the date (or when the date is null) → **200** `{ "status": "success", "booking": { …, "status": "completed", … } }`. Completing clears the wallet escrow → funds become withdrawable.

### 4.5 Availability calendar (appointment vendors)

#### `GET /vendor/availability`
**200:**
```json
{
  "status": "success",
  "booked": ["2026-09-15", "…"],
  "blocked": [ { "id": 3, "date": "2026-09-18", "reason": "day off" } ]
}
```
`booked` = distinct `YYYY-MM-DD` dates that have a booking in `awaiting_payment|pending|approved`. `blocked` = manually blocked rows.

#### `POST /vendor/blocked-dates`
| field | type | required | rule |
|---|---|---|---|
| date | date | yes | `after_or_equal:today` |
| reason | string | no | max:255, nullable |

**200:** `{ "status": "success", "blocked": { "id": N, "date": "YYYY-MM-DD", "reason": "…"|null } }`
Date already has a booking → **409** `{ "status": "error", "message": "This date already has a booking" }`.
Blocking an already-blocked date is idempotent → **200** (returns the existing row, no duplicate).
`date` in the past / missing → **422**.

#### `DELETE /vendor/blocked-dates/{date}`
`{date}` is a `YYYY-MM-DD` path segment.
**200:** `{ "status": "success", "message": "Date unblocked" }`
Date wasn't blocked → **404** `{ "status": "error", "message": "This date is not blocked" }`.

### 4.6 Reviews (vendor-side)

#### `GET /vendor/reviews`
**200:** `{ "status": "success", "total_reviews": N, "rating_avg": "…", "reviews": [ { "id","booking_id","user_id","vendor_id","rating","comment","created_at","updated_at","user": {"id","first_name","last_name"} } ] }`

#### `GET /vendor/reviews/summary`
**200:**
```json
{
  "status": "success",
  "rating_avg": 4.5,
  "total_reviews": 10,
  "star_breakdown": {
    "5": { "count": 6, "percent": 60.0 },
    "4": { "count": 2, "percent": 20.0 },
    "3": { "count": 1, "percent": 10.0 },
    "2": { "count": 1, "percent": 10.0 },
    "1": { "count": 0, "percent": 0 }
  },
  "positive_percent": 80.0,
  "trend": 0.0
}
```
`positive_percent` = share of 4- and 5-star reviews. `trend` = this-month avg vs last-month avg (percent, can be negative; 0 when no last-month data).

### 4.7 Stats & earnings

#### `GET /vendor/stats`
**200:**
```json
{
  "status": "success",
  "stats": {
    "bookings": { "total": N, "pending": N, "approved": N, "completed": N, "declined": N, "cancelled": N },
    "earnings": 0.0,
    "rating_avg": 0.0,
    "total_reviews": 0
  }
}
```
`earnings` = sum of `vendor_payout` across verified payments for this vendor's bookings (all-time gross payout, not net-of-refunds).

#### `GET /vendor/earnings`
**200:** `{ "status": "success", "earnings": { "this_month": 0.0, "last_month": 0.0, "growth": 0.0 } }`
`growth` = MoM % (can be negative; 100.0 when last month was 0 and this month > 0).

#### `GET /vendor/response-time`
Auto-computed average of (vendor's `responded_at` − booking's payment `created_at`) over bookings the vendor approved/declined that have a verified payment. Returned as a moderated range, never an exact figure.
- **200 (has data):**
```json
{ "status": "success", "response_time": { "is_new": false, "label": "1-2 hours", "average_minutes": 113, "based_on": 2 } }
```
- **200 (new vendor — never responded to any booking):**
```json
{ "status": "success", "response_time": { "is_new": true, "label": null } }
```
`label` is one of: `under 30 minutes` · `30-60 minutes` · `1-2 hours` · `2-6 hours` · `6-24 hours` · `over a day`. Assert: a brand-new vendor returns `is_new:true`; after a vendor approves/declines at least one paid booking, `is_new:false` and `based_on >= 1`.

### 4.8 Wallet

#### `GET /vendor/wallet`
**200:**
```json
{
  "status": "success",
  "wallet": {
    "available_balance": 0.0,
    "pending_clearance": 0.0,
    "total_earned": 0.0,
    "currency": "SYP",
    "pending_note": "Earnings stay pending until the booking is completed (service delivered), then become withdrawable."
  },
  "transactions": [
    { "id","vendor_id","booking_id","type","amount","paid_at","created_at","updated_at",
      "booking": { "id","user_id","vendor_product_id","booking_style","status",
                   "user": {"id","first_name","last_name"},
                   "product": {"id","name","price"} } }
  ]
}
```
`type` ∈ `credit` (>0), `refund` (<0), `withdrawal` (<0). Balance rules — see §6.

#### `POST /vendor/withdraw`
Withdraws the full available balance (creates a negative `withdrawal` transaction, resets available to 0).
Available > 0 → **200:**
```json
{ "status": "success", "message": "Withdrawal successful (real payout pending ShamCash payout API)",
  "amount_withdrawn": 850.0, "available_balance": 0.0, "withdrawal": { /* transaction */ } }
```
Available <= 0 → **422** `{ "status": "error", "message": "No balance available to withdraw yet" }`.

### 4.9 Notifications

#### `GET /vendor/notifications`
**200:** `{ "status": "success", "unread_count": N, "notifications": [ { "id","notifiable_type":"vendor","notifiable_id","title","body","data","read_at","created_at","updated_at" } ] }`

#### `POST /vendor/notifications/read-all`
**200:** `{ "status": "success", "message": "All notifications marked as read" }`

#### `POST /vendor/notifications/{id}/read`
**200:** `{ "status": "success", "notification": { …, "read_at": "…" } }`
Not the caller's / missing → **404** `{ "status": "error", "message": "Notification not found" }`.

---

## 5. Rules & invariants to assert (turn each into an assertion)

1. **Draft hidden from vendor until paid.** A booking created by a user is `awaiting_payment`; it does not surface in `recent-requests` (which is `status=pending` only) or `recent-orders` (which excludes `awaiting_payment`). `GET /vendor/bookings` filters only by vendor_id, so a draft can appear there — but it becomes actionable only after payment moves it to `pending`.
2. **Payment gates visibility/action.** `approve`/`decline` require `status=pending`; a booking is `pending` only after `POST /payments/verify` succeeds. Calling approve/decline on an `awaiting_payment` booking → **404**.
3. **Vendor booking responses carry a `user` object** with `{id, first_name, last_name, profile_image}` (approve/decline/complete/index) and `items[]` with per-item `product`. `show` additionally includes `phone` in `user` and a `payment` object.
4. **Approve is oversell-safe.** Stock is decremented atomically at approve; if any item's stock can't cover its quantity → **409** "out of stock — cannot approve", and the whole approval rolls back. A product that hits stock 0 auto-sets `is_available=false`.
5. **Complete is time-gated.** Completing before the `event_date` (appointment) / `delivery_date` (order) → **422**. Only on/after that date (or when null) does it succeed. Prevents early cash-out of escrow.
6. **Escrow: money is pending until completed.** On approve, a `credit` wallet transaction is created but counts as `pending_clearance` while the booking is `pending`/`approved`. It moves to `available_balance` only when the booking is `completed` or `cancelled`. Assert wallet numbers before/after complete.
7. **Commission 15% / payout 85%.** For any verified payment: `commission = round(amount_paid × 0.15, 2)`, `vendor_payout = round(amount_paid × 0.85, 2)`.
8. **Deposit rule.** Appointment payment = `price × deposit_percent/100` (`deposit_percent` default **20**, not vendor-editable). Order payment = full cart total `Σ(unit_price × quantity)`.
9. **`toggleAvailability` flips `is_accepting_bookings`.** With no body it toggles; with `{is_accepting_bookings:false}` it sets. An **offline vendor** (`is_accepting_bookings=false`) causes a user `POST /bookings` against them to return **403** "This vendor is currently unavailable". (Offline vendors STILL appear in public browse — the `is_accepting_bookings` flag is returned so the app shows a "not accepting bookings" banner and disables the booking button.)
10. **Booking requires approved + active + accepting vendor.** `POST /bookings` returns **403** if the vendor is not `is_approved`, not `is_active`, or not `is_accepting_bookings`.
11. **Manual block blocks user booking.** After `POST /vendor/blocked-dates` for date D, a user appointment booking with `event_date` on D → **409** "This date is not available".
12. **Block rejects a booked date.** `POST /vendor/blocked-dates` on a date that already has an active booking → **409** "This date already has a booking".
13. **Double-booking prevented (appointment).** Two bookings for the same vendor on the same calendar day are rejected: the second user `POST /bookings` → **409** "This date is already booked" (enforced by the conflict check AND a `(vendor_id, event_day)` unique index).
14. **Cancel/decline frees the date.** On cancel/decline, `event_date` is nulled (moved to `old_event_date`), so that day becomes bookable/blockable again and drops out of `booked`.
15. **Ownership isolation.** Every vendor `{id}` route (`products/{id}`, `portfolio/{id}`, `bookings/{id}`, notifications) is scoped to the authenticated vendor; another vendor's id → **404**, never another vendor's data.
16. **`getVendorProducts` hides unavailable products.** `GET /vendor/products` only returns `is_available=true`; a sold-out product disappears from it (still reachable via `GET /vendor/products/{id}`).
17. **No secret fields leak.** `fcm_token` and `remember_token` never appear on any vendor/user object in any response.
18. **Withdraw needs cleared funds.** `POST /vendor/withdraw` → **422** when `available_balance <= 0` (e.g. only pending escrow exists). It succeeds only after at least one booking is `completed`/`cancelled`.
19. **Payment idempotency.** Paying an already-paid booking → **409** "This booking is already paid". Paying a booking that isn't `awaiting_payment` (already pending) → **404** (the query filters `status=awaiting_payment`).

---

## 6. Wallet balance semantics (assert with numbers)

Balances are derived from wallet transactions grouped by booking:
- For each booking, `net = Σ(credit + refund)` (refunds are negative).
- If the booking's status is `completed` or `cancelled` → `net` counts toward **cleared**.
- Otherwise (`pending`/`approved`) → `net` counts toward **pending**.
- `withdrawals` are negative and reduce **available** immediately.

Reported keys:
- `available_balance = round(cleared + withdrawn, 2)` (withdrawn is negative).
- `pending_clearance = round(pending, 2)`.
- `total_earned = round(cleared, 2)`.

**Assertion path:** pay a booking → approve it → `available_balance` still 0, `pending_clearance` = `vendor_payout` → complete it (on/after the date) → `pending_clearance` drops to 0, `available_balance` = `vendor_payout` → withdraw → `available_balance` 0, a negative `withdrawal` transaction appears.

---

## 7. Ready-to-run Test Scenarios

Run in order. Prereq for booking scenarios (S4+): an **approved, active** vendor (see §1.3) and a user token (§2.1). Use the **appointment** vendor unless a scenario says order.

**S1 — Vendor auth bootstrap.**
Goal: obtain a vendor token. Steps: `POST /vendor/send-otp` → read `otp` → `POST /vendor/verify-otp` → if `new_vendor`, `POST /vendor/complete-registration`. Assert: 200s; token present; `vendor.booking_style` matches §1.2 for the chosen `vendor_type`; `fcm_token`/`remember_token` absent; `account_status` present.

**S2 — Profile round-trip.**
`GET /vendor/profile` → `POST /vendor/profile` with `{business_name, bio, response_time:"within_2h"}` → `GET /vendor/profile`. Assert updated fields persisted; `response_time` accepts only the enum (send `"within_5h"` → 422). Then `POST /vendor/profile` with `{vendor_type:"cakes"}` and assert `booking_style` flips to `order` (revert afterward if you want to keep testing appointment flows).

**S3 — Products CRUD + inventory.**
`POST /vendor/products` (multipart, one `images[]`, `name`,`price`,`stock:3`) → capture id → `GET /vendor/products/{id}` → `GET /vendor/products` (present) → `POST /vendor/products/{id}` update price → `GET /vendor/products/low-stock?threshold=5` (product listed, since stock 3<=5) → `DELETE /vendor/products/{id}`. Negative: `POST /vendor/products` with no images → 422; `GET /vendor/products/{someoneElsesId}` → 404.

**S4 — Portfolio CRUD.**
`POST /vendor/portfolio` (multipart images[]) → `GET /vendor/portfolio` → `GET /vendor/portfolio/{id}` → `POST /vendor/portfolio/{id}` (change title, add an image) → `DELETE /vendor/portfolio/{id}`. Negative: create with no images → 422.

**S5 — Availability toggle & offline-booking guard.**
`POST /vendor/availability/toggle` `{is_accepting_bookings:false}` → assert `is_accepting_bookings:false`. As a user, `POST /bookings` against this vendor → **403** "This vendor is currently unavailable". Toggle back `true` (or empty body to flip) → assert `true`.

**S6 — Appointment happy path (full money lifecycle).**
Prereq: approved appointment vendor + a product. 
1. User `POST /bookings` (appointment shape, `event_date` future) → 200, capture `booking.id`, status `awaiting_payment`.
2. Vendor `GET /vendor/bookings/recent-requests` → booking NOT present (still unpaid).
3. User `POST /payments/verify` `{booking_id, transaction_id:"0000"}` → 200, booking `pending`; assert `amount_paid = price×0.20`, `commission=15%`, `vendor_payout=85%`.
4. Vendor `GET /vendor/bookings/recent-requests` → booking now present with `user` object.
5. Vendor `GET /vendor/wallet` → `available_balance` 0, `pending_clearance` 0 (not approved yet).
6. Vendor `POST /vendor/bookings/{id}/approve` → 200, status `approved`, `user` object present.
7. Vendor `GET /vendor/wallet` → `pending_clearance = vendor_payout`, `available_balance` 0.
8. Vendor `POST /vendor/bookings/{id}/complete` **before** `event_date` → **422** (time gate).
9. (If you can use a past/near event_date, or the auto-complete has run) complete on/after date → 200 `completed`; wallet `available_balance = vendor_payout`, `pending_clearance` 0.
10. Vendor `POST /vendor/withdraw` → 200 `amount_withdrawn = vendor_payout`; re-check wallet → available 0, a `withdrawal` transaction present. Second `POST /vendor/withdraw` → 422.

**S7 — Approve twice / decline after approve.**
After S6 step 6, `POST /vendor/bookings/{id}/approve` again → **404** (no longer `pending`). `POST /vendor/bookings/{id}/decline` on the approved booking → **404**.

**S8 — Order happy path + stock decrement + oversell.**
Prereq: approved **order** (e.g. cakes) vendor + product with `stock:1`.
1. User `POST /bookings` order shape `items:[{vendor_product_id, quantity:1}]` → capture id.
2. User `POST /payments/verify` "0000" → assert `amount_paid = unit_price×1` (full price, no deposit).
3. Vendor `POST /vendor/bookings/{id}/approve` → 200; product stock now 0 and `is_available=false` (verify via `GET /vendor/products/{id}`; it disappears from `GET /vendor/products`).
4. Create a **second** booking for the same product: user `POST /bookings` → **409** "not available" (auto-hidden at stock 0). If you instead requested `quantity` above stock at booking time → **409** "Only N left in stock".

**S9 — Wrong booking shape (negative validation).**
Against an **appointment** vendor, user `POST /bookings` sending order fields (`items:[…]`) → **422** (`items` prohibited). Against an **order** vendor, user `POST /bookings` sending `event_date` → **422** (`event_date` prohibited). Appointment with no `event_date` → **422** (`event_date` required).

**S10 — Pay twice (idempotency).**
After a successful `POST /payments/verify`, call it again with the same `booking_id` → **409** "already paid" OR **404** if status already moved off `awaiting_payment` (accept either; note which).

**S11 — Calendar: block, booked, conflicts.**
1. Vendor `POST /vendor/blocked-dates` `{date: <future D>, reason:"day off"}` → 200.
2. User `POST /bookings` appointment with `event_date` on D → **409** "This date is not available".
3. Vendor `GET /vendor/availability` → D appears in `blocked`.
4. Vendor `DELETE /vendor/blocked-dates/{D}` → 200. Delete again → **404**.
5. Create+pay a booking on date E. Vendor `POST /vendor/blocked-dates` `{date:E}` → **409** "already has a booking". `GET /vendor/availability` → E in `booked`.
6. Second user booking on E (double-book) → **409** "already booked".

**S12 — Reviews & summary.**
Have a user create+pay+ (vendor) approve a booking, then user `POST /reviews` `{booking_id, rating:5}`. Vendor `GET /vendor/reviews` → review present with `user`; `total_reviews` incremented; `rating_avg` updated. `GET /vendor/reviews/summary` → `star_breakdown["5"].count>=1`, `positive_percent` reflects it.

**S13 — Stats & earnings.**
`GET /vendor/stats` → `stats.bookings` counts match what you created; `earnings` = sum of verified `vendor_payout`. `GET /vendor/earnings` → `this_month`/`last_month`/`growth` present and numeric.

**S14 — Notifications.**
After paying/approving (which generate notifications), vendor `GET /vendor/notifications` → `unread_count>=1`; each notification `notifiable_type="vendor"`. `POST /vendor/notifications/{id}/read` → `read_at` set. `POST /vendor/notifications/read-all` → 200; re-GET → `unread_count:0`. Reading a non-existent id → 404.

**S15 — Auth negatives.**
Call any `/vendor/*` route with no token (but WITH `Accept: application/json`) → **401** `{"message":"Unauthenticated."}`. Call a `/vendor/*` route with a USER token → **401**. Confirm that OMITTING `Accept: application/json` on a 401 case yields HTML/redirect (demonstrates why the header is mandatory) — then always send it.

**S16 — Logout revokes the token.**
With a valid vendor token, `POST /vendor/logout` → 200 `{"message":"Logged out"}`. Then reuse the SAME token on any `/vendor/*` route → **401** (token was revoked). Log in again (send-otp → verify-otp) to get a fresh token for further tests.

**S17 — Response time (auto-computed).**
On a brand-new vendor (no bookings answered), `GET /vendor/response-time` → `response_time.is_new:true`, `label:null`. Then run S6 (create+pay a booking) and have the vendor approve or decline it; call again → `is_new:false`, `label` is one of the six range strings, `average_minutes` a positive int, `based_on>=1`.

**S18 — Localization (Accept-Language).**
Take any request that returns a `message` or validation `errors` (e.g. `POST /vendor/send-otp` with no `phone` → 422). Call it once with `Accept-Language: ar` and once with `Accept-Language: en`. Assert: same status code and same JSON shape, but the `message`/`errors` text is Arabic in the first and English in the second. Also assert that enum VALUES in any response (`vendor_type`, `status`, `booking_style`) stay the English key in BOTH languages (they are never translated by the API — the app maps them to Arabic labels).

---

## 8. Response field glossary

| field / value | meaning |
|---|---|
| **booking `status`** | `awaiting_payment` (unpaid draft, hidden from vendor) → `pending` (paid, awaiting vendor) → `approved` (vendor accepted, stock taken, escrow credited) → `completed` (service delivered, escrow cleared). Side states: `declined` (vendor rejected a pending), `cancelled` (user/admin cancelled). |
| **`booking_style`** | `appointment` (service vendors: one package, event_date/location/duration) or `order` (seller vendors: items[] cart, delivery_date/address). Server-derived from `vendor_type`. |
| **`account_status`** | `active` (`is_active=true`), `winding_down` (banned but finishing existing bookings), `banned` (`is_active=false` & not winding down). |
| **`is_approved`** | KYC approved by admin. New vendors are `false` and cannot receive bookings. |
| **`is_active`** | Admin ban switch. `false` = banned (blocks login unless winding_down). NOT vendor-controlled. |
| **`is_accepting_bookings`** | Vendor's own online/offline switch. `false` = rejects new bookings (403) but still appears in browse (with the flag) and stays logged in. |
| **`response_time`** | Badge enum: `within_1h`, `within_2h`, `within_3h`, `within_24h`. |
| **wallet `available_balance`** | Cleared (completed/cancelled) net earnings minus withdrawals — withdrawable now. |
| **wallet `pending_clearance`** | Escrowed net earnings for `pending`/`approved` bookings — not yet withdrawable. |
| **wallet `total_earned`** | Lifetime cleared earnings. |
| **wallet transaction `type`** | `credit` (payout on approve, +), `refund` (partial claw-back, −), `withdrawal` (−). |
| **`profile_image_url` / `cover_image_url` / `image_url`** | Full public Supabase URLs computed from the stored path; `null` when no image. The raw `*_image`/`image_path` are the storage keys. |
| **`deposit_percent`** | Fixed platform deposit rate for appointments (default 20). Not vendor-editable. |
| **`commission` / `vendor_payout`** | 15% platform fee / 85% vendor share of `amount_paid`. |
| **`currency`** | Always `SYP`. |
| **`event_date` vs `delivery_date`** | Appointment bookings use `event_date`; order bookings use `delivery_date`. `complete` time-gates on whichever applies. |
| **`old_event_date`** | Set when a booking is cancelled/declined (its former event_date), which frees the calendar slot. |

---

## 9. How to use this file

1. Run **§1 Auth Bootstrap** first; keep the vendor token(s) for the whole run. If you need booking data, also get a user token (§2) and ensure your vendor is approved (§1.3) — if you cannot approve, run booking scenarios against a pre-approved vendor and say so in your report.
2. Execute the **§7 scenarios in order**. For each call, assert the HTTP status code AND the JSON shape against **§4** and the invariants in **§5/§6**.
3. Always send `Accept: application/json`. Never expect `fcm_token`/`remember_token`.
4. **Report** every deviation: endpoint, request sent, expected status+shape (per this file), actual status+body. A passing run is: all documented status codes match, all documented fields present with the right types, and all §5 invariants hold.
