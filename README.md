# HomeStock

Technical README for the HomeStock project.

Business scope, product behavior, inventory rules, category rules, shopping-list rules, and MVP limitations are documented in [`PRODUCT_DESCRIPTION.md`](./docs/PRODUCT_DESCRIPTION.md).

The implementation plan and current project status are documented in [`PLAN.md`](./docs/PLAN.md). The original MVP notes are in [`MVP.md`](./docs/MVP.md).

## Stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.5 + Symfony 8.0 REST/JSON API |
| Database | SQLite file stored in a Docker named volume |
| Frontend | Vue 3 + TypeScript + Vite |
| Frontend state/router | Pinia + Vue Router |
| i18n | `symfony/translation` + `vue-i18n`; default `pt_BR`, fallback `en` |
| PWA | `vite-plugin-pwa` + Workbox runtime cache |
| Auth | Symfony Security `json_login`, `argon2id`, stateful session cookie |
| Infra | Docker Compose for dev and production |
| Web server | Nginx |
| Process supervisor | Supervisord in the production image |
| Backend tests | PHPUnit + Symfony functional tests |
| Frontend tests | Vitest + Vue Test Utils + MSW |
| Quality tools | PHP CS Fixer, PHPStan, ESLint, Prettier |
| CI | GitHub Actions backend and frontend workflows |

## Repository Layout

```text
.
├── backend/                  Symfony 8 application
│   ├── src/
│   │   ├── Controller/Api/   API controllers
│   │   ├── Entity/           Doctrine entities and enums
│   │   ├── Repository/       Doctrine repositories
│   │   ├── Security/         JSON auth handlers
│   │   └── DataFixtures/     Seed data
│   ├── config/               Symfony configuration
│   ├── migrations/           Doctrine migrations
│   ├── public/               Symfony front controller
│   ├── tests/Functional/     Symfony functional tests
│   ├── Dockerfile.dev        Development PHP image
│   ├── Dockerfile            Production multi-stage image
│   └── phpunit.dist.xml
├── frontend/                 Vue 3 SPA
│   ├── src/api/              API client and types
│   ├── src/components/       Shared Vue components
│   ├── src/i18n/             Frontend translations
│   ├── src/pages/            Route pages
│   ├── src/router/           Vue Router configuration
│   ├── src/stores/           Pinia stores
│   └── src/test/             Frontend test setup
├── docker/                   Nginx and supervisord configs
├── docs/
│   ├── PRODUCT_DESCRIPTION.md  Product and business rules
│   ├── MVP.md                  MVP source notes
│   └── PLAN.md                 Implementation plan and status
├── docker-compose.yml        Development stack
├── docker-compose.prod.yml   Production stack
├── Makefile                  Project commands
└── postman_test.json         Postman collection
```

## Development

Prerequisites:

- Docker 24+
- `make`

Start the development stack:

```bash
make build
make up
make install
make migrate
make fixtures
```

Smoke test:

```bash
curl -s http://localhost:8080/api/health
```

Expected response:

```json
{ "status": "ok" }
```

Development services:

| URL | Service |
|---|---|
| `http://localhost:8080` | Symfony API through Nginx |
| `http://localhost:5173` | Vite dev server |

Demo credentials after `make fixtures`:

```text
email: demo@homestock.local
password: demopass123
```

## Make Targets

| Target | Description |
|---|---|
| `make build` | Build development images |
| `make up` | Start development containers |
| `make down` | Stop development containers |
| `make restart` | Restart development containers |
| `make logs` | Tail container logs |
| `make sh` | Open a shell inside the PHP container |
| `make install` | Install backend and frontend dependencies |
| `make migrate` | Run Doctrine migrations |
| `make fixtures` | Load seed data |
| `make test` | Run backend and frontend tests |
| `make test-backend` | Run PHPUnit |
| `make test-frontend` | Run Vitest |
| `make lint` | Run PHP CS Fixer dry run and ESLint |
| `make stan` | Run PHPStan |
| `make backup` | Snapshot the SQLite database into `./backups/` |
| `make prod-build` | Build the production image |
| `make prod-up` | Start the production stack |
| `make prod-down` | Stop the production stack |

## Testing

Backend:

```bash
make test-backend
```

Frontend:

```bash
make test-frontend
```

All tests:

```bash
make test
```

Static analysis and linting:

```bash
make lint
make stan
```

## API

Base URL in development:

```text
http://localhost:8080
```

All API endpoints accept and return JSON. Authenticated endpoints require the Symfony session cookie created by `POST /api/auth/login`.

Public endpoints:

| Method | Path |
|---|---|
| `GET` | `/api/health` |
| `POST` | `/api/auth/register` |
| `POST` | `/api/auth/login` |

Authenticated endpoints:

| Method | Path |
|---|---|
| `GET` | `/api/auth/me` |
| `POST` | `/api/auth/logout` |
| `GET` | `/api/products` |
| `POST` | `/api/products` |
| `GET` | `/api/products/{id}` |
| `PATCH` | `/api/products/{id}` |
| `DELETE` | `/api/products/{id}` |
| `POST` | `/api/products/{id}/movements` |
| `GET` | `/api/categories` |
| `POST` | `/api/categories` |
| `GET` | `/api/storage-locations` |
| `POST` | `/api/storage-locations` |
| `GET` | `/api/stores` |
| `POST` | `/api/stores` |
| `GET` | `/api/reports/expiring?days=N` |
| `GET` | `/api/reports/shopping-list` |

Validation errors return HTTP 422:

```json
{
  "error": "validation_failed",
  "fields": {
    "field": "message"
  }
}
```

Other errors use a single `error` key, for example:

```json
{ "error": "unauthenticated" }
```

Business behavior for these endpoints is documented in [`PRODUCT_DESCRIPTION.md`](./docs/PRODUCT_DESCRIPTION.md).

## Postman

Import [`postman_test.json`](./postman_test.json) into Postman.

Default collection variables:

| Variable | Value |
|---|---|
| `baseUrl` | `http://localhost:8080` |
| `email` | `demo@homestock.local` |
| `password` | `demopass123` |

Postman keeps the Symfony session cookie after login. If the cookie jar is disabled, authenticated requests return 401.

CLI run with Newman:

```bash
npx newman run postman_test.json \
  --env-var baseUrl=http://localhost:8080 \
  --env-var email=demo@homestock.local \
  --env-var password=demopass123
```

## Production

Production uses `docker-compose.prod.yml` and the multi-stage `backend/Dockerfile`.

The production image includes:

- Frontend build.
- Composer production install.
- PHP runtime.
- Nginx.
- Supervisord.

Start production:

```bash
make prod-build
make prod-up
```

Stop production:

```bash
make prod-down
```

SQLite data is persisted in a Docker named volume. Backups are written to `./backups/`:

```bash
make backup
```

Behind a reverse proxy, configure `TRUSTED_PROXIES` so Symfony receives the real client IP.

## CI

Backend workflow:

```text
.github/workflows/backend.yml
```

Frontend workflow:

```text
.github/workflows/frontend.yml
```

The backend workflow runs Composer install, schema validation, PHP CS Fixer dry run, PHPStan, and PHPUnit.

The frontend workflow runs npm install, linting, Vitest, and Vite production build.
