# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`reachweb/statamic-resrv-vouchers` — a **Statamic 6** addon (Inertia.js + Vue 3 CP) that generates signed-token QR-code vouchers for confirmed Resrv reservations, attaches them (inline PNG + PDF) to Resrv's existing confirmation email, and provides a CP scanner for staff to validate/mark-used vouchers via phone camera.

- **Namespace:** `Reach\StatamicResrvVouchers\` (PSR-4 from `src/`)
- **PHP:** 8.3+ · **Laravel:** 12.x or 13.x · **Statamic:** 6.x · **Vue:** 3 · **CP:** Inertia.js
- **Sibling addon (hard dep):** `reachweb/statamic-resrv` at [../statamic-resrv](../statamic-resrv), on `main` (v6-ready: Statamic 6 / PHP 8.4 / Vite 8 / Tailwind v4; the `BuildingReservationEmail` hook is merged in). Constraint is `^6.0` (Resrv v6.0.0 is tagged and on Packagist). Locally a path repository symlinks the sibling checkout, with a repository-level `options.versions` override pinning it as `6.x-dev` so the symlink satisfies `^6.0` regardless of which branch the sibling is on. `VouchersProvider::bootAddon()` still throws if the installed Resrv predates `BuildingReservationEmail` (defense-in-depth for misconfigured hosts).
- **v6 retarget:** The backend (Phases 0–8 in [tasks.md](tasks.md)) was authored against v5 and remains valid. The CP frontend is being built v6-native from the start (Phase 9). A dedicated **Phase V6** sweep covers the v5→v6 alignment items (`composer.json` bumps, `boot()` → `bootAddon()`, Blade CP views → Inertia pages, `OrchestraTestCase` → `Statamic\Testing\AddonTestCase`). The reference playbook lives at `/Users/afonic/.claude/skills/statamic-addon-v5-to-v6/`.

## Canonical task list

**[tasks.md](tasks.md) is the source of truth for everything in this repo** — phased tasks, self-checks, design decisions, and a "Decisions reference (do not re-litigate without user input)" table. Read it first. Each task has a checkbox; flip `[ ]` → `[x]` when done and append a one-line note to the "Done log" at the bottom.

The full design rationale (not in this repo) is at `/Users/afonic/.claude/plans/i-want-to-create-immutable-rabin.md`.

## Commands

```bash
# Tests (PEST 3 — once tests/ is scaffolded in Phase 2)
vendor/bin/pest
vendor/bin/pest --stop-on-failure
vendor/bin/pest tests/Feature/SomeTest.php           # single file
vendor/bin/pest --filter "name fragment"             # single test

# Code style
vendor/bin/pint

# Artisan commands — there is no `php artisan` in a package; use Testbench:
vendor/bin/testbench <command>
vendor/bin/testbench env                             # confirm local env
vendor/bin/testbench list                            # list commands
vendor/bin/testbench migrate                         # run package migrations
vendor/bin/testbench boost:mcp                       # start Laravel Boost MCP server

