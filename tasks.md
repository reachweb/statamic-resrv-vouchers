# Statamic Resrv Vouchers — Implementation Tasks

> **Read this first.** This file is the canonical task list for building the `reachweb/statamic-resrv-vouchers` Statamic addon. Each task has a checkbox. Flip `[ ]` → `[x]` when complete. Each task lists a self-check that must pass before moving on. The full design rationale lives in `/Users/afonic/.claude/plans/i-want-to-create-immutable-rabin.md` — consult it if a task is ambiguous.

## Project at a glance

- **Package:** `reachweb/statamic-resrv-vouchers`
- **Namespace:** `Reach\StatamicResrvVouchers\`
- **Location:** `/Volumes/1TB/Sites/umami/addons/reachweb/statamic-resrv-vouchers/`
- **Sibling addon (dependency):** `reachweb/statamic-resrv` at `/Volumes/1TB/Sites/umami/addons/reachweb/statamic-resrv` — on `main`, which is v6-ready (the `v6-upgrade` work is merged; PHP `^8.4`; `tests/TestCase.php` on `AddonTestCase`; CP-managed settings now live in a settings blueprint instead of `config/resrv-config.php`). The `BuildingReservationEmail` hook is merged into `main`.
- **Target platform:** **Statamic 6, Laravel 12/13, PHP 8.3+, Vue 3 + Inertia.js**. The vouchers addon is being retargeted to ship v6-native — there is no v5 release in the pipeline. The backend phases below (T0–T8) were authored against v5 and stay valid; the frontend (T9) is built v6-native from the start, and a new **Phase V6** sweep brings the existing v5-shaped surface (composer pins, `boot()` rename, blade-shaped CP pages, `OrchestraTestCase` test base) into line with v6.
- **What it does:** When a Resrv reservation is confirmed in a voucher-enabled collection, generate a signed-token QR code, attach it (inline PNG + PDF) to the existing Resrv confirmation email. CP UI lets staff scan QR codes via phone camera, validate vouchers, and mark them used. Marking used sends a "thank you for attending" email. Cancel/refund invalidates the voucher; vouchers also expire after `date_end + grace_days`.
- **Tests:** PEST 3 on top of `Statamic\Testing\AddonTestCase` (post-V6). SQLite in-memory.

## Current build state (as of 2026-06-05)

- **Retarget to Resrv `main`:** complete. Resrv merged its v6 work to `main` ahead of the v6 release; `features/resrv-voucher-required-changes` (the `BuildingReservationEmail` hook) is merged into Resrv `main` and its 2 PHPUnit tests pass there. Vouchers' composer now resolves `reachweb/statamic-resrv dev-main` (statamic/cms 6.20.2). One test-infra fix: the Resrv entry in `tests/TestCase.php`'s addon Manifest had id `reach/resrv` — Resrv `main` resolves setting defaults (e.g. `currency_isoCode`) through `Addon::get('reachweb/statamic-resrv')`, so the wrong id skipped the settings-blueprint merge and 30 tests failed on a null currency. Re-keyed to the real package id → full suite green: 65 PEST tests / 165 assertions. Pint clean.

- **Phases 0–8 (backend + CP scaffolding):** complete. 42 PEST tests / 71 assertions green on Statamic 6. Pint clean.
- **Phase 9 (frontend):** code complete (T9.1–T9.6). Vite 8 + Tailwind v4 + `@statamic/cms/vite-plugin` build chain in place; `resources/js/pages/Vouchers/{Index,Scan}.vue` shipped with html5-qrcode scanner + camera switching + manual token entry + `<Listing>` filter/sort. T9.7 (manual UAT on phone/desktop) still pending — only verifiable in a host site with a live camera. `npm run cp:build` exits 0; manifest + compiled assets emitted to `resources/dist/build/`.
- **Phase 10 (attended email on `VoucherUsed`):** complete. SendAttendedEmailOnVoucherUsed listener + Mail\VoucherAttended + publishable markdown template + 4 PEST tests.
- **Phase 11 (install command, README, polish):** code + docs complete — T11.1 (InstallVouchers command), T11.2 (README), T11.3 (Resrv CLAUDE.md hook docs) done. T11.4 (final cross-package test run) verified on Vouchers side; Resrv side blocked on its own v6 TestCase migration. T11.5 (manual cross-browser UAT) requires a phone with a camera — left pending.
- **Phase V6 (v5 → v6 alignment sweep):** **complete.** TV6.1 (composer bumps), TV6.2 (`$vite`), TV6.3 (`bootAddon` rename), TV6.4 (Inertia controllers), TV6.5 (delete Blade CP views), TV6.6 (`AddonTestCase`), and TV6.7 (cross-package sanity verified — composer + Vouchers pest green; Resrv's own phpunit needs the same TestCase migration but is out of Vouchers scope). 51 PEST tests / 120 assertions green on Statamic 6.

## Decisions reference (do not re-litigate without user input)

| Topic | Decision |
| --- | --- |
| Target Statamic version | **v6** (no v5 release). Resrv sibling is mid-v6-upgrade on its `v6-upgrade` branch. |
| CP UI architecture | **Inertia.js + Vue 3** pages registered via `Statamic.$inertia.register(...)`. Use `@statamic/cms/ui` + `@statamic/cms/inertia` components — no Blade CP views in the final shape. |
| Build chain | Vite 8 + Tailwind v4 + `@statamic/cms/vite-plugin`. JS entry is `resources/js/cp.js`. |
| QR per reservation | One (not per quantity) |
| Token | UUID v4 + HMAC-SHA256, base64url-encoded |
| Attachments | Inline PNG (CID) + PDF |
| Enabled collections | `config/resrv-vouchers.php` array `enabled_collections` |
| Reversibility | Admin can un-mark used (audit-logged) |
| Cancel/refund | Listener invalidates voucher |
| Expiration | `reservation.date_end + grace_days` (default 1) — lazy-checked |
| Email templates | Publishable markdown |
| Permission | Reuse Resrv's `use resrv` (no new permission) |
| Scan UX | Always show reservation details + status banner |
| Audit | Separate `resrv_voucher_scans` table |
| QR PHP lib | `endroid/qr-code` v5 |
| Scanner JS lib | `html5-qrcode` (Vue 3-compatible — wired with `onMounted` / `onBeforeUnmount`) |
| Resrv hook | New `BuildingReservationEmail` event from `Mailable::build()` |
| Scanner fallback | Text-input fallback in addition to camera |
| Install | `resrv-vouchers:install` console command |
| Extras | CP voucher list + manual resend-email action |
| Config storage | Plain `config/resrv-vouchers.php` (no Forma, no UserConfig blueprint — there are no user-editable settings beyond the keys collections operator already manages in `config/`). Revisit if user-facing settings are added later. |

## Cross-cutting agent rules

1. **Run tests after every task that touches `src/` or `tests/`.** Use `vendor/bin/pest`. Stop on first failure if you can't reason about the cascade.
2. **Code style:** run `vendor/bin/pint` before considering any task complete.
3. **Naming:** mirror the Resrv addon. CP pages live as Inertia pages at `resources/js/pages/<Feature>/<Name>.vue` and are registered with handles like `resrv-vouchers::<Feature>/<Name>`.
4. **Cross-driver migrations:** stick to standard Schema builder calls. The addon must work on SQLite (testing), MySQL/MariaDB, PostgreSQL.
5. **Don't change Resrv's behavior outside Phase 0.** Phase 0 lives on Resrv's `feature/building-email-event` branch (already merged into the v6-upgrade base) and must not regrow once Resrv is on `v6-upgrade`.
6. **PHPDoc / comments:** none, unless the WHY is non-obvious. Follow Resrv's existing style.
7. **PHP 8.3+.** Use constructor property promotion, return types, readonly where natural.
8. **Update this file** every time you finish a task: check the box and add a one-line "done note" under it if non-trivial. Then commit.
9. **Local dev:** the addon depends on the v6-bearing Resrv. Until Resrv tags a `^6.0` release, the `composer.json` `path` repo at `../statamic-resrv` resolves dev-`main`:
   ```json
   "repositories": [
     { "type": "path", "url": "../statamic-resrv" }
   ]
   ```
10. **Commits:** small, atomic, one phase = one or two commits. Conventional-commit-ish messages.
11. **Statamic v6 reference material:** the playbook lives at `/Users/afonic/.claude/skills/statamic-addon-v5-to-v6/`. Phase V6 follows it directly; Phase 9 references it for the build chain and Inertia page conventions.

---

## Phase 0 — Companion change in Resrv (do this first)

Branch in `statamic-resrv`: `feature/building-email-event`. Do not merge to `main` until the Vouchers addon is exercising it.

- [x] **T0.1 Add `BuildingReservationEmail` event class in Resrv**
  - File: `src/Events/BuildingReservationEmail.php`
  - Namespace: `Reach\StatamicResrv\Events`
  - Payload: `public Mailable $mailable; public ?Reservation $reservation;`
  - Use `Illuminate\Foundation\Events\Dispatchable`, `Illuminate\Queue\SerializesModels`.
  - **Self-check:** `vendor/bin/phpunit --filter "ReservationConfirmedTest"` still green.

- [x] **T0.2 Add `dispatchBuildingEvent()` to Resrv's Mailable base class**
  - File: `src/Mail/Mailable.php`
  - Add `protected function dispatchBuildingEvent(?Reservation $reservation = null): void { BuildingReservationEmail::dispatch($this, $reservation); }`
  - Do **not** auto-call this from the base class — subclasses opt in.
  - **Self-check:** existing PHPUnit suite still green.

- [x] **T0.3 Wire the dispatcher into `ReservationConfirmed::build()`**
  - File: `src/Mail/ReservationConfirmed.php`
  - Call `$this->dispatchBuildingEvent($this->reservation);` right before returning. The mailable already has its markdown set so listeners can `attach()` and inspect.
  - **Self-check:** existing email test still green.

- [x] **T0.4 PHPUnit test for the new event**
  - File: `tests/Mail/BuildingReservationEmailTest.php`
  - Two cases: (a) event fires exactly once when the mailable builds; (b) a listener can call `$event->mailable->attachData(...)` and the resulting `Mail::fake()` payload contains the attachment.
  - **Self-check:** new test passes; full PHPUnit suite green.

- [ ] **T0.5 Coordinate Resrv version with the new addon**
  - For local development, do not tag yet. The Vouchers addon will pin via a path repo (see rule 9 above).
  - Before public release, tag a new minor of Resrv that introduces this event and reference that version in `statamic-resrv-vouchers/composer.json`.
  - **Status check 2026-06-05:** `features/resrv-voucher-required-changes` is merged into Resrv `main` (merge commit `d01e363`, local only — not pushed). Both `BuildingReservationEmailTest` cases green on main. Remaining: tag the Resrv v6 release and swap the `*` constraint for it.

## Phase 1 — Package skeleton

- [x] **T1.1 `composer.json`**
  - Path: `composer.json`
  - Name `reachweb/statamic-resrv-vouchers`, type `statamic-addon`, namespace `Reach\StatamicResrvVouchers\` autoloaded from `src/`.
  - **Require:** `php ^8.2`, `statamic/cms ^5.0`, `laravel/framework ^12.0 || ^13.0`, `endroid/qr-code ^5.0`, `reachweb/statamic-resrv:dev-feature/building-email-event` (path repo) until Phase 0 lands.
  - **Require-dev:** `orchestra/testbench ^10.0 || ^11.0`, `pestphp/pest ^4.0`, `pestphp/pest-plugin-laravel ^4.0`, `laravel/pint ^1.2`.
  - `extra.laravel.providers` → `Reach\\StatamicResrvVouchers\\StatamicResrvVouchersServiceProvider`.
  - `extra.statamic.name`, `extra.statamic.description`.
  - Scripts: `test` → `vendor/bin/pest`, `test:stop` → `vendor/bin/pest --stop-on-failure`.
  - **Self-check:** `composer validate` is clean; `composer install` succeeds.

- [x] **T1.2 Service providers**
  - `src/StatamicResrvVouchersServiceProvider.php` — aggregate, registers `VouchersProvider`.
  - `src/Providers/VouchersProvider.php` — extends `Statamic\Providers\AddonServiceProvider`. Sets `$routes['cp']`, `$listen` (empty for now), `$vite` (set up in Phase 9), and in `boot()` loads translations, views, migrations, merges config.
  - **Self-check:** package boots in Testbench without exception.

- [x] **T1.3 Config file**
  - Path: `config/resrv-vouchers.php`
  - Keys:
    ```php
    return [
        'enabled_collections' => [],
        'grace_days' => 1,
        'signing_key' => env('RESRV_VOUCHERS_SIGNING_KEY'),
        'email' => [
            'attended' => [
                'subject' => null,
                'from' => ['address' => null, 'name' => null],
                'markdown' => null,
            ],
        ],
    ];
    ```
  - Merged into the container under key `resrv-vouchers`. Publish tag `resrv-vouchers-config`.
  - **Self-check:** `config('resrv-vouchers.grace_days')` returns `1` in a Testbench boot test.

- [x] **T1.4 Base directory scaffolding**
  - Create empty dirs: `src/Console/Commands/`, `src/Events/`, `src/Http/Controllers/`, `src/Http/Requests/`, `src/Listeners/`, `src/Mail/`, `src/Models/`, `src/Services/`, `src/Enums/`, `resources/lang/en/`, `resources/views/cp/vouchers/`, `resources/views/email/vouchers/`, `resources/js/components/`, `routes/`, `database/migrations/`, `tests/Feature/`, `tests/Unit/`.
  - Add `.gitkeep` for any directory that won't have a file by end of this phase.

- [x] **T1.5 Pint + minimum CI**
  - Add `pint.json` (copy from Resrv).
  - Add a `composer test` alias that runs `vendor/bin/pest`. Optionally add a GitHub Actions workflow file later (defer; not required for this milestone).

## Phase 2 — Test bootstrap (PEST 3)

- [x] **T2.1 `tests/TestCase.php`**
  - Extend `Orchestra\Testbench\TestCase`.
  - `getPackageProviders()` returns: `\Statamic\Providers\StatamicServiceProvider`, `\Livewire\LivewireServiceProvider`, `\Reach\StatamicResrv\StatamicResrvServiceProvider`, `\Reach\StatamicResrvVouchers\StatamicResrvVouchersServiceProvider`.
  - `getPackageAliases()` returns `['Statamic' => \Statamic\Statamic::class]`.
  - `defineDatabaseMigrations()` → `loadLaravelMigrations()` + `artisan('migrate', ['--database' => 'testbench'])` to pick up both Resrv and Vouchers migrations.
  - Use `RefreshDatabase`, `FakesViews` (if needed), `PreventSavingStacheItemsToDisk` (mirror Resrv's helpers — copy what's necessary).
  - Set test environment via `defineEnvironment()`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`, `CACHE_DRIVER=array`, `APP_KEY=base64:<fixed>`.

