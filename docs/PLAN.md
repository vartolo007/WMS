# Implementation Plan

**Current phase: 6** — everything except Phase 6 is complete (see the phase log); Phase 6 stays ⚠ BLOCKED (Q3, Q5) until Mr. Kamal answers

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
- [x] Enums: Role, CompanyStatus
- [x] Migrations + models: companies, users changes (company_id, role, is_active)
- [x] `BelongsToCompany` trait (applied to tenant models as they are created)
- [x] Company registration: Company (trial) + owner User in one transaction
- [x] `EnsureSubscriptionActive`, `EnsureRole` middleware
- [x] Super admin seeder; admin page: list companies, activate / suspend / extend trial
- [x] Owner: create / edit / deactivate warehouse users
- [x] Role-based dashboards (empty shells)

**Done when:** a company registers, creates warehouses, a suspended company is blocked,
and tests prove company A cannot see company B's users or data.

## Screens
Designs live in `docs/design/01-design-system/` as exported from the design tool ("الدفعة N - …".dc.html,
plus `Sidebar` / `Topbar`). Screen numbers below are the designs' own numbers. Each phase builds only its screens;
the SRS wins over a design (see the design decisions in DECISIONS.md).

## Phase 2 — Master data and settings (FR-07 → FR-09, FR-25, FR-26)
Screens: 1–4 (batch 1), 11–15 (batch 5), 19–20 (batch 7).
- [x] App shell and design system from `docs/design/01-design-system/` (design batch 1): Tailwind tokens,
      IBM Plex Sans Arabic, Blade components (button, input, select, badge, table, modal, toast, empty state),
      right sidebar per role (owner / warehouse / super admin), top bar, Alpine mobile menu — screens 1–2
- [x] Owner warehouse selector in the top bar, kept in the session (filters lists and reports;
      "all warehouses" is not valid when creating a movement)
- [x] Login and register redesigned per the design (trial days from config, no terms link) — screens 3–4
- [x] Units in `config('wms.units')`, each with `allows_fraction` (لتر / كيلو yes, علبة / قطعة no)
- [x] products (barcode unique per company, location, min_stock, is_active; unit is a select from the config list;
      manual sale price field) — screen 11 without balance / suggested-price columns and "low only" filter
- [x] parties (customer / supplier, is_active; PartyType enum) — screen 12 without the "حركته" link
- [x] Warehouses page per screen 13 (form beside the list; no "last login" column)
- [x] Company settings page (FR-25): name, logo upload, palette colour, currency symbol,
      markup_percent between 100 and 1000 — screen 14
- [x] Currency formatting component used for every amount — `x-ui.money` exists; the first amounts on screen come with Phase 3
- [x] Profile page (FR-26) available to all roles; remove Breeze "delete account" — screen 15
- [x] Policies (Q2 default: owner and warehouses can manage products and parties) — one `CatalogPolicy::manage()`
- [x] Search products by name / barcode
- [x] Companies page per screen 19 (status cards, search, expired trials first); it is the super admin
      dashboard (FR-24)
- [x] Suspended subscription page per screen 20: users of a suspended company may sign in, then every
      route except logout redirects to it and no data page opens (test covers every route); warehouse
      users see "contact the owner" instead of the platform contact (contact details in config)

**Done when:** every screen uses the design batch 1 shell and components, CRUD works,
barcode uniqueness is per company, isolation tests pass,
changing the colour changes the UI for all company users, an SVG logo is rejected,
a suspended company reaches nothing but the suspended page and logout.

## Phase 3 — Purchases, sales, posting (FR-10 → FR-14, FR-18) ⚠ review posting with Kamal first
Screens: 7 (purchase / sale tabs), 8, 9, 10; balance and suggested-price columns of screen 11;
"+ فاتورة بيع / شراء" quick buttons of screen 5.
- [x] Enum MovementType with `effects()` and helpers (incl. display prefix S- / P- / …); MovementStatus
- [x] Migrations + models: movements, movement_items, operations
- [x] `WarehouseScope` on movements and operations (incoming transfers visible to the target)
- [x] `Money`, `NumberGenerator`, `StockService`, `PricingService`
- [x] `PostingService::post()` with discount distribution
- [x] `MovementService::purchase()` and `sale()`
- [x] Invoice screen (Alpine.js): barcode input, rows, live totals, suggested price; quantity decimals per
      unit `allows_fraction`; discount above the subtotal is rejected, not clamped — screen 7
- [x] Movements list and movement details page (item count, no total quantity; no cancel action yet) — screens 8–9
- [x] Movement print page for every type built so far (A4, company header with logo and warehouse,
      "ملغاة" stamp when cancelled; SRS 2.3 FR-10) — screen 10
