# Template Laravel Platform Architecture

## Target Stack

- Backend: Laravel API and Laravel admin CMS
- Database: MySQL
- Cache: Redis
- Frontend: React-capable frontend integration, with API-first contracts
- CMS: Multi-site, multilingual, modular backend driven by `config/cms/set/*Set.php`

## Product Scope

- Brand official websites
- Ecommerce websites
- Shopping cart and checkout
- Payment gateways
- Coupon codes and discounts
- Multi-site management
- Backend CMS
- Custom business systems

## Architecture Rules

- Every public-facing dataset must be scoped by `site_id`.
- Multilingual content uses independent language rows through a `locale` or translation table.
- Deleting localized data must affect only the current language unless a destructive full-resource operation is explicit.
- Admin modules should be config-driven through individual `*Set.php` files.
- Shared behavior belongs in services, models, or reusable controllers, not duplicated per page.
- API responses should be stable enough for React, static frontend pages, and custom integrations.
- Redis should be used for read-heavy site settings, navigation, language, and product/content listing cache.

## Ecommerce Foundation

- Customers belong to a site.
- Orders belong to a site and optionally to a customer.
- Order numbers are unique per platform and should not expose database IDs.
- Coupons belong to a site and can support fixed, percentage, and shipping discounts.
- Payment providers are site-level configurations.
- Payment transactions are append-only records linked to orders.
- Cart implementation is intentionally separate and can be session, database, or Redis-backed later.

## Current Build Priority

1. API foundation and site resolution
2. Ecommerce core schema
3. Public content/product APIs
4. Customer auth APIs
5. Cart and checkout
6. Payment provider adapters
7. Discount and coupon validation engine
8. React frontend integration