# Composer install (path-repo to ../statamic-resrv resolves dev-{branch})
composer install
```

To verify the sibling Resrv tests still pass after a Phase 0 change:
`cd ../statamic-resrv && vendor/bin/phpunit`.

## Architecture

### Bootstrap

- [src/StatamicResrvVouchersServiceProvider.php](src/StatamicResrvVouchersServiceProvider.php) — aggregate provider declared in `composer.json` `extra.laravel.providers`. Registers `VouchersProvider`.
- [src/Providers/VouchersProvider.php](src/Providers/VouchersProvider.php) — extends `Statamic\Providers\AddonServiceProvider`. Holds `$routes['cp']`, `$listen`, and (in Phase 9) `$vite`. `boot()` loads translations/views/migrations + merges config. Mirror Resrv's `ResrvProvider` patterns when adding nav, permissions, commands.

### Integration with Resrv (event-driven)

The addon does not modify Resrv; it consumes Resrv's events. Key wiring points (most still to-build, see `tasks.md`):

- `Reach\StatamicResrv\Events\ReservationConfirmed` → `GenerateVoucherForReservation` listener (queued) creates a `Voucher` row.
- `Reach\StatamicResrv\Events\BuildingReservationEmail` → `AttachVoucherToReservationEmail` listener (**must be synchronous** — it mutates the mailable). This event is the Phase 0 addition to Resrv (`src/Mail/Mailable.php` + `ReservationConfirmed::build()`), merged to Resrv `main` for the v6 release.
- `Reach\StatamicResrv\Events\ReservationCancelled|Refunded|Expired` → `InvalidateVoucherOnCancellation`.
- Internal: `VoucherUsed` → `SendAttendedEmailOnVoucherUsed` (the "thanks for attending" mail).

The `VoucherStateMachine` service is the only thing that mutates voucher state; controllers and listeners go through it. Lazy-expiration: `statusOf()` reports `Expired` without writing to the DB.

### Eligibility & token

- A reservation is voucher-eligible iff its Statamic entry's collection handle is in `config('resrv-vouchers.enabled_collections')`.
- Tokens are UUID v4 + HMAC-SHA256, base64url-encoded, signed with `config('resrv-vouchers.signing_key') ?? config('app.key')`. Verification uses `hash_equals` (constant time). One QR per reservation (not per quantity — see Decisions table).

### Permissions & CP UI

Reuses Resrv's existing `use resrv` permission — no new permission. CP nav lives under the "Resrv" section. CP scanner is a Vue 3 Inertia page + `html5-qrcode` with a text-input fallback when the camera is denied/absent.

## Testbench + Laravel Boost

[testbench.yaml](testbench.yaml) configures the Testbench app: providers (Statamic, Livewire, Resrv, this addon), `APP_ENV=local`, `APP_DEBUG=true`, fixed `APP_KEY`, sqlite-in-memory, array mail/cache/session. This makes `vendor/bin/testbench <command>` work for all package commands.

`tests/TestCase.php` registers Resrv in Statamic's addon `Manifest` keyed by its real package id (`reachweb/statamic-resrv`). The key matters: Resrv `main` sources its CP-managed setting defaults (e.g. `resrv-config.currency_isoCode`) from its settings blueprint via `Addon::get('reachweb/statamic-resrv')` — a wrong manifest id silently skips that merge and `Price` casts blow up on a null currency.

Laravel Boost (dev dep) auto-discovers through Boost's own service provider. `vendor/bin/testbench boost:mcp` starts the MCP server (stdio JSON-RPC) — point Claude Code's MCP client at that command with `cwd` set to the addon root. Boost skips itself when `app()->runningUnitTests()`, so PEST runs are unaffected.

## Repo conventions (from `tasks.md` "Cross-cutting agent rules")

- Run `vendor/bin/pest` after every task that touches `src/` or `tests/`.
- Run `vendor/bin/pint` before considering a task complete.
- `resources/dist/build` is **committed** (Packagist installs need the built CP assets; only `resources/dist/hot` is gitignored). Whenever `resources/js` or `resources/css` change, run `npm run cp:build` and commit the fresh dist alongside.
- **PHPDoc / comments:** none, unless the WHY is non-obvious. Follow Resrv's existing style.
- Migrations must work on SQLite (tests), MySQL/MariaDB, and PostgreSQL — stick to the standard Schema builder. **No FK constraint on `reservation_id`** (Resrv's migrations live in a separate package; FK order is fragile across drivers).
- Don't change Resrv's behavior outside Phase 0. The Phase 0 hook (`BuildingReservationEmail`) is merged to Resrv `main`; any further Resrv change goes on its own feature branch over there.
- Commits: small, atomic, conventional-commit-ish. One phase ≈ one or two commits. Update `tasks.md` (checkbox + done-log line) when finishing a task, then commit.

## Out of scope (don't implement without re-asking)

Multiple QRs per reservation, public "scan my own ticket" page, voucher transfer between customers, branded PDF layouts beyond a simple QR + summary, a separate `use resrv-vouchers` permission, replacing Resrv's confirmation template (we ship an `@include` snippet instead). The full list is at the bottom of [tasks.md](tasks.md).
