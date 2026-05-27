# Per-Batch Inventory Refactor — HomeStock

## Context

Today every Product carries a single scalar `quantity` and `expiration_date`. Real households buy the same product across multiple purchase dates with different expirations (e.g. two cartons of milk, one expiring 2026-05-10 and one 2026-06-01). Aggregating them into one row hides the per-lot expiration that drives the "Expiring Soon" report and creates confusion when consuming stock.

This refactor introduces a **Batch** entity. Each Product has many Batches; each Batch owns its own `quantity` and `expiration_date`. Stock movements (purchase / consume / discard / adjust) record deltas against a specific Batch. The user explicitly chooses which batch to add to or remove from.

User decisions (confirmed):
- **Migration: WIPE** — drop existing `quantity`/`expiration_date` from `products` and all `stock_movements`. Users start fresh.
- **Add flow:** pick existing batch OR create a new batch.
- **Remove flow:** user picks the batch explicitly.

The work ships in two PRs in order: **Step 1 backend**, then **Step 2 frontend**.

---

## Step 1 — Backend (Symfony 8 + Doctrine + SQLite)

### Files to modify
- [backend/src/Entity/Product.php](backend/src/Entity/Product.php)
- [backend/src/Entity/StockMovement.php](backend/src/Entity/StockMovement.php)
- [backend/src/Repository/ProductRepository.php](backend/src/Repository/ProductRepository.php)
- [backend/src/Controller/Api/ProductController.php](backend/src/Controller/Api/ProductController.php)
- [backend/src/Controller/Api/ReportController.php](backend/src/Controller/Api/ReportController.php)
- [backend/tests/Functional/ApiFlowTest.php](backend/tests/Functional/ApiFlowTest.php)

### Files to create
- `backend/src/Entity/Batch.php`
- `backend/src/Repository/BatchRepository.php`
- `backend/src/Controller/Api/BatchController.php`
- `backend/migrations/Version2026MMDDHHMMSS.php`

### 1.1 New `Batch` entity

Table `batches`:

| column | type | notes |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | |
| `product_id` | INTEGER NOT NULL FK products(id) ON DELETE CASCADE | |
| `quantity` | NUMERIC(12,3) NOT NULL | `Assert\PositiveOrZero` |
| `expiration_date` | DATE NULL | nullable; required only when `product.category.requiresExpiration` |
| `created_at` / `updated_at` | DATETIME NOT NULL | |

Indexes: `(product_id)`, `(expiration_date)`, `(product_id, expiration_date)` (non-unique; supports same-date merge lookup).

Relations:
- `Batch::product` → ManyToOne `Product` inversedBy `batches`, `JoinColumn(nullable: false, onDelete: 'CASCADE')`.
- `Batch::movements` → OneToMany `StockMovement` mappedBy `batch`, `cascade: ['remove']`, `orphanRemoval: true`.

Validation: custom `@Assert\Callback` on the entity — when `product.category.requiresExpiration() === true` and `expirationDate === null`, violate with `expiration_required_for_category`. Enforced only on mutation (does not retroactively invalidate batches if the category flag flips later).

### 1.2 Modified `Product` entity

- **Remove** scalar fields and accessors: `quantity`, `expirationDate`, plus `getQuantity` / `setQuantity` / `getExpirationDate` / `setExpirationDate`.
- **Remove** the `products_expiration_idx` ORM index attribute.
- **Remove** `movements: OneToMany StockMovement` (movements now hang off Batch, not Product).
- **Add** `#[ORM\OneToMany(Batch::class, mappedBy: 'product', cascade: ['persist','remove'], orphanRemoval: true)] $batches`.
- **Replace** `isBelowMinStock()` with computed-on-read versions (NOT persisted):
  ```php
  public function getTotalQuantity(): string {
      $sum = '0';
      foreach ($this->batches as $b) $sum = bcadd($sum, $b->getQuantity(), 3);
      return $sum;
  }
  public function getNextExpiration(): ?\DateTimeImmutable { /* min non-null over batches, NULL if none */ }
  public function isBelowMinStock(): bool {
      return bccomp($this->getTotalQuantity(), $this->minStock, 3) <= 0;
  }
  ```