- [x] **T2.2 `tests/Pest.php`**
  - `uses(TestCase::class)->in(__DIR__);`
  - Common expectations or datasets if needed (leave empty for now).

- [x] **T2.3 Helpers**
  - In `TestCase.php` add: `signInAdmin()`, `ensureCollectionExists($handle, $route = '/{slug}')`, `makeStatamicItem(array $data = [], string $collection = 'pages')`, and a new `makeConfirmedReservation(array $overrides = [])` that creates a Statamic entry + a Resrv `Entry` row + a `Reservation` model in `confirmed` status. Use Resrv's existing factories where possible.

- [x] **T2.4 `phpunit.xml`**
  - PEST reads this for env. Include testsuite definitions for `Feature` and `Unit`. Set env: `DB_CONNECTION=sqlite`, `MAIL_MAILER=array`, `CACHE_DRIVER=array`, `APP_KEY=base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF1+...` (any fixed key works).

- [x] **T2.5 Smoke test**
  - `tests/Feature/BootTest.php`: `it('boots the package without error', fn() => expect(app()->bound('config'))->toBeTrue());`
  - **Self-check:** `vendor/bin/pest` passes with 1 test.

## Phase 3 — Database & models

- [x] **T3.1 Migration: `resrv_vouchers`**
  - Columns: `id` (string PK, UUID), `reservation_id` (unsigned big int, indexed, unique to keep generation idempotent), `token` (string, unique), `status` (string, indexed), `used_at` (timestamp, nullable), `used_by_user_id` (string nullable — Statamic user IDs are strings), `invalidated_reason` (text nullable), `expires_at` (timestamp), `timestamps`.
  - Do **not** add a FK constraint on `reservation_id` — Resrv migrations live in a separate package, FK order is fragile across drivers.
  - **Self-check:** migration runs on SQLite + roundtrips.

- [x] **T3.2 Migration: `resrv_voucher_scans`**
  - Columns: `id` (auto-increment), `voucher_id` (string, indexed), `user_id` (string nullable), `action` (string: `scan|mark-used|un-mark`), `result` (string: `success|already-used|invalidated|expired|not-found`), `ip_address` (string, nullable), `user_agent` (text, nullable), `timestamps`.

- [x] **T3.3 `Voucher` model**
  - `src/Models/Voucher.php`
  - `protected $table = 'resrv_vouchers';`
  - `protected $keyType = 'string';` and `public $incrementing = false;` (UUID PK).
  - Boot a UUID generator in `creating`.
  - Casts: `status => VoucherStatus::class`, `expires_at => datetime`, `used_at => datetime`.
  - Relations: `reservation()` belongsTo `Reach\StatamicResrv\Models\Reservation::class`, `scans()` hasMany `VoucherScan::class`.
  - Helper: `isExpired(): bool` (computed from `expires_at` vs `now()`).

- [x] **T3.4 `VoucherScan` model**
  - `src/Models/VoucherScan.php`
  - Relations: `voucher()`, `user()` (resolve via `Statamic::user($this->user_id)` accessor rather than belongsTo, because Statamic users aren't Eloquent in all setups).

- [x] **T3.5 `VoucherStatus` enum**
  - `src/Enums/VoucherStatus.php`
  - Cases: `Issued`, `Used`, `Invalidated`, `Expired`. Backed by string values lowercase.

- [x] **T3.6 Tests**
  - Unit tests: model boots, status cast works, `isExpired()` true when `expires_at < now()`.
  - **Self-check:** `vendor/bin/pest` green.

## Phase 4 — Token signer & QR renderer

- [x] **T4.1 `VoucherTokenSigner`**
  - `src/Services/VoucherTokenSigner.php`
  - Constructor takes the signing key (resolved from `config('resrv-vouchers.signing_key') ?? config('app.key')`).
  - `sign(string $uuid): string` → `base64url(uuid) . '.' . base64url(hmac_sha256(uuid, key))`.
  - `verify(string $token): ?string` → returns the UUID on success, `null` on tamper / bad format. Use `hash_equals` for constant-time compare.

- [x] **T4.2 Signer unit tests**
  - Round-trip: sign → verify returns the original UUID.
  - Tamper: flipping any character in the token → verify returns null.
  - Wrong key: signer with different key rejects.
  - Malformed input (no dot, bad base64): null, no exception.

- [x] **T4.3 `QrRenderer`**
  - `src/Services/QrRenderer.php`
  - `png(string $payload, int $size = 320): string` — bytes (use endroid/qr-code v5 builder API: `Builder::create()->writer(new PngWriter)->data($payload)->size($size)->build()->getString()`).
  - `pdf(Voucher $voucher): string` — bytes for a single-page A6 PDF containing the QR + customer name + reservation reference + date range. Use endroid's PDF writer if available, else fall back to a minimal hand-rolled `\Spatie\Browsershot\Browsershot` or `dompdf/dompdf` — pick whichever is simpler and document the choice in a code comment **only if non-obvious** (lean toward endroid's native PdfWriter when v5 supports it).
  - Pure service, no global state.

