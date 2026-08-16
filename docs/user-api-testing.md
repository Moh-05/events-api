# Haflati Customer (User) API — Machine-Executable Integration Test Spec

> **Reading agent: this file is your instruction set.** You are verifying that the Flutter **customer app**'s linking to the backend is correct by running real HTTP calls against the live production API. Execute the **Setup** (§1–§2) first, hold the tokens, then run the **Ready-to-run Test Scenarios** (§10) top to bottom. For every call, assert the HTTP status code AND the JSON shape against the "Endpoint Reference" (§3–§8). Report any mismatch (wrong status, missing field, extra field, wrong type) with the endpoint, what you sent, what you expected, and what you got. Do not assume — verify against what is written here, which was derived directly from the controller source.

This spec covers **customer/user endpoints only** (user auth, profile, public discovery, bookings, payments, reviews, favorites, chat, notifications, support tickets, content reports). Vendor and admin endpoints appear only as much as needed to set up test data.

---

## 0. Global conventions

- **Base URL:** `https://events-api-production-138b.up.railway.app/api`
- **Auth header:** `Authorization: Bearer {USER_TOKEN}` on every protected call. User tokens use guard `auth:sanctum`.
- **ALWAYS send `Accept: application/json`** on every request. Without it, auth/validation failures return an HTML redirect or a 500 instead of clean JSON — you will misread the result. This is mandatory.
- **Content types:**
  - JSON bodies: `Content-Type: application/json`.
  - Profile-image upload MUST be `multipart/form-data` (field `profile_image`, one file).
- **Production test helpers (live right now):**
  1. **OTP is returned in the send-otp response body** in the `otp` field (integer). No WhatsApp needed — read it straight from the JSON.
  2. **Payment bypass:** send `transaction_id: "0000"` to `POST /payments/verify`. It skips ShamCash and is reusable across bookings. This is how you fund a booking so it becomes `pending`.
