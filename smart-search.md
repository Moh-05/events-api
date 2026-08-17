# smart-search.md — why AI-related things exist in this repo

**Read this before touching anything named `ai_*`, `HaflatiDemoSeeder`,
`SmartSearchController`, or `smart-search` in this repo.**

This is the Laravel repo (`events-api`). Smart Search itself is **not** built
here — it is a separate FastAPI service in a separate repo. But a few things
have to live on this side, and without this file they look random or wrong.

---

## What Smart Search is

Natural-language search over Haflati's real vendors, products and services.
"بدي مصور رخيص بالشام" → relevant results + a natural-language answer.
Arabic in → Arabic out.

Built by **Mohamad and Amer** as the **last feature** of the graduation project,
in parallel with the final ~20% of this API. It is a learning sprint — both
members do every task.

| Repo | Path | Branch |
| ---- | ---- | ------ |
| `events-api` (this one) | `C:\Users\Moh\events-api` | **`dev`** |
| `haflati-ai-search` | `C:\Users\Moh\haflati-ai-search` | `main` |

The AI repo has its own `CLAUDE.md` with the full architecture, the settled
decisions, and the day-by-day plan. **That file is the source of truth for the
search design.** This file only explains the parts that touch Laravel.

---

## The architecture, and why Laravel is in the middle

```
Flutter app
    |  (existing REST API)
Laravel 12 (this repo)  -- POST /api/smart-search -->  FastAPI (haflati-ai-search)
    |   throttle:20,1 - public - NOT auth:sanctum          |
    +--------------------->  MySQL events_api  <-----------+
                             user `ai_search`:
                             SELECT on everything,
                             INSERT/UPDATE/DELETE on ai_embeddings ONLY
```

Flutter never calls FastAPI directly. Laravel is the only caller, authenticated
by a shared secret in an `X-API-Key` header.

**FastAPI returns ids only** — `{answer, results:[{type, id, score}]}`. Laravel
hydrates the cards with its own accessors (`profile_image_url`,
`cover_image_url`, `products_min_price`), so Flutter gets one JSON shape instead
of two that drift apart.

That also makes leaks structurally impossible: Laravel re-applies the visibility
rule on **live rows** when hydrating, so even if FastAPI returned a banned
vendor's id, it dies here.

---

## The visibility rule — NON-NEGOTIABLE

Already appended to this repo's `README.md`. Repeated because it is the one rule
that matters. **All four flags, every time, in every customer-facing query —
including anything Smart Search touches:**

```
vendors.is_active = 1 AND vendors.is_approved = 1
  AND vendor_products.is_available = 1 AND vendor_products.is_hidden = 0
```

What each one means, and why dropping any one of them is a real bug:

| Flag | Table | Who sets it | Dropping it leaks |
| ---- | ----- | ----------- | ----------------- |
| `is_approved` | `vendors` | Admin, at KYC | an unvetted business nobody has verified |
| `is_active` | `vendors` | Admin, at ban | a **banned** vendor — the worst case |
| `is_available` | `vendor_products` | System, from stock | a sold-out item the customer cannot buy |
| `is_hidden` | `vendor_products` | The **vendor**, manually | an item the vendor deliberately took down |

`is_approved` and `is_hidden` are the two that get forgotten, and they are the
two with the sharpest consequences — `is_approved` because it exposes an unvetted
business, `is_hidden` because the vendor made an explicit choice we would be
overriding. Treat a query missing either as broken, not as a style issue.

**Three traps that have each caused a real bug in this repo:**

1. **`Vendor::scopeActive()` checks `is_active` ONLY.** No scope, middleware or
   helper adds `is_approved` — `EnsureActive` also only covers `is_active`.
   Write both conditions explicitly, every time. There is no shortcut, and
   `active()` looking complete is exactly why this is easy to get wrong.
2. **Apply the rule to the JOINED row, not one table.** A product with
   `is_available = 1` whose shop was banned must still disappear. The bait rows
   below exist to prove this specific case.
3. **Secondary surfaces leak too.** The 2026-08-17 audit (§4) found the leak in
   *Saved items*, not in the discovery endpoints. Any query that returns a
   product — favourites, recommendations, a search result, anything Smart Search
   hydrates — carries the full rule.

---

## What lives in THIS repo because of Smart Search

### 1. `HaflatiDemoSeeder` + the two factories — **added 2026-07-31**

`database/factories/VendorFactory.php`,
`database/factories/VendorProductFactory.php`,
`database/seeders/HaflatiDemoSeeder.php`

