# HomeStock

A self-hosted household stock manager. Tracks pantry, fridge, freezer, medicine, cleaning and other home supplies; records purchases and consumption as explicit stock movements; and produces a shopping list when items run low or are about to expire.

> **LAN-only.** Designed to run on a home server inside a trusted network. Do not expose it to the public internet without TLS and strong passwords.

Current MVP version: **0.0.2** (see [`MVP.md`](./MVP.md) for the product spec and [`PLAN.md`](./PLAN.md) for the running implementation plan).

---

## Stack

| Layer       | Choice                                                      |
|-------------|-------------------------------------------------------------|
| Backend     | PHP 8.5 + Symfony 8.0 (REST/JSON)                           |
| Database    | SQLite (file inside a Docker named volume, WAL mode)        |
| Frontend    | Vue 3 + TypeScript + Vite (planned, see PLAN.md Step 6)     |
| Auth        | Symfony `json_login` firewall, `argon2id`, session cookie   |
| i18n        | `symfony/translation` + `vue-i18n` (`pt_BR` default, `en` fallback) |
| Infra       | Docker Compose (dev + prod)                                 |
| CI          | GitHub Actions (planned, see PLAN.md Step 7)                |
| Quality     | PHPUnit, Vitest, php-cs-fixer, PHPStan, ESLint, Prettier    |

---

## Repository layout

```
.
├── backend/                Symfony 8 application
│   ├── src/
│   │   ├── Controller/Api/   AuthController, ProductController, ReferenceController, ReportController, HealthController
│   │   ├── Entity/           User, Category, Product, StockMovement, StorageLocation, Store, UnitType, MovementReason
│   │   ├── Repository/       Doctrine repositories
│   │   ├── Security/         JSON success/failure handlers
│   │   └── DataFixtures/     Seed categories, locations, demo user
│   ├── config/, migrations/, tests/Functional/, public/
│   ├── Dockerfile.dev / Dockerfile (3-stage prod)
│   └── phpunit.dist.xml
├── frontend/               Vue 3 SPA (skeleton — Step 6 of PLAN.md)
├── docker/                 nginx + supervisord configs
├── docker-compose.yml      Dev stack (php-fpm, nginx, node)
├── docker-compose.prod.yml Single-image prod + named volume + backups bind-mount
├── Makefile                Convenience targets (up/down/migrate/fixtures/test/backup/...)
├── postman_test.json       Postman collection covering every endpoint
├── MVP.md                  Product specification
└── PLAN.md                 Implementation plan & status
```

---

## Quickstart (development)

Prerequisites: Docker 24+ and `make`.

```bash
# 1. Build images and start the dev stack
make build
make up

# 2. Install backend deps and run migrations
make install
make migrate

# 3. Load seed data (11 categories, 5 storage locations, demo user)
make fixtures

# 4. Smoke test
curl -s http://localhost:8080/api/health
# => {"status":"ok"}
```

Services exposed in dev:

| URL                      | Service                         |
|--------------------------|---------------------------------|
| http://localhost:8080    | Backend (Symfony via nginx)     |
| http://localhost:5173    | Frontend (Vite dev server)      |

### Demo credentials (after `make fixtures`)

```
email:    demo@homestock.local
password: demopass123
```

### Common Make targets

| Target              | Description                                       |
|---------------------|---------------------------------------------------|
| `make up`           | Start dev stack (php, nginx, node)                |
| `make down`         | Stop dev stack                                    |
| `make restart`      | `down` + `up`                                     |
| `make logs`         | Tail container logs                               |
| `make sh`           | Open a shell inside the PHP container             |
| `make install`      | `composer install` + `npm install`                |
| `make migrate`      | Run Doctrine migrations                           |
| `make fixtures`     | Load seed data                                    |
| `make test`         | Run PHPUnit + Vitest                              |
| `make test-backend` | PHPUnit only                                      |
| `make test-frontend`| Vitest only                                       |
| `make lint`         | php-cs-fixer (dry run) + ESLint                   |
| `make stan`         | PHPStan analyse                                   |
| `make backup`       | Snapshot the SQLite DB into `./backups/`          |
| `make prod-build`   | Build the production image                        |
| `make prod-up`      | Start the production stack                        |
| `make prod-down`    | Stop the production stack                         |

---

## Running tests

### Backend (PHPUnit functional + unit)

```bash
make test-backend
# or, inside the php container:
make sh
vendor/bin/phpunit
```

The functional suite (`tests/Functional/`) boots a Symfony `WebTestCase`, recreates the schema in the dedicated test SQLite DB, and walks the full register → login → product CRUD → stock movement → reports flow.

### Frontend (Vitest)

```bash
make test-frontend
```

### All tests

```bash
make test
```

### Static analysis & formatting

```bash
make lint   # php-cs-fixer --dry-run + eslint
make stan   # phpstan analyse
```

---

## Manual API testing with Postman

A ready-to-import collection is provided as [`postman_test.json`](./postman_test.json).

1. Postman → **File → Import** → select `postman_test.json`.
2. Open the collection's **Variables** tab. Defaults match the dev fixtures:
   - `baseUrl` = `http://localhost:8080`
   - `email` = `demo@homestock.local`
   - `password` = `demopass123`
3. Run requests in order:
   1. **Health → Health check**
   2. **Auth → Register** (skip if you already created the user; 409 means the email is taken — which is fine)
   3. **Auth → Login** — Symfony returns a `PHPSESSID` cookie that Postman keeps for the rest of the session
   4. **Categories → List categories** — populates `categoryId`
   5. **Storage Locations → List storage locations** — populates `storageLocationId`
   6. **Products → Create product** — populates `productId`
   7. Any other request

