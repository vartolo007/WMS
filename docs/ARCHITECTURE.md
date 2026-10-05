# Code Architecture

This document turns SRS v2.2 into code structure. Table and column names come from `docs/schema.json`.
If this document and `schema.json` ever disagree, `schema.json` wins and this document must be fixed.

## 1. Layers and responsibilities

| Layer | Responsibility | Must NOT |
|---|---|---|
| Controller | Receive request, call one service method, return response | Contain business logic or calculations |
| FormRequest | Validate data shape (required, numeric, > 0, decimals, exists in same company) | Query balances or states |
| Policy | Decide if the user is allowed to do the action | Validate data |
| Service | Business rules that need DB state; orchestrate the transaction | Know about HTTP |
| Model | Table, relations, casts, scopes | Contain complex business logic |
| Enum | Behaviour that varies by type | — |

## 2. Folder map

```
app/
├── Enums/
│   ├── MovementType.php      purchase, sale, purchase_return, sale_return, transfer (+ effects)
│   ├── Role.php              owner, warehouse, super_admin
│   ├── CompanyStatus.php     trial, active, suspended
│   ├── MovementStatus.php    confirmed, cancelled
│   └── PartyType.php         customer, supplier
├── Models/
│   ├── Concerns/BelongsToCompany.php
│   ├── Scopes/WarehouseScope.php
│   └── Company, User, Product, Party, Movement, MovementItem, Operation
├── Services/
│   ├── MovementService.php   orchestrator: purchase(), sale(), return(), transfer(), cancel()
│   ├── PostingService.php    the ONLY writer of `operations`: post(), unpost()
│   ├── StockService.php      balance(), lockAndCheck()
│   ├── PricingService.php    lastPurchasePrice(), suggestedPrice()
│   ├── NumberGenerator.php   next(company, type)
│   └── ReportService.php     read-only report engine
├── Support/
│   ├── Money.php             all money/quantity arithmetic (brick/math)
│   └── ReportFilters.php     DTO for report parameters
├── Policies/                 one policy per model
└── Http/
    ├── Middleware/EnsureSubscriptionActive.php
    ├── Middleware/EnsureRole.php
    ├── Requests/
    └── Controllers/
```

## 3. Request flow: saving a sales invoice

1. Middleware: `auth` → `subscription` (company not suspended).
2. FormRequest: shape rules; product and party exist in the user's company.
3. Policy: user may create movements in this warehouse.
4. Controller: `MovementService::sale($data)`.
5. MovementService opens `DB::transaction`:
   1. `StockService::lockAndCheck()` locks product rows (ordered by id) and checks stock in the source warehouse.
   2. `PricingService::suggestedPrice()` fills the unit price of each line.
   3. `Money` computes `line_total`, `subtotal`, `total`.
   4. `NumberGenerator::next()` gives the movement number.
   5. Save `movements` and `movement_items`.
   6. `PostingService::post()` writes the `operations` rows.
6. Commit. Any exception in step 5 rolls everything back.

## 4. Where each validation rule lives (SRS section 7)

| Rule type | Layer | Examples |
|---|---|---|
| Data shape | FormRequest | required, numeric, quantity > 0, max 3 decimals, price ≥ 0, max 2 decimals, at least one line |
| Same-company references | FormRequest (`Rule::exists` scoped by company_id) | product, party, warehouse belong to the company |
| Permission | Policy | warehouse user acts only on its own warehouse |
| Needs DB state | Service, inside the transaction | enough stock, returnable quantity, invoice with active returns, warehouse is active and has role warehouse |
| Isolation | Global scopes | company and warehouse visibility |
| Last line of defence | Database | unique indexes, foreign keys |

Stock is never checked in a FormRequest: another request could consume the stock between the check and the save.

## 5. Components

### 5.1 MovementType (posting rules, SRS 6.1)

```php
public function effects(): array
{
    return match ($this) {
        self::Purchase, self::SaleReturn => [['warehouse_id', +1]],
        self::Sale, self::PurchaseReturn => [['warehouse_id', -1]],
        self::Transfer => [['warehouse_id', -1], ['target_warehouse_id', +1]],
    };
}
```