- Keep `minStock`, `notes`, all foreign keys.

### 1.3 Modified `StockMovement` entity

- **Remove** `product` field + FK + `inversedBy`.
- **Add** `batch: ManyToOne(Batch::class, inversedBy: 'movements')` with `JoinColumn(nullable: false, onDelete: 'CASCADE')`.
- Constructor: `__construct(Batch $batch, string $delta, MovementReason $reason)`.
- Index `stock_movements_product_idx` → `stock_movements_batch_idx` on `(batch_id)`.
- Helper: `getProduct(): Product { return $this->batch->getProduct(); }` for any read-side code.

### 1.4 New `BatchController` endpoints

```
POST   /api/products/{id}/batches
DELETE /api/products/{id}/batches/{batchId}
POST   /api/products/{id}/batches/{batchId}/movements
```

**POST `/api/products/{id}/batches`** — create or merge a batch
- Payload: `{ "quantity": "3.000", "expirationDate": "2026-06-15" | null }`
- 404 if product not owned by current user.
- **Merge rule**: if a batch with the same `expirationDate` (NULL counts as a match) already exists, add `quantity` to it and record a PURCHASE movement against it — do NOT create a duplicate row.
- Otherwise create new Batch + PURCHASE `StockMovement($batch, $quantity, PURCHASE)` in a transaction.
- 422 with `expiration_required_for_category` if validation fails.
- Response: 201, body = full updated **Product** (includes refreshed `batches[]`).

**DELETE `/api/products/{id}/batches/{batchId}`**
- 404 if not owned.
- 409 `batch_has_movements` if any movement rows exist (simpler than soft-delete; user can DISCARD to zero but not remove a batch with history).
- 204 on success.

**POST `/api/products/{id}/batches/{batchId}/movements`** — adjust batch quantity
- Payload: `{ "delta": "-1.5", "reason": "consume" }`
- Computes `newQty = batch.quantity + delta` via `bcadd(_, _, 3)`. Reject `< 0` with 422 `quantity_cannot_go_negative`.
- Persists `StockMovement($batch, $delta, $reason)` in a transaction; updates batch `quantity` and `updatedAt`, plus product `updatedAt`.
- Response: 200, body = full updated **Product**.

### 1.5 Removed endpoint

Delete `POST /api/products/{id}/movements` (current `ProductController::addMovement`). Frontend always targets a batch. `ProductController::applyPayload` no longer reads `quantity` or `expirationDate` (silently ignores them).

### 1.6 New Product serialization shape

```json
{
  "id": 12,
  "name": "Milk", "brand": null,
  "category": { "id": 1, "name": "Market", "slug": "market", "requiresExpiration": true },
  "storageLocation": null, "preferredStore": null,
  "unitType": "l",
  "minStock": "1.000", "notes": null,
  "quantity": "2.500",
  "nextExpiration": "2026-05-10",
  "belowMinStock": false,
  "batches": [
    { "id": 7, "quantity": "1.500", "expirationDate": "2026-05-10", "createdAt": "...", "updatedAt": "..." },
    { "id": 8, "quantity": "1.000", "expirationDate": "2026-06-01", "createdAt": "...", "updatedAt": "..." }
  ],
  "createdAt": "...", "updatedAt": "..."
}
```

`quantity`, `nextExpiration`, `belowMinStock` are computed on read. Batches sorted ascending by `expirationDate` (NULLs last).

### 1.7 Repository changes

`ProductRepository::findForUser`:
- `LEFT JOIN p.batches b` + `addSelect('b')` always (avoid N+1 in serialization).
- `expiringWithinDays` filter: switch to `b.expirationDate IS NOT NULL AND b.expirationDate <= :cutoff`, DISTINCT on product.
- `belowMinStock` filter: `LEFT JOIN p.batches b GROUP BY p.id HAVING COALESCE(SUM(b.quantity), 0) <= p.minStock`.