- **Guards are not interchangeable.** A user token cannot call a `/vendor/*` route and vice-versa (→ `401`). Both are Sanctum plain-text tokens.
- **Banned account** hitting a protected route → **403** `{ "status": "error", "message": "Your account has been suspended. Please contact support." }` (the `active` middleware; a `winding_down` account still passes).
- **Standard error envelopes:**
  - Laravel validation error → **422** `{ "message": "...", "errors": { "field": ["..."] } }`.
  - Missing/invalid token (with `Accept: application/json`) → **401** `{ "message": "Unauthenticated." }`.
  - `findOrFail()`/`firstOrFail()` miss (wrong id, or a resource that isn't yours) → **404** `{ "message": "..." }` (Laravel `ModelNotFoundException`; message key varies).
  - Business-rule rejections use `{ "status": "error", "message": "..." }` with a specific status code (403/404/409/422) — documented per endpoint.

### Secret fields — assert ABSENT

The `User` model hides **both** `fcm_token` and `remember_token`. Assert **both are ABSENT** on every user object — including the full user object returned by user auth (`login`/`new_user`/registration) and `GET /profile` / `POST /profile`. This matches the vendor behavior (the `Vendor` model also hides `fcm_token`). If either token ever appears in a response, that is a bug — report it.

### Localization — Arabic / English

- **Send `Accept-Language: ar` or `Accept-Language: en` on EVERY request.** Missing or anything other than `en` → the API defaults to **Arabic**.
- Human-facing text (success `message`, error `message`, validation `errors`, push title/body) switches language by this header.
- **Notifications follow the recipient's stored language, not the request header** — the backend remembers each account's language from `Accept-Language` on their authenticated requests (`users.language`, default `ar`). No separate "set language" endpoint.
- **The app translates enum VALUES itself, NOT the API.** `vendor_type` (`photographer`, `weddingHall`, `dj`, `makeupArtist`, `flowers`, `gifts`, `dresses`, `accessories`, `candles`, `cakes`), `booking_style` (`appointment`/`order`), booking `status` (`awaiting_payment`, `pending`, `approved`, `declined`, `completed`, `cancelled`), `account_status` — the API always returns the English enum KEY; the app maps it to an Arabic label. Never expect the API to return an Arabic enum value.
- **User-generated content is never translated:** `business_name`, `bio`, product `name`/`description`, `notes`, `address` come back exactly as typed (usually Arabic).

**Assertion:** repeat any message-returning call with `Accept-Language: ar` then `en`; assert the `message`/`errors` text differs (Arabic vs English) for the SAME status code and JSON shape.

---

## 1. Auth Bootstrap (do this first)

Users authenticate by phone + OTP. A phone that already exists logs in; a new phone must complete registration. **User auth routes have NO `/vendor` prefix.**

**Step 1 — Send OTP**
```http
POST /send-otp
Content-Type: application/json
Accept: application/json

{ "phone": "+963900000009" }
```
`phone` rule: `required|string|min:7|max:15|regex:/^\+?[0-9]+$/`.

Response `200`:
```json
{ "message": "OTP sent", "otp": 123456, "ultramsg_status": 200, "ultramsg_response": {} }
```
**Extract `otp`** (integer). `ultramsg_*` may show a gateway error in test — ignore; the OTP is cached server-side and valid.

**Step 2 — Verify OTP**
```http
POST /verify-otp
Content-Type: application/json
Accept: application/json

{ "phone": "+963900000009", "otp": 123456 }
```
Rules: `phone` same as above; `otp` = `required|integer|digits:6`.

Two outcomes:
- Existing user → **200** `{ "status": "login", "token": "…plainTextToken…", "user": { /* User object, §3 */ } }`. **Extract `token`.** Skip Step 3.
- New phone → **200** `{ "status": "new_user", "registration_token": "…64 chars…" }`. **Extract `registration_token`**, go to Step 3.
- Wrong/expired OTP → **400** `{ "message": "Invalid OTP" }`.

> Note: there is **no `suspended` branch** on the user verify-otp (unlike the vendor side). A banned user still receives a `login` token here; the ban is enforced later by the `active` middleware on protected routes (→ 403).

**Step 3 — Complete registration (new user only)**
```http
POST /complete-registration
Content-Type: application/json
Accept: application/json

{
  "registration_token": "…64 chars…",
  "first_name": "Test",
  "last_name": "Customer",
  "city": "Damascus",
  "birth_date": "1996-04-10"
}
```
Rules:
| field | rule |
|---|---|
| `registration_token` | required, string, size:64 |
| `first_name` | required, string, 2–50, letters+spaces only (`/^[\p{L}\s]+$/u`) |
| `last_name` | required, string, 2–50, letters+spaces only |
| `city` | required, string, 2–100 |
| `birth_date` | required, date, `before:today`, `after:1900-01-01` |

**Note:** the user registration body has **no `vendor_type`/`vendor_style`** fields.

Response **200** — note there is **no `status` key** here:
```json
{ "token": "…plainTextToken…", "user": { /* User object, §3 */ } }
```
**Extract `token`.** Expired/invalid registration_token → **403** `{ "message": "Expired" }`.

---

## 2. Cross-role setup — you NEED an approved vendor with a product

Booking, payment, review, favorites-with-real-data, and chat scenarios all require a **real approved vendor that has a product**. Production DB may be empty, and **there is NO customer self-serve way to approve a vendor.** Two options:

**Option A — approve a test vendor via admin (preferred if you have admin creds).**
1. Register a vendor via the vendor auth flow (`POST /vendor/send-otp` → `/vendor/verify-otp` → `/vendor/complete-registration`). Pick `vendor_type:"photographer"` for an **appointment** vendor and/or `vendor_type:"cakes"` for an **order** vendor. Capture the vendor `id` and vendor token.
2. As the vendor, create a product: `POST /vendor/products` (multipart, `images[]` required, plus `name`, `price`, and `stock` for order vendors). Capture `product.id` → this is your `vendor_product_id`.
3. Admin approve:
   ```http
   POST /admin/login      { "email": "admin@haflati.com", "password": "0000" }   → extract admin token
   POST /admin/vendors/{vendorId}/approve   (Authorization: Bearer {ADMIN_TOKEN})
   ```
4. The vendor is now `is_approved=true, is_active=true, is_accepting_bookings=true` → bookable.

**Option B — use an already-approved vendor.** Discover one via `GET /vendors` (§4.1) — every vendor returned there is already approved+active. Capture its `id`, then get a `vendor_product_id` from `GET /vendors/{id}/products/search` (§4.4) or `GET /products` (§4.3). You can't act as that vendor (approve bookings, chat) unless you can OTP into its phone — see the prerequisites below.

**Prerequisites for specific scenarios — flag these in your report if unmet:**
- **Reviews** (`POST /reviews`): the booking must be `approved` OR `completed` (the vendor must have approved the paid booking). Needs vendor-side access.
- **Chat** (`POST /conversations/vendor/{id}`): returns **403** unless the vendor has **APPROVED** a paid booking with you (status `approved`/`completed` + verified payment). Needs vendor-side access.
- **Complete/wallet-clearing** scenarios are vendor-side and out of scope here.

If you cannot approve a vendor and cannot act as a pre-approved one, run everything up to and including payment (`awaiting_payment` → `pending`), and note that review/chat could not be exercised.

---

## 3. User object shape (reference)

Returned by user auth + `GET /profile` + `POST /profile`. Serialized straight from the `User` model.

Present fields: `id, first_name, last_name, phone, city, birth_date, profile_image, latitude, longitude, address, language, is_active, created_at, updated_at`.
Appended (always present): `profile_image_url` — full Supabase public URL or `null`.
**Hidden — assert ABSENT:** `fcm_token`, `remember_token`.
Casts: `birth_date` = date; `latitude`/`longitude` = 8-decimal strings; `is_active` = boolean.

---

## 4. Endpoint Reference — PUBLIC discovery (no auth)

These four groups need **no token** (`Accept: application/json` still recommended). All are leak-safe: they only surface **approved + active** vendors, and products additionally require `is_available=1 AND is_hidden=0`.

### 4.1 `GET /vendors` — browse vendors (Home / Explore / Filters)
Query params (all `sometimes` unless noted):
| param | rule | effect |
|---|---|---|
| `vendor_type` | string | exact `vendor_type` match |
| `city` | string | `LIKE %city%` |
| `min_rating` | numeric 0–5 | `rating_avg >=` |
| `min_price` / `max_price` | numeric ≥0 | filter on the vendor's cheapest product (`products_min_price`) |
| `search` | string | `business_name LIKE %search%` |
| `sort` | in:`top_rated,most_booked,newest,nearest` | default `newest` |
| `lat` / `lng` | numeric | **required_if `sort=nearest`**; adds `distance_km`, sorts closest-first |

**200** — paginated:
```json
{
  "status": "success",
  "vendors": {
    "current_page": 1,
    "data": [
      {
        "id": 3, "business_name": "…", "vendor_type": "photographer",
        "city": "Damascus", "rating_avg": "4.50",
        "profile_image": "…|null", "latitude": "…|null", "longitude": "…|null",
        "is_accepting_bookings": true,
        "products_min_price": "150.00|null",
        "bookings_count": 12,
        "profile_image_url": "…|null", "account_status": "active",
        "distance_km": 4.21
      }
    ],
    "per_page": 15, "total": 1, "last_page": 1, "…": "…"
  }
}
```
Notes: each card is a **column-limited** vendor (card fields only), so `fcm_token`/`remember_token`/`bio` are absent. `distance_km` appears **only** when `sort=nearest`. `products_min_price` is `null` for a vendor with no products. Offline vendors (`is_accepting_bookings=false`) **still appear** — the app shows a "not accepting bookings" banner.
`sort=nearest` without `lat`/`lng` → **422**.

### 4.2 `GET /vendors/{id}` — vendor detail header
Only approved+active (else 404). Column-limited + counts.
**200:**
```json
{
  "status": "success",
  "vendor": {
    "id": 3, "business_name": "…", "vendor_type": "photographer",
    "vendor_style": "…", "booking_style": "appointment",
    "city": "…", "bio": "…", "rating_avg": "4.50",
    "profile_image": "…|null", "cover_image": "…|null",
    "is_accepting_bookings": true, "latitude": "…|null", "longitude": "…|null",
    "reviews_count": 8, "events_hosted_count": 5,
    "profile_image_url": "…|null", "cover_image_url": "…|null", "account_status": "active"
  }
}
```
`events_hosted_count` = count of `completed` bookings. Unapproved/banned/missing id → **404**.

### 4.3 `GET /products` — item discovery (Home rails / Filters / search)
One endpoint powers every product/service rail. Query params (`sometimes`):
| param | rule | effect |
|---|---|---|
| `type` | in:`service,product` | `service`→vendors with `booking_style=appointment`; `product`→`order` |
| `category` | string | a specific `vendor_type` |
| `on_offer` | boolean | only live discounts (`discount_percent` set AND `discount_ends_at > now`) |
| `min_price` / `max_price` | numeric ≥0 | on the item's own `price` |
| `min_rating` | numeric 0–5 | the vendor's `rating_avg >=` |
| `search` | string | item `name LIKE %search%` |
| `sort` | in:`newest,top_rated,price_low,price_high,most_booked,nearest` | default `newest` |
| `lat` / `lng` | numeric | **required_if `sort=nearest`** |

**200** — paginated `products`:
```json
{
  "status": "success",
  "products": {
    "current_page": 1,
    "data": [
      {
        "id": 20, "vendor_id": 3, "name": "…", "description": "…",
        "price": "150.00|null", "stock": 3, "meta": { },
        "is_available": true, "is_hidden": false, "deposit_percent": 20,
        "discount_percent": "25.00|null", "discount_ends_at": "…|null",
        "is_on_offer": true, "discounted_price": "112.50",
        "created_at": "…", "updated_at": "…",
        "vendor": {
          "id": 3, "business_name": "…", "vendor_type": "photographer",
          "booking_style": "appointment", "city": "…", "rating_avg": "4.50",
          "profile_image": "…|null", "profile_image_url": "…|null", "account_status": null
        },
        "primary_image": { "id": 9, "vendor_product_id": 20, "image_path": "…", "is_primary": true, "image_url": "…" }
      }
    ],
    "per_page": 15, "total": 1, "…": "…"
  }
}
```
Assertions:
- Every item's `is_available=true` AND `is_hidden=false`; every item's `vendor` is approved+active.
- `is_on_offer` + `discounted_price` are always present (appended). `discounted_price` **equals `price`** when no live offer; when on offer it equals `price × (1 − discount_percent/100)`.
- The embedded `vendor` mini-object's `account_status` is **`null`** (the select omits `is_active`/`winding_down`, so the appended attribute can't compute — this is intentional, not a bug). `fcm_token`/`remember_token` absent.
- `primary_image` may be `null` if the product has no primary image.
- A hidden or sold-out (`is_available=false`) product, or an unapproved vendor's product, must **not** appear.
`sort=nearest` without `lat`/`lng` → **422**.