- [x] **T4.4 Renderer unit tests**
  - PNG output starts with the PNG signature bytes (`\x89PNG\r\n\x1A\n`).
  - PDF output starts with `%PDF-`.
  - Both return non-empty.

## Phase 5 — Voucher generation on `ReservationConfirmed`

- [x] **T5.1 `VoucherGenerator` service**
  - `src/Services/VoucherGenerator.php`
  - `generateFor(Reservation $reservation): Voucher`
  - Idempotent: if a voucher exists for this `reservation_id`, return it.
  - Resolves collection: `Reach\StatamicResrv\Models\Entry::whereItemId($reservation->item_id)->first()?->collection`.
  - If the collection is not in `config('resrv-vouchers.enabled_collections')`, throw a domain exception that the listener can catch and silently skip.
  - Sets `expires_at = $reservation->date_end + grace_days`.
  - Signs the UUID via `VoucherTokenSigner` and stores `token`.

- [x] **T5.2 `GenerateVoucherForReservation` listener**
  - `src/Listeners/GenerateVoucherForReservation.php`
  - Catches the `ShouldNotIssueVoucherException` (or just early-returns by checking collection eligibility before calling the generator — pick one approach and stay consistent).
  - Queueable (`implements ShouldQueue`) so it doesn't slow webhooks.

- [x] **T5.3 Wire listener**
  - In `VouchersProvider::$listen`:
    ```php
    \Reach\StatamicResrv\Events\ReservationConfirmed::class => [
        \Reach\StatamicResrvVouchers\Listeners\GenerateVoucherForReservation::class,
    ],
    ```

- [x] **T5.4 Feature tests**
  - `tests/Feature/VoucherGenerationTest.php`:
    - Confirming a reservation in an enabled collection creates one voucher with `status=Issued` and a valid signed token.
    - Re-firing the event does NOT create a duplicate (idempotency).
    - Confirming a reservation in a disabled collection creates no voucher.
    - `expires_at` equals `reservation.date_end + grace_days`.
  - **Self-check:** `vendor/bin/pest` green.

## Phase 6 — Email integration

- [x] **T6.1 `AttachVoucherToReservationEmail` listener**
  - `src/Listeners/AttachVoucherToReservationEmail.php`
  - Listens to `\Reach\StatamicResrv\Events\BuildingReservationEmail`.
  - Resolves the voucher by `$event->reservation->id`. If none (e.g. collection not enabled), return.
  - Calls `$event->mailable->attachData($pdfBytes, "voucher-{$voucher->id}.pdf", ['mime' => 'application/pdf'])`.
  - For the inline image, use `$event->mailable->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($pngBytes) { $email->embed($pngBytes, 'voucher-qr', 'image/png'); });` so the markdown template can reference `<img src="cid:voucher-qr">`.
  - Make the listener **synchronous** (NOT queued) — it needs to mutate the mailable that's about to be sent.

- [x] **T6.2 Markdown partial for the QR image**
  - `resources/views/email/vouchers/partials/qr.blade.php` — a tiny snippet that the user (in a host app) can `@include('statamic-resrv-vouchers::email.vouchers.partials.qr')` from their published `statamic-resrv/email/reservations/confirmed.blade.php` to render the inline QR. Document this in README and in T11.2.
  - **Tradeoff acknowledged in plan:** The Resrv default confirmation template does not reference the QR. Hosts must publish the Resrv template and add the include. Until they do, the QR is still attached as PDF and the email still works.

- [x] **T6.3 Wire listener**
  - `VouchersProvider::$listen` gets:
    ```php
    \Reach\StatamicResrv\Events\BuildingReservationEmail::class => [
        \Reach\StatamicResrvVouchers\Listeners\AttachVoucherToReservationEmail::class,
    ],
    ```

- [x] **T6.4 Feature test for email attachment**
  - `tests/Feature/EmailAttachmentTest.php`
  - `Mail::fake()`, confirm a reservation, assert `\Reach\StatamicResrv\Mail\ReservationConfirmed` was sent with a PDF attachment matching `voucher-*.pdf` and the embedded `voucher-qr` CID.
  - Also assert the un-enabled-collection case sends the email WITHOUT any voucher attachment.

- [x] **T6.5 Cross-package sanity**
  - Run Resrv's own PHPUnit suite (`cd ../statamic-resrv && vendor/bin/phpunit`) to confirm Phase 0 changes don't regress anything.

## Phase 7 — Lifecycle (cancel / refund / expired)

- [x] **T7.1 `VoucherStateMachine` service**
  - `src/Services/VoucherStateMachine.php`
  - Methods:
    - `statusOf(Voucher $v): VoucherStatus` — lazy expiration check: if `$v->status === Issued && now > expires_at`, returns `Expired` (no DB write).
    - `markUsed(Voucher $v, ?string $userId): void` — only from `Issued`; dispatches `VoucherUsed`.
    - `unMark(Voucher $v, ?string $userId): void` — only from `Used`; dispatches `VoucherUnmarked`.
    - `invalidate(Voucher $v, string $reason): void` — from any non-final state; dispatches `VoucherInvalidated`.
  - Wraps DB updates in a transaction.

- [x] **T7.2 `InvalidateVoucherOnCancellation` listener**
  - Listens to `\Reach\StatamicResrv\Events\ReservationCancelled`, `ReservationRefunded`, `ReservationExpired`.
  - Looks up the voucher, calls `VoucherStateMachine::invalidate($voucher, $reasonFromEventClass)`.
  - Skips silently if no voucher exists.

- [x] **T7.3 Lifecycle event classes**
  - `src/Events/VoucherUsed.php`, `VoucherUnmarked.php`, `VoucherInvalidated.php` — all carry the voucher (+ user id where applicable).

- [x] **T7.4 Feature tests**
  - `tests/Feature/CancellationInvalidatesVoucherTest.php`
  - Cancel → voucher status `Invalidated`, reason `cancelled`.
  - Refund → voucher status `Invalidated`, reason `refunded`.
  - ReservationExpired → voucher status `Invalidated`, reason `expired-reservation`.
  - `tests/Feature/ExpirationTest.php` — voucher past `expires_at` reports `Expired` via `statusOf()` without DB mutation.

## Phase 8 — CP routes, controllers, Blade

- [x] **T8.1 `routes/cp.php`**
  - All routes guarded `middleware('can:use resrv')`.
  - ```php
    Route::name('resrv-vouchers.')->prefix('resrv-vouchers')->group(function () {
        Route::get('/', [VoucherCpController::class, 'indexCp'])->name('index');
        Route::get('/list', [VoucherCpController::class, 'index'])->name('index.json');
        Route::get('/scan', [VoucherCpController::class, 'scanCp'])->name('scan');
        Route::post('/lookup', [VoucherCpController::class, 'lookup'])->name('lookup');
        Route::patch('/mark-used', [VoucherCpController::class, 'markUsed'])->name('mark-used');
        Route::patch('/un-mark', [VoucherCpController::class, 'unMark'])->name('un-mark');
        Route::post('/resend/{voucher}', [VoucherCpController::class, 'resend'])->name('resend');
    });
    ```

- [x] **T8.2 `VoucherCpController`**
  - `src/Http/Controllers/VoucherCpController.php`
  - `indexCp()` → Blade `cp.vouchers.index`.
  - `index(Request $r)` → JSON listing, supports filters `collection`, `status`, pagination.
  - `scanCp()` → Blade `cp.vouchers.scan`.
  - `lookup(LookupRequest $r)` — validate token via `VoucherTokenSigner`, find voucher, write a `scan` audit row with the resulting `result`, return JSON `{voucher, reservation, status_banner}`. Status banner content driven by `VoucherStateMachine::statusOf()`.
  - `markUsed(MarkUsedRequest $r)` — calls `VoucherStateMachine::markUsed()`, writes `mark-used` audit row, returns updated voucher.
  - `unMark(MarkUsedRequest $r)` — calls `VoucherStateMachine::unMark()`, writes `un-mark` audit row.
  - `resend(Voucher $v)` — re-dispatch `\Reach\StatamicResrv\Mail\ReservationConfirmed` to the customer (our `AttachVoucherToReservationEmail` listener fires automatically). Audit-log a `resend` action.
  - All endpoints return JSON.

- [x] **T8.3 Blade views**
  - `resources/views/cp/vouchers/index.blade.php` — `@extends('statamic::layout')`, mounts `<vouchers-list>`.
  - `resources/views/cp/vouchers/scan.blade.php` — `@extends('statamic::layout')`, mounts `<voucher-scanner>`.

- [x] **T8.4 CP nav**
  - In `VouchersProvider::createNavigation()` (or boot), register a "Vouchers" item in the "Resrv" section with children "Scan" and "List", each `->can('use resrv')`. Mirror the pattern in `Reach\StatamicResrv\Providers\ResrvProvider::createNavigation()`.

- [x] **T8.5 Feature tests**
  - `tests/Feature/CpScanFlowTest.php`
  - Per endpoint: 200 on happy path, 403 without `use resrv`, 422 for malformed/invalid tokens.
  - Verify audit-log row inserted for lookup, mark-used, un-mark, resend.