Add `findExpiringBatchesForUser(User $user, int $days): list<Batch>` — flat batch rows (joined with product), ordered by `b.expirationDate ASC`.

`findShoppingListForUser` — keeps `list<Product>` signature; uses new `belowMinStock` SQL.

### 1.8 ReportController changes

**`/api/reports/expiring`** — per-batch rows:
```json
{ "days": 7,
  "items": [{
    "productId": 12, "batchId": 7, "name": "Milk", "brand": null,
    "quantity": "1.500", "minStock": "1.000", "unitType": "l",
    "expirationDate": "2026-05-10", "category": "Market", "preferredStore": null
  }]
}
```

**`/api/reports/shopping-list`** — per-product, no `expirationDate`:
```json
{ "items": [{
    "id": 12, "name": "Milk", "brand": null,
    "quantity": "0.500", "minStock": "1.000", "unitType": "l",
    "category": "Market", "preferredStore": null
}]}
```

### 1.9 Migration SQL (SQLite rebuild dance)

`up()`:
1. `CREATE TABLE batches` + 3 indexes.
2. `DROP INDEX products_expiration_idx`; rebuild `products` without `quantity` / `expiration_date` via temp-table copy.
3. `DROP TABLE stock_movements` (wipe is accepted); recreate with `batch_id NOT NULL` FK.

`down()`: irreversible — `throwIrreversibleMigrationException('per-batch refactor is one-way')`.

### 1.10 Test rewrite — `ApiFlowTest::testAuthAndProductLifecycle`

1. Create product without quantity/expirationDate; assert `quantity === '0'`, `batches === []`.
2. POST `/api/products/{id}/batches` `{quantity:'2', expirationDate:'+5 days'}` → 1 batch, total `'2'`.
3. POST batch movement delta `-1.5` reason `consume` → total `'0.500'`, `belowMinStock: true`.
4. GET `/api/reports/shopping-list` → product appears.
5. GET `/api/reports/expiring?days=7` → row with `batchId` field.
6. Movement delta `-10` → 422.
7. **New**: Cleaning category + batch with `expirationDate: null` → 201.
8. **New**: Market category + batch with `expirationDate: null` → 422 `expiration_required_for_category`.
9. **New**: two POSTs with same `expirationDate` → still one batch, quantity merged.
10. **New**: DELETE batch with movements → 409 `batch_has_movements`.

### Backend exit criteria
- `php bin/console doctrine:migrations:migrate` runs cleanly on a fresh DB.
- `php bin/phpunit` is green.
- `curl /api/products/{id}` returns the new shape.

---

## Step 2 — Frontend (Vue 3 + TypeScript) — only after Step 1 is merged

### Files to modify
- [frontend/src/api/types.ts](frontend/src/api/types.ts)
- [frontend/src/api/client.ts](frontend/src/api/client.ts)
- [frontend/src/pages/ProductForm.vue](frontend/src/pages/ProductForm.vue)
- [frontend/src/pages/ProductsList.vue](frontend/src/pages/ProductsList.vue)
- [frontend/src/components/ProductCard.vue](frontend/src/components/ProductCard.vue)
- [frontend/src/pages/ExpiringSoon.vue](frontend/src/pages/ExpiringSoon.vue)
- [frontend/src/i18n/locales/en.json](frontend/src/i18n/locales/en.json)
- [frontend/src/i18n/locales/pt-BR.json](frontend/src/i18n/locales/pt-BR.json)

### Files to create
- `frontend/src/components/BatchAddPanel.vue` + `.test.ts`
- `frontend/src/components/BatchRemovePanel.vue` + `.test.ts`
- `frontend/src/components/BatchList.vue`