### 4.4 `GET /vendors/{id}/products/search?name=...` — within one vendor
Public. If the vendor is not approved+active (`is_approved=false` OR `is_active=false`) → **200** `{ "status": "success", "products": [], "note": "Vendor unavailable" }`. Otherwise returns that vendor's products filtered to **`is_available=true AND is_hidden=false`** (same visibility rule as `GET /products` — sold-out and hidden items do NOT appear), each with `images[]`. `?name=` filters `name LIKE %name%` (optional).
**200:** `{ "status": "success", "products": [ { /* product + images[] */ } ] }`

### 4.5 `GET /vendors/{id}/reviews` — public vendor reviews
If the vendor is not approved+active → **200** `{ "status": "success", "reviews": [] }`. Otherwise:
**200:** `{ "status": "success", "reviews": [ { "id","booking_id","user_id","vendor_id","rating","comment","created_at","updated_at","deleted_at":null,"user": {"id","first_name","last_name"} } ] }` (newest first; soft-deleted reviews excluded).

### 4.6 `GET /vendors/{id}/portfolio` — public portfolio
If the vendor is not approved+active → **200** `{ "status": "success", "portfolio": [] }`. Otherwise:
**200:** `{ "status": "success", "portfolio": [ { "id","vendor_id","title","description","created_at","updated_at","images":[ {"id","portfolio_item_id","image_path","is_primary","image_url",…} ] } ] }` (newest first).

### 4.7 `GET /vendors/{id}/availability` — unavailable dates for the calendar
Only approved+active (else 404). Returns ONE flat merged list (booked + blocked), so the app greys out days. A date is unavailable if a booking in `awaiting_payment|pending|approved` sits on it, or the vendor blocked it.
**200:** `{ "status": "success", "unavailable": ["2026-09-15", "2026-09-18"] }`

---

## 5. Endpoint Reference — Profile & auth (auth:sanctum)

All require `Authorization: Bearer {USER_TOKEN}` + `Accept: application/json`.

#### `GET /profile`
**200:** `{ "status": "success", "user": { /* User object, §3 — fcm_token/remember_token hidden */ } }`

#### `POST /profile`  (JSON, or multipart if sending `profile_image`)
| field | required | rule |
|---|---|---|
| first_name | no | `sometimes` string 2–50, letters+spaces |
| last_name | no | `sometimes` string 2–50, letters+spaces |
| city | no | `sometimes|nullable` string 2–100 |
| birth_date | no | `sometimes` date |
| latitude | no | `sometimes` numeric between -90,90 |
| longitude | no | `sometimes` numeric between -180,180 |
| address | no | `sometimes` string max:255 |
| profile_image | no | `sometimes` image jpg/jpeg/png max 2048 KB (multipart) |

**200:** `{ "status": "success", "user": { /* updated User */ } }`. Note: `fcm_token` is NOT updatable here (only via `POST /fcm-token`).

#### `DELETE /profile/image`
**200:** `{ "status": "success", "message": "Profile image removed" }` (idempotent — succeeds even if none set).

#### `POST /fcm-token`
| field | required |
|---|---|
| fcm_token | yes (string) |

**200:** `{ "status": "success" }` — the response never echoes the token.

#### `POST /logout`
No body. Revokes ONLY the token used for this request (other devices stay logged in) and nulls this user's `fcm_token`. After it, reusing the same token → **401**.
**200:** `{ "status": "success", "message": "Logged out" }`

---

## 6. Endpoint Reference — Bookings, Payments (auth:sanctum)

