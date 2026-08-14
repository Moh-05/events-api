# Haflati Admin Panel — React Integration Guide

> **For the Claude working with Ali on the React admin dashboard.**
> This explains how to wire the React app to the Laravel admin API. The full
> per-endpoint contract is in **`admin-api.html`** (params + responses) and the
> behaviour/permission reference is in **`admin-system.html`**. Read this first,
> then use those two as the endpoint dictionary.

---

## 1. The basics

| | |
|---|---|
| **Base URL** | `https://events-api-production-138b.up.railway.app/api` |
| **Auth** | Laravel Sanctum token. Send `Authorization: Bearer {token}` on every request except `/admin/login`. |
| **Always send** | `Accept: application/json` (otherwise Laravel may return HTML on errors). |
| **Body** | `application/json`. There are **no file uploads** on the admin side. |
| **All admin routes** | are prefixed with `/admin`. |

Every response is wrapped in an envelope:

```json
{ "status": "success", ...data }
// or
{ "status": "error", "message": "..." }
```

So in the client: treat `status === "error"` (or a non-2xx HTTP code) as a failure.

---

## 2. Auth flow (login → token → guard)

1. `POST /admin/login` with `{ email, password }`.
2. On success you get `{ token, admin: { id, name, email, role } }`.
3. Store the `token` (e.g. `localStorage`) and the `admin` object (for the UI + role gating).
4. Attach `Authorization: Bearer {token}` to every subsequent request.
5. `POST /admin/logout` revokes the token; then clear local storage and redirect to login.

**`role` is one of `super_admin` | `support`.** Keep it in app state — you'll gate
the UI with it (see §4).

### Recommended: one axios instance with interceptors

```js
// api.js
import axios from "axios";

const api = axios.create({
  baseURL: "https://events-api-production-138b.up.railway.app/api",
  headers: { Accept: "application/json" },
});

// attach token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("admin_token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// global error handling
api.interceptors.response.use(
  (res) => res,
  (err) => {
    const status = err.response?.status;
    if (status === 401) {
      // token invalid/expired → force re-login
      localStorage.removeItem("admin_token");
      window.location.href = "/login";
    }
    // 403 (wrong role), 422 (validation), 404 (not found) → let the caller handle
    return Promise.reject(err);
  }
);

export default api;
```

---

## 3. HTTP status codes you'll see