Reuse existing: [QuantityStepper.vue](frontend/src/components/QuantityStepper.vue) for quantity inputs; [ExpirationBadge.vue](frontend/src/components/ExpirationBadge.vue) inside `BatchList` per-batch row.

### 2.1 Types (`types.ts`)

```ts
export interface Batch {
  id: number
  quantity: string
  expirationDate: string | null
  createdAt: string
  updatedAt: string
}

export interface Product {
  /* ...existing minus quantity, expirationDate... */
  minStock: string
  quantity: string                 // computed total from server
  nextExpiration: string | null
  belowMinStock: boolean
  batches: Batch[]
}

export interface ProductPayload {
  /* drop quantity + expirationDate */
  name: string
  brand?: string | null
  categoryId: number
  storageLocationId?: number | null
  preferredStoreId?: number | null
  unitType: UnitType
  minStock: string
  notes?: string | null
}

export interface BatchPayload {
  quantity: string
  expirationDate?: string | null
}

export interface ExpiringRow {
  productId: number
  batchId: number
  name: string; brand: string | null
  quantity: string; minStock: string; unitType: UnitType
  expirationDate: string | null
  category: string; preferredStore: string | null
}

export interface ShoppingListRow {
  id: number
  name: string; brand: string | null
  quantity: string; minStock: string; unitType: UnitType
  category: string; preferredStore: string | null
}
```

Replace old `ReportRow` with the two distinct row interfaces (they diverged in shape).

### 2.2 API client (`client.ts`)

- **Remove** `addMovement`.
- **Add**:
  ```ts
  createBatch: (productId: number, payload: BatchPayload) =>
    request<Product>(`/api/products/${productId}/batches`, { method: 'POST', body: payload }),
  deleteBatch: (productId: number, batchId: number) =>
    request<void>(`/api/products/${productId}/batches/${batchId}`, { method: 'DELETE' }),
  batchMovement: (productId: number, batchId: number, delta: string, reason: MovementReason) =>
    request<Product>(`/api/products/${productId}/batches/${batchId}/movements`,
      { method: 'POST', body: { delta, reason } }),
  ```
- `createProduct` / `updateProduct` payload type tightens (no `quantity`/`expirationDate`); the compiler will surface every consumer.

### 2.3 ProductForm.vue

Edits product **metadata only** — name, brand, category, locations, unit, **minStock**, notes. Drop the quantity stepper and the expiration date input. Creating a product yields a row with no batches. The user adds stock from the card via the new panels.

### 2.4 ProductCard.vue

- Replace the inline movement form with two action buttons: **Add stock** and **Remove stock**, each toggling its panel inline.
- Above the actions, render a `BatchList` showing each batch as `quantity {unitType} • <ExpirationBadge :date=batch.expirationDate />`, with a small delete button per batch (toast 409 message on failure).
- Replace single `<ExpirationBadge :date="product.expirationDate" />` with a summary using `product.nextExpiration` (or omit when no batches).
- Quantity display unchanged — `product.quantity` is now the server-computed total.

Emits:
- `add-batch (productId, payload)`
- `batch-movement (productId, batchId, delta, reason)`
- `delete-batch (productId, batchId)`

### 2.5 BatchAddPanel.vue

- Radio list of existing batches (`{qty} • {expiration|"no date"}`) plus a special **+ New batch** option.
- Quantity input via `QuantityStepper`.
- If existing batch chosen → submit emits `batch-movement(productId, batchId, '+qty', 'purchase')`.
- If "+ New batch" chosen → reveal optional date input (required iff `product.category.requiresExpiration`), submit emits `add-batch(productId, { quantity, expirationDate })`.

### 2.6 BatchRemovePanel.vue

- Required radio list of existing batches.
- Reason `<select>`: consume / discard / adjust.
- Quantity input (positive number); panel formats as negative delta.
- Disable submit when `qty > selectedBatch.quantity`; show `max: …` hint.
- Emits `batch-movement(productId, batchId, '-qty', reason)`.