### 6.1 Booking object shape
`id, user_id, vendor_id, vendor_product_id, booking_style, status, event_date, old_event_date, event_type, event_location, duration_hours, details (array|null), delivery_date, delivery_address, notes, price_agreed (2-dec|null), selected_options (array|null), responded_at, refund_amount (2-dec|null), refund_paid_at, refund_waived_at, created_at, updated_at`. Store/pay responses eager-load `vendor` (full), `product`, `items.product`. `event_date`/`delivery_date`/`old_event_date` are datetime.
BookingItem: `id, booking_id, vendor_product_id, quantity (int), unit_price (2-dec), original_unit_price (2-dec), selected_options (array|null), created_at, updated_at`.

### 6.2 `POST /bookings` — create (ONE endpoint, shape chosen by the vendor's `booking_style`)
The server looks up the vendor from the first product id, then applies the matching strict validation. **Vendor gate (all shapes):** the vendor must be `is_approved AND is_active AND is_accepting_bookings`, else → **403** `{ "status": "error", "message": "…vendor unavailable…" }`.

**APPOINTMENT shape** (photographer/dj/weddingHall/makeupArtist):
```http
POST /bookings
{
  "vendor_product_id": 12,
  "event_date": "2026-09-15 18:00:00",
  "event_location": "Damascus",
  "duration_hours": 4,
  "selected_options": { "package": "gold" },
  "notes": "outdoor shoot"
}
```
Rules: `vendor_product_id` required|exists; `event_date` **required|date|after:now**; `event_location` nullable string; `duration_hours` nullable integer; `selected_options` nullable array; `notes` nullable string. **Prohibited (→422 if sent):** `items`, `details`, `delivery_date`, `delivery_address`.

**ORDER shape** (cakes/flowers/gifts/dresses/accessories/candles) — cart, even for one product:
```http
POST /bookings
{
  "items": [
    { "vendor_product_id": 20, "quantity": 2, "selected_options": { "flavor": "chocolate" } }
  ],
  "delivery_date": "2026-09-20",
  "delivery_address": "Mazzeh, Damascus",
  "notes": "no nuts"
}
```
Rules: `items` **required|array|min:1**; `items.*.vendor_product_id` required|exists; `items.*.quantity` integer min:1 (default 1); `items.*.selected_options` nullable array; `delivery_date` nullable date; `delivery_address` nullable string; `details` nullable array; `notes` nullable string. **Prohibited (→422 if sent):** `event_date`, `event_location`, `duration_hours`.

`selected_options` semantics: free-form JSON from the product `meta`. Appointment → one blob on the booking. Order → per item. **Cart-line merge rule:** two request items merge into ONE line only when they share the **same product AND the same `selected_options`**; same product with different options stays as separate lines (quantities of identical lines sum).

**Success 200:**
```json
{ "status": "success", "booking": { "…": "…", "status": "awaiting_payment", "items": [ { "…": "…", "unit_price": "…", "original_unit_price": "…" } ] } }
```
**Extract `booking.id`.** The booking is `awaiting_payment` and invisible to the vendor until paid.

Other rejections:
- Product unavailable/hidden/sold-out → **409** `{ "status": "error", "message": "…not available…" }`.
- Order quantity above stock → **409** `"Only N left in stock"`.
- Mixed vendors in one order → **422** `"…same vendor…"`.
- Appointment date already booked (active booking on that day) → **409** `"…already booked…"`.
- Appointment date manually blocked by the vendor → **409** `"…not available…"`.

### 6.3 `GET /bookings` — the user's own bookings (newest first)
Eager loads `vendor` (full), `product`, `items.product:{id,name,price}`. No status filter — includes `awaiting_payment` drafts.
**200:** `{ "status": "success", "bookings": [ { …, "vendor": {…}, "items": [ { …, "product": {…} } ] } ] }`

> There is **no** `GET /bookings/{id}` on the user side (that path is vendor-only). The customer reads a single booking from the list, or acts on it via the routes below.

### 6.4 `POST /bookings/{id}` — update an unpaid draft
Only the user's OWN booking with `status=awaiting_payment` (else **404**). Strict per style (same prohibition rules as create). Order edits can replace the whole cart via `items[]` (re-validated for vendor/availability/stock). Appointment date-change re-checks the conflict (→ **409** if taken).
**200:** `{ "status": "success", "booking": { /* refreshed */ } }`

### 6.5 `POST /bookings/{id}/cancel`
Only the user's OWN booking (else **404**). Refund tier by status:
| status when cancelled | result | refund |
|---|---|---|
| `awaiting_payment` | → `cancelled` | none (nothing paid) |
| `pending` (paid, not approved) | → `cancelled` | **100%** |
| `approved` — ≤24h since approval | → `cancelled`, stock restored | **100%** |
| `approved` — ≤72h | → `cancelled`, stock restored | **50%** |
| `approved` — >72h | → `cancelled`, stock restored | **0%** |
| `completed`/`declined`/`cancelled` | → **422** | cannot cancel |

**200 (awaiting_payment):** `{ "status": "success", "message": "…cancelled…" }`
**200 (pending):** `{ "status": "success", "message": "…", "refund": { "percent": 100, "note": "real refund pending ShamCash API" } }`
**200 (approved):** `{ "status": "success", "message": "…", "refund": { "percent": 100|50|0, "note": "…" } }`
**422 (terminal state):** `{ "status": "error", "message": "Cannot cancel a booking with status '…'" }`
On cancel of an appointment, `event_date` moves to `old_event_date` and is nulled (frees the calendar).

### 6.6 `POST /payments/verify` — pay a draft (makes it `pending`)
Send **only** `{ booking_id, transaction_id }`; the server computes the amount. `transaction_id:"0000"` = test bypass.
Rules: `booking_id` required|exists; `transaction_id` required|string.
Server picks the booking by `id + user_id + status=awaiting_payment` (`firstOrFail`).

