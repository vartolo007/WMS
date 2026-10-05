# Implementation Plan

**Current phase: 1**

Each phase: plan mode → user approval → code + tests → `php artisan test` and `pint` green → tick tasks → log line.

## Phase 0 — Project setup
- [x] Install Breeze (Blade), Pest, brick/math; configure Tailwind + Alpine
- [x] Arabic RTL base layout (`lang="ar" dir="rtl"`), Arabic validation messages
- [x] Theme via CSS variables: Tailwind `primary` → `var(--color-primary)`; palette in `config/wms.php`
- [x] `APP_TIMEZONE`, `config/wms.php` (trial_days), `.env.example` with `ADMIN_EMAIL`, `ADMIN_PASSWORD`
- [x] `composer.json` authors + README with project summary and authorship
- [x] Git initialised, first commit

**Done when:** the app runs, login page renders in Arabic RTL, `php artisan test` passes.

## Phase 1 — Platform, tenancy, roles (FR-01 → FR-06)
- [ ] Enums: Role, CompanyStatus
- [ ] Migrations + models: companies, users changes (company_id, role, is_active)
- [ ] `BelongsToCompany` trait (applied to tenant models as they are created)
- [ ] Company registration: Company (trial) + owner User in one transaction
- [ ] `EnsureSubscriptionActive`, `EnsureRole` middleware
- [ ] Super admin seeder; admin page: list companies, activate / suspend / extend trial
- [ ] Owner: create / edit / deactivate warehouse users
- [ ] Role-based dashboards (empty shells)

**Done when:** a company registers, creates warehouses, a suspended company is blocked,
and tests prove company A cannot see company B's users or data.

## Phase 2 — Master data and settings (FR-07 → FR-09, FR-25, FR-26)
- [ ] products (barcode unique per company, location, min_stock, is_active)
- [ ] parties (customer / supplier, is_active)
- [ ] Company settings page (FR-25): name, logo upload, palette colour, currency symbol, markup_percent
- [ ] Currency formatting component used for every amount
- [ ] Profile page (FR-26) available to all roles
- [ ] Policies (Q2 default: owner and warehouses can manage products and parties)
- [ ] Search products by name / barcode

**Done when:** CRUD works, barcode uniqueness is per company, isolation tests pass,
changing the colour changes the UI for all company users, an SVG logo is rejected.

## Phase 3 — Purchases, sales, posting (FR-10 → FR-14, FR-18) ⚠ review posting with Kamal first
- [ ] Enum MovementType with `effects()` and helpers; MovementStatus, PartyType
- [ ] Migrations + models: movements, movement_items, operations
- [ ] `WarehouseScope` on movements and operations (incoming transfers visible to the target)
- [ ] `Money`, `NumberGenerator`, `StockService`, `PricingService`
- [ ] `PostingService::post()` with discount distribution
- [ ] `MovementService::purchase()` and `sale()`
- [ ] Invoice screen (Alpine.js): barcode input, rows, live totals, suggested price
- [ ] Movements list and movement details page

**Done when:** purchase increases stock, sale decreases it, sale beyond stock is rejected,
Σ net_value = movement total, the SRS 6.2 examples pass as tests.

## Phase 4 — Returns (FR-15)
- [ ] `MovementService::return()` for purchase_return and sale_return
- [ ] Returnable quantity, original price, proportional discount
- [ ] Return screen starting from the original invoice

**Done when:** returnable quantity is enforced and the 2,000 → 1,800 example passes.

## Phase 5 — Reports and dashboard (FR-19 → FR-24)
- [ ] `ReportFilters` + `ReportService` (summary, details, ledger, stock)
- [ ] Daily movements and daily net sales per warehouse
- [ ] Product ledger with opening balance (SRS 3.6.4 example as a test)
- [ ] Party movement, stock report, below_min, moved, stagnant
- [ ] Reports screen with filters; print-friendly view
- [ ] Dashboards filled per role

**Done when:** every report in SRS 3.6.2 is reachable from the engine and matches hand-calculated data.

## Phase 6 — Transfers and cancellation ⚠ BLOCKED (Q3, Q5)
- [ ] `MovementService::transfer()` (after Q3 is answered)
- [ ] `MovementService::cancel()` + `PostingService::unpost()` (after Q5 is answered)

## Phase 7 — Hardening
- [ ] Daily database backup via Laravel Scheduler
- [ ] Full review against the SRS traceability matrix (section 12)
- [ ] Final README

## Phase log
<!-- one line per finished task: YYYY-MM-DD — phase — what was done -->
2026-10-05 — 0 — Breeze (Blade) + Pest 3 + brick/math installed; Tailwind v3 with Alpine from Breeze
2026-10-05 — 0 — Arabic RTL layouts (Cairo font), hand-written `lang/ar` + `lang/ar.json`, Arabic welcome page
2026-10-05 — 0 — Theme: `--color-primary` partial + Tailwind `primary`; palette and trial_days in `config/wms.php`
2026-10-05 — 0 — `APP_TIMEZONE` from env, `.env.example` (MySQL, ADMIN_*, WMS_TRIAL_DAYS), tests on `wms_testing`
2026-10-05 — 0 — composer authors + Arabic README; leftovers and Sanctum removed
2026-10-05 — 0 — Git initialised, first commit; 30 tests pass, Pint clean