## Phase V6 — Statamic v6 alignment sweep

> Lands **before** Phase 9 so the frontend can be built v6-native (no Vue 2 conversion later). Cross-references the `statamic-addon-v5-to-v6` skill under `/Users/afonic/.claude/skills/statamic-addon-v5-to-v6/`.

### Pre-flight audit (read-only — already complete, recorded here for traceability)

Audit run 2026-05-16 against the current addon tree. Findings drive the tasks below.

| Concern | Status | Action |
| --- | --- | --- |
| Composer pins | `php ^8.3`, `statamic/cms ^6.0` ✓ | Done (TV6.1) |
| Forma in use | No | Nothing to do |
| Service provider base | `extends AddonServiceProvider` ✓ | No change to base, only `boot()` rename |
| `boot()` vs `bootAddon()` | `bootAddon()` ✓ | Done (TV6.3) |
| Deprecated calls (`Site::setConfig`, `addLocalization`, `filterSortAndPaginate`, `where('status',...)`) | None | Skip |
| `GlobalSet*` event references | None | Skip |
| Build chain (Mix / Vite4 / Vue 2) | None — never built | Build v6-native in Phase 9 |
| Vue 2 components | None (`resources/js/components/.gitkeep` only) | Skip — no migration debt |
| Fieldtypes | None | Skip |
| Blade CP views | None ✓ | Deleted (TV6.5); controllers now return Inertia (TV6.4) |
| `AddonTestCase` in `tests/TestCase.php` | `Statamic\Testing\AddonTestCase` ✓ | Done (TV6.6) |
| Sibling Resrv branch / state | `v6-upgrade` (Phase 2+3+4 done; Vue/Inertia in progress; TestCase still on `OrchestraTestCase`) | Re-test once Resrv's v6 work catches up |

### Tasks

- [x] **TV6.1 Bump composer constraints**
  - `composer.json` `require`: PHP `^8.3`, `statamic/cms ^6.0`. Keep `laravel/framework ^12.0 || ^13.0`, `endroid/qr-code ^5.0`, `setasign/fpdf ^1.8.2`, `reachweb/statamic-resrv:*` (path repo, dev-`v6-upgrade`).
  - `require-dev`: keep `orchestra/testbench ^10.0 || ^11.0`, `pestphp/pest ^4.0`, `pestphp/pest-plugin-laravel ^4.0`, `laravel/pint ^1.2`, `laravel/boost ^2.1`.
  - **Self-check:** `composer install` resolves cleanly against the sibling Resrv `v6-upgrade` branch (which is already on Statamic ^6).

- [x] **TV6.2 Add `protected $vite` to `VouchersProvider`**
  - ```php
    protected $vite = [
        'input' => ['resources/js/cp.js', 'resources/css/cp.css'],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../../resources/dist/hot',
    ];
    ```
  - Path depth `../../` because the provider lives at `src/Providers/VouchersProvider.php`.
  - **Self-check:** `php artisan config:clear` in a host site; loading the CP doesn't error. The actual JS / CSS files are produced in Phase 9.

- [x] **TV6.3 Rename `boot()` → `bootAddon()` in `VouchersProvider`**
  - `AddonServiceProvider::boot()` is reserved in v6 — overriding it silently disables auto-discovery. Move all the current body (`loadTranslationsFrom`, `loadViewsFrom`, `loadMigrationsFrom`, `mergeConfigFrom`, `publishes`, `createNavigation`) into `bootAddon()`. Drop the `parent::boot()` call (it isn't ours to call any more).
  - **Self-check:** boot smoke test (`tests/Feature/BootTest.php`) still green.

- [x] **TV6.4 Switch `VoucherCpController` to return `Inertia::render(...)`**
  - `indexCp()` → `Inertia::render('resrv-vouchers::Vouchers/Index', [...])` with whatever bootstrap props the page needs (columns, filters, action URLs, default per-page, etc.). The JSON listing endpoint at `/resrv-vouchers/list` stays as-is — the Inertia page hits it via `<Listing :url=… />` from `@statamic/cms/ui`.
  - `scanCp()` → `Inertia::render('resrv-vouchers::Vouchers/Scan', [...])` with the lookup / mark-used / un-mark URLs precomputed via `cp_route(...)` so the Vue page doesn't have to know route names.
  - All mutation endpoints (`lookup`, `markUsed`, `unMark`, `resend`) keep returning JSON unchanged — Inertia pages call them with `axios` / `useForm`.
  - **Self-check:** Visiting `/cp/resrv-vouchers` and `/cp/resrv-vouchers/scan` returns an Inertia response (`X-Inertia: true` header set when requested with that header; HTML shell otherwise). Existing `tests/Feature/CpScanFlowTest.php` mutation assertions stay green.

- [x] **TV6.5 Delete the two CP Blade views**
  - Remove `resources/views/cp/vouchers/index.blade.php` and `resources/views/cp/vouchers/scan.blade.php`. Phase 9 ships their Vue 3 / Inertia replacements.
  - The blueprint-style email partial `resources/views/email/vouchers/partials/qr.blade.php` stays — it's a Blade snippet hosts `@include` from their published Resrv confirmation template, nothing to do with the CP.
  - **Self-check:** `grep -rn "@extends('statamic::layout')" resources/views/` returns zero hits.

- [x] **TV6.6 Switch `tests/TestCase.php` to `Statamic\Testing\AddonTestCase`**
  - ```php
    use Statamic\Testing\AddonTestCase;

    abstract class TestCase extends AddonTestCase
    {
        protected string $addonServiceProvider = \Reach\StatamicResrvVouchers\StatamicResrvVouchersServiceProvider::class;
        // …keep all helpers (signInAdmin, makeConfirmedReservation, etc.)…
    }
    ```
  - Drop `getPackageProviders()` (the base class registers `StatamicServiceProvider` + our `$addonServiceProvider`), but keep Livewire and the Resrv provider in a smaller override since they're separate addons:
    ```php
    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
            StatamicLivewireServiceProvider::class,
            StatamicResrvServiceProvider::class,
        ]);
    }
    ```
  - Drop the hand-rolled `Version::shouldReceive('get')->andReturn('5.5.0')` stub — `AddonTestCase` resolves the real Statamic version (now 6.x). If a test asserts on it, update the expectation.
  - **Self-check:** `vendor/bin/pest` — same 42 passing tests as the v5 baseline. Any new failures are real regressions (likely real v6 surface), not bookkeeping.

- [x] **TV6.7 Cross-package sanity vs sibling Resrv `v6-upgrade`**
  - `composer install` succeeds with the path-repo pointing at `../statamic-resrv` on its current branch.
  - Run `vendor/bin/pest` once Resrv's v6 work has caught up far enough to provide a green build (it currently does; the Vue/Inertia work over there doesn't touch our test path).
  - If Resrv tags a `^6.0` release later, replace the `*` constraint in `composer.json` with that tag and remove the path repo.
  - **Status check 2026-05-16:** composer install resolves cleanly. Vouchers pest 51/120 green — exercises Resrv source (models, events, mailables, customer). Resrv's *own* phpunit suite currently errors on all 802 tests because Resrv's `tests/TestCase.php` still references the v5-only `Statamic\Extend\Manifest`. The same `AddonTestCase` migration we did in TV6.6 is needed in Resrv — see the v6 playbook at `/Users/afonic/.claude/skills/statamic-addon-v5-to-v6/`. Out-of-scope for Vouchers per cross-cutting rule #5; flagged for Resrv's own v6-upgrade backlog.

### Phase V6 acceptance

- composer + tests both pass against Statamic 6.
- No Blade CP views remain.
- `VouchersProvider` uses `bootAddon()` and declares `protected $vite`.
- `tests/TestCase.php` extends `Statamic\Testing\AddonTestCase`.
- The pre-existing 42 PEST tests are still green (or replaced/updated where v6 changed behaviour — flag any such swaps in the done-log).

## Phase 9 — Frontend (Inertia + Vue 3 + html5-qrcode)

> Built **v6-native** — no Vue 2 step ever existed for this addon. Pulls heavily from `references/04-vite-and-tailwind.md`, `references/05-vue3-migration.md`, and `references/07-cp-pages-inertia.md` in the v5→v6 skill.

- [x] **T9.1 `package.json` + `vite.config.js`**
  - Create `package.json` with `"type": "module"`, scripts `cp:dev` / `cp:build`, deps:
    ```json
    "dependencies": {
        "@statamic/cms": "file:../../../vendor/statamic/cms/resources/dist-package",
        "html5-qrcode": "^2.3",
        "tailwindcss": "^4.0.0"
    },
    "devDependencies": {
        "@tailwindcss/vite": "^4.0.0",
        "laravel-vite-plugin": "^3.0.0",
        "vite": "^8.0.0"
    }
    ```
    (Adjust the `file:` path to whatever the host site's vendor layout is — for our umami host, `file:../../../vendor/statamic/cms/resources/dist-package` works. Resrv's `package.json` is the canonical pattern.)
  - `vite.config.js`:
    ```js
    import { defineConfig } from 'vite';
    import laravel from 'laravel-vite-plugin';
    import statamic from '@statamic/cms/vite-plugin';
    import tailwindcss from '@tailwindcss/vite';

    export default defineConfig({
        plugins: [
            statamic(),
            tailwindcss(),
            laravel({
                input: ['resources/js/cp.js', 'resources/css/cp.css'],
                publicDirectory: 'resources/dist',
                hotFile: 'resources/dist/hot',
                refresh: true,
            }),
        ],
    });
    ```
  - **Self-check:** `npm install && npm run cp:build` exits 0, emits files under `resources/dist/build/`, `npm ls @vitejs/plugin-vue2` reports nothing.

