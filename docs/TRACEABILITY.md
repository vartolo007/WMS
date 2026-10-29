# Traceability review (Phase 7)

Review of the code against SRS section 12 (FR-01 → FR-26) and the non-functional requirements 4.1 → 4.8, done by walking the
routes, classes and tests (not from memory) on 2026-10-20. Status: ✔ implemented and tested · ⚠ blocked or open (reason given).
The rules that can be checked mechanically live in `tests/Feature/ArchitectureRulesTest.php` and `tests/Feature/SecurityTest.php`.

## Functional requirements

| FR | Requirement | Where it lives | Proved by | |
|---|---|---|---|---|
| 01 | Company registration | `RegisteredUserController`, `CompanyRegistrationService`, `RegisterCompanyRequest` | `Auth/RegistrationTest` | ✔ |
| 02 | Subscription management | `Admin\CompanyController`, `CompanySubscriptionService`, companies page | `AdminCompaniesTest`, `AdminCompanyListTest` | ✔ |
| 03 | Subscription check | `EnsureSubscriptionActive`, `User::companyIsSuspended()`, `SuspendedSubscriptionController` | `SubscriptionTest`, `SuspendedSubscriptionTest` (walks every signed-in route) | ✔ |
| 04 | Login, role dashboards | `AuthenticatedSessionController`, `LoginRequest` (throttle), `DashboardController` | `AuthenticationTest`, `RoleTest`, `SecurityTest` | ✔ |
| 05 | Warehouses | `WarehouseController`, `WarehouseAccountService`, `UserPolicy`, `CurrentWarehouse` | `WarehouseAccountsTest`, `WarehouseContextTest` | ✔ |
| 06 | Data isolation | `BelongsToCompany` + `CompanyScope`, `WarehouseScope` | `CompanyScopeTest`, `ProductIsolationTest`, `PartyIsolationTest`, `MovementIsolationTest`, `ArchitectureRulesTest` | ✔ |
| 07 | Product catalogue | `ProductController`, `ProductService`, `ProductPolicy`, `Units` | `ProductTest`, `UnitsTest`, `CatalogMovementRulesTest` | ✔ |
| 08 | Customers and suppliers | `PartyController`, `PartyService`, `PartyPolicy` | `PartyTest`, `PartyIsolationTest`, `CatalogMovementRulesTest` | ✔ |
| 09 | Pricing setting | `CompanySettingsController` / `Service` (`markup_percent` 100–1000) | `CompanySettingsTest` | ✔ |
| 10 | Movement header | `Movement`, `MovementType`, `NumberGenerator`, print page | `MovementServiceTest`, `MovementScreensTest` | ✔ |
| 11 | Items | `StoreMovementRequest`, `MovementService::lines()`, `invoice-form.js` | `MovementScreensTest`, `MovementServiceTest` | ✔ |
| 12 | Purchase invoice | `MovementService::purchase()` | `MovementServiceTest`, `PricingAndStockServiceTest` | ✔ |
| 13 | Sale invoice | `MovementService::sale()`, `StockService::lockAndCheck()` | `MovementServiceTest` | ✔ |
| 14 | Pricing rule | `PricingService` | `PricingAndStockServiceTest`, `CatalogMovementRulesTest` | ✔ |
| 15 | Returns | `ReturnService`, `MovementService::return()`, `MovementReturnController` | `ReturnServiceTest`, `ReturnScreensTest` | ✔ |
| 16 | Transfer | `MovementService::transfer()`, `StoreMovementRequest`, transfer tab on the movement screen, `WarehouseScope` target side | `TransferTest`, `TransferScreensTest`, `ReportServiceTest` (transfer rows) | ✔ (Q3: immediate) |
| 17 | Cancellation | `MovementService::cancel()`, `PostingService::unpost()`, `MovementPolicy::cancel()`, `MovementCancelController`, dialog on details and list | `CancellationTest`, `CancelScreensTest` | ✔ (Q5: rows deleted) |
| 18 | Posting | `PostingService::post()` (only writer of `operations`) | `MovementServiceTest` (incl. the guard that scans `app/`), `ReturnServiceTest` | ✔ |
| 19 | Report engine | `ReportFilters`, `ReportKind`, `ReportService` | `ReportServiceTest` | ✔ |
| 20 | Daily movements, net sales | `ReportService::dailySales()`, `ReportKind::DailyMovements` / `DailySales` | `ReportServiceTest`, `ReportScreensTest` | ✔ |
| 21 | Product ledger | `ReportService::ledger()` (SRS 3.6.4 example) | `ReportServiceTest` | ✔ |
| 22 | Party movement | `ReportService::partySummary()`, `ReportKind::PartyMovement` | `ReportServiceTest`, `ReportScreensTest` | ✔ |
| 23 | Stock, low, moved, stagnant | `ReportService::stock()`, products "low only" filter | `ReportServiceTest`, `ReportScreensTest` | ✔ |
| 24 | Dashboards | `DashboardService`; platform owner: the companies page | `DashboardTest`, `AdminCompanyListTest` | ✔ |
| 25 | Company settings | `CompanySettingsController`, theme partial, logo rules | `CompanySettingsTest`, `CompanyLogoTest`, `ThemeTest` | ✔ |
| 26 | Profile | `ProfileController` | `ProfileTest` | ✔ |

