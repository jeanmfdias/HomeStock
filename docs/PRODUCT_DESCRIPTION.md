# HomeStock Product Description

HomeStock is a self-hosted household stock manager for tracking products kept at home, such as market items, vegetables and fruits, meat, beverages, bakery goods, frozen food, medicine, cleaning supplies, hygiene products, pet supplies, and car supplies.

The product is designed for a single household running on a local home server inside a trusted LAN. It should not be exposed directly to the public internet unless TLS, strong passwords, and proper reverse-proxy configuration are in place.

## Product Goal

HomeStock helps a household know:

- What products are currently in stock.
- Where products are stored.
- Which products are close to expiration.
- Which products need to be bought because stock is low.
- How stock changed over time through explicit stock movements.

The MVP focuses on practical home inventory management rather than multi-tenant warehouse management.

## Target Users

- A household user managing groceries, medicine, cleaning supplies, and similar items.
- One or more trusted users inside the same home network.
- A technically comfortable owner who can run Docker Compose on a local server.

## Deployment Model

- Single-tenant per deployment.
- LAN-first and self-hosted.
- SQLite database persisted in a Docker named volume.
- Docker Compose for development and production.
- Production stack serves the Vue SPA and Symfony API from the same origin through Nginx.

## Core Product Features

### Product Inventory

Users can create, list, view, edit, and delete products.

Each product stores:

- Name, required.
- Brand, optional.
- Category, required.
- Storage location, optional.
- Preferred store, optional.
- Unit type, required.
- Current quantity, required decimal.
- Minimum stock, required decimal.
- Expiration date, optional unless required by category.
- Notes, optional.

### Stock Movements

Stock quantity changes through explicit movement records.

Allowed movement reasons:

- `purchase`
- `consume`
- `discard`
- `adjust`

Movements update the product quantity transactionally. A movement that would make quantity negative is rejected.

### Shopping List

HomeStock generates a shopping list from products whose quantity is at or below their minimum stock threshold.

Rule:

- A product appears on the shopping list when `quantity <= min_stock`.

### Expiration Report

HomeStock shows products with expiration dates approaching within a configurable window.

Rules:

- Default expiration window is 7 days.
- API supports `GET /api/reports/expiring?days=N`.
- Supported `days` range is 1 to 365.

### Settings

Users can manage reference data used by products:

- Categories.
- Storage locations.
- Stores.

Categories are database records seeded by fixtures, not hardcoded application enums, so they can be edited without redeploying the application.

### Profile

The product includes a profile area. A change-password flow is planned but not fully wired in the current MVP implementation.

## Product Rules

### Inventory Rules

- Product `name` is required.
- Product `category` is required.
- Product `unit_type` is required.
- Product `quantity` is required and stored as a decimal.
- Product `min_stock` is required and stored as a decimal.
- Product `expiration_date` is required when the selected category has `requires_expiration = true`.
- Product `expiration_date` is optional when the selected category has `requires_expiration = false`.
- Product quantity must not become negative.
- Product quantity should not be edited directly in normal user flows; it must change through stock movements.
- Products are scoped to the authenticated user.
- Cross-user product access returns 404.

### Decimal Rules

- Quantities and minimum stock values are decimal values.
- In PHP/Doctrine, decimal values round-trip as strings.
- Backend comparisons must use decimal-safe comparison, such as `bccomp`, not strict string or float equality.

### Category Rules

Seed categories:

| Category | Requires expiration |
|---|---:|
| Market | true |
| Vegetables & Fruits | true |
| Meat | true |
| Beverages | true |
| Bakery | true |
| Frozen | true |
| Medicine | true |
| Cleaning | false |
| Hygiene | false |
| Pet | false |
| Car | false |

### Unit Rules

Supported unit types:

- `unit`
- `g`
- `kg`
- `ml`
- `l`

### Authentication Rules

