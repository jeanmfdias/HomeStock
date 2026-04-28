# HomeStock — Implementation Plan

This is the running execution plan for building HomeStock from MVP.md. It mirrors `~/.claude/plans/read-the-mvp-md-understand-eager-puzzle.md` (the approved planning doc) and tracks completed and remaining work.

## Decisions (resolved with the user)
1. **Tech versions:** PHP 8.5 (8.5.5) + Symfony 8.0 (8.0.x). Plan upgrade to Symfony 8.1 when it ships in May 2026 (8.0 EOL: July 2026).
2. **Auth:** email + password — Symfony Security `json_login` firewall, `argon2id` hashing, session cookie + CSRF, login throttling 5/min/IP.
3. **Integration:** Vue 3 SPA + REST API (separate apps).
4. **i18n:** `pt_BR` default, `en` fallback. `symfony/translation` + `vue-i18n` wired from day one.

---

## Completed steps

### ✅ Step 0 — Update MVP.md (v0.0.2)
- Bumped version, removed contradictory auth wording, expanded product fields (unit type, storage location, min stock, stock movements, seed categories), pinned PHP/Symfony versions, added i18n + PWA + tests + CI sections, added Changelog block.

### ✅ Step 1 — Repository skeleton & Docker
Created:
- `docker-compose.yml` (dev: php-fpm, nginx, node services)
- `docker-compose.prod.yml` (single-image app + named SQLite volume + backups bind-mount)
- `backend/Dockerfile.dev` (php:8.5-fpm-alpine, intl, pdo_sqlite, opcache, apcu)
- `backend/Dockerfile` (3-stage prod: frontend build, composer install, runtime with nginx + supervisord)
- `docker/nginx/dev.conf`, `docker/nginx/prod.conf`, `docker/supervisord.conf`
- `Makefile` (`up`, `down`, `restart`, `logs`, `sh`, `install`, `migrate`, `fixtures`, `test`, `lint`, `stan`, `backup`, `prod-build`, `prod-up`, `prod-down`)
- `.gitignore`, `.dockerignore`

### ✅ Step 2 — Symfony 8.0 backend skeleton
- `composer create-project symfony/skeleton:^8.0` resolved to `8.0.8`.
- Bundles installed: `orm-pack`, `security-bundle`, `validator`, `serializer-pack`, `translation`, `rate-limiter`, `uid`, `property-info/access`, `doctrine-migrations-bundle`. Dev: `maker-bundle`, `doctrine-fixtures-bundle`, `test-pack`, `phpstan` (+ symfony/doctrine extensions), `php-cs-fixer`.
- Configured: `framework.yaml` (locales, CSRF, session cookie), `doctrine.yaml` (sqlite, test isolated DB), `security.yaml` (json_login + entity provider + login_throttling + access_control), `.env` DATABASE_URL.

### ✅ Step 3 — Domain model
Entities: `User`, `Category`, `StorageLocation`, `Store`, `UnitType` (enum), `MovementReason` (enum), `Product`, `StockMovement`. Repositories for all. Initial migration generated and applied. Fixtures load 11 categories, 5 storage locations, and a `demo@homestock.local / demopass123` user.

### ✅ Step 4 — Auth endpoints
- `POST /api/auth/register`, `POST /api/auth/login` (json_login), `POST /api/auth/logout`, `GET /api/auth/me`.
- `JsonAuthenticationSuccessHandler` / `JsonAuthenticationFailureHandler`.
- Login throttled 5/min/IP. Stateful session via Symfony cookie.

### ✅ Step 5 — Product / movement / report / reference endpoints
- `GET/POST /api/products`, `GET/PATCH/DELETE /api/products/{id}`, `POST /api/products/{id}/movements`.
- `GET /api/reports/expiring?days=N`, `GET /api/reports/shopping-list`.
- `GET/POST /api/categories | /api/storage-locations | /api/stores`.
- Products are user-scoped (404 on cross-user access). Stock movements update `quantity` transactionally and reject going negative.
- Functional tests (`tests/Functional/HealthTest.php`, `tests/Functional/ApiFlowTest.php`): 3 tests / 23 assertions green.

---

## Remaining steps

### Step 6 — Vue 3 frontend
- `npm create vite@latest frontend -- --template vue-ts`.
- Add: `pinia`, `vue-router`, `vue-i18n`, `@vueuse/core`, `vite-plugin-pwa`.
- Dev tooling: `eslint`, `prettier`, `vitest`, `@vue/test-utils`, `msw`.
- Layout:
  - `src/api/client.ts` — fetch wrapper (credentials include, X-CSRF-TOKEN header, 401 redirect).
  - `src/stores/auth.ts` — Pinia store calling `/api/auth/*`.
  - `src/i18n/` — `pt-BR.json` (default) and `en.json` mirroring backend keys.
  - `src/router/index.ts` — guarded routes.
  - Pages: `Login`, `Register`, `ProductsList` (filter chips), `ProductForm`, `ShoppingList`, `ExpiringSoon`, `Settings`, `Profile`.
  - Components: `<ProductCard>`, `<QuantityStepper>`, `<ExpirationBadge>`, `<LocaleSwitcher>`.
- PWA: `vite-plugin-pwa` manifest + Workbox precache; runtime cache for `GET /api/products` and `GET /api/reports/shopping-list`.
- Tests: Vitest snapshots + interaction tests for `<QuantityStepper>` and `<ExpirationBadge>`; MSW-mocked API tests for the auth store.

### Step 7 — CI (GitHub Actions)
- `.github/workflows/backend.yml`: `shivammathur/setup-php@v2` (php-version: 8.5), `composer install --no-interaction`, `php-cs-fixer fix --dry-run`, `phpstan analyse`, `phpunit` against the test SQLite DB.
- `.github/workflows/frontend.yml`: `actions/setup-node@v4` (Node 22), `npm ci`, `npm run lint`, `npm run test -- --run`, `npm run build`.
- Both run on `push` and `pull_request`. Cache composer + npm.
- Optional `docker.yml`: build prod image on tag push, push to GHCR.

### Step 8 — Production polish
- Verify `docker-compose.prod.yml` boots end-to-end (build, migrate, healthcheck).
- README.md: quickstart (`make up`), demo credentials, LAN-only warning, `make backup`/restore docs.
- Add `bin/console app:create-user` command (interactive admin creation in prod).
- Add a basic preload.php for opcache preloading (referenced in `Dockerfile`).
- Verify `make backup` produces a restorable file.

### Step 9 — End-to-end verification
1. `make up` brings stack up; `localhost:8080/api/health` → `{"status":"ok"}`; frontend at `localhost:5173`.
2. Register, log in, add 3 products across unit types and categories, register a `consume` movement, confirm `quantity` drops.
3. Set one product's `min_stock` above its `quantity` → it appears in `/shopping-list`.
4. Set another's expiration to today+3 → it appears in `/expiring?days=7`.
5. `make test` runs all PHPUnit + Vitest tests green.
6. CI on a PR runs both workflows green.
7. `make backup` produces a restorable `.db` file.

---

## Notes / risks
- Symfony 8.0 line is supported until **July 2026**. Schedule an 8.1 upgrade after May 2026.
- SQLite + WAL is fine for a single-host LAN deployment with 1–2 concurrent users; not appropriate for multi-tenant or write-heavy use.
- `decimal` columns round-trip as strings in PHP. Compare quantities with `bccomp`, never `===`.
- Login throttling is IP-based; behind a reverse proxy, ensure `TRUSTED_PROXIES` is configured so Symfony sees the real client IP.