**200:**
```json
{
  "status": "success",
  "message": "Payment verified successfully",
  "booking": { "…": "…", "status": "pending" },
  "payment": {
    "id": 1, "booking_id": 55, "amount_paid": "30.00", "commission": "4.50",
    "vendor_payout": "25.50", "currency": "SYP",
    "transaction_id": "0000-55", "sender_name": "TEST", "status": "verified",
    "created_at": "…", "updated_at": "…"
  },
  "debug_notifications": { "user": "…", "vendor": "…" }
}
```
Amount rules:
- **Appointment:** `amount_paid = discounted_price × deposit_percent/100` (deposit_percent default **20** → 20% deposit of the discounted price).
- **Order:** `amount_paid = Σ(unit_price × quantity)` over the cart (full total).
- `commission = round(ORIGINAL_amount × 0.15, 2)` — **always on the original (pre-discount) price**; the vendor carries the discount.
- `vendor_payout = round(amount_paid − commission, 2)`.
- For `transaction_id:"0000"`, the stored `transaction_id` becomes `"0000-{bookingId}"`.

Rejections:
- Already paid (a verified payment exists) → **409** `"…already paid…"`.
- Booking not in `awaiting_payment` (already `pending`/etc.) → **404** (the query filters `status=awaiting_payment`).
- Non-test `transaction_id` reused → **409** `"…transaction used…"` (the `"0000"` id is exempt).

---

## 7. Endpoint Reference — Reviews, Favorites (auth:sanctum)

### 7.1 Reviews
Review object: `id, booking_id, user_id, vendor_id, rating (int), comment (string|null), created_at, updated_at, deleted_at`.

#### `POST /reviews`
| field | required | rule |
|---|---|---|
| booking_id | yes | exists:bookings |
| rating | yes | integer 1–5 |
| comment | no | nullable string |

Gate: the booking must be the user's own **and** in status `approved` OR `completed` (else **422** `"…cannot review… '{status}'"`). If not the user's booking → **404**. **One review per booking**: a live review → **409** `"…already reviewed…"`; a previously soft-deleted review is **restored and overwritten** (re-review). On success the vendor's `rating_avg` is recomputed.
**200:** `{ "status": "success", "review": { … } }`

#### `GET /my-reviews`
The reviews the user wrote, newest first, each with `vendor:{id,business_name,profile_image,profile_image_url,account_status,cover_image_url}` (full vendor via `with('vendor:id,business_name,profile_image')` — note appended URL fields still compute; `account_status` will be `null` due to the limited select).
**200:** `{ "status": "success", "reviews": [ { …, "vendor": {…} } ] }`

#### `POST /reviews/{id}` — edit own review
Only the author (else **404**). `rating` (integer 1–5) and/or `comment` (nullable string), both `sometimes`. Editing **recomputes** the vendor's `rating_avg`.
**200:** `{ "status": "success", "review": { /* refreshed */ } }`

#### `DELETE /reviews/{id}` — soft-delete own review
Only the author (else **404**). Soft delete (row kept, `deleted_at` set); vendor `rating_avg` recomputed over remaining reviews (0 if none left). The user can review the same booking again later (re-review restores the row).
**200:** `{ "status": "success", "message": "…review deleted…" }`

### 7.2 Favorites ("Saved")
There is **no `is_saved` boolean** on product objects. The app fills hearts by fetching `GET /saved/ids` once and matching product ids.

#### `GET /saved`
The user's saved items, split into two tabs by the product's vendor `booking_style`, banned vendors' items hidden.
**200:**
```json
{
  "status": "success",
  "counts": { "packages": 1, "products": 2 },
  "packages": [ { "id","user_id","vendor_product_id","created_at","updated_at",
                  "product": { …, "images":[…], "vendor": { "id","business_name","profile_image","cover_image","vendor_type","booking_style","is_active","winding_down","profile_image_url","cover_image_url","account_status" } } } ],
  "products": [ … ]
}
```
`packages` = saved items whose vendor is an appointment vendor; `products` = order vendors.

#### `GET /saved/ids`
**200:** `{ "status": "success", "ids": [5, 7, 12] }` (just the saved `vendor_product_id`s — to fill hearts on browse pages).

#### `POST /saved` — save a product
| field | required | rule |
|---|---|---|
| vendor_product_id | yes | exists:vendor_products |

Idempotent (unique per user+product). **201** on first save, **200** if already saved:
`{ "status": "success", "item": { …, "product": { …, "images":[…] } } }`

#### `DELETE /saved/{productId}`
Idempotent — removing something not saved still succeeds. `{productId}` is the **vendor_product_id**.
**200:** `{ "status": "success", "message": "Removed from saved" }`

---

## 8. Endpoint Reference — Chat, Notifications, Support, Reports (auth:sanctum)

### 8.1 Chat (with a vendor)
Conversation object: `id, user_id, vendor_id, last_message_at, created_at, updated_at` (+ eager relations per endpoint). Message object: `id, conversation_id, sender_type ("user"|"vendor"), sender_id, body, read_at, created_at, updated_at`.

#### `POST /conversations/vendor/{vendorId}` — open/create (THE GATE)
- Vendor id missing → **404** `{ "status": "error", "message": "Vendor not found" }`.
- **No approved-paid booking with this vendor → 403** `{ "status": "error", "message": "You can chat a vendor only after they approve a booking with you." }`. The gate passes only when a booking with this vendor is `approved` OR `completed` AND has a `verified` payment.
- Success: **201** (first open) or **200** (already existed) `{ "status": "success", "conversation": { …, "vendor": { /* full Vendor, fcm_token hidden */ } } }`.

#### `GET /conversations` — the chat list
The user's conversations, newest activity first, each with the `vendor` (full), `latest_message`, and `unread_count` (messages the vendor sent that you haven't read).
**200:** `{ "status": "success", "conversations": [ { …, "vendor": {…}, "latest_message": {…}|null, "unread_count": 0 } ] }`

#### `GET /conversations/{id}/messages?after={lastId}`
Only if the caller owns the conversation (else **404**). Oldest-first. `?after={lastMessageId}` returns only newer messages — the polling mechanism.
**200:** `{ "status": "success", "messages": [ { … } ] }`

#### `POST /conversations/{id}/messages`
| field | required | rule |
|---|---|---|
| body | yes | string max:5000 |

Only if the caller owns the conversation (else **404**). Bumps `last_message_at`; pushes FCM to the vendor.
**201:** `{ "status": "success", "message": { …, "sender_type": "user" } }`

#### `POST /conversations/{id}/read`
Marks the vendor's messages in this conversation as read. Owner-only (else 404).
**200:** `{ "status": "success", "message": "Marked as read" }`