Also: **`HasFactory` was added to `Vendor`, `VendorProduct` and `PortfolioItem`**
— one line each. None of them had it, because those models were written by hand
rather than generated, so `Vendor::factory()` threw "Class VendorFactory not
found". The trait only enables `::factory()`. It changes no existing behaviour —
bookings, wallet, admin and auth code are untouched.

**Why:** semantic search needs real rows to index. The database was completely
empty (0 vendors, 0 products, 0 portfolio items) and the only seeder was
`AdminSeeder`. Searching over 8 hand-typed rows demos terribly — you cannot tell
a good ranking from a lucky one.

**Local only.** Never run this against Railway — the Flutter team tests against
that database and 40 fake vendors would appear in their app.

```powershell
php artisan db:seed --class=HaflatiDemoSeeder   # add the demo data
```

**`DatabaseSeeder` is deliberately NOT wired to this seeder.** That is a safety
feature, not an oversight: `migrate:fresh --seed` gives you the admin only, so
demo data always needs an explicit second command and can never reach production
by accident. To rebuild from scratch:

```powershell
php artisan migrate:fresh --seed
php artisan db:seed --class=HaflatiDemoSeeder
```

`db:seed` **inserts** — running it twice gives 94 vendors, not 47.

**What it produces** (numbers vary slightly per run, content is randomised):

| | count | note |
| - | ----- | ---- |
| vendors | 47 | 42 visible + 5 bait |
| products | 145 | 128 visible + 7 bait + 10 under hidden vendors |
| portfolio items | 64 | only the four `appointment` types get them |

All 10 `vendor_type` values are covered, and `booking_style` is derived from the
type exactly the way `VendorProfileController` derives it, so the demo data can
never disagree with the real registration flow.

**Three things about it that are deliberate, not accidents:**

**a. The Arabic content is realistic, not `fake()->word()`.** This data is what
search actually indexes. Random Latin gibberish in a bio means "بدي مصور عرس"
matches nothing, and the search looks broken when it is not.

**b. It seeds `portfolio_items`.** Portfolio text is embedded into the parent
vendor's text so he stays findable — a photographer may leave `bio` empty and
name his packages "Package 1", while his portfolio holds the words a customer
actually types ("تصوير عرس", "جلسة خطوبة"). Portfolio items are **never**
returned as a search result themselves.

**c. It seeds deliberately hidden "bait" rows.** Vendors with `is_approved = 0`,
vendors with `is_active = 0`, products with `is_available = 0`, products with
`stock = 0`. These **must never appear in any search result**. A later task
searches for them specifically and proves they stay hidden.

Three of these are subtle enough to be worth spelling out, because they look
like data bugs and are not:

1. **10 products with `is_available = 1` that belong to banned or unapproved
   vendors.** Nothing is wrong with those product rows on their own — they are
   caught *only* by the JOIN to `vendors`. If a query filters
   `vendor_products.is_available` but forgets the vendor's two flags, exactly
   these 10 leak. That is what they are for.
2. **A fully visible flower shop (`زهور الربيع`) that owns the 5 unavailable and
   2 out-of-stock items.** The vendor *should* be returned by search; his hidden
   items must not be. A blanket "hide the whole vendor" filter fails this case.
3. **A photographer with `bio = null` and packages named "باقة 1/2/3"**
   (`استوديو الفن الرقمي`), but a full portfolio. He is findable *only* through
   his portfolio text — he is the proof that indexing portfolio items is worth
   doing.

Bait rows are prefixed `BAIT-` in `business_name` / `name` so they survive a
`migrate:fresh` — ids change on every reseed, the prefix does not:

```sql
SELECT id, business_name FROM vendors WHERE business_name LIKE 'BAIT-%';
SELECT id, name FROM vendor_products WHERE name LIKE 'BAIT-%';
```

**Do not "fix" the bait rows.** They look like broken data on purpose.

### 2. `ai_embeddings` migration — *not created yet*

The table that stores the vectors. It is a **Laravel** migration on purpose, so
it deploys automatically with `migrate --force` on Railway. FastAPI is its only
writer, through the restricted `ai_search` MySQL user.

Columns: `entity_type`, `entity_id`, `text_hash`, `vector` (JSON), `model`,
timestamps, `unique(entity_type, entity_id)`.

⚠️ **`php artisan migrate:fresh` WIPES `ai_embeddings`.** After any fresh
migrate, the vectors are gone and the reindex endpoint must be called again.

### 3. `SmartSearchController` — *not created yet*

`POST /api/smart-search`, public, `throttle:20,1`, **not** `auth:sanctum` —
vendor discovery is already public (`GET /vendors`), so a locked search on top of
public browsing would be inconsistent.