Test scripts inside the collection capture IDs into collection variables, so subsequent requests just work.

> Auth is session-cookie based. Postman's cookie jar is enabled by default; if you disabled it, requests after login will return 401.

You can also drive the same collection from the CLI with [Newman](https://github.com/postmanlabs/newman):

```bash
npx newman run postman_test.json \
  --env-var baseUrl=http://localhost:8080 \
  --env-var email=demo@homestock.local \
  --env-var password=demopass123
```

---

## API specification

Base URL (dev): `http://localhost:8080`. All endpoints accept and return JSON. Authenticated routes require a valid Symfony session cookie obtained from `POST /api/auth/login`.

### Public endpoints

| Method | Path                  | Purpose                                  |
|--------|-----------------------|------------------------------------------|
| GET    | `/api/health`         | Liveness probe → `{"status":"ok"}`       |
| POST   | `/api/auth/register`  | Create a user                            |
| POST   | `/api/auth/login`     | Log in, set session cookie               |

### Authenticated endpoints (`ROLE_USER`)

| Method | Path                                  | Purpose                                                   |
|--------|---------------------------------------|-----------------------------------------------------------|
| GET    | `/api/auth/me`                        | Current user                                              |
| POST   | `/api/auth/logout`                    | Invalidate session                                        |
| GET    | `/api/products`                       | List products (filters: `category`, `storage`, `expiring_within_days`, `below_min_stock`) |
| POST   | `/api/products`                       | Create product                                            |
| GET    | `/api/products/{id}`                  | Get product                                               |
| PATCH  | `/api/products/{id}`                  | Partial update                                            |
| DELETE | `/api/products/{id}`                  | Delete product                                            |
| POST   | `/api/products/{id}/movements`        | Add stock movement (`delta` ≠ 0, `reason` ∈ enum)         |
| GET    | `/api/categories`                     | List categories                                           |
| POST   | `/api/categories`                     | Create category                                           |
| GET    | `/api/storage-locations`              | List storage locations                                    |
| POST   | `/api/storage-locations`              | Create storage location                                   |
| GET    | `/api/stores`                         | List stores                                               |
| POST   | `/api/stores`                         | Create store                                              |
| GET    | `/api/reports/expiring?days=N`        | Products expiring within `N` days (1–365, default 7)      |
| GET    | `/api/reports/shopping-list`          | Products where `quantity <= minStock`                     |

### Domain rules

- **Products are user-scoped.** Cross-user reads/writes return 404.
- **Quantity is never edited directly through normal flows** — adjust via `POST /api/products/{id}/movements`. Allowed reasons: `purchase`, `consume`, `discard`, `adjust`. Going negative is rejected.
- **Decimals as strings.** `quantity` and `minStock` round-trip as strings (Doctrine `decimal`). Compare with `bccomp`, never `===`.
- **Unit types:** `unit`, `g`, `kg`, `ml`, `l`.
- **Expiration:** optional, but required for categories whose `requiresExpiration = true` (Market, Vegetables & Fruits, Meat, Beverages, Bakery, Frozen, Medicine).
- **Shopping list rule:** a product appears when `quantity <= minStock`.
- **Auth throttle:** `/api/auth/login` is limited to 5 attempts per minute per IP.

### Error shape

Validation failures return 422 with:

```json
{ "error": "validation_failed", "fields": { "<field>": "<message>" } }
```

Other domain errors return a single-key object, e.g. `{"error": "email_taken"}` (409), `{"error": "not_found"}` (404), `{"error": "unauthenticated"}` (401).

### Sample payloads

**Register**

```json
POST /api/auth/register
{ "email": "you@example.com", "name": "You", "password": "supersecret" }
```

**Login**

```json
POST /api/auth/login
{ "email": "you@example.com", "password": "supersecret" }
```

**Create product**

```json
POST /api/products
{
  "name": "Milk",
  "brand": "Acme",
  "categoryId": 1,
  "storageLocationId": 2,
  "preferredStoreId": null,
  "unitType": "l",
  "quantity": "2",
  "minStock": "1",
  "expirationDate": "2026-12-31",
  "notes": "Whole milk"
}
```

**Stock movement (consume 1.5 L)**

```json
POST /api/products/42/movements
{ "delta": "-1.5", "reason": "consume" }
```

---

## Production

The production stack lives in `docker-compose.prod.yml` and uses the multi-stage `backend/Dockerfile` (frontend build → composer install → runtime with nginx + supervisord). SQLite data is persisted in a named volume; backups land in a bind-mounted `./backups/` directory.

```bash
make prod-build
make prod-up
# ...
make prod-down
```

Backups (any time the dev or prod stack is up):

```bash
make backup
# => backups/backup-YYYYMMDD-HHMMSS.db
```

Behind a reverse proxy, configure `TRUSTED_PROXIES` so Symfony's IP-based login throttling sees the real client IP.

---

## Versioning & roadmap

- **0.0.2** — Auth clarified (email + password), product fields expanded (unit type, storage location, min stock, stock movements), PHP/Symfony versions pinned, i18n + PWA + tests + CI sections added.
- **0.0.1** — Initial idea.

Remaining MVP work (from `PLAN.md`):

- Step 6 — Vue 3 frontend (Pinia, vue-router, vue-i18n, vite-plugin-pwa, Vitest)
- Step 7 — GitHub Actions CI (backend + frontend workflows)
- Step 8 — Production polish (`bin/console app:create-user`, opcache preload, backup verification)
- Step 9 — End-to-end verification

Symfony 8.0 is supported until July 2026; an 8.1 upgrade is scheduled after May 2026.