### 2.7 ProductsList.vue

Replace the single `addMovement` handler with three handlers wired to the new emits; each calls the matching `api.*` and replaces the product in the list with the response.

### 2.8 ExpiringSoon.vue

- Iterate `ExpiringRow[]`, key by `item.batchId` (not `item.id`).
- Same columns; the product name may repeat across rows when it has multiple expiring batches; the quantity column shows the **batch** quantity. `ExpirationBadge` already handles `string | null`.

### 2.9 i18n additions (en + pt-BR)

New keys under `products.*`: `addStock`, `removeStock`, `pickBatch`, `newBatch`, `batchOptional`, `batches`, `batchEmpty`, `nextExpiration`, `addBatchSuccess`, `cannotDeleteBatchWithHistory`.

### 2.10 Tests

- `BatchAddPanel.test.ts`: existing-batch path emits `batch-movement` with positive delta and `purchase`; new-batch path emits `add-batch`; date required when category requires expiration.
- `BatchRemovePanel.test.ts`: emits `batch-movement` with negative delta and chosen reason; rejects qty > selectedBatch.quantity.
- Existing `ExpirationBadge.test.ts` and `QuantityStepper.test.ts` unchanged.

### Frontend exit criteria
- `npm run typecheck` and `npm run test` are green.
- E2E happy path in browser: create product → add first batch from card → add second batch with different date → both visible in `BatchList` with totals → remove from a chosen batch → expiring report lists each batch as a separate row.

---

## Verification (end-to-end)

After both steps land:

1. Reset DB: `cd backend && rm var/data.db && php bin/console doctrine:migrations:migrate -n && php bin/console doctrine:fixtures:load -n`.
2. Backend: `php bin/phpunit` — all green.
3. Frontend: `cd frontend && npm run typecheck && npm run test`.
4. Run dev stack (Docker compose or `make dev`) and walk the flow:
   - Log in as the demo user.
   - Create a Market-category product "Milk" with `minStock = 1`.
   - From the card: **Add stock** → New batch → 1 L, expiration +10 days.
   - **Add stock** → New batch → 1 L, expiration +30 days. Confirm two batches listed.
   - **Add stock** → New batch → 0.5 L, expiration +10 days. Confirm merge: still two batches; first batch quantity is now 1.5.
   - **Remove stock** → pick the first batch → 0.5 L, reason `consume`. Confirm product total drops, batch quantity drops.
   - Open Expiring Soon (7 days) — should see only the +10-day batch row.
   - Try to delete a batch that has movements — confirm toast / 409.
   - Create a Cleaning-category product, add a batch with no expiration — should succeed.

## Anticipated risks
- **N+1 on product list**: enforce `LEFT JOIN p.batches + addSelect('b')` in `findForUser`.
- **SQLite ALTER limits**: use the temp-table rebuild pattern in the migration; verify locally on a copy of `var/data.db` before merging.
- **Decimal precision**: keep server-side `bcadd`/`bccomp` and string transport; the frontend continues to treat `quantity` as `string`.
- **Same-date merge correctness**: check `expirationDate` equality including the `NULL == NULL` case in PHP comparison; non-unique index supports the lookup.

## Critical files
- [backend/src/Entity/Product.php](backend/src/Entity/Product.php)
- `backend/src/Entity/Batch.php` (new)
- `backend/src/Controller/Api/BatchController.php` (new)
- `backend/migrations/Version2026MMDDHHMMSS.php` (new)
- [backend/src/Repository/ProductRepository.php](backend/src/Repository/ProductRepository.php)
- [frontend/src/api/types.ts](frontend/src/api/types.ts)
- [frontend/src/api/client.ts](frontend/src/api/client.ts)
- [frontend/src/components/ProductCard.vue](frontend/src/components/ProductCard.vue)
- `frontend/src/components/BatchAddPanel.vue` (new)
- `frontend/src/components/BatchRemovePanel.vue` (new)