Also on the enum: `requiresParty()`, `partyType()` (supplier for purchase, customer for sale), `isReturn()`,
`originalType()` (purchase_return → purchase, sale_return → sale), `reducesSourceStock()`.
For returns, `warehouse_id` is copied from the original invoice, so `['warehouse_id', ±1]` is correct.

### 5.2 Tenant isolation

`BelongsToCompany` trait on: Product, Party, Movement, Operation.
- **Not on User:** login looks users up before anyone is authenticated, so a fail-safe scope would block
  every login. Users are always reached through the company instead (`$company->users()`), and
  user-management queries are covered by Policies and isolation tests.
- Global scope: `where company_id = auth()->user()?->company_id`. No user → `company_id = null` → no rows (fail safe).
- `creating` hook: fills `company_id` from the authenticated user if empty.
- `super_admin` has `company_id = null` and only works with `companies` (not scoped).
- `MovementItem` has no `company_id`; it is always reached through its `Movement`.

`WarehouseScope` (applied only when the user role is `warehouse`):
- `movements`: `warehouse_id = me OR target_warehouse_id = me` (the receiving warehouse sees incoming transfers).
- `operations`: `warehouse_id = me`.

### 5.3 Money

- Wraps `Brick\Math\BigDecimal`. Quantity scale 3, money scale 2, `RoundingMode::HALF_UP`.
- `line(qty, price)` → money; `sum([...])`; `ratio(value, total, subtotal)`; `percent(price, percent)`.
- Model casts for decimal columns return strings or Money objects, never floats.

### 5.4 PostingService

`post(Movement $m)`: for each item, for each effect in `$m->type->effects()`, insert one `operations` row:
`company_id, warehouse_id (from the effect field), movement_id, movement_item_id, product_id, party_id, type, date,
qty_in / qty_out (by sign), unit_price, value = line_total, net_value`.

Discount distribution (SRS 6.2):
- If `subtotal = 0` → `net_value = value` (avoid division by zero).
- Otherwise lines 1..n-1: `net = round(value × total ÷ subtotal, 2)`; the last line: `net = total − Σ previous nets`.
  This guarantees Σ `net_value` = movement `total` exactly.
- For a transfer, both rows of an item carry the same `value` and `net_value` (discount is 0).

`unpost(Movement $m)`: ⚠ BLOCKED (see DECISIONS.md). Tentative: delete the movement's rows.

### 5.5 StockService

- `balance(productId, warehouseId, ?asOf)`: `SUM(qty_in) − SUM(qty_out)` from `operations`.
- `lockAndCheck(items, warehouseId)`: aggregate quantities per product (the same product may appear on
  several lines), lock product rows with `lockForUpdate()` ordered by id (prevents deadlocks), then compare.
- Used by: sale, purchase_return, transfer (source warehouse), and cancel of purchase / sale_return / transfer.

### 5.6 PricingService

- `lastPurchasePrice(product)`: `unit_price` of the latest `operations` row of type `purchase`
  for the product in the company, ordered by `date desc, id desc`. Returns and transfers are ignored.
- `suggestedPrice(product)`: `custom_sale_price` if set, else `lastPurchasePrice × markup_percent ÷ 100`, else null.

### 5.7 NumberGenerator

Inside the transaction: lock the company row (`companies ... FOR UPDATE`), then `MAX(number) + 1`
for `(company_id, type)` in `movements`. No extra counters table (schema stays at 7 tables).

### 5.8 Returns

- The original invoice must be confirmed and of `type->originalType()`.
- Returnable quantity per original item = item quantity − quantities in confirmed returns of that item.
- Unit price = original item `unit_price` (not editable).
- `subtotal = Σ qty × unit_price`; `total = round(subtotal × original.total ÷ original.subtotal, 2)`
  (0 if original subtotal is 0); `discount = subtotal − total`.
- `warehouse_id` and `party_id` are copied from the original.

### 5.9 ReportService

