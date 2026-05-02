# HomeStock

## Version 0.0.2

This project is a household stock manager: it controls and organizes home inventory (market items, medicine, cleaning supplies, personal care) and generates a shopping list when stock runs low or items are about to expire.

It is intended to run on a **local home server** (LAN-only). Do not expose it directly to the public internet without TLS and strong passwords.

# Rules to products

- Track expiration date (where applicable — see "Required for" in `Category` below)
- Track current quantity and a minimum-stock threshold (used to build the shopping list)
- Decrement quantity through explicit stock movements (`purchase`, `consume`, `discard`, `adjust`) — `quantity` is never edited directly

# Details to products

- Name (Required)
- Brand (Optional)
- Category (Required) — see seed list below
- Storage location (Optional) — Pantry, Fridge, Freezer, Bathroom, Garage, …
- Preferred store (Optional) — store where the item is usually purchased
- Unit type (Required) — `unit`, `g`, `kg`, `ml`, `l`
- Quantity (Required, decimal)
- Minimum stock (Required, decimal) — when `quantity <= min_stock`, item appears on the shopping list
- Expiration date (Optional; required for categories where `requires_expiration = true`)
- Notes (Optional)

## Seed categories
| Category            | requires_expiration |
|---------------------|---------------------|
| Market              | true                |
| Vegetables & Fruits | true                |
| Meat                | true                |
| Beverages           | true                |
| Bakery              | true                |
| Frozen              | true                |
| Medicine            | true                |
| Cleaning            | false               |
| Hygiene             | false               |
| Pet                 | false               |
| Car                 | false               |

Categories live in a database table (seed-driven), not a hardcoded enum, so users can edit them without redeploy.

# Screens

- CRUD to Product
- Report of products with expiration date approaching (configurable window, default 7 days)
- Report of products to buy (shopping list — products with `quantity <= min_stock`)
- Settings (categories, storage locations, stores)
- Profile / change password

# Authentication & Authorization

- Login = **email + password** (one user per email).
- Passwords hashed with `argon2id` (Symfony Security `password_hashers: 'auto'`).
- Stateful session via Symfony session cookie + CSRF protection on state-changing endpoints.
- Login endpoint rate-limited (5 attempts / minute / IP).
- Single-tenant per deployment; intended for trusted LAN use.

# Tech Skills

- Docker for infrastructure (`docker-compose.yml` for dev, `docker-compose.prod.yml` for production)
- PHP **8.5** (currently `8.5.5`) with Symfony **8.0** (`^8.0`, currently `8.0.8`; supported until **July 2026** — plan upgrade to 8.1 when it ships)
- SQLite database (file persisted in a Docker named volume)
- Vue **3** + TypeScript + Vite for the frontend (separate SPA, talks to Symfony over JSON)
- `vue-i18n` (frontend) + `symfony/translation` (backend); default locale `pt_BR`, fallback `en`
- PWA (vite-plugin-pwa) — offline cache for shopping list and product list
- Tests:
  - Backend: PHPUnit unit tests + Symfony functional tests (`WebTestCase`)
  - Frontend: Vitest component tests
- CI: GitHub Actions (separate workflows for backend and frontend)
- Code quality: `php-cs-fixer`, `phpstan`, `eslint`, `prettier`

# Rules to edition this file

- Always update the version at the top of the file
- Add an entry to the Changelog below for any scope or tech change
- Pin concrete version numbers when a tech choice is made

# Changelog

- **0.0.2** — Clarified auth (email + password), expanded product fields (unit type, storage location, min stock, stock movements), pinned PHP/Symfony versions, added i18n, PWA, tests and CI sections.
- **0.0.1** — Initial idea.
