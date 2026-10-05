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