- Input: `ReportFilters` (mode, types[], dateFrom, dateTo, warehouseId, partyId, productId, stockFilter).
  For warehouse users `warehouseId` is forced to their own warehouse.
- Base query: `operations` with filters; money columns use `net_value`.
- Modes: `summary` (GROUP BY product / party / warehouse), `details`, `ledger`, `stock`.
- `ledger`: opening balance = `SUM(qty_in − qty_out)` where `date < dateFrom`; running balance with
  `SUM(qty_in − qty_out) OVER (ORDER BY date, id)` plus the opening balance.
- `stock` filters: `below_min` (balance ≤ min_stock, only products with min_stock), `moved` (sale rows in period),
  `stagnant` (balance > 0 and no sale rows in period; include last sale date).
- Daily net sales = Σ `net_value` of `sale` rows − Σ `net_value` of `sale_return` rows for the day, per warehouse.

### 5.10 Subscription and roles

- `EnsureSubscriptionActive`: if the user's company is `suspended` → logout with an Arabic message.
  `super_admin` bypasses. Trial expiry does NOT block automatically.
- `EnsureRole:owner|warehouse|super_admin` for route groups. Fine-grained checks stay in Policies.
- Registration (Breeze register, modified): in one transaction create `Company` (status trial,
  `trial_ends_at = today + config('wms.trial_days')`) and `User` (role owner).
- Super admin is created by a seeder from `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`).

### 5.11 Company settings and theme (FR-25, FR-26)

- One settings page for the owner: company `name`, `logo_path`, `primary_color`, `currency_symbol`, `markup_percent`.
- Palette in `config/wms.php` (`palette` => 8 hex colours, all readable with white text):
  `#1F4E79, #2E75B6, #0F766E, #15803D, #B45309, #B91C1C, #7E22CE, #374151`. Default `#1F4E79`.
  Validation: `Rule::in(config('wms.palette'))`.
- Theme: the base layout prints `<style>:root{--color-primary: …}</style>` from the company setting;
  Tailwind's `primary` colour maps to `var(--color-primary)`. Lighter/darker shades via CSS `color-mix()`.
  The layout is built this way from Phase 0, so the setting only fills the variable later.
- Logo: `mimes:png,jpg,jpeg,webp` + `max:1024` (KB); SVG rejected (can carry scripts). Stored on the `public`
  disk under `companies/{company_id}/logo.{ext}`; replacing a logo deletes the old file.
- Currency: a Blade helper / component formats every amount with `currency_symbol`; never concatenate in views.
- Profile (FR-26): Breeze profile page (name, email, password with current password). Available to all roles.

## 6. Migration rules

- Columns, types and names exactly as `docs/schema.json`. All tables have `timestamps()`.
- Nullable: `users.company_id`, `products.barcode`, `products.location`, `products.custom_sale_price`,
  `products.min_stock`, `parties.phone`, `movements.target_warehouse_id`, `movements.document_no`,
  `movements.related_movement_id`, `movements.party_id`, `operations.party_id`.
- Nullable (v2.2): `companies.logo_path`, `companies.currency_symbol`.
- Defaults: `companies.status = trial`, `companies.markup_percent = 200`, `companies.primary_color = #1F4E79`, `is_active = true` (users, products, parties),
  `movements.status = confirmed`, `movements.discount = 0`.
- Foreign keys: `restrictOnDelete()` everywhere (nothing is hard-deleted by users).
- Indexes: exactly SRS section 5.9 (unique email; unique company_id+barcode; unique company_id+type+number;
  the composite and single indexes listed there).

## 7. Testing strategy

- Unit: `Money` (rounding, 1.25 × 19,999.50 = 24,999.38), `MovementType::effects()`, discount distribution
  (6,000 + 4,000 with discount 1,000 → 5,400 + 3,600), returns proportional discount (2,000 → 1,800).
- Feature: company isolation for every tenant model; warehouse visibility (incoming transfer visible);
  sale beyond stock rejected; suspended company blocked; returnable quantity enforced.
- Every bug fix starts with a failing test that reproduces it.
