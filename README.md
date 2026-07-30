<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
//lll

---

# Haflati — Search visibility rule

The one rule that decides which rows a customer is allowed to see. The same
lines are copied in the `haflati-ai-search` README (the FastAPI smart-search
service). Never invent a different filter in either repo.

```
vendors.is_active = 1 AND vendors.is_approved = 1 AND vendor_products.is_available = 1
```

| Flag           | Table             | Who sets it               | Meaning                      |
| -------------- | ----------------- | ------------------------- | ---------------------------- |
| `is_approved`  | `vendors`         | Admin, at KYC             | Verified as a real business  |
| `is_active`    | `vendors`         | Admin, at ban             | Not banned                   |
| `is_available` | `vendor_products` | The system, automatically | This item can be ordered now |

**`Vendor::scopeActive()` checks `is_active` ONLY — it does not check
`is_approved`.** There is no scope, middleware, or helper that adds
`is_approved` for you. `EnsureActive` middleware also only covers `is_active`.
Write both conditions explicitly in every customer-facing query.

The one place both are applied today is `VendorBrowseController::index()`
(public browse). Smart search applies the same pair for consistency — a vendor
returned by search must be a vendor the customer can also find by browsing.

Notes:

- `winding_down` vendors need no separate check — they already have
  `is_active = 0`.
- `is_available` is auto-toggled by stock (false at 0, true again when a booking
  is cancelled and stock returns).
- A `product` result inherits its vendor's flags: a bouquet that is
  `is_available = 1` whose shop was banned must still disappear. Apply the rule
  to the joined row, not to one table.

## Related: smart search

`POST /api/smart-search` (public, `throttle:20,1`, **not** `auth:sanctum`)
proxies to the FastAPI service, which returns **ids only**. Laravel re-applies
the rule above on live rows when hydrating, so the AI layer can never widen
what a customer sees. If that service is down, Laravel returns a clean 503.