- [x] Products list: balance per warehouse and suggested price columns — screen 11

**Done when:** purchase increases stock, sale decreases it, sale beyond stock is rejected,
Σ net_value = movement total, the SRS 6.2 examples pass as tests.

## Phase 4 — Returns (FR-15)
Screens: 7 (return tabs), 9 (returns on an invoice, link to the original invoice).
- [x] `MovementService::return()` for purchase_return and sale_return
- [x] Returnable quantity, original price, proportional discount (shown on the return screen)
- [x] Return screen starting from the original invoice — screen 7
- [x] Movement details: returns list on an invoice, original invoice on a return — screen 9

**Done when:** returnable quantity is enforced and the 2,000 → 1,800 example passes.

## Phase 5 — Reports and dashboard (FR-19 → FR-24)
Screens: 5, 6, 16–18; "low only" filter of screen 11; "حركته" link of screen 12.
- [x] `ReportFilters` + `ReportService` (summary, details, ledger, stock)
- [x] Daily movements and daily net sales per warehouse
- [x] Product ledger with opening balance (SRS 3.6.4 example as a test)
- [x] Party movement, stock report, below_min (balance ≤ min), moved, stagnant (period from the filter)
- [x] Reports screen with all SRS 3.6.2 reports, filters; print-friendly view — screens 16–18
- [x] Dashboards filled per role; stagnant count uses `config('wms.stagnant_days', 60)` — screens 5–6
- [x] Products "low only" filter (screen 11) and party "حركته" link to the party report (screen 12)

**Done when:** every report in SRS 3.6.2 is reachable from the engine and matches hand-calculated data.

## Phase 6 — Transfers and cancellation ⚠ BLOCKED (Q3, Q5)
Screens: 7 (transfer tab), "+ نقل" on screen 5, cancel actions and dialog on screens 8–9.
- [ ] `MovementService::transfer()` (after Q3 is answered) — transfer valued at the last purchase price
- [ ] `MovementService::cancel()` + `PostingService::unpost()` (after Q5 is answered)
- [ ] Cancel actions (hidden for an invoice with active returns), confirmation text per movement type
- [ ] Decide "cancelled at / by" on the details page (screen 9) — needs a schema proposal first

## Phase 7 — Hardening
- [x] Daily database backup via Laravel Scheduler
- [x] Full review against the SRS traceability matrix (section 12)
- [x] Final README