## Non-functional requirements

| SRS | Requirement | Evidence | |
|---|---|---|---|
| 4.1 | Company and warehouse scopes on every query; isolation tests | Scopes on `Product`, `Party`, `Movement`, `Operation`; `User` and `MovementItem` are the documented exceptions (T13, ARCHITECTURE 5.2) — checked in `ArchitectureRulesTest` | ✔ |
| 4.2 | One transaction for save + posting | `MovementService` (`DB::transaction`); rollback tests in `MovementServiceTest` and `ReturnServiceTest` | ✔ |
| 4.2 | One writer of `operations` | Guard test in `MovementServiceTest` | ✔ |
| 4.2 | Stock checked on the server under row locks | `StockService::lockAndCheck()` (product rows ordered by id), original movement lock for returns, company row lock for numbers | ✔ (the lock order is by design; a real two-session race is not simulated in tests) |
| 4.2 | `decimal`, never float | Decimal casts; `ArchitectureRulesTest` bans float casts / `round()` / `number_format()` in `app/` and `parseFloat` / `toFixed` in JS | ✔ |
| 4.2 | Prices fixed at saving time | `movement_items.unit_price`; `MovementServiceTest` | ✔ |
| 4.3 | Indexes of SRS 5.9 | `ArchitectureRulesTest` reads them from the database | ✔ |
| 4.3 | Reports in SQL (GROUP BY, window functions) | `ReportService` | ✔ |
| 4.3 | Pagination of long lists | Products, parties, movements, reports, companies are paginated; the warehouses list is not (one account per warehouse, a short list by nature) | ✔ accepted |
| 4.4 | Hashed passwords, CSRF | `SecurityTest` (hash), `ArchitectureRulesTest` (every POST form has `@csrf`, the JSON POST helper sends the token) | ✔ |
| 4.4 | Middleware for login, subscription, role | Route walk in `SuspendedSubscriptionTest`; every signed-in route has a role guard except the listed shared ones (`ArchitectureRulesTest`) | ✔ |
| 4.4 | Server-side validation | FormRequests + services, tests per screen | ✔ |
| 4.4 | SVG logo refused, real file type checked | `CompanyLogoTest` | ✔ |
| 4.4 | Login throttling | `SecurityTest` (5 attempts) | ✔ (added in this review) |
| 4.5 | Creator and date on every movement; cancel instead of delete | `movements.created_by`, timestamps, `cancelled_by` / `cancelled_at`; no delete route exists | ✔ |
| 4.6 | One place for movement-type behaviour | `MovementType` enum | ✔ |
| 4.6 | UI colours from CSS variables only | `ArchitectureRulesTest` (no hex or rgb colour in any view) | ✔ (3 hard-coded neutral hexes replaced by tokens in this review) |
| 4.6 | Three clear services | `MovementService`, `PostingService`, `ReportService`; controllers hold no maths, transactions or locks (`ArchitectureRulesTest`) | ✔ |
| 4.7 | New movement types without schema change | Add a `MovementType` case and its effects | ✔ (by design) |
| 4.8 | Responsive pages | Every screen checked at 1440px and 375px during its phase | ✔ |
| 4.8 | Daily backup by a scheduled task | `wms:backup` at 02:00 (`routes/console.php`), `BackupService`; needs the scheduler running (README) | ✔ |
| 4.8 | Periodic restore test | `wms:backup:check` weekly: restores the newest archive into a scratch database and compares tables and rows | ✔ |

## What the review changed

- `MovementController::show` used `Money` to decide whether a return is still possible → moved into `ReturnService::anyReturnable()` (rule 6); a guard test now keeps controllers free of money maths.
- Three hard-coded neutral hexes in the movement views (`#FAFBFB`, `#D5D8DD`) → colour tokens (rule 10); a guard test now bans hex / rgb colours in views.
- Login throttling had no test → `SecurityTest`.
- Index, tenant-scope, float, CSRF and role-guard rules became tests (`ArchitectureRulesTest`).

## Still open

- Nothing blocks the SRS any more: Q3 and Q5 were decided on 2026-10-20 (see DECISIONS).
- 2026-10-29 delivery pass: every screen checked in the browser at desktop and 375px for the three roles; the password-reset e-mail (login screen, SRS 10) is now Arabic.
- Quick-add of a party / product from the invoice screen was added later in the comfort pack (not in the SRS; same catalogue rules).
- Comfort pack additions beyond the SRS: CSV import, camera scan, save-and-print, low-stock and failure e-mails (column `companies.low_stock_alerts`, SRS 5.2).
