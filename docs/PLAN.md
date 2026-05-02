# HomeStock — Implementation Plan

This is the running execution plan for building HomeStock from [MVP.md](./MVP.md). It mirrors `~/.claude/plans/read-the-mvp-md-understand-eager-puzzle.md` (the approved planning doc).

## Decisions (resolved with the user)
1. **Tech versions:** PHP 8.5 (8.5.5) + Symfony 8.0 (8.0.x). Plan upgrade to Symfony 8.1 when it ships in May 2026 (8.0 EOL: July 2026).
2. **Auth:** email + password — Symfony Security `json_login` firewall, `argon2id` hashing, session cookie + `SameSite=Lax`, login throttling 5/min/IP.
3. **Integration:** Vue 3 SPA + REST API, same-origin in dev via Vite proxy, same-origin in prod via the bundled Nginx serving both the SPA and the API.
4. **i18n:** `pt_BR` default, `en` fallback. `symfony/translation` + `vue-i18n` wired from day one.

---

## Completed steps

### ✅ Step 0 — Update [MVP.md](./MVP.md) (v0.0.2)
Bumped version, removed contradictory auth wording, expanded product fields (unit type, storage location, min stock, stock movements, seed categories), pinned PHP/Symfony versions, added i18n + PWA + tests + CI sections, added Changelog block.

### ✅ Step 1 — Repository skeleton & Docker
- `docker-compose.yml` (dev: php-fpm, nginx, node + Vite proxy `/api → nginx`)
- `docker-compose.prod.yml` (single image + named SQLite volume + backups bind-mount)
- `backend/Dockerfile.dev`, `backend/Dockerfile` (3-stage prod), `docker/nginx/{dev,prod}.conf`, `docker/supervisord.conf`
- `Makefile` (`up`, `down`, `restart`, `logs`, `sh`, `install`, `migrate`, `fixtures`, `test`, `lint`, `stan`, `backup`, `prod-build`, `prod-up`, `prod-down`)
- `.gitignore`, `.dockerignore`

### ✅ Step 2 — Symfony 8.0 backend skeleton
`composer create-project symfony/skeleton:^8.0` → `8.0.8`. Bundles: `orm-pack`, `security-bundle`, `validator`, `serializer-pack`, `translation`, `rate-limiter`, `uid`, `property-info/access`, `doctrine-migrations-bundle`. Dev: `maker-bundle`, `doctrine-fixtures-bundle`, `test-pack`, `phpstan` (+ symfony/doctrine extensions), `php-cs-fixer`. Configured: `framework.yaml` (locales, CSRF, session cookie), `doctrine.yaml` (sqlite, isolated test DB), `security.yaml` (json_login + entity provider + login_throttling + access_control), `.env`.

### ✅ Step 3 — Domain model
Entities: `User`, `Category`, `StorageLocation`, `Store`, `UnitType` (enum), `MovementReason` (enum), `Product`, `StockMovement`. Repositories for all. Initial migration generated and applied. Fixtures seed 11 categories, 5 storage locations, and `demo@homestock.local / demopass123`.

### ✅ Step 4 — Auth endpoints
- `POST /api/auth/register`, `POST /api/auth/login` (json_login), `POST /api/auth/logout`, `GET /api/auth/me`.
- `JsonAuthenticationSuccessHandler` / `JsonAuthenticationFailureHandler`.
- Login throttled 5/min/IP. Stateful session cookie.

### ✅ Step 5 — Product / movement / report / reference endpoints
- `GET/POST /api/products`, `GET/PATCH/DELETE /api/products/{id}`, `POST /api/products/{id}/movements`.
- `GET /api/reports/expiring?days=N`, `GET /api/reports/shopping-list`.
- `GET/POST /api/categories | /api/storage-locations | /api/stores`.
- Products are user-scoped (404 on cross-user access). Stock movements update `quantity` transactionally; going negative returns 422 `quantity_cannot_go_negative`.
- Functional tests (`tests/Functional/HealthTest.php`, `tests/Functional/ApiFlowTest.php`): **3 tests / 23 assertions green.**