## Phase log
<!-- one line per finished task: YYYY-MM-DD — phase — what was done -->
2026-10-05 — 0 — Breeze (Blade) + Pest 3 + brick/math installed; Tailwind v3 with Alpine from Breeze
2026-10-05 — 0 — Arabic RTL layouts (Cairo font), hand-written `lang/ar` + `lang/ar.json`, Arabic welcome page
2026-10-05 — 0 — Theme: `--color-primary` partial + Tailwind `primary`; palette and trial_days in `config/wms.php`
2026-10-05 — 0 — `APP_TIMEZONE` from env, `.env.example` (MySQL, ADMIN_*, WMS_TRIAL_DAYS), tests on `wms_testing`
2026-10-05 — 0 — composer authors + Arabic README; leftovers and Sanctum removed
2026-10-05 — 0 — Git initialised, first commit; 30 tests pass, Pint clean
2026-10-05 — 1 — Enums Role, CompanyStatus; companies migration + users company_id/role/is_active
2026-10-05 — 1 — `BelongsToCompany` + `CompanyScope` (fail safe `1 = 0`), tested on a temporary table
2026-10-05 — 1 — Registration creates a trial company + owner in one transaction (`CompanyRegistrationService`)
2026-10-05 — 1 — `EnsureSubscriptionActive` + login check via `User::accessBlockReason()`; `EnsureRole`
2026-10-05 — 1 — Super admin seeder; admin companies page: activate / suspend / extend trial
2026-10-05 — 1 — Owner manages warehouse accounts (create / edit / deactivate, no delete) with `UserPolicy`
2026-10-05 — 1 — Role dashboards (shells) and role-based navigation; 76 tests pass, Pint clean
2026-10-06 — 2 — Design batch 1: Tailwind tokens, IBM Plex Sans Arabic, `x-ui.*` Blade components, role shell + mobile menu
2026-10-06 — 2 — Owner warehouse selector (`CurrentWarehouse`, session, re-checked on every read)
2026-10-06 — 2 — Login / register / password pages per design; auth errors as one banner (`login` key)
2026-10-06 — 2 — Profile page in the shell, account deletion removed; 98 tests pass, Pint clean, checked at 1440px and 375px
2026-10-15 — 2 — Units in `config('wms.units')` + `Units` helper; `products` migration, `Product` model (tenant scope), `ProductPolicy` (Q2 in one method)
2026-10-15 — 2 — Product catalogue screen 11: search by name / barcode, inactive toggle, `x-ui.drawer` form, stop with confirmation; 129 tests pass, Pint clean, checked at 1440px and 375px
2026-10-15 — 2 — Parties: `PartyType` enum, `parties` migration, `Party` model, `PartyPolicy`; screen 12 with tabs, search, modal form, mobile cards
2026-10-15 — 2 — `CatalogPolicy` shared by products and parties (Q2 in one place); `recordForm` Alpine component shared by the drawer and modal pages
2026-10-15 — 2 — Warehouses page per screen 13: list with the create / edit form beside it, separate create page removed; 156 tests pass, Pint clean, checked at 1440px and 375px
2026-10-15 — 2 — Company settings page (screen 14): name, logo, palette colour, currency symbol, pricing ratio 100–1000, live preview; `CompanySettingsService`, `CompanyPolicy`
2026-10-15 — 2 — `x-ui.money` component; `Company::logoUrl()`; owner sidebar link; 200 tests pass, Pint clean, checked at 1440px and 375px
2026-10-15 — 2 — Suspended subscription (FR-03): users of a suspended company sign in, then every route but logout redirects to screen 20; a test walks every signed-in route and guards against public ones
2026-10-15 — 2 — Companies page (screen 19) is the platform dashboard: five filter cards with counts, search, expired trials first, activate / extend (7 / 14 / 30 days with the new end date) / suspend dialogs, mobile cards
2026-10-15 — 2 — Phase 2 complete: 237 tests pass, Pint clean, checked at 1440px and 375px
2026-10-17 — 3 — Batch A: `Money`, `MovementType` / `MovementStatus`, movements / movement_items / operations migrations + models + factories, `WarehouseScope`; 254 tests pass, Pint clean
2026-10-17 — 3 — Batch B: `NumberGenerator`, `StockService`, `PricingService`, `PostingService::post()` (SRS 6.2 discount split), `MovementService::purchase()` / `sale()`; rule-1 guard test; 277 tests pass, Pint clean
2026-10-17 — 3 — Batch C: invoice screen (Alpine, JSON save, barcode lookup), movements list / details / A4 print, sidebar + dashboard quick buttons; checked at 1440px and 375px
2026-10-17 — 3 — Batch D: products list with balance (own / chosen / all warehouses) and suggested price columns, `ProductService` (unit fixed once movements exist), `PartyService` (type fixed once movements exist)
2026-10-17 — 3 — Phase 3 complete: 305 tests pass, Pint clean; purchase + sale checked end to end in the browser (1.25 × 19,999.50 = 24,999.38) at 1440px and 375px
2026-10-20 — 4 — `MovementService::return()` + `ReturnService` (returnable per product, proportional discount), one product per invoice rule, `StoreReturnRequest`, `MovementPolicy::createReturn`
2026-10-20 — 4 — Return screens (search the original, return form with live totals), shared type tabs, returns panel / returned column / original link on details, print shows the original; 331 tests pass, Pint clean; checked end to end at 1440px and 375px
2026-10-20 — 5 — Batch A: `ReportFilters`, `ReportKind` (the 14 reports), `StockFilter`, `ReportService` (summary, details, ledger, stock, daily sales, party summary), `wms.stagnant_days`; SRS 3.6.4 ledger example passes
2026-10-20 — 5 — Batch B: reports centre (14 reports, filters, mobile select), print copy, `ReportPolicy`, `ReportFilterRequest`, sidebar link; 355 tests pass; checked at 1440px and 375px
2026-10-20 — 5 — Batch C: warehouse and owner dashboards (`DashboardService` on the report engine): net sales per warehouse, today's movements, low stock, stagnant count, subscription card
2026-10-20 — 5 — Batch D: products "low only" filter and party "حركته" link; Phase 5 complete: 364 tests pass, Pint clean, checked at 1440px and 375px
2026-10-20 — 7 — Batch A: `wms:backup` (mysqldump → gzip, MYSQL_PWD, retention) and `wms:backup:check` (restore into a scratch database), scheduled daily 02:00 and weekly; tried on the local database
2026-10-20 — 7 — Batch B: `docs/TRACEABILITY.md` (FR-01..26 and NFR 4.1–4.8), `ArchitectureRulesTest` and `SecurityTest` guard tests; fixes: returnable check moved to `ReturnService`, neutral hexes replaced by tokens
2026-10-20 — 7 — Batch C: final README (setup, env keys, scheduler, backup and restore steps verified by hand); Phase 7 complete: 384 tests pass, Pint clean