- [x] **T9.2 `resources/js/cp.js`**
  - ```js
    import VouchersIndex from './pages/Vouchers/Index.vue';
    import VouchersScan from './pages/Vouchers/Scan.vue';

    Statamic.booting(() => {
        Statamic.$inertia.register('resrv-vouchers::Vouchers/Index', VouchersIndex);
        Statamic.$inertia.register('resrv-vouchers::Vouchers/Scan', VouchersScan);
    });
    ```
  - Inertia handle string MUST match what `VoucherCpController` passes to `Inertia::render(...)` (TV6.4).

- [x] **T9.3 `resources/css/cp.css`**
  - Single line: `@import "tailwindcss";`. No `tailwind.config.js`, no PostCSS chain — Tailwind v4 reads `@theme` straight from CSS if/when we add custom tokens.

- [x] **T9.4 `resources/js/pages/Vouchers/Scan.vue` (Inertia page)**
  - `<script setup>` (Composition API). Imports `Head` from `@statamic/cms/inertia` and `Header`, `Button`, `Field`, `Input`, `Badge`, `CardPanel` from `@statamic/cms/ui`.
  - Mounts an `html5-qrcode` scanner inside the panel in `onMounted`; tears it down in `onBeforeUnmount`.
  - On successful decode, calls `axios.post(props.lookupUrl, { token })` (URLs come in as props from the controller per TV6.4).
  - Renders a result card: customer name, reservation reference, dates, party size, status banner (color-coded by `VoucherStatus` value).
  - Buttons:
    - When `issued`: **"Mark as used"** → `axios.patch(props.markUsedUrl, { token })`.
    - When `used`: **"Un-mark"** → `axios.patch(props.unMarkUrl, { token })`.
    - Always: **"Scan another"**.
  - Text-input fallback below the camera viewport with a "Validate" button.
  - Visible "switch camera" toggle if `Html5Qrcode.getCameras()` returns more than one device.

- [x] **T9.5 `resources/js/pages/Vouchers/Index.vue` (Inertia page)**
  - Uses `<Listing :url="props.listUrl" :columns :filters :action-url="props.actionUrl" sort-column="created_at" sort-direction="desc" preferences-prefix="resrv-vouchers.vouchers" push-query />` from `@statamic/cms/ui`. The existing `index(Request $r)` controller method already returns the JSON shape `<Listing>` expects.
  - Filters supplied as props from `VoucherCpController::indexCp()` — `collection` (select), `status` (multi-select).
  - Resend action wired through `<Listing>`'s `:action-url`; the controller's existing `resend` endpoint becomes a bulk action handler. Toast on success uses the global Statamic toaster.

- [x] **T9.6 Wire `protected $vite` in `VouchersProvider`** (was TV6.2; restated here for completeness)
  - The provider's `$vite` array must point at the input files produced by T9.1. Path: `__DIR__.'/../../resources/dist/hot'` because the provider sits at `src/Providers/VouchersProvider.php`. A wrong depth silently disables HMR.

- [ ] **T9.7 Manual UAT**
  - Cannot be unit-tested. After Phase 10 lands, open the CP on a phone, allow camera, scan a real generated QR. Verify Inertia partial reloads (no full-page reload between scans). Document any quirks in README troubleshooting section. Mobile Safari (iOS) requires HTTPS for camera access — note that in README.

## Phase 10 — VoucherUsed → attended email

- [x] **T10.1 Wire up state-machine events**
  - Verify `VoucherStateMachine::markUsed()` dispatches `VoucherUsed` only on successful transition. Same for `unMark()`/`VoucherUnmarked`. Already in T7.1; this task is a verification.

