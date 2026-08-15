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

## The visibility rule

Already appended to this repo's `README.md`. Repeated because it is the one rule
that matters:

```
vendors.is_active = 1 AND vendors.is_approved = 1
  AND vendor_products.is_available = 1 AND vendor_products.is_hidden = 0
```

(`is_hidden` was added 2026-08: the vendor's manual hide, separate from the
stock-driven `is_available`. Both must be excluded.)

**`Vendor::scopeActive()` checks `is_active` ONLY.** No scope, middleware or
helper adds `is_approved` — `EnsureActive` also only covers `is_active`. Write
both conditions explicitly in every customer-facing query.

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

### 4. Partial-leak note — `VendorProductController::searchVendorProducts`

It filters the vendor with `Vendor::active()` and (as of 2026-08) also excludes
`is_hidden = 1` products. It STILL returns products **regardless of `is_available`**
— i.e. a sold-out product can appear through that endpoint. Low priority (a
sold-out item showing is far less bad than a banned vendor's), but add
`is_available = 1` there on the leak-audit day for full consistency with the
visibility rule above.

---

## Things that are NOT affected by Smart Search

- **Arabic translation.** `lang/` does not exist yet and translation has not
  started. It does not block search: user-generated content (business_name, bio,
  product names) is **never translated** — vendors type Arabic already. `lang/ar/`
  is for validation and error messages, which search never reads.
- **`vendor_type` values.** Always the English enum (`photographer`,
  `makeupArtist`, …) in every JSON, whatever language the query is in — the value
  goes straight into `WHERE vendor_type = ?`. Translation only adds display
  labels; the column never changes.
- **Booking, payment, wallet, admin flows.** Search is read-only over
  `vendors`, `vendor_products` and `portfolio_items`. It never writes to any app
  table — only to `ai_embeddings`.

---

## Rules for anyone (human or Claude) working in this repo

1. **Work on `dev`.** `main` auto-deploys to Railway.
2. **Seed locally only.** Never against Railway.
3. **Do not delete the `BAIT-` rows.** They are the leak test.
4. **Do not add `is_approved` to `scopeActive()`.** Admin queries deliberately
   see banned and unapproved vendors. Write the conditions explicitly in
   customer-facing queries instead.
5. **After `migrate:fresh`, the embeddings are gone.** Reindex.
6. The AI repo's `CLAUDE.md` is the source of truth for search design. If this
   file and that file disagree, that one wins — and fix this one.