### ✅ Step 6 — Vue 3 frontend
- Vite + Vue 3 + TypeScript + Pinia + Vue Router + vue-i18n + vite-plugin-pwa, tooling: ESLint + Prettier + Vitest + MSW + @vue/test-utils.
- `src/api/client.ts` — fetch wrapper, `credentials: 'same-origin'`, dispatches `homestock:unauthorized` event on 401.
- `src/stores/auth.ts` — Pinia store backing `/api/auth/*`.
- `src/i18n/locales/{pt-BR,en}.json` — full parity, default `pt-BR`, fallback `en`, persisted in localStorage.
- `src/router/index.ts` — guarded routes; redirects to `/login` on unauthenticated access; intercepts the 401 event.
- Pages: `LoginPage`, `RegisterPage`, `ProductsList`, `ProductForm`, `ShoppingList`, `ExpiringSoon`, `SettingsPage`, `ProfilePage`.
- Components: `ProductCard`, `QuantityStepper`, `ExpirationBadge`, `LocaleSwitcher`.
- PWA manifest + Workbox runtime cache for `GET /api/products` and `GET /api/reports/shopping-list`.
- **Vitest: 3 files / 6 tests green** (auth store via MSW, QuantityStepper, ExpirationBadge). **Production build green** (PWA artifacts generated).
- Dev mode wiring fixed: `VITE_API_PROXY` env var → Vite proxies `/api` so the SPA stays same-origin and the session cookie flows.

### ✅ Step 7 — CI (GitHub Actions)
- `.github/workflows/backend.yml` — PHP 8.5 via `shivammathur/setup-php@v2`, composer cache, `doctrine:schema:validate`, `php-cs-fixer --dry-run`, `phpstan`, `phpunit`. Triggers on `backend/**` paths.
- `.github/workflows/frontend.yml` — Node 22 with `actions/setup-node@v4` npm cache, `npm ci`, lint, `vitest --run`, `vite build`. Triggers on `frontend/**` paths.

### ✅ Step 8 — Production polish
- `backend/config/preload.php` exists (shipped by the framework-bundle recipe; referenced by the prod opcache settings in `Dockerfile`).
- `bin/console app:create-user` — interactive (or `--email --name --password` for scripts) command for creating users in production. Validates email + minimum 8-char password, refuses duplicate emails.
- README quickstart, demo creds, LAN-only warning, backup & restore docs (`make backup` + named-volume restore command).
- `make backup` round-trip verified manually: `sqlite3 .backup` produces a 73KB file containing all 8 tables and seeded data; `sqlite3 backup.db .tables` re-opens cleanly.

### ✅ Step 9 — End-to-end verification
Walked the golden path live against `php -S` against the dev SQLite database:
1. ✅ `/api/health` → `{"status":"ok"}`
2. ✅ Unauthenticated `/api/auth/me` → 401
3. ✅ Login (demo creds) → 200 + user payload + session cookie
4. ✅ Authenticated `/api/auth/me` → 200
5. ✅ List categories → seeded 11
6. ✅ Create 3 products (Milk 2 L / Rice 5 kg / Bleach 1 unit) across `Market` + `Cleaning` categories
7. ✅ Consume `-1.5` L of Milk → `quantity = 0.5`
8. ✅ Shopping list contains Milk (0.5 ≤ 1) and Bleach (1 ≤ 1), correctly excludes Rice (5 > 2)
9. ✅ Expiring within 7 days contains Milk only (Rice expires in 200 days)
10. ✅ Consume `-99` of Milk → 422 `quantity_cannot_go_negative` (transaction aborts, quantity unchanged)
11. ✅ Logout → session invalidated; subsequent `/api/auth/me` → 401

All four verification items in this plan (1–4) pass. Tests (`make test`): backend **3 tests / 23 assertions**, frontend **3 files / 6 tests**, total green.

---

## Outstanding (non-blocking)

- `make prod-build` end-to-end on a clean host — not exercised yet (requires building the multi-stage image + booting the prod stack).
- Plan an upgrade to **Symfony 8.1** after May 2026 (8.0 EOL: July 2026).
- Optional: change-password endpoint + form (Profile page acknowledges it isn't wired). The frontend already shows the placeholder copy.
- Optional: trim trailing zeros for decimal display in the SPA (backend returns `"0.500"`, UI shows it raw).
- Optional: CSRF tokens if the SPA is ever served from a different origin than the API. Currently same-origin both in dev (Vite proxy) and prod (Nginx serves both), so `SameSite=Lax` cookies are sufficient.

---

## Notes / risks
- Symfony 8.0 line is supported until **July 2026**. Schedule an 8.1 upgrade after May 2026.
- SQLite is fine for a single-host LAN deployment with 1–2 concurrent users; not appropriate for multi-tenant or write-heavy use.
- `decimal` columns round-trip as strings in PHP. Compare quantities with `bccomp`, never `===`.
- Login throttling is IP-based; behind a reverse proxy, ensure `TRUSTED_PROXIES` is configured so Symfony sees the real client IP.