| Code | Meaning | What the UI should do |
|---|---|---|
| `200 / 201` | success | use the payload |
| `401` | no/invalid token | redirect to login (handled globally above) |
| `403` | authenticated but **wrong role** (a `support` hit a `super_admin` route) | show "not allowed" — but better: **don't render that button for `support` at all** (§4) |
| `422` | validation / business rule (e.g. banning a user who has a paid booking, missing `reason`) | show `message` to the admin; keep them on the form |
| `404` | not found (or not yours, e.g. another admin's notification) | show "not found" |

`422` is important on the admin side — several actions **refuse** with a helpful
`message` (ban guard, re-cancel, waiving an already-paid refund, deleting your own
admin account). Always surface `response.data.message`.

---

## 4. The two roles — gate the UI, don't just rely on 403

The backend enforces roles (a `support` calling a `super_admin` route gets `403`),
but the UX should **hide** what `support` can't do. Rule of thumb from
`admin-system.html`:

- **`support`** can: view everything, do vendor **KYC** (approve/reject), and handle
  **support** (reply/resolve tickets). That's it.
- **`super_admin`** can additionally: bans, all money (payments/wallets/refunds/
  withdrawals/financials), disputes & cancellations, content **deletion**/dismiss,
  audit log, and managing admin accounts.

```jsx
const isSuper = admin.role === "super_admin";

// examples
{isSuper && <BanVendorButton />}
{isSuper && <NavLink to="/money">Money</NavLink>}
{isSuper && <NavLink to="/financials">Financials</NavLink>}
{isSuper && <NavLink to="/audit">Audit log</NavLink>}
{isSuper && <NavLink to="/admins">Admins</NavLink>}
// KYC, Support, Vendors/Users/Bookings lists, Moderation *browsing* → show for both
```

There's a full **permission matrix** in `admin-system.html` §1 — mirror it in the nav.

---

## 5. Pagination shape (Laravel)

Every list endpoint returns a standard Laravel paginator under its key
(`vendors`, `users`, `bookings`, `payments`, `reviews`, `products`, `portfolio`,
`withdrawals`, `refunds`, `logs`, `admins`, `threads`, `notifications`):

```json
{
  "status": "success",
  "vendors": {
    "data": [ ... ],
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 92,
    "next_page_url": "...?page=2",
    "prev_page_url": null
  }
}
```

- Read rows from `.data`.
- Drive the pager from `current_page` / `last_page` / `total`.
- Go to a page with `?page=N`. Filters are extra query params (see each endpoint).

Example: `GET /admin/vendors?status=kyc_pending&search=studio&page=2`.

---

## 6. Screen → endpoints map

| Screen | Endpoint(s) |
|---|---|
| **Login** | `POST /admin/login`, `POST /admin/logout` |
| **Dashboard** | `GET /admin/dashboard` → `stats` (cards) + `nav_badges` (sidebar dots) |
| **Notifications (bell)** | `GET /admin/notifications` (+ `unread_count`), `POST /admin/notifications/read-all`, `POST /admin/notifications/{id}/read` |
| **Vendors list** | `GET /admin/vendors?status=&search=` · tabs = `status` (`kyc_pending / active / winding_down / banned`) |
| **Vendor detail (drawer)** | `GET /admin/vendors/{id}` (+ `bookings_count`, `reviews_count`, `account_status`); wallet: `GET /admin/vendors/{id}/wallet` |
| **Vendor actions** | `approve` / `reject` (both) · `ban` / `ban-gradual` / `unban` (super) |
| **KYC queue** | `GET /admin/vendors/pending` |
| **Users list / detail** | `GET /admin/users?search=&is_active=`, `GET /admin/users/{id}`, `POST /admin/users/{id}/toggle` (super) |
| **Bookings** | `GET /admin/bookings?status=&search=&vendor_id=&user_id=`, `GET /admin/bookings/{id}` |
| **Booking actions** | `cancel` (dispute) · `cancel-vendor-request` (both super, both need/allow `reason`) |
| **Complaints / Support** | `GET /admin/support?owner_type=&status=&unread=1`, `GET /admin/support/{id}`, `POST .../messages`, `POST .../resolve` |
| **Moderation** | tabs → `GET /admin/reviews`, `GET /admin/products`, `GET /admin/portfolio` (each row has `reports_count`); actions → `DELETE .../{id}` + `POST /admin/reports/{type}/{id}/dismiss` (super) |
| **Money** | `GET /admin/refunds-due`, `GET /admin/withdrawals?unpaid=1`, `GET /admin/payments`; actions → refunds `mark-paid` / `waive`, withdrawals `mark-paid` / `reject` (super) |
| **Financials** | `GET /admin/stats/financial` → `summary` (gross) + `net` + `monthly_trend` (chart) |
| **Audit log** | `GET /admin/audit-logs?search=` |
| **Admins** | `GET/POST/DELETE /admin/admins` (super) |

---

## 7. The sidebar badges + the bell (two different things)

They look similar but come from different places — use both:

- **Sidebar red dots** = `GET /admin/dashboard` → `nav_badges`:
  `{ pending_vendors, unread_support, reported_content, refunds_due, unpaid_withdrawals }`.
  These are **live counts** ("how much work, where"). Poll the dashboard (or just
  refetch on navigation) to keep them fresh.
- **The bell dropdown** = `GET /admin/notifications` → an **event feed** (history you
  click), with `unread_count`. Each notification has `data.type`
  (`vendor_kyc | support | content_report | withdrawal | refund_due`) + a target id —
  use it to route the click to the right screen:

```js
function notificationHref(n) {
  const d = n.data || {};
  switch (d.type) {
    case "vendor_kyc":     return `/vendors/${d.vendor_id}`;
    case "support":        return `/support/${d.thread_id}`;
    case "content_report": return `/moderation?type=${d.reportable_type}&id=${d.reportable_id}`;
    case "withdrawal":     return `/money?tab=withdrawals`;
    case "refund_due":     return `/money?tab=refunds`;
    default:               return "/";
  }
}
```

There's **no websocket** — poll `GET /admin/notifications` (e.g. every 30–60s) for
the unread count. Read-state is **per-admin** (marking read only affects the logged-in
admin).

---

## 8. Behaviour notes that affect the UI

These are the non-obvious rules the backend enforces. Reflect them so the admin
isn't surprised (full detail in `admin-system.html`):

- **Vendor status** — every vendor has a computed `account_status` =
  `active | winding_down | banned`. Use it for the status pill and the list tabs
  (`?status=`). Don't compute it from `is_active` yourself.
- **Two ban modes** — `ban` (immediate, fraud: cancels+refunds all, claws commission
  from the vendor) vs `ban-gradual` (lets approved bookings finish). Both `super_admin`.
- **User ban is guarded** — `POST /admin/users/{id}/toggle` returns **422** with
  `active_bookings` if the user has a **paid** in-flight booking. Show that message and
  point the admin to settle/cancel it first. (Unpaid-draft-only bans succeed but return
  a `warning` string — show it.)
- **Refund has three fates** — a cancelled booking owes a refund until it's either
  `mark-paid` **or** `waive`d (keep the money, e.g. fraud — needs a `reason`). Both
  drop it off `refunds-due`.
- **Withdrawal has reject** — a suspicious payout can be **rejected** (needs `reason`);
  the money returns to the vendor's wallet. So the withdrawals list needs both a
  "Mark paid" and a "Reject" action.
- **A wallet can be negative** — after a commission clawback a vendor may owe the
  platform (`available_balance < 0`). Show negatives in red; it's expected.
- **Financials: gross vs net** — `summary.platform_profit` is **gross**; the honest
  bottom line is `net.net_profit` (gross minus commission lost to refunds). Show both,
  and `net.refunded_to_customers` / `net.commission_reclaimed` for the full picture.
- **Support model** — a user **ticket** can't be replied to by the user until an admin
  replies first (admin decides to open the chat). A vendor has one persistent **chat**.
  Resolving closes a ticket for good; a vendor chat reopens on a new vendor message.
  Ticket `category` = `no_show | payment | behavior | other` (helps triage: money vs
  vendor behaviour).
- **Moderation badge** — show FLAGGED/REPORTED when a row's `reports_count > 0`. The
  admin can either delete the content or **dismiss** the flag (keep the content).

---

## 9. Quick sanity test (before building screens)

```bash
# 1. login (there must be a seeded admin; ask the backend team)
curl -X POST .../api/admin/login -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@haflati.com","password":"..."}'

# 2. use the token
curl .../api/admin/dashboard -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

If `/admin/dashboard` returns `stats` + `nav_badges`, the wiring is correct — build
from the screen map in §6.

---

*Backend: Laravel 12 · Sanctum (`auth:admins` guard + `role:` middleware). Endpoint
dictionary: `admin-api.html`. Behaviour + permission matrix: `admin-system.html`.*