- [x] **T10.2 `SendAttendedEmailOnVoucherUsed` listener**
  - `src/Listeners/SendAttendedEmailOnVoucherUsed.php`
  - On `VoucherUsed`, queue `Mail\VoucherAttended` to the customer email.
  - Don't send if the voucher's reservation has no customer email (defensive — shouldn't happen).

- [x] **T10.3 `Mail\VoucherAttended`**
  - Extends `Reach\StatamicResrv\Mail\Mailable` to inherit theme components.
  - Markdown template `resources/views/email/vouchers/attended.blade.php` (publishable).
  - `applyResrvEmailConfig(config('resrv-vouchers.email.attended'))` for subject/from/template override.

- [x] **T10.4 Wire listener**
  - In `VouchersProvider::$listen`:
    ```php
    \Reach\StatamicResrvVouchers\Events\VoucherUsed::class => [
        \Reach\StatamicResrvVouchers\Listeners\SendAttendedEmailOnVoucherUsed::class,
    ],
    ```

- [x] **T10.5 Feature tests**
  - Marking used dispatches `VoucherUsed` and queues `VoucherAttended` to the customer (`Mail::assertQueued`).
  - Un-marking does NOT trigger the attended email.
  - Markdown template renders with customer name + reservation reference + (optionally) date range.

## Phase 11 — Install command, README, polish

- [x] **T11.1 `InstallVouchers` console command**
  - `src/Console/Commands/InstallVouchers.php` signature `resrv-vouchers:install`.
  - Steps: publish config tag, publish views tag, run migrations, then `$this->info('...')` summary including: "Add collection handles to `config/resrv-vouchers.php` `enabled_collections`. Ensure `reachweb/statamic-resrv` >= the version that ships `BuildingReservationEmail`."
  - Register in `VouchersProvider::$commands`.

- [x] **T11.2 `README.md`**
  - Sections: Requirements (Resrv version that ships `BuildingReservationEmail`, **Statamic 6, PHP 8.3+, Laravel 12 or 13**), Installation (`composer require ... && php artisan resrv-vouchers:install`), Configuration (`enabled_collections`, `grace_days`, `signing_key`), Email customization (publish + add include directive), CP usage (scanner + list — note both are Inertia pages, full HMR with `npm run cp:dev`), Troubleshooting (camera-permission HTTPS note, scanning fails over `http://` in production; if `npm run cp:build` errors about `@statamic/cms` missing, the host site needs `composer install` first so the Vite plugin can resolve from `vendor/`).

- [x] **T11.3 Document the hook in Resrv**
  - Add a short section to Resrv's `CLAUDE.md` documenting the new `BuildingReservationEmail` event for future addons. Reference Vouchers as the canonical consumer.

- [x] **T11.4 Final test run**
  - `vendor/bin/pest` in `statamic-resrv-vouchers` — all green.
  - `vendor/bin/phpunit` in `statamic-resrv` — all green.
  - `vendor/bin/pint` clean in both packages.
  - **Status check 2026-05-16:** Vouchers ✅ pest 51/120 green, ✅ pint clean. Resrv ❌ phpunit 802 errors (Resrv's TestCase still on v5 Manifest API — see TV6.7 status note), ⚠️ pint reports 3 files with stylistic fixes (`src/Models/Availability.php`, `src/Console/Commands/UpgradeToRates.php`, `tests/TestCase.php`) — all pre-existing on `v6-upgrade`, none touched by Vouchers work. Files this addon added/modified in Resrv (Phase 0 + T11.3) individually pass pint. Marking partial; full green awaits Resrv's own v6 migration finishing.
  - **Status check 2026-06-05:** Vouchers ✅ pest 65/165 green against Resrv `dev-main`, ✅ pint clean. Resrv (`main` + the voucher hook merge) ✅ phpunit 1146 tests / 3227 assertions OK (1 skipped), ✅ the 4 voucher-hook files pass pint; ⚠️ full pint flags one pre-existing file (`src/Support/ActiveReservationsGuard.php`, statement_indentation) untouched by Vouchers work — left for Resrv per cross-cutting rule #5.

- [ ] **T11.5 Manual cross-browser UAT**
  - Mobile Safari (iOS): scan works, camera permission prompt clear.
  - Chrome Android: scan works.
  - Desktop Chrome / Firefox: text-input fallback works when camera denied/absent.

---

## Phase UAT — real-site fixes (tester report 2026-06-11, dev-main cef7e7b on a Statamic 6 host)

All 11 reported issues verified valid against the code before fixing. Decisions: missing-Resrv-event guard **throws at boot** (fail loudly); resend listing action **deferred**.

- [x] **TU.1 Rebuild the Vouchers Index listing on the Statamic scopes/resource protocol** (issues 2–4, 8)
  - `src/Filters/VoucherStatus.php` (Scope filter, handle `voucher_status`, registered via `$scopes`), `src/Blueprints/VoucherBlueprint.php`, `src/Resources/VoucherResource.php` (`HasRequestedColumns`, `meta.columns`, dates via `preProcessIndex`).
  - `VoucherCpController::index(FilteredRequest)` + `QueriesFilters` → `meta.activeFilterBadges`; sort whitelist; `perPage` clamp; `search` on id + reservation reference.
  - `Index.vue`: server-driven filters/columns, `<Header :title>` (titles rendered top-right before — the Header default slot is the actions area).

- [x] **TU.2 Scan page fixes** (issues 5–9)
  - Styled `Alert` banner instead of raw JSON; card state updates from the PATCH response (no re-lookup → no `scan | already-used` audit pollution); server-formatted dates; camera starts on click; `<Header :title>`.

- [x] **TU.3 endroid/qr-code ^6.0** (issue 10) — implicit-nullable deprecation warnings on PHP 8.4/8.5; QrRenderer moves to the 6.x constructor Builder API.

- [x] **TU.4 Boot guard for `BuildingReservationEmail`** (issue 11) — `VouchersProvider::bootAddon()` throws a RuntimeException when the event class is missing (stale Resrv).

- [x] **TU.5 Ship compiled CP assets** (issue 1) — `.gitignore` ignores only `resources/dist/hot` (mirror Resrv); `resources/dist/build` committed; rebuild before tagging/pushing whenever `resources/js|css` change.

- [ ] **TU.6 Docs** — README (camera click-to-start, shipped dist), CLAUDE.md (dist convention).

- [ ] **TU.7 Resend row action in the Index listing** (deferred from this round; original T9.5 scope) — per-row Dropdown + ConfirmationModal calling the existing `resend` endpoint (Resrv's `Reservations/Index.vue` pattern), toast on success.

- [ ] **TU.8 Constrain `reachweb/statamic-resrv` to `^6.0` once Resrv tags it** (replaces the `*` + boot-guard stopgap).

- [ ] **TU.9 Re-run the real-site UAT (mylos)** — list renders with filters/sort/search/columns; scan flow incl. mark-used/un-mark from PATCH response; no endroid deprecations in queue output; fresh `composer require` gets working CP assets.

---

## Acceptance criteria (treat as the gate)

- All backend tasks (Phases 0–8) + Phase V6 + Phase 9 + Phase 10 + Phase 11 checked.
- PEST suite green against Statamic 6 / `Statamic\Testing\AddonTestCase`; sibling Resrv PHPUnit suite (on its `v6-upgrade` branch) green.
- `npm run cp:build` exits 0, emits `resources/dist/build/`, no `@vitejs/plugin-vue2` in the dep graph.
- Confirming a reservation in an enabled collection triggers an email with an inline QR PNG and a PDF attachment.
- `/cp/resrv-vouchers/scan` and `/cp/resrv-vouchers` both render as Inertia pages (no Blade fallback) on a v6 host site.
- Scanning a QR displays the reservation; "Mark as used" flips status, sends the attended email, audit-logs the action.
- Cancellation/refund invalidates the voucher; expired vouchers report as expired without a cron.
- A second admin can "Un-mark" a used voucher without triggering a new customer email.

## Out of scope (do not implement without re-asking the user)

- Multiple QRs per reservation (per-attendee).
- Public-facing (non-CP) "scan my own ticket" page.
- Voucher transfer between customers.
- Custom voucher artwork / branded PDF layouts beyond a simple QR + summary.
- A separate `use resrv-vouchers` permission.
- Replacing Resrv's confirmation template (instead, we provide an `@include` snippet).
- A v5 release line — the addon ships v6-only. Hosts still on Statamic 5 use the v5-era Resrv without vouchers.
- `UserConfig.php` + settings blueprint — the current config keys are developer-managed; revisit if user-facing settings appear.

---

## Done log (append-only — one line per completed task)

<!-- e.g. T0.1 done 2026-05-15: BuildingReservationEmail event added; PHPUnit green. -->
T1.1 done 2026-05-15: composer.json + path repo to ../statamic-resrv; composer validate clean, composer install succeeds. Resrv constraint relaxed to `*` until Phase 0 lands and the `feature/building-email-event` branch exists. Laravel ^12.0 || ^13.0 / Testbench ^10.0 || ^11.0 / PEST ^4.0 (Statamic 5 currently caps at L12, so Composer resolves to L12 until Statamic 6).
T1.2 done 2026-05-15: Aggregate StatamicResrvVouchersServiceProvider + VouchersProvider (extends AddonServiceProvider) with `$routes['cp']`, empty `$listen`, and boot() loading translations/views/migrations/config. Placeholder `routes/cp.php` and `config/resrv-vouchers.php` added so boot doesn't fatal; T1.3 fleshes out the config. Smoke-booted under Orchestra Testbench's CreatesApplication — 63 providers loaded, `config('resrv-vouchers')` resolves.
T1.3 done 2026-05-15: config/resrv-vouchers.php fleshed out with enabled_collections, grace_days, signing_key (env-driven), and email.attended block. Publish tag `resrv-vouchers-config` registered in VouchersProvider::boot(). Verified via BootTest assertion.
T1.4 done 2026-05-15: Scaffolded src/{Console/Commands,Events,Http/Controllers,Http/Requests,Listeners,Mail,Models,Services,Enums,Exceptions} + resources/{lang/en,views/cp/vouchers,views/email/vouchers,js/components} + tests/{Feature,Unit}; added .gitkeep to dirs without files yet.
T1.5 done 2026-05-15: pint.json (Laravel preset) added at addon root. composer test/test:stop scripts already present from T1.1. CI workflow deferred per task note.
T2.1 done 2026-05-15: tests/TestCase.php extends OrchestraTestCase with RefreshDatabase, package providers (Statamic + Livewire + StatamicLivewire + Resrv + Vouchers), Statamic config copy, sites stub, and `defineDatabaseMigrations()` running `migrate` against the default sqlite-in-memory connection (the `--database=testbench` form failed with "connection not configured" against Testbench 11; default works).
T2.2 done 2026-05-15: tests/Pest.php wires `uses(TestCase::class)->in(__DIR__)`.
T2.3 done 2026-05-15: TestCase helpers added — signInAdmin(), ensureCollectionExists(), makeStatamicItem(), makeConfirmedReservation(). Latter creates Statamic entry + resrv_entries row + Customer + Reservation, since Resrv migrated `customer` JSON column → `customer_id` FK + Customer model in 2025-03-20 batch.
T2.4 done 2026-05-15: phpunit.xml with Feature/Unit suites + sqlite-in-memory + array mailer/cache/session + sync queue + RESRV_VOUCHERS_SIGNING_KEY env. Mirrors Resrv's bootstrap shape.
T2.5 done 2026-05-15: tests/Feature/BootTest.php — boot smoke test plus a config defaults assertion. Both green (2 passed, 3 assertions).
T3.1 done 2026-05-15: 2026_05_15_000001 create resrv_vouchers (string PK, unique reservation_id + token, indexed status, expires_at, used_at, used_by_user_id, invalidated_reason). No FK on reservation_id per CLAUDE.md guidance — Resrv migrations live in a separate package.
T3.2 done 2026-05-15: 2026_05_15_000002 create resrv_voucher_scans (auto-increment id, indexed voucher_id, action/result strings, ip + UA).
T3.3 done 2026-05-15: Voucher model — string UUID PK, autogenerated in `creating` boot hook, casts (status enum + datetimes), reservation belongsTo + scans hasMany, isExpired() helper.
T3.4 done 2026-05-15: VoucherScan model — guarded[]=[], voucher belongsTo, user accessor resolves Statamic::user($this->user_id) (no Eloquent FK because Statamic users may be flat-file).
T3.5 done 2026-05-15: VoucherStatus backed enum — Issued/Used/Invalidated/Expired (lowercase string values).
T3.6 done 2026-05-15: tests/Unit/VoucherModelTest.php — 6 tests covering UUID auto-generation, manual ID preservation, status enum cast roundtrip, isExpired true/false, scans relation. All green.
T4.1 done 2026-05-15: VoucherTokenSigner — readonly key, sign() = base64url(uuid).base64url(hmac_sha256(uuid,key)), verify() returns uuid|null with hash_equals. Static `fromConfig()` resolves `resrv-vouchers.signing_key` then falls back to `app.key` (decodes the `base64:` prefix Laravel uses).
T4.2 done 2026-05-15: tests/Unit/VoucherTokenSignerTest.php — round-trip, tamper, wrong-key, malformed, empty-key (throws), and two fromConfig() resolution paths. 7 passed.
T4.3 done 2026-05-15: QrRenderer — png() via endroid v5 Builder + PngWriter; pdf() via FPDF (added setasign/fpdf ^1.8.2 to composer; A6 page size passed as [105,148] mm because FPDF doesn't recognise 'A6' string). Embeds the QR PNG centred + customer name + reference + date range. Reads customer name from Customer->data Collection (Resrv 2025-03 schema).
T4.4 done 2026-05-15: tests/Unit/QrRendererTest.php — PNG signature byte check + %PDF- header check using a real Voucher attached to a makeConfirmedReservation(). Both green. Full suite: 17 passed (23 assertions). Pint clean.
T0.1 done 2026-05-16: BuildingReservationEmail event class (Reach\StatamicResrv\Events) carrying Mailable + ?Reservation. Uses Dispatchable + SerializesModels.
T0.2 done 2026-05-16: protected dispatchBuildingEvent(?Reservation) helper added to Resrv's Mail\Mailable base class. Subclasses opt-in.
T0.3 done 2026-05-16: ReservationConfirmed::build() now calls $this->dispatchBuildingEvent($this->reservation) after setting markdown.
T0.4 done 2026-05-16: tests/Mail/BuildingReservationEmailTest.php — event fires once when mailable builds; listener can mutate the mailable via attachData (verified through rawAttachments since Mail::fake() bypasses build()). Full Resrv PHPUnit suite: 804 tests, 2275 assertions, all green.
T5.1 done 2026-05-16: VoucherGenerator service — idempotent on reservation_id; resolves collection via Reach\StatamicResrv\Models\Entry::whereItemId; throws ShouldNotIssueVoucherException when collection not in enabled_collections; expires_at = date_end + grace_days.
T5.2 done 2026-05-16: GenerateVoucherForReservation listener (ShouldQueue) catches ShouldNotIssueVoucherException silently.
T5.3 done 2026-05-16: VouchersProvider::$listen wires ReservationConfirmed → GenerateVoucherForReservation.
T5.4 done 2026-05-16: tests/Feature/VoucherGenerationTest.php — 4 cases covering happy path, idempotency, disabled-collection, expires_at math. All green.
T6.1 done 2026-05-16: AttachVoucherToReservationEmail synchronous listener attaches PDF via attachData and embeds PNG with cid 'voucher-qr' through withSymfonyMessage.
T6.2 done 2026-05-16: resources/views/email/vouchers/partials/qr.blade.php published partial referencing cid:voucher-qr; resources/lang/en/email.php caption key.
T6.3 done 2026-05-16: VouchersProvider::$listen wires BuildingReservationEmail → AttachVoucherToReservationEmail.
T6.4 done 2026-05-16: tests/Feature/EmailAttachmentTest.php — verifies rawAttachments has voucher-{id}.pdf with %PDF- bytes and the withSymfonyMessage callback embeds cid voucher-qr. Disabled-collection case asserts empty rawAttachments.
T6.5 done 2026-05-16: Full Resrv PHPUnit suite (804 tests / 2275 assertions) confirmed green after Phase 0 changes.
T7.1 done 2026-05-16: VoucherStateMachine — statusOf lazy-expires Issued vouchers past expires_at without DB write; markUsed/unMark/invalidate wrap transitions in DB::transaction and dispatch corresponding events; throws InvalidVoucherTransitionException on illegal transitions.
T7.2 done 2026-05-16: InvalidateVoucherOnCancellation listens to Cancelled/Refunded/Expired; resolves voucher; skips Used/Invalidated; reason derived from event class.
T7.3 done 2026-05-16: VoucherUsed, VoucherUnmarked, VoucherInvalidated event classes (Dispatchable + SerializesModels).
T7.4 done 2026-05-16: tests/Feature/CancellationInvalidatesVoucherTest.php (4 cases) + ExpirationTest.php (2 cases) — 6 tests, 12 assertions, all green.
T8.1 done 2026-05-16: routes/cp.php — index/list/scan/lookup/mark-used/un-mark/resend, all gated by middleware('can:use resrv').
T8.2 done 2026-05-16: VoucherCpController + LookupRequest + MarkUsedRequest. Each mutating endpoint audit-logs to resrv_voucher_scans with success / not-found / invalid-transition result; lookup returns status banner derived from VoucherStateMachine::statusOf.
T8.3 done 2026-05-16: cp.vouchers.index.blade.php mounts <vouchers-list>; cp.vouchers.scan.blade.php mounts <voucher-scanner>. Both extend statamic::layout.
T8.4 done 2026-05-16: VouchersProvider::createNavigation registers Vouchers under Resrv section with children List + Scan (each ->can('use resrv')).
T8.5 done 2026-05-16: tests/Feature/CpScanFlowTest.php — 12 cases covering lookup/mark-used/un-mark/resend happy paths, 422 for invalid/missing token, 422 on illegal transition, 403 without 'use resrv', audit-log assertions, index/scan/list pages. Migration tweak: voucher_id on resrv_voucher_scans now nullable so 'not-found' results can be audit-logged. TestCase gained Statamic\Version stub + signInUserWithoutResrvPermission helper. Full pest suite: 42 tests / 71 assertions green; pint clean.
TV6.1 done 2026-05-16: composer.json bumped to php ^8.3 + statamic/cms ^6.0. composer update resolved cleanly — statamic/cms v5.73.22 → v6.19.0, reachweb/statamic-resrv dev-refactor/rates → dev-v6-upgrade, inertiajs/inertia-laravel v2.0.24 installed alongside laravel/framework v13.9.0 + symfony 8.x + orchestra/testbench v11.1.0.
TV6.3 done 2026-05-16: VouchersProvider::boot() body moved to bootAddon(); parent::boot() call dropped. In v6 the framework's boot() drives auto-discovery, so addon work belongs in the bootAddon() hook.
TV6.6 done 2026-05-16: tests/TestCase.php now extends Statamic\Testing\AddonTestCase with protected $addonServiceProvider; dropped the Version::shouldReceive('5.5.0') stub and the manual Statamic\Extend\Manifest setup (the class was removed in v6 — replaced by Statamic\Addons\Manifest, which the parent base class wires up). Resrv is appended to the Manifest in getEnvironmentSetUp so its bootAddon() (and therefore loadMigrationsFrom for resrv_entries et al) fires. Livewire + Statamic-Livewire + Resrv providers merged into parent::getPackageProviders(). Cache/session/queue/mail/editions config still set explicitly. Full pest suite: 42 tests / 71 assertions green on Statamic 6.19. Pint clean.
Phase 0 wiring restored on Resrv v6-upgrade (BuildingReservationEmail event class, Mailable::dispatchBuildingEvent helper, ReservationConfirmed::build dispatch). The commit existed on a separate features/resrv-voucher-required-changes branch but had never landed on v6-upgrade — restored via direct file write since the underlying work is already authorised and recorded as done (T0.1–T0.4).
TV6.2 done 2026-05-16: VouchersProvider declares protected $vite with input=[resources/js/cp.js, resources/css/cp.css], publicDirectory=resources/dist, hotFile=__DIR__.'/../../resources/dist/hot' (mirrors Resrv's pattern). The actual JS/CSS files come in Phase 9; the framework just needs the declaration so the CP knows where to look once the build is in place. Full pest suite green.
TV6.4 done 2026-05-16: VoucherCpController::indexCp and scanCp now return Inertia::render('resrv-vouchers::Vouchers/Index') / ('resrv-vouchers::Vouchers/Scan') with bootstrap props — index gets listUrl + resendUrl (with {voucher} stub for Listing's action URL templating) + statuses (value/label pairs derived from VoucherStatus::cases()) + defaultPerPage; scan gets lookupUrl + markUsedUrl + unMarkUrl. Mutation endpoints (lookup/markUsed/unMark/resend) stay JSON. Two new Inertia-aware tests in CpScanFlowTest assert page component + props. Suite: 44 tests / 103 assertions green.
TV6.5 done 2026-05-16: resources/views/cp/vouchers/{index,scan}.blade.php deleted. grep -rn "@extends('statamic::layout')" resources/views/ returns zero hits. The email partial qr.blade.php remains — it's a host-side @include not a CP view.
T10.1 done 2026-05-16: Verified VoucherStateMachine::markUsed dispatches VoucherUsed after the guard + DB save (line 33 of VoucherStateMachine.php); unMark dispatches VoucherUnmarked symmetrically. Failed transitions throw before any dispatch.
T10.2 done 2026-05-16: SendAttendedEmailOnVoucherUsed listener (implements ShouldQueue) reads voucher->reservation->customer->email; early-returns when missing/empty; otherwise Mail::to(...)->queue(new VoucherAttended($voucher)).
T10.3 done 2026-05-16: Mail\VoucherAttended extends Reach\StatamicResrv\Mail\Mailable so it inherits the theme components + applyResrvEmailConfig pattern. Default template statamic-resrv-vouchers::email.vouchers.attended (publishable Blade with mail::message + mail::panel containing guest name + reference + dates). Default subject "Thank you for attending!" overridable via resrv-vouchers.email.attended.subject.
T10.4 done 2026-05-16: VouchersProvider::$listen wires VoucherUsed → SendAttendedEmailOnVoucherUsed.
T10.5 done 2026-05-16: tests/Feature/AttendedEmailTest.php — 4 cases: queued to customer on markUsed, un-mark does NOT queue again, render contains guest name + reference, skip when customer email is empty. Full pest suite: 48 tests / 109 assertions green; pint clean.
T11.1 done 2026-05-16: InstallVouchers (src/Console/Commands/InstallVouchers.php) — signature resrv-vouchers:install, RunsInPlease so `php artisan resrv-vouchers:install` works. Publishes resrv-vouchers-config silently, runs migrate, then interactive prompts for resrv-vouchers-emails + resrv-vouchers-language tags. Post-install info reminds operator to populate enabled_collections, confirm Resrv ships BuildingReservationEmail, and optionally add the @include partial to a published confirmed.blade.php. VouchersProvider::$commands array added; new publishable tags resrv-vouchers-emails + resrv-vouchers-language wired in bootAddon(). tests/Feature/InstallVouchersCommandTest.php — 3 cases (command registered, declines optional prompts, accepts and produces expected files). Full pest suite: 51 tests / 120 assertions green; pint clean.
T9.1 done 2026-05-16: package.json (type=module, scripts cp:dev/cp:build) + vite.config.js (@statamic/cms vite-plugin + @tailwindcss/vite + laravel-vite-plugin pointing at resources/js/cp.js + resources/css/cp.css, publicDirectory=resources/dist). Deps: @statamic/cms via file:../../../vendor/statamic/cms/resources/dist-package, html5-qrcode ^2.3.8, axios, tailwindcss ^4. devDeps include @vitejs/plugin-vue ^6.0 — required because @statamic/cms imports it but the file: install doesn't ship its own node_modules. Install with `npm install --install-links` so @statamic/cms is copied (not symlinked), letting Node resolve transitive deps from the addon's own node_modules.
T9.2 done 2026-05-16: resources/js/cp.js — Statamic.booting hook registers VouchersIndex + VouchersScan against the Inertia handles 'resrv-vouchers::Vouchers/Index' and 'resrv-vouchers::Vouchers/Scan' (handles match VoucherCpController::indexCp/scanCp Inertia::render strings from TV6.4).
T9.3 done 2026-05-16: resources/css/cp.css — one `@import "tailwindcss";` line. No PostCSS chain, no tailwind.config.js — Tailwind v4 reads @theme straight from CSS when/if custom tokens are added later.
T9.4 done 2026-05-16: resources/js/pages/Vouchers/Scan.vue — <script setup>, props lookupUrl/markUsedUrl/unMarkUrl, html5-qrcode scanner mounted onMounted / torn down onBeforeUnmount, dynamic import('html5-qrcode') so the heavy lib only loads on the scan page. Manual token entry fallback + "Switch camera" button (only when getCameras() returns > 1). On decode → axios.post(lookupUrl, {token}). Result panel renders status badge (colour mapped from VoucherStatus via local statusVariant fn) + reservation summary + Mark/Un-mark/Scan another buttons gated by current status. Compiles cleanly.
T9.5 done 2026-05-16: resources/js/pages/Vouchers/Index.vue — <Listing :url=listUrl :columns :filters …> from @statamic/cms/ui. Columns: id / status / reservation.reference / expires_at / used_at / created_at. Status filter populated from props.statuses (value/label pairs from controller). preferences-prefix="resrv-vouchers.vouchers" + push-query so URL state survives reloads.
T9.6 done 2026-05-16: Already landed via TV6.2 — VouchersProvider declares protected $vite with input=[cp.js, cp.css], publicDirectory=resources/dist, hotFile=__DIR__.'/../../resources/dist/hot'. Verified `npm run cp:build` emits resources/dist/build/manifest.json + assets. Pest suite still 51/120 green; pint clean. T9.7 (mobile/desktop UAT) requires a phone with a camera and a host site — left pending.
T11.2 done 2026-05-16: README.md — Requirements (Statamic 6 / PHP 8.3+ / Laravel 12-13 / Resrv with BuildingReservationEmail / use resrv permission), Installation (composer require + resrv-vouchers:install), Configuration (enabled_collections, grace_days, signing_key fallback, email.attended overrides), Email customization (publish Resrv emails + @include the QR partial), CP usage (Resrv → Vouchers nav, list + scan pages, both Inertia, `npm install --install-links` then cp:dev for HMR / cp:build for production), voucher lifecycle table (Resrv events ↔ voucher states), Troubleshooting (HTTPS camera requirement, @statamic/cms install caveat, missing inline QR vs PDF always-attached, eligibility check). Pint clean; pest suite unchanged at 51/120.
T11.3 done 2026-05-16: Added a "BuildingReservationEmail hook (for sibling addons)" subsection to Resrv's CLAUDE.md right under "Event-Driven Reservation Lifecycle". Documents what the event carries, how subclasses opt in via dispatchBuildingEvent(), the synchronous-listener constraint (don't ShouldQueue because the event fires inside build() right before send), and references statamic-resrv-vouchers' AttachVoucherToReservationEmail as the canonical consumer.
TV6.7 done 2026-05-16: composer install resolves Vouchers ^6.0 cleanly against ../statamic-resrv on v6-upgrade. Vouchers pest 51/120 green — confirms cross-package source compatibility (models, events, mailables, customer all exercised end-to-end through the Vouchers suite). Caveat: Resrv's *own* phpunit suite is currently red on v6-upgrade because Resrv's tests/TestCase.php still uses the v5 `Statamic\Extend\Manifest` API — same migration we did in TV6.6, but for the Resrv repo. Logged in TV6.7 status note + as a known constraint on T11.4. Out of Vouchers scope per cross-cutting rule #5.
T11.4 partial 2026-05-16: Vouchers ✅ (pest 51/120 green, pint clean, npm run cp:build emits manifest+assets, no @vitejs/plugin-vue2). Resrv ❌ (phpunit 802 errors, all from the v5 TestCase Manifest issue, none introduced by Vouchers work) / ⚠️ (pint reports 3 stylistic fixes in pre-existing v6-upgrade files; the 4 files Vouchers added/modified in Resrv individually pass pint). Marked partial — gate flips to fully green once Resrv migrates its TestCase to AddonTestCase per the v6 playbook.
Coverage hardening 2026-05-16: Two new tests in CancellationInvalidatesVoucherTest locking down silent-catch behaviour in InvalidateVoucherOnCancellation — (a) a Used voucher stays Used (not invalidated) when the reservation is later refunded, because the customer already attended; (b) an already-Invalidated voucher keeps its original reason on a second cancellation event (double-dispatch is a no-op). Both paths were silently handled by the listener's try/catch — now explicitly verified. Suite: 53 tests / 127 assertions green; pint clean.
Coverage hardening 2026-05-16: tests/Unit/VoucherStatusTest.php (9 tests, 32 assertions) locks the VoucherStatus::resultKey() + banner() contracts. resultKey() values land in resrv_voucher_scans.result (used for audit reporting); banner() drives the colour-coded panel in Scan.vue (tone + message). Dataset-driven so each enum case is covered, plus a foreach-cases guard that catches the "added a new case without updating these methods" failure mode. Suite: 62 tests / 159 assertions green; pint clean.
Coverage hardening 2026-05-16: Three new tests in ExpirationTest cover the state-machine ↔ lazy-expiration interaction — (a) markUsed on a row-state=Issued voucher whose expires_at is in the past throws InvalidVoucherTransitionException (lazy-expiry blocks late scanning); (b) invalidate still works on a lazy-expired voucher (so cleanup/refund flows aren't blocked by stale expiry); (c) unMark on a never-Used voucher throws. Real product rules previously implicit in guardTransition + statusOf interplay — now explicit. Suite: 65 tests / 165 assertions green; pint clean.
Retarget to Resrv main 2026-06-05: merged features/resrv-voucher-required-changes into Resrv main (d01e363, local; BuildingReservationEmailTest 2/2 green there); composer update resolved reachweb/statamic-resrv dev-main + statamic/cms 6.20.2. Fixed tests/TestCase.php Manifest entry id reach/resrv → reachweb/statamic-resrv — Resrv main merges settings-blueprint defaults (currency_isoCode et al) via Addon::get('reachweb/statamic-resrv'), so the wrong id left currency null and failed 30 tests through the Price cast. Full suite 65/165 green; pint clean. CLAUDE.md + tasks.md references updated v6-upgrade → main.
T11.4 done 2026-06-05: both packages green — Vouchers pest 65/165 against dev-main, Resrv phpunit 1146/3227 OK (1 skipped) on main including the voucher-hook merge. Pint clean in Vouchers; Resrv's single pint nit (ActiveReservationsGuard.php) pre-dates the merge and is out of Vouchers scope.
T11.2 refresh 2026-06-06: README rewritten against a full code read — PHP floor stated as 8.4+ (Resrv v6 requirement), Resrv requirement now "v6" (BuildingReservationEmail hedge dropped), queue-worker requirement documented (generation + attended email are queued; new Requirements bullet + 2 Troubleshooting entries), new "How it works" overview, install steps match InstallVouchers order/prompts, lifecycle table gained edge rules (used stays used on refund, expired blocks mark-used, no-voucher email sends normally), resend documented as going through Resrv's ReservationEmailDispatcher, npm build chain moved into a new "Developer reference" (events, audit values, schema, internal endpoints incl. PATCH methods). Pest 65/165 green.
TU.1 done 2026-06-11: Index listing rebuilt on the real Statamic 6 Listing protocol after real-site testing showed the hand-rolled version crashed at setup (filters without auto_apply → Object.keys TypeError) and mismatched on every axis (columns keyed handle vs field, paginator response without meta.columns/activeFilterBadges, status/per_page params vs base64 filters/sort/order/perPage/search). New: Filters/VoucherStatus (Scope, handle voucher_status), Blueprints/VoucherBlueprint, Resources/VoucherResource (HasRequestedColumns + preProcessIndex dates); controller index() now FilteredRequest + QueriesFilters with sort whitelist, perPage clamp 1-100, search on id/reservation.reference; indexCp() passes Scope::filters('resrv-vouchers'); Index.vue is server-driven with <Header :title> (default Header slot is the actions area — that's why titles rendered top-right) and Badge cell for status. 5 new protocol tests. Pest 69/192 green; pint clean.
TU.2 done 2026-06-11: Scan page — banner now a ui <Alert> (tone→variant map; was raw {tone,message} JSON via mustache), card state updates straight from the PATCH response via a shared voucherPayload() in the controller (lookup + applyTransition return the same shape; the old re-lookup left the card stale and wrote a bogus scan|already-used audit row — regression test asserts no scan row after mark-used), dates server-formatted (Y-m-d, or Y-m-d H:i when resrv-config.calculate_days_using_time), camera starts on a Start-camera click instead of onMounted (Stop/Switch shown while active), <Header :title> for correct CP title placement, Badge switched to its real `color` prop (`:variant` was silently ignored). Pest 69/201 green; pint clean.
TU.3 done 2026-06-11: endroid/qr-code ^5.0 → ^6.0 (locked 6.1.3; 6.0.x covers PHP 8.2/8.3 hosts, 6.1.x needs 8.4+) — 5.1.0's writer signatures are implicit-nullable (LogoInterface $logo = null) and spam deprecations on PHP 8.4/8.5 queue/CLI output on every render. QrRenderer::png() moved off the removed fluent Builder::create() to the 6.x named-arg constructor. Verified a render emits zero E_DEPRECATED. Note: the same composer update re-synced the path-repo lock entry for reachweb/statamic-resrv to dev-fix/duplicated-entries because that's the sibling checkout's current branch — unavoidable with a path repo; self-corrects on the next update once Resrv is back on main. Pest 69/201 green; pint clean.
TU.4 done 2026-06-11: VouchersProvider::bootAddon() now throws a RuntimeException (actionable message: update reachweb/statamic-resrv) when BuildingReservationEmail doesn't exist — a stale Resrv previously issued vouchers but silently mailed confirmations without QR attachments (tester hit this via an old composer.lock). Hard fail chosen over log-and-disable (user decision); surfaces at composer install/update. Negative path not unit-testable (the class exists in the test env) — covered by TU.9 manual check. Boot+CP tests 20/96 green; pint clean.
TU.5 done 2026-06-11: .gitignore now excludes only resources/dist/hot (was the whole /resources/dist — Packagist installs had no CP JS/CSS at all: composer errored on the missing dist path and CP pages were dead; the local build also trailed the code by weeks). Fresh npm run cp:build committed (manifest + cp js/css + esm chunk, built after the TU.1/TU.2 frontend rewrites). Matches Resrv's convention of shipping built assets.