### 8.2 Notifications
Notification object: `id, notifiable_type ("user"), notifiable_id, title, body, data (object), read_at, created_at, updated_at`.

#### `GET /notifications`
**200:** `{ "status": "success", "unread_count": N, "notifications": [ { …, "notifiable_type": "user" } ] }` (newest first).

#### `POST /notifications/read-all`
**200:** `{ "status": "success", "message": "…all marked read…" }`

#### `POST /notifications/{id}/read`
Only the caller's own (else **404** `{ "status": "error", "message": "…notification not found…" }`).
**200:** `{ "status": "success", "notification": { …, "read_at": "…" } }`

### 8.3 Support tickets (user → admin)
SupportThread (ticket): `id, owner_type ("user"), owner_id, booking_id (nullable), subject, category (nullable), status ("open"|"in_review"|"resolved"), last_message_at, resolved_at, handled_by, created_at, updated_at`. Status flow: `open` (created, admin hasn't replied) → `in_review` (an admin replied — the user may now reply) → `resolved` (closed). SupportMessage: `id, support_thread_id, sender_type ("user"|"admin"), sender_id, body, read_at, created_at, updated_at`.

#### `POST /support/tickets` — open a ticket
| field | required | rule |
|---|---|---|
| subject | yes | string max:255 |
| message | yes | string max:5000 |
| booking_id | no | nullable integer; if sent, must be the user's own booking (else **404** `"Booking not found"`) |
| category | no | nullable in:`no_show,payment,behavior,other` |

**201:** `{ "status": "success", "message": "Ticket opened — support will get back to you.", "ticket": { …, "status": "open" }, "first_message": { … } }`

#### `GET /support/tickets` — the user's tickets (paginated 20, newest activity first)
**200:** `{ "status": "success", "tickets": { "current_page":1, "data":[ { …, "unread_count": N } ], "…":"…" } }` (`unread_count` = unread admin replies).

#### `GET /support/tickets/{id}` — one ticket + messages
Owner-only (else **404**). Opening it marks admin replies as read. Loads `messages` (oldest first), plus `booking` + `booking.vendor` + `booking.product` when the ticket points at a booking.
**200:** `{ "status": "success", "ticket": { …, "messages": [ … ], "booking": {…}|null } }`

#### `POST /support/tickets/{id}/messages` — reply
| field | required | rule |
|---|---|---|
| message | yes | string max:5000 |

Owner-only (else 404). Reply is allowed **only after support has replied** (thread `status="in_review"`):
- thread `status="open"` (support hasn't replied yet) → **422** `"Support has not replied yet — please wait for their response."`
- thread `status="resolved"` → **422** `"This ticket is resolved. Open a new ticket if you need more help."`
- `status="in_review"` → **201** `{ "status": "success", "sent": { … } }`

### 8.4 Content reports
Flag abusive content; creates a pending moderation flag. Reporting twice is a silent no-op (unique per reporter+item).
| route | preflight |
|---|---|
| `POST /products/{id}/report` | `VendorProduct::findOrFail` → 404 if missing |
| `POST /reviews/{id}/report` | `Review::findOrFail` → 404 if missing |
| `POST /portfolio/{id}/report` | `PortfolioItem::findOrFail` → 404 if missing |

Body: `reason` optional (`nullable|string|max:1000`).
**201** (new report): `{ "status": "success", "message": "Report submitted — our team will review it." }`
**200** (already reported): `{ "status": "success", "message": "You already reported this." }`

---

## 9. Rules & invariants to assert

1. **Discovery is leak-safe.** `GET /products` returns only items with `is_available=true AND is_hidden=false` whose vendor is `is_approved AND is_active`. `GET /vendors` / `GET /vendors/{id}` / `GET /vendors/{id}/availability` return only approved+active vendors (else 404 for the detail routes).
1b. **A not-approved/not-active vendor's sub-resources return EMPTY, not data.** For a vendor that is not (`is_approved AND is_active`): `GET /vendors/{id}/reviews` → `reviews:[]`; `GET /vendors/{id}/portfolio` → `portfolio:[]`; `GET /vendors/{id}/products/search` → `products:[]` (+ `note:"Vendor unavailable"`). And `GET /vendors/{id}/products/search` on an approved+active vendor filters to `is_available=true AND is_hidden=false` (sold-out/hidden items excluded).
2. **Two booking shapes, server-decided.** The vendor's `booking_style` chooses appointment vs order. Sending the wrong shape's fields → **422** (prohibited). Appointment requires `event_date` (`after:now`); order requires `items[]` (`min:1`).
3. **selected_options merge.** In an order, two items with the same product+options merge (quantities sum); same product, different options stay separate lines.
4. **Vendor gate on booking.** `POST /bookings` → **403** unless the vendor is `is_approved AND is_active AND is_accepting_bookings`.
5. **Payment computes the amount server-side.** Send only `{booking_id, transaction_id}`. Appointment = 20% deposit of the discounted price; order = full cart total. `commission = 15%` of the **original** price; `vendor_payout = amount_paid − commission`.
6. **Payment moves `awaiting_payment` → `pending`.** After paying, the booking is `pending` (visible to the vendor).
7. **Payment idempotency.** Paying an already-paid booking → **409**; paying a booking no longer `awaiting_payment` → **404**.
8. **Cancel refund tiers.** `awaiting_payment`=nothing; `pending`=100%; `approved` ≤24h=100%, ≤72h=50%, >72h=0%. Terminal states → 422.
9. **Only unpaid drafts are editable.** `POST /bookings/{id}` requires `status=awaiting_payment` (else 404).
10. **Review gate.** `POST /reviews` needs the booking `approved`/`completed`; one review per booking (409 on duplicate); soft-delete then re-review restores/overwrites; edit and delete recompute `rating_avg`.
11. **Chat gate.** `POST /conversations/vendor/{id}` → **403** until the vendor has APPROVED a paid booking (status `approved`/`completed` + verified payment). Messages poll with `?after={lastId}`.
12. **Ownership isolation.** Every `{id}` route (bookings, reviews, conversations, tickets, notifications) is scoped to the caller; someone else's id → **404** (or 403 for the chat gate), never their data.
13. **Favorites.** `GET /saved/ids` is the heart-state source (no `is_saved` field). Save/delete are idempotent (201 first save, 200 thereafter; delete always 200).
14. **Support reply gating.** A user can only reply once support has replied (`status="in_review"`); `open`→422, `resolved`→422.
15. **Content report dedupe.** Second report of the same item by the same user → **200** "already reported" (not a new flag).
16. **Secrets.** `fcm_token` and `remember_token` never appear on any user/vendor object — including the full `User` object (`GET /profile`, auth). Both are hidden by the model.
17. **Localization.** Same status+shape, different `message`/`errors` language by `Accept-Language`; enum VALUES stay English keys.

---

## 10. Ready-to-run Test Scenarios

Run in order. Prereq for S4+: an approved vendor with a product (§2) and a user token (§1).

**S1 — User auth bootstrap.**
`POST /send-otp` → read `otp` → `POST /verify-otp` → if `new_user`, `POST /complete-registration`. Assert: 200s; token present; user object has `profile_image_url`; `fcm_token` and `remember_token` both ABSENT; registration response has **no `status` key**; `verify-otp` new phone returns `status:"new_user"`.

**S2 — Profile round-trip.**
`GET /profile` → `POST /profile` `{first_name:"Sara", city:"Aleppo", latitude:33.5, longitude:36.3}` → `GET /profile`. Assert updates persisted. Negative: `POST /profile` `{latitude:200}` → **422**; `first_name:"A1"` (has a digit) → **422**.

**S3 — Public discovery (no token).**
`GET /vendors` → assert paginated `vendors.data[]`, each approved+active, `account_status:"active"`, no `distance_km`. `GET /vendors?sort=nearest` (no lat/lng) → **422**. `GET /vendors?sort=nearest&lat=33.5&lng=36.3` → items carry `distance_km`. `GET /vendors/{id}` → detail with `reviews_count`/`events_hosted_count`. `GET /products` → every item `is_available=true`, `is_hidden=false`, `is_on_offer`+`discounted_price` present, embedded `vendor.account_status` is `null`. `GET /products?on_offer=1` → every item has a live discount and `discounted_price < price`. `GET /products?type=service` → all appointment vendors; `?type=product` → all order vendors. `GET /products?search=<name>&sort=price_low` → filtered + ascending price. `GET /vendors/{id}/products/search?name=x` → only `is_available=true`+`is_hidden=false` items (assert no returned item has `is_available:false` or `is_hidden:true`); `/reviews`, `/portfolio`, `/availability` → correct shapes.

**S4 — Appointment booking lifecycle.**
Against an approved **appointment** vendor + its product:
1. `POST /bookings` (appointment shape, `event_date` future) → 200, capture `booking.id`, status `awaiting_payment`.
2. `POST /payments/verify` `{booking_id, transaction_id:"0000"}` → 200, booking `pending`; assert `amount_paid = discounted_price × 0.20`, `commission = original × 0.15`, `vendor_payout = amount_paid − commission`, `transaction_id:"0000-{id}"`.
3. `GET /bookings` → the booking is present with `status:"pending"`, has `vendor` + `items[]`.

**S5 — Order booking lifecycle + merge + selected_options.**
Against an approved **order** vendor + product with `stock>=3`:
1. `POST /bookings` with two items: same product, one `{flavor:"chocolate"}` qty 1 and one `{flavor:"chocolate"}` qty 1, plus a third `{flavor:"vanilla"}` qty 1. Assert the two chocolate lines **merged** into one `quantity:2` line, vanilla is a **separate** line.
2. `POST /payments/verify` "0000" → assert `amount_paid = Σ(unit_price×quantity)` (full total, no deposit).
3. `GET /bookings` → order present, `booking_style:"order"`, `items[]` each with `product`.

**S6 — Wrong booking shape (negative).**
Appointment vendor: `POST /bookings` with `items:[…]` → **422** (`items` prohibited). Order vendor: `POST /bookings` with `event_date` → **422** (`event_date` prohibited). Appointment vendor with no `event_date` → **422** (required). Appointment with `event_date` in the past → **422** (`after:now`).

**S7 — Pay twice (idempotency).**
After a successful `POST /payments/verify`, call it again with the same `booking_id` → **409** "already paid" OR **404** (status moved off `awaiting_payment`) — accept either, note which.

**S8 — Discovery excludes hidden/unapproved.**
If you control a vendor: hide a product (`POST /vendor/products/{id}/toggle-hidden {is_hidden:true}`), then assert it is ABSENT from `GET /products` and from `GET /vendors/{vid}/products/search`, and `POST /bookings` for it → **409**. Un-hide → it reappears. (If you can't act as a vendor, at minimum assert no returned item ever has `is_hidden:true` or `is_available:false`.)

**S8b — Unapproved/inactive vendor sub-resources return EMPTY.**
Take a vendor that is NOT approved+active (e.g. a freshly registered, un-approved vendor's id — obtainable via vendor auth without admin approval; or a banned vendor). Assert:
- `GET /vendors/{id}/reviews` → **200** `{ "reviews": [] }`.
- `GET /vendors/{id}/portfolio` → **200** `{ "portfolio": [] }`.
- `GET /vendors/{id}/products/search` → **200** `{ "products": [], "note": "Vendor unavailable" }`.
- `GET /vendors/{id}` (detail) and `GET /vendors/{id}/availability` → **404** (these `findOrFail` on approved+active).
Even though the vendor may have real reviews/portfolio/products in the DB, none leak. Then, for an approved+active vendor with a hidden or sold-out product, confirm `GET /vendors/{id}/products/search` excludes it (no `is_hidden:true`/`is_available:false` item in the list).

**S9 — Favorites.**
`POST /saved {vendor_product_id}` → **201**, `item` present. Repeat → **200**. `GET /saved/ids` → contains that id. `GET /saved` → item in `packages` or `products` by the vendor's style; `counts` correct. `DELETE /saved/{productId}` → 200; `GET /saved/ids` no longer contains it. `DELETE` again → still 200 (idempotent). `POST /saved {vendor_product_id: 999999999}` → **422** (exists rule).

**S10 — Review write → edit → delete → re-review.**
Prereq: a booking with this vendor in `approved`/`completed` (needs vendor approval — see §2). `POST /reviews {booking_id, rating:5, comment:"great"}` → 200. `POST /reviews` again same booking → **409**. `POST /reviews/{reviewId} {rating:3}` → 200 (edit; vendor `rating_avg` recomputes). `GET /my-reviews` → present. `DELETE /reviews/{reviewId}` → 200 (soft delete). `GET /vendors/{vid}/reviews` → the review is gone. `POST /reviews {booking_id, rating:4}` again → 200 (re-review restores). Negative: `POST /reviews` on an `awaiting_payment`/`pending` booking → **422**.

**S11 — Chat gate 403 then works.**
Before the vendor approves any paid booking: `POST /conversations/vendor/{vendorId}` → **403**. After a booking with that vendor is `approved` (vendor approved a paid booking): `POST /conversations/vendor/{vendorId}` → **201/200**, capture `conversation.id`. `POST /conversations/{id}/messages {body:"hi"}` → **201**. `GET /conversations/{id}/messages` → the message present. `GET /conversations/{id}/messages?after={lastId}` → empty when no newer. `GET /conversations` → conversation listed with `unread_count`. `POST /conversations/{id}/read` → 200. Someone else's conversation id → **404**. `POST /conversations/vendor/999999` → **404** "Vendor not found".

**S12 — Notifications.**
After paying/approval (which generate notifications), `GET /notifications` → `unread_count>=1`, each `notifiable_type:"user"`. `POST /notifications/{id}/read` → `read_at` set. `POST /notifications/read-all` → 200; re-GET → `unread_count:0`. Non-existent id → **404**.

**S13 — Support ticket create + reply gating.**
`POST /support/tickets {subject, message}` → **201**, `ticket.status:"open"`. `GET /support/tickets` → paginated, ticket present. `GET /support/tickets/{id}` → thread + `first_message`. `POST /support/tickets/{id}/messages {message}` while `status="open"` → **422** ("Support has not replied yet…"). `POST /support/tickets {subject, message, booking_id: <not yours>}` → **404** "Booking not found". `POST /support/tickets {subject, message, category:"invalid"}` → **422**.

**S14 — Content report.**
`POST /products/{id}/report {reason:"spam"}` → **201**. Repeat same product → **200** "already reported". `POST /products/999999/report` → **404**. Same pattern for `/reviews/{id}/report`, `/portfolio/{id}/report`.

**S15 — Auth negatives.**
Any `/profile`, `/bookings`, etc. with no token (WITH `Accept: application/json`) → **401** `{"message":"Unauthenticated."}`. A protected route with a VENDOR token → **401**. Confirm OMITTING `Accept: application/json` on a 401 case yields HTML/redirect (why the header is mandatory) — then always send it. `verify-otp` with a wrong/expired `otp` → **400** "Invalid OTP". `complete-registration` with a bad `registration_token` → **403** "Expired".

**S16 — Cancel refund tiers.**
Create+pay a booking (`pending`) → `POST /bookings/{id}/cancel` → **200** `refund.percent:100`. Create a draft (`awaiting_payment`) → cancel → 200, no refund. (Approved-tier %s need vendor approval + a controllable clock; assert what you can and note the rest.) Cancel a `cancelled`/`completed` booking → **422**.

**S17 — Logout revokes the token.**
`POST /logout` → 200 "Logged out". Reuse the SAME token on any protected route → **401**. Re-login (send-otp → verify-otp) for further tests.

**S18 — Localization.**
`POST /send-otp` with no `phone` → 422. Call once with `Accept-Language: ar`, once with `en`; assert same status + same JSON shape, `message`/`errors` Arabic vs English. Assert enum VALUES (`vendor_type`, `status`, `booking_style`) in any response stay the English key in BOTH languages.

---

## 11. Response field glossary

| field / value | meaning |
|---|---|
| **booking `status`** | `awaiting_payment` (unpaid draft) → `pending` (paid, awaiting vendor) → `approved` (vendor accepted) → `completed` (delivered). Side states: `declined`, `cancelled`. |
| **`booking_style`** | `appointment` (service vendors: one package + event fields) or `order` (seller vendors: items[] cart + delivery fields). Server-derived from the vendor's `vendor_type`. |
| **`is_on_offer` / `discounted_price`** | Appended on every product. `discounted_price` equals `price` with no live offer; else `price × (1 − discount_percent/100)`. |
| **`deposit_percent`** | Fixed 20% appointment deposit rate (default). Not editable. |
| **`amount_paid` / `commission` / `vendor_payout`** | Deposit-or-cart-total paid / 15% of the original price / `amount_paid − commission`. |
| **`selected_options`** | Free-form JSON the customer picks from the product `meta`; on the booking (appointment) or per item (order). |
| **`account_status`** | `active` / `winding_down` / `banned`; **`null`** on column-limited vendor mini-objects (missing source flags) — expected, not a bug. |
| **`refund_amount` / `refund.percent`** | What the customer is owed on cancel; real payout is pending the ShamCash refund API. |
| **`old_event_date`** | Set when an appointment is cancelled/declined (its former `event_date`), freeing the calendar slot. |
| **`unavailable`** (public availability) | Flat merged list of booked + blocked `YYYY-MM-DD` dates. |
| **chat `sender_type`** | `user` or `vendor` — which side sent the message. |
| **support thread `status`** | `open` (awaiting admin) → `in_review` (admin replied; user may reply) → `resolved` (closed; open a new ticket). |
| **`currency`** | Always `SYP`. |

---

## 12. How to use this file

1. Run **§1 Auth Bootstrap** first; keep the user token for the whole run. For booking/review/chat data, ensure an **approved vendor with a product** exists (§2) — if you can't approve/act as a vendor, run through payment and note the review/chat prerequisites you couldn't meet.
2. Execute the **§10 scenarios in order**. For each call assert the HTTP status code AND the JSON shape against §3–§8 and the §9 invariants.
3. Always send `Accept: application/json`. Assert both `fcm_token` and `remember_token` are absent on every user object, including `GET /profile` (§0).
4. **Report** every deviation: endpoint, request sent, expected status+shape (per this file), actual status+body. A passing run: all documented status codes match, all documented fields present with the right types, and all §9 invariants hold.