- Login uses email and password.
- Each user email must be unique.
- Passwords are hashed with Symfony Security password hashers using `argon2id` through the `auto` hasher configuration.
- Authentication is stateful through Symfony session cookies.
- State-changing same-origin endpoints rely on Symfony session behavior and CSRF protection configuration.
- Login is rate-limited to 5 attempts per minute per IP.
- Authenticated API routes require `ROLE_USER`.

### Network and Security Rules

- The application is intended for trusted LAN use.
- Public internet exposure requires TLS and strong passwords.
- If running behind a reverse proxy, `TRUSTED_PROXIES` must be configured so Symfony sees the real client IP for rate limiting.
- Same-origin deployment is the expected model for both development and production.

## Main Screens

- Product CRUD.
- Shopping list.
- Expiring soon report.
- Settings for categories, storage locations, and stores.
- Profile page.
- Login and registration.

## API Surface

Public endpoints:

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/health` | Liveness check |
| POST | `/api/auth/register` | Register user |
| POST | `/api/auth/login` | Log in and create session |

Authenticated endpoints:

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/auth/me` | Current user |
| POST | `/api/auth/logout` | Invalidate session |
| GET | `/api/products` | List products |
| POST | `/api/products` | Create product |
| GET | `/api/products/{id}` | Get product |
| PATCH | `/api/products/{id}` | Update product |
| DELETE | `/api/products/{id}` | Delete product |
| POST | `/api/products/{id}/movements` | Add stock movement |
| GET | `/api/categories` | List categories |
| POST | `/api/categories` | Create category |
| GET | `/api/storage-locations` | List storage locations |
| POST | `/api/storage-locations` | Create storage location |
| GET | `/api/stores` | List stores |
| POST | `/api/stores` | Create store |
| GET | `/api/reports/expiring?days=N` | Products expiring soon |
| GET | `/api/reports/shopping-list` | Products at or below minimum stock |

## Current Technical Shape

- Backend: PHP 8.5 with Symfony 8.0.
- Frontend: Vue 3, TypeScript, Vite, Pinia, Vue Router.
- Database: SQLite.
- i18n: `pt_BR` default, `en` fallback.
- PWA support for offline cache of product list and shopping list.
- Backend tests: PHPUnit and Symfony functional tests.
- Frontend tests: Vitest component and store tests.
- CI: GitHub Actions for backend and frontend.
- Code quality: PHP CS Fixer, PHPStan, ESLint, and Prettier.

## Implemented MVP Status

The MVP implementation currently includes:

- Symfony backend skeleton and domain model.
- User registration, login, logout, and current-user endpoint.
- Product CRUD.
- Stock movement endpoint.
- Shopping list report.
- Expiring products report.
- Category, storage location, and store endpoints.
- Vue SPA with authenticated routes.
- Product, shopping list, expiring soon, settings, profile, login, and registration pages.
- PWA runtime caching for product list and shopping list.
- Demo fixtures with categories, storage locations, and demo user.
- Functional API flow tests.
- Frontend component and store tests.

Verified golden path:

- Health check works.
- Unauthenticated `/api/auth/me` returns 401.
- Demo login works.
- Authenticated `/api/auth/me` works.
- Seed categories are available.
- Products can be created.
- Consumption movement decrements quantity.
- Shopping list includes products where quantity is at or below minimum stock.
- Expiring report returns products within the selected window.
- Negative stock movement is rejected with 422.
- Logout invalidates the session.

## Known Limitations and Follow-up Work

- Production image build and clean-host production boot still need full end-to-end verification.
- Symfony 8.1 upgrade should be planned after release because Symfony 8.0 support ends in July 2026.
- Change-password endpoint and form are not fully implemented.
- Decimal display in the frontend may show trailing zeros.
- CSRF handling should be revisited if the SPA and API are ever served from different origins.

## Non-Goals for the MVP

- Public SaaS hosting.
- Multi-tenant organization support.
- Heavy concurrent warehouse-style inventory workloads.
- Barcode scanning.
- Purchase price tracking.
- Supplier management.
- Advanced analytics.
