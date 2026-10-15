# Decisions

## Technical decisions (approved)

| # | Decision | Reason |
|---|---|---|
| T1 | Laravel 12 on PHP 8.2, MySQL (XAMPP) | Use the existing local environment; no upgrades (see decision log) |
| T2 | Laravel Breeze (Blade) for auth | Blade-only project; Breeze supports Laravel 12 |
| T3 | Own `BelongsToCompany` trait + global scope (single database) | Simpler than tenancy packages built for multiple databases |
| T4 | Roles as an Enum + Laravel Policies | Three fixed roles do not justify a permissions package |
| T5 | Invoice screen with Blade + Alpine.js | Dynamic rows, barcode input and live totals without a SPA |
| T6 | No barcode library | USB scanners type the code and press Enter like a keyboard |
| T7 | Arabic-only UI, RTL | All users are Arabic speakers |
| T8 | Pest for tests | Readable tests; isolation tests are required by the SRS |
| T9 | `brick/math` for all money/quantity arithmetic | PHP floats would reintroduce the rounding errors `decimal` avoids |
| T10 | `APP_TIMEZONE` set to the business region (e.g. `Asia/Damascus`) | "Today's sales" must start at local midnight, not UTC |
| T11 | Movement number: lock company row + `MAX + 1` | Prevents duplicate numbers without an extra table |
| T12 | Foreign keys `restrictOnDelete()` | Nothing is hard-deleted; cancellation is a status |
| T13 | `BelongsToCompany` is not applied to `User` | A fail-safe scope on users would block login |
| T14 | Company settings as explicit columns (`logo_path`, `primary_color`, `currency_symbol`) | Few, fixed, typed settings; documented in schema.json |
| T15 | Primary colour from a fixed 8-colour palette | Prevents unreadable UI instead of fixing it later |
| T16 | Logo: PNG/JPG/WEBP ≤ 1 MB, no SVG | SVG can contain scripts (XSS) |
| T17 | Theme through CSS variables from Phase 0 | Per-company colours cannot be compiled by Tailwind at build time |

Allowed packages: `laravel/breeze`, `pestphp/pest` (+ Laravel plugin), `brick/math`, `alpinejs`, `tailwindcss`.
Anything else → ask first.

## Open questions (waiting for Mr. Kamal)

| # | Question | Current default | Status | Affects |
|---|---|---|---|---|
| Q1 | Are purchases also cash only? | Yes | Non-blocking (no payments table exists) | — |
| Q2 | Who can add products and parties? | Owner and warehouses | Non-blocking: implement in one Policy method so it can be flipped | Phase 2 |
| Q3 | Does the receiving warehouse confirm a transfer? | No, immediate | ⚠ BLOCKED | Phase 6 (transfer) |
| Q4 | Trial period length? | `config('wms.trial_days', 14)` | Non-blocking: config value | Phase 1 |
| Q7 | Platform name? (design uses the placeholder "رفّ") | `config('app.name')`, currently `WMS` | Non-blocking: one config value, shown on login / register / admin bar | Phase 2 |
| Q6 | Product location per warehouse? (shared catalog, but shelves differ per warehouse) | One general `location` per product | Non-blocking: a per-warehouse location would need a new product–warehouse table | Phase 2 |
| Q5 | Cancelling a posted movement: delete its `operations` rows or add reversing rows? | Delete rows | ⚠ BLOCKED | Phase 6 (cancel), `PostingService::unpost()` |

Kamal said the posting details will be discussed when we reach them: before Phase 3, the user reviews
`PostingService::post()` with him. Do not start Phase 3 posting code until the user confirms.

## Decision log

Add new decisions here: `YYYY-MM-DD — decision — reason`.