It calls FastAPI with the `X-API-Key` header and a short timeout, then hydrates
the returned ids with Eloquent.

**Graceful degradation is mandatory.** If FastAPI is down, times out, or answers
with anything but 200, Laravel returns a clean **503** — never a 500 stack trace.
The app must never crash because of the AI layer.

Env vars needed here: `SMART_SEARCH_URL`, `SMART_SEARCH_KEY`.

### 4. Leak audit — CLOSED 2026-08-17

An audit of every customer-facing product query was run on 2026-08-17. The old
note here ("`searchVendorProducts` still returns products regardless of
`is_available`") is **out of date — that was fixed.** Current state of every
path a customer can reach a product through:

| Path | Status |
| ---- | ------ |
| `GET /products` (browse/rails/search) | full rule applied |
| `GET /products/{id}` (detail, added 2026-08-17) | full rule; a failing product 404s exactly like a missing id, so an id cannot be probed |
| `GET /vendors/{id}/products/search` (vendor profile) | full rule + returns `[]` for a not-approved/not-active vendor |
| `POST /bookings` (all 3 call sites) | 409 on `is_available = 0` or `is_hidden = 1` |
| `GET /saved` + `GET /saved/ids` | **was leaking — fixed 2026-08-17** (see below) |
| `VendorProductController::index()` | unfiltered, but **not routed** — dead code, no live exposure. Delete it or add the rule if it is ever wired up. |

**The bug that was found:** `GET /saved` filtered only `is_active`, and
`GET /saved/ids` filtered *nothing*. So a product the vendor had hidden — or one
belonging to an unapproved vendor — stayed visible in every user's Saved screen
and kept its heart lit, even though it had disappeared from browse, search and
detail. Both now apply the full four-flag rule. Saved rows are **not deleted**
when they fail the rule; they simply drop out of the response and come back if
the vendor un-hides or restocks the item.

**Why this one is worth remembering for search:** it is the exact failure mode
Smart Search is most likely to repeat. The leak did not come from the discovery
endpoints everyone thinks to check — it came from a *secondary* surface that
also happens to return products. When FastAPI returns ids and Laravel hydrates
them, the rule must be applied at the **hydration** step, not just assumed from
whatever the index contains. An embedding index goes stale the moment a vendor
hides a product; only a live re-check on the joined row is trustworthy.

---

## Things that are NOT affected by Smart Search

- **Arabic translation.** (Updated: `lang/ar` + `lang/en` were BUILT and deployed
  in the 2026-08-07 session — this section used to say translation had not
  started.) It still does not affect search: `lang/` holds validation and error
  messages only, which search never reads. User-generated content (business_name,
  bio, product names) is **never translated** — vendors type Arabic already, and
  that raw text is exactly what gets embedded.
- **`vendor_type` values.** Always the English enum (`photographer`,
  `makeupArtist`, …) in every JSON, whatever language the query is in — the value
  goes straight into `WHERE vendor_type = ?`. Translation only adds display
  labels; the column never changes.
- **Booking, payment, wallet, admin flows.** Search is read-only over
  `vendors`, `vendor_products` and `portfolio_items`. It never writes to any app
  table — only to `ai_embeddings`.

---

## Rules for anyone (human or Claude) working in this repo

1. **The four-flag visibility rule is mandatory** — `is_approved` + `is_active` on
   the vendor, `is_available` + `is_hidden` on the product, in EVERY
   customer-facing query, applied to the joined row. This is rule #1 for a
   reason: it is the only one where getting it wrong exposes a banned or
   unvetted business to customers. See "The visibility rule" above.
2. **Re-apply the rule when hydrating FastAPI results.** The AI layer returns ids;
   the index can be stale. Laravel re-checks live rows so the AI can never widen
   what a customer sees. Never trust an id just because the index returned it.
3. **Note on branches:** this file used to say "work on `dev`". Since the big
   merge (2026-08-14) work has gone **straight to `main`**, which auto-deploys to
   Railway — so a push here is a deploy. `dev` is behind and catches up by
   fast-forward when Amer needs it. Confirm the current branch before starting.
4. **Seed locally only.** Never against Railway.
5. **Do not delete the `BAIT-` rows.** They are the leak test — and they target
   exactly the flags people forget (§ the bait notes above).
6. **Do not add `is_approved` to `scopeActive()`.** Admin queries deliberately
   see banned and unapproved vendors. Write the conditions explicitly in
   customer-facing queries instead.
7. **After `migrate:fresh`, the embeddings are gone.** Reindex.
8. The AI repo's `CLAUDE.md` is the source of truth for search design. If this
   file and that file disagree, that one wins — and fix this one.