- 2026-10-05 — Stay on Laravel 12 / PHP 8.2 / XAMPP MySQL instead of Laravel 13 / PHP 8.3 — use the existing environment. Pest stays on v3 (v4 needs PHP 8.3). Note: the XAMPP server reports `10.4.32-MariaDB`; it supports the window functions the reports need, but check SQL compatibility before Phase 5.
- 2026-10-05 — Removed `laravel/sanctum` and `routes/api.php` — Blade-only project, no API.
- 2026-10-05 — Tailwind v3 as installed by Breeze; `primary` maps to `var(--color-primary)` with `primary-dark` / `primary-light` shades from CSS `color-mix()` — keeps Breeze views working. Opacity modifiers (`bg-primary/50`) do not work with plain variables; use the shades.
- 2026-10-05 — Arabic translations written by hand in `lang/ar/*.php` and `lang/ar.json` (no laravel-lang package); `APP_FALLBACK_LOCALE=en` so a missing key shows English, not a raw key.
- 2026-10-05 — Font: Cairo from fonts.bunny.net (same CDN Breeze uses) — readable Arabic UI font.
- 2026-10-05 — Tests run on a separate MySQL database `wms_testing` (not SQLite) — row locks and window functions must be tested on the real engine.
- 2026-10-05 — Removed `tracking-widest` / `uppercase` from Breeze buttons — letter spacing breaks Arabic letter joining.
- 2026-10-05 — `users` keeps Laravel's `email_verified_at` and `remember_token` (added to schema.json) — needed by Breeze ("remember me", optional email verification); approved by the user.
- 2026-10-05 — Breeze "delete account" stays until Phase 2 (FR-26) — user's choice; until then an owner deleting their account leaves the company without an owner.
- 2026-10-05 — One rule for "may this user use the system": `User::accessBlockReason()` (inactive account or suspended company; super admin never blocked), used by login and by `EnsureSubscriptionActive` — the same rule cannot drift between the two places.
- 2026-10-05 — Extending a trial starts from the later of today and the current end date, sets status back to trial, and is refused for an active company — the admin page offers it only for trial / suspended companies.
- 2026-10-05 — `BelongsToCompany` tests use a TEMPORARY table — CREATE/DROP TABLE implicitly commits the RefreshDatabase transaction in MySQL.
- 2026-10-05 — Local `DemoSeeder` (APP_ENV=local only) with demo accounts, password "password" — fast manual testing; never runs in production.
- 2026-10-05 — New UI text is written in Arabic directly in views; `__()` + `lang/ar.json` stays for Breeze strings.
- 2026-10-05 — UI follows `docs/design/` (design batch 1 in `01-design-system/`); where it contradicts the SRS, the SRS wins — the design is a prototype, the SRS is the approved requirement.
- 2026-10-05 — Font changes from Cairo to IBM Plex Sans Arabic — the approved design uses it (supersedes the Cairo decision).
- 2026-10-05 — Neutral and semantic colours (ink, muted, borders, success, warning, danger, badge colours) become fixed Tailwind tokens; only `--color-primary` varies per company — rule 10 bans hard-coded brand colours, not neutrals.
- 2026-10-05 — Login and register use `var(--color-primary)` (the default colour before login) instead of the design's hard-coded `#1F4E79` — rule 10.
- 2026-10-05 — Owner warehouse selector: selection stored in the session, filters lists and reports; "all warehouses" is not valid for creating a movement, the owner picks one (FR-05).
- 2026-10-05 — Movement number prefixes (S-, P-, …) are display only and live in `MovementType` — the schema keeps an unsigned int number (rule 4).
- 2026-10-05 — Movements list shows the number of items, not a total quantity — quantities of different units cannot be summed (design deviation).
- 2026-10-05 — Cancel confirmation text must describe the real stock effect per movement type, and the cancel action is hidden where the SRS refuses it (invoice with active returns); cancellation itself stays ⚠ BLOCKED (Q5).
- 2026-10-05 — No "terms of use" link on register — not in the SRS (design deviation).
- 2026-10-05 — Trial length on the register page comes from `config('wms.trial_days')`, never hard-coded (Q4).
- 2026-10-05 — Dates shown with Levantine month names (تشرين الأول …) as in the design, via one formatting helper.
- 2026-10-05 — The design's "delete" button is used only to remove an invoice line before saving — records are never deleted (T12).
- 2026-10-05 — Quick-add of a party from inside an invoice (design) is deferred to Phase 3 and follows Q2 — not required by the SRS.
- 2026-10-06 — Design files stay exactly as exported from the design tool (Arabic names, all batches in `docs/design/01-design-system/`, plus `Sidebar` / `Topbar`); never renamed — re-exports replace them cleanly. PLAN.md maps each design screen number to its phase.
- 2026-10-06 — Units: `config('wms.units')` lists each unit with `allows_fraction` (لتر / كيلو: yes; علبة / قطعة: no); the product unit is a select from that list; quantities of a non-fractional unit must be whole numbers — user's decision; replaces the design's per-product "accepts fractions" checkbox, which has no column (rule 11).
- 2026-10-06 — Stagnant products: dashboards use `config('wms.stagnant_days', 60)`; reports use the period from the filter (SRS 3.6.6) — user's decision; the design's fixed "60 days" applies to dashboards only.
- 2026-10-06 — Suspended company: users may sign in, then every route except logout redirects to the suspended-subscription page (design screen 20) and no data page opens; a test walks every route — user's decision; supersedes refusing the login (Phase 1). A deactivated warehouse account is still refused at login (FR-05). Platform contact details (phone, email, hours) come from config, not the database.
- 2026-10-06 — Invoice print page added to Phase 3 (design screen 10) — user's decision.
- 2026-10-06 — Markup percent limited to 100–1000% — user's decision (design); below 100% would suggest selling under cost.
- 2026-10-06 — No "last login" column on the warehouses page — no column in the schema (rule 11); user's decision.
- 2026-10-06 — No camera barcode button on mobile — would need a barcode library (T6); USB scanners type like a keyboard; user's decision.
- 2026-10-06 — "Cancelled at / by" on the movement details page deferred to Phase 6 with Q5 — needs a schema proposal; user's decision.
- 2026-10-06 — The companies page (status cards + list, design screen 19) is the super admin dashboard — covers FR-24 for the platform owner without a separate page.
- 2026-10-06 — Design deviations fixed in favour of the SRS: return net carries its proportional discount (SRS 3.4.6); a transfer is valued at the last purchase price (SRS 3.4.7); a discount above the subtotal is rejected, not clamped (SRS 7); "low stock" means balance ≤ min_stock (SRS 3.6.6); the reports screen lists all SRS 3.6.2 reports, not four; the product form has the manual sale price field (FR-07); dates show the real weekday.
- 2026-10-06 — SRS 2.3 prints every movement type, not only invoices — the Phase 3 print page is generic per movement and grows with each type (returns in Phase 4, transfers in Phase 6).
- 2026-10-06 — Authentication failures (wrong credentials, throttle, inactive account, suspended company) are thrown on the `login` key and shown as one banner above the form; shape errors stay under their fields — design batch 1 and security (no hint which field was wrong).
- 2026-10-06 — Subscription status labels follow the design: تجريبي / فعّال / موقوف (the subscription, masculine), one place in `CompanyStatus::label()`.
- 2026-10-06 — Display formatting in `App\Support\Format` (money, quantity, Levantine dates) uses brick/math strings only, never floats; arithmetic stays for `Money` in Phase 3.
- 2026-10-15 — `products.unit` stores the stable unit key (`liter`, `kilo`, `box`, `piece`), the Arabic label comes from `Units::label()` — renaming a label in the config never breaks stored data.
- 2026-10-15 — An empty barcode is stored as NULL, so many products can have none while the unique (`company_id`, `barcode`) index still protects real barcodes.
- 2026-10-15 — `min_stock` must be a whole number when the unit takes no fractions (SRS 13.2.14 applied to the minimum); form fields show stored decimals through `Format::plain()` ("12.000" → "12") so an unchanged product can always be saved again.
- 2026-10-15 — No `ProductService` yet: create / update / deactivate hold no business rule. It is added when a rule appears (Phase 3: refuse a unit change once the product has movements).
- 2026-10-15 — `ProductPolicy::manage()` is the single place for Q2; the other abilities call it, so flipping the answer is one line. `custom_sale_price` accepts 0 or more (SRS 7 rejects only negative prices).
- 2026-10-15 — Product form lives in a reusable `x-ui.drawer` (design screen 11); after a failed save the drawer stays open with the old input and errors, and closing it reloads the page for a clean form.
- 2026-10-15 — `CatalogPolicy` is the one place for Q2 (products and parties both extend it); flipping who may manage the shared catalogues is a one-line change in `manage()`.
- 2026-10-15 — Parties: name is not unique (two customers may share a name), phone is optional digits / spaces / `+ - ( )` up to 30 characters, and the list shows every party with a status badge (design) — stopping a party is added to the design because SRS FR-08 requires it.
- 2026-10-15 — Changing a party's type is allowed for now; Phase 3 must refuse it once the party has movements (a purchase needs a supplier), like the product unit.
- 2026-10-15 — Parties use a centered `x-ui.modal` and products a side `x-ui.drawer`, as designed; both are driven by one `recordForm` Alpine component (fill fields, create / update action, reopen after a failed save, reload on close).
- 2026-10-15 — Warehouses page is one screen: `GET /warehouses` is the list + create form, `GET /warehouses/{id}/edit` the same screen switched to that warehouse; the separate create page is gone.
- 2026-10-15 — The duplicate-email error on the warehouse form stays generic (design names the warehouse): emails are unique across the whole platform, so naming one would leak another company's data.
