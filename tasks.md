# Statamic Resrv Vouchers — Implementation Tasks

> **Read this first.** This file is the canonical task list for building the `reachweb/statamic-resrv-vouchers` Statamic addon. Each task has a checkbox. Flip `[ ]` → `[x]` when complete. Each task lists a self-check that must pass before moving on. The full design rationale lives in `/Users/afonic/.claude/plans/i-want-to-create-immutable-rabin.md` — consult it if a task is ambiguous.

## Project at a glance

- **Package:** `reachweb/statamic-resrv-vouchers`
- **Namespace:** `Reach\StatamicResrvVouchers\`
- **Location:** `/Volumes/1TB/Sites/umami/addons/reachweb/statamic-resrv-vouchers/`
- **Sibling addon (dependency):** `reachweb/statamic-resrv` at `/Volumes/1TB/Sites/umami/addons/reachweb/statamic-resrv`
- **What it does:** When a Resrv reservation is confirmed in a voucher-enabled collection, generate a signed-token QR code, attach it (inline PNG + PDF) to the existing Resrv confirmation email. CP UI lets staff scan QR codes via phone camera, validate vouchers, and mark them used. Marking used sends a "thank you for attending" email. Cancel/refund invalidates the voucher; vouchers also expire after `date_end + grace_days`.
- **Tests:** PEST 3 with Orchestra Testbench (SQLite in-memory).

## Decisions reference (do not re-litigate without user input)

| Topic | Decision |
| --- | --- |
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
| Scanner JS lib | `html5-qrcode` |
| Resrv hook | New `BuildingReservationEmail` event from `Mailable::build()` |
| Scanner fallback | Text-input fallback in addition to camera |
| Install | `resrv-vouchers:install` console command |
| Extras | CP voucher list + manual resend-email action |

## Cross-cutting agent rules

1. **Run tests after every task that touches `src/` or `tests/`.** Use `vendor/bin/pest`. Stop on first failure if you can't reason about the cascade.
2. **Code style:** run `vendor/bin/pint` before considering any task complete.
3. **Naming:** mirror the Resrv addon (`tests/TestCase.php`, `src/Providers/...`, `resources/views/cp/<feature>/...`).
4. **Cross-driver migrations:** stick to standard Schema builder calls. The addon must work on SQLite (testing), MySQL/MariaDB, PostgreSQL.
5. **Don't change Resrv's behavior outside Phase 0.** Any change in Resrv must be on the companion PR branch and reviewed.
6. **PHPDoc / comments:** none, unless the WHY is non-obvious. Follow Resrv's existing style.
7. **PHP 8.2+.** Use constructor property promotion, return types, readonly where natural.
8. **Update this file** every time you finish a task: check the box and add a one-line "done note" under it if non-trivial. Then commit.
9. **Local dev:** the addon depends on the companion Resrv PR (Phase 0). Until that branch is merged & tagged, point the addon's `composer.json` at a local path repo for `reachweb/statamic-resrv`:
   ```json
   "repositories": [
     { "type": "path", "url": "../statamic-resrv" }
   ]
   ```
10. **Commits:** small, atomic, one phase = one or two commits. Conventional-commit-ish messages.

---

## Phase 0 — Companion change in Resrv (do this first)

Branch in `statamic-resrv`: `feature/building-email-event`. Do not merge to `main` until the Vouchers addon is exercising it.

- [ ] **T0.1 Add `BuildingReservationEmail` event class in Resrv**
  - File: `src/Events/BuildingReservationEmail.php`
  - Namespace: `Reach\StatamicResrv\Events`
  - Payload: `public Mailable $mailable; public ?Reservation $reservation;`
  - Use `Illuminate\Foundation\Events\Dispatchable`, `Illuminate\Queue\SerializesModels`.
  - **Self-check:** `vendor/bin/phpunit --filter "ReservationConfirmedTest"` still green.

- [ ] **T0.2 Add `dispatchBuildingEvent()` to Resrv's Mailable base class**
  - File: `src/Mail/Mailable.php`
  - Add `protected function dispatchBuildingEvent(?Reservation $reservation = null): void { BuildingReservationEmail::dispatch($this, $reservation); }`
  - Do **not** auto-call this from the base class — subclasses opt in.
  - **Self-check:** existing PHPUnit suite still green.

- [ ] **T0.3 Wire the dispatcher into `ReservationConfirmed::build()`**
  - File: `src/Mail/ReservationConfirmed.php`
  - Call `$this->dispatchBuildingEvent($this->reservation);` right before returning. The mailable already has its markdown set so listeners can `attach()` and inspect.
  - **Self-check:** existing email test still green.

- [ ] **T0.4 PHPUnit test for the new event**
  - File: `tests/Mail/BuildingReservationEmailTest.php`
  - Two cases: (a) event fires exactly once when the mailable builds; (b) a listener can call `$event->mailable->attachData(...)` and the resulting `Mail::fake()` payload contains the attachment.
  - **Self-check:** new test passes; full PHPUnit suite green.

- [ ] **T0.5 Coordinate Resrv version with the new addon**
  - For local development, do not tag yet. The Vouchers addon will pin via a path repo (see rule 9 above).
  - Before public release, tag a new minor of Resrv that introduces this event and reference that version in `statamic-resrv-vouchers/composer.json`.

## Phase 1 — Package skeleton

- [ ] **T1.1 `composer.json`**
  - Path: `composer.json`
  - Name `reachweb/statamic-resrv-vouchers`, type `statamic-addon`, namespace `Reach\StatamicResrvVouchers\` autoloaded from `src/`.
  - **Require:** `php ^8.2`, `statamic/cms ^5.0`, `laravel/framework ^11.0 || ^12.0`, `endroid/qr-code ^5.0`, `reachweb/statamic-resrv:dev-feature/building-email-event` (path repo) until Phase 0 lands.
  - **Require-dev:** `orchestra/testbench ^9.0 || ^10.0`, `pestphp/pest ^3.0`, `pestphp/pest-plugin-laravel ^3.0`, `laravel/pint ^1.2`.
  - `extra.laravel.providers` → `Reach\\StatamicResrvVouchers\\StatamicResrvVouchersServiceProvider`.
  - `extra.statamic.name`, `extra.statamic.description`.
  - Scripts: `test` → `vendor/bin/pest`, `test:stop` → `vendor/bin/pest --stop-on-failure`.
  - **Self-check:** `composer validate` is clean; `composer install` succeeds.

- [ ] **T1.2 Service providers**
  - `src/StatamicResrvVouchersServiceProvider.php` — aggregate, registers `VouchersProvider`.
  - `src/Providers/VouchersProvider.php` — extends `Statamic\Providers\AddonServiceProvider`. Sets `$routes['cp']`, `$listen` (empty for now), `$vite` (set up in Phase 9), and in `boot()` loads translations, views, migrations, merges config.
  - **Self-check:** package boots in Testbench without exception.

- [ ] **T1.3 Config file**
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

- [ ] **T1.4 Base directory scaffolding**
  - Create empty dirs: `src/Console/Commands/`, `src/Events/`, `src/Http/Controllers/`, `src/Http/Requests/`, `src/Listeners/`, `src/Mail/`, `src/Models/`, `src/Services/`, `src/Enums/`, `resources/lang/en/`, `resources/views/cp/vouchers/`, `resources/views/email/vouchers/`, `resources/js/components/`, `routes/`, `database/migrations/`, `tests/Feature/`, `tests/Unit/`.
  - Add `.gitkeep` for any directory that won't have a file by end of this phase.

- [ ] **T1.5 Pint + minimum CI**
  - Add `pint.json` (copy from Resrv).
  - Add a `composer test` alias that runs `vendor/bin/pest`. Optionally add a GitHub Actions workflow file later (defer; not required for this milestone).

## Phase 2 — Test bootstrap (PEST 3)

- [ ] **T2.1 `tests/TestCase.php`**
  - Extend `Orchestra\Testbench\TestCase`.
  - `getPackageProviders()` returns: `\Statamic\Providers\StatamicServiceProvider`, `\Livewire\LivewireServiceProvider`, `\Reach\StatamicResrv\StatamicResrvServiceProvider`, `\Reach\StatamicResrvVouchers\StatamicResrvVouchersServiceProvider`.
  - `getPackageAliases()` returns `['Statamic' => \Statamic\Statamic::class]`.
  - `defineDatabaseMigrations()` → `loadLaravelMigrations()` + `artisan('migrate', ['--database' => 'testbench'])` to pick up both Resrv and Vouchers migrations.
  - Use `RefreshDatabase`, `FakesViews` (if needed), `PreventSavingStacheItemsToDisk` (mirror Resrv's helpers — copy what's necessary).
  - Set test environment via `defineEnvironment()`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`, `CACHE_DRIVER=array`, `APP_KEY=base64:<fixed>`.

- [ ] **T2.2 `tests/Pest.php`**
  - `uses(TestCase::class)->in(__DIR__);`
  - Common expectations or datasets if needed (leave empty for now).

- [ ] **T2.3 Helpers**
  - In `TestCase.php` add: `signInAdmin()`, `ensureCollectionExists($handle, $route = '/{slug}')`, `makeStatamicItem(array $data = [], string $collection = 'pages')`, and a new `makeConfirmedReservation(array $overrides = [])` that creates a Statamic entry + a Resrv `Entry` row + a `Reservation` model in `confirmed` status. Use Resrv's existing factories where possible.

- [ ] **T2.4 `phpunit.xml`**
  - PEST reads this for env. Include testsuite definitions for `Feature` and `Unit`. Set env: `DB_CONNECTION=sqlite`, `MAIL_MAILER=array`, `CACHE_DRIVER=array`, `APP_KEY=base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF1+...` (any fixed key works).

- [ ] **T2.5 Smoke test**
  - `tests/Feature/BootTest.php`: `it('boots the package without error', fn() => expect(app()->bound('config'))->toBeTrue());`
  - **Self-check:** `vendor/bin/pest` passes with 1 test.

## Phase 3 — Database & models

- [ ] **T3.1 Migration: `resrv_vouchers`**
  - Columns: `id` (string PK, UUID), `reservation_id` (unsigned big int, indexed, unique to keep generation idempotent), `token` (string, unique), `status` (string, indexed), `used_at` (timestamp, nullable), `used_by_user_id` (string nullable — Statamic user IDs are strings), `invalidated_reason` (text nullable), `expires_at` (timestamp), `timestamps`.
  - Do **not** add a FK constraint on `reservation_id` — Resrv migrations live in a separate package, FK order is fragile across drivers.
  - **Self-check:** migration runs on SQLite + roundtrips.

- [ ] **T3.2 Migration: `resrv_voucher_scans`**
  - Columns: `id` (auto-increment), `voucher_id` (string, indexed), `user_id` (string nullable), `action` (string: `scan|mark-used|un-mark`), `result` (string: `success|already-used|invalidated|expired|not-found`), `ip_address` (string, nullable), `user_agent` (text, nullable), `timestamps`.

- [ ] **T3.3 `Voucher` model**
  - `src/Models/Voucher.php`
  - `protected $table = 'resrv_vouchers';`
  - `protected $keyType = 'string';` and `public $incrementing = false;` (UUID PK).
  - Boot a UUID generator in `creating`.
  - Casts: `status => VoucherStatus::class`, `expires_at => datetime`, `used_at => datetime`.
  - Relations: `reservation()` belongsTo `Reach\StatamicResrv\Models\Reservation::class`, `scans()` hasMany `VoucherScan::class`.
  - Helper: `isExpired(): bool` (computed from `expires_at` vs `now()`).

- [ ] **T3.4 `VoucherScan` model**
  - `src/Models/VoucherScan.php`
  - Relations: `voucher()`, `user()` (resolve via `Statamic::user($this->user_id)` accessor rather than belongsTo, because Statamic users aren't Eloquent in all setups).

- [ ] **T3.5 `VoucherStatus` enum**
  - `src/Enums/VoucherStatus.php`
  - Cases: `Issued`, `Used`, `Invalidated`, `Expired`. Backed by string values lowercase.

- [ ] **T3.6 Tests**
  - Unit tests: model boots, status cast works, `isExpired()` true when `expires_at < now()`.
  - **Self-check:** `vendor/bin/pest` green.

## Phase 4 — Token signer & QR renderer

- [ ] **T4.1 `VoucherTokenSigner`**
  - `src/Services/VoucherTokenSigner.php`
  - Constructor takes the signing key (resolved from `config('resrv-vouchers.signing_key') ?? config('app.key')`).
  - `sign(string $uuid): string` → `base64url(uuid) . '.' . base64url(hmac_sha256(uuid, key))`.
  - `verify(string $token): ?string` → returns the UUID on success, `null` on tamper / bad format. Use `hash_equals` for constant-time compare.

- [ ] **T4.2 Signer unit tests**
  - Round-trip: sign → verify returns the original UUID.
  - Tamper: flipping any character in the token → verify returns null.
  - Wrong key: signer with different key rejects.
  - Malformed input (no dot, bad base64): null, no exception.

- [ ] **T4.3 `QrRenderer`**
  - `src/Services/QrRenderer.php`
  - `png(string $payload, int $size = 320): string` — bytes (use endroid/qr-code v5 builder API: `Builder::create()->writer(new PngWriter)->data($payload)->size($size)->build()->getString()`).
  - `pdf(Voucher $voucher): string` — bytes for a single-page A6 PDF containing the QR + customer name + reservation reference + date range. Use endroid's PDF writer if available, else fall back to a minimal hand-rolled `\Spatie\Browsershot\Browsershot` or `dompdf/dompdf` — pick whichever is simpler and document the choice in a code comment **only if non-obvious** (lean toward endroid's native PdfWriter when v5 supports it).
  - Pure service, no global state.

- [ ] **T4.4 Renderer unit tests**
  - PNG output starts with the PNG signature bytes (`\x89PNG\r\n\x1A\n`).
  - PDF output starts with `%PDF-`.
  - Both return non-empty.

## Phase 5 — Voucher generation on `ReservationConfirmed`

- [ ] **T5.1 `VoucherGenerator` service**
  - `src/Services/VoucherGenerator.php`
  - `generateFor(Reservation $reservation): Voucher`
  - Idempotent: if a voucher exists for this `reservation_id`, return it.
  - Resolves collection: `Reach\StatamicResrv\Models\Entry::whereItemId($reservation->item_id)->first()?->collection`.
  - If the collection is not in `config('resrv-vouchers.enabled_collections')`, throw a domain exception that the listener can catch and silently skip.
  - Sets `expires_at = $reservation->date_end + grace_days`.
  - Signs the UUID via `VoucherTokenSigner` and stores `token`.

- [ ] **T5.2 `GenerateVoucherForReservation` listener**
  - `src/Listeners/GenerateVoucherForReservation.php`
  - Catches the `ShouldNotIssueVoucherException` (or just early-returns by checking collection eligibility before calling the generator — pick one approach and stay consistent).
  - Queueable (`implements ShouldQueue`) so it doesn't slow webhooks.

- [ ] **T5.3 Wire listener**
  - In `VouchersProvider::$listen`:
    ```php
    \Reach\StatamicResrv\Events\ReservationConfirmed::class => [
        \Reach\StatamicResrvVouchers\Listeners\GenerateVoucherForReservation::class,
    ],
    ```

- [ ] **T5.4 Feature tests**
  - `tests/Feature/VoucherGenerationTest.php`:
    - Confirming a reservation in an enabled collection creates one voucher with `status=Issued` and a valid signed token.
    - Re-firing the event does NOT create a duplicate (idempotency).
    - Confirming a reservation in a disabled collection creates no voucher.
    - `expires_at` equals `reservation.date_end + grace_days`.
  - **Self-check:** `vendor/bin/pest` green.

## Phase 6 — Email integration

- [ ] **T6.1 `AttachVoucherToReservationEmail` listener**
  - `src/Listeners/AttachVoucherToReservationEmail.php`
  - Listens to `\Reach\StatamicResrv\Events\BuildingReservationEmail`.
  - Resolves the voucher by `$event->reservation->id`. If none (e.g. collection not enabled), return.
  - Calls `$event->mailable->attachData($pdfBytes, "voucher-{$voucher->id}.pdf", ['mime' => 'application/pdf'])`.
  - For the inline image, use `$event->mailable->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($pngBytes) { $email->embed($pngBytes, 'voucher-qr', 'image/png'); });` so the markdown template can reference `<img src="cid:voucher-qr">`.
  - Make the listener **synchronous** (NOT queued) — it needs to mutate the mailable that's about to be sent.

- [ ] **T6.2 Markdown partial for the QR image**
  - `resources/views/email/vouchers/partials/qr.blade.php` — a tiny snippet that the user (in a host app) can `@include('statamic-resrv-vouchers::email.vouchers.partials.qr')` from their published `statamic-resrv/email/reservations/confirmed.blade.php` to render the inline QR. Document this in README and in T11.2.
  - **Tradeoff acknowledged in plan:** The Resrv default confirmation template does not reference the QR. Hosts must publish the Resrv template and add the include. Until they do, the QR is still attached as PDF and the email still works.

- [ ] **T6.3 Wire listener**
  - `VouchersProvider::$listen` gets:
    ```php
    \Reach\StatamicResrv\Events\BuildingReservationEmail::class => [
        \Reach\StatamicResrvVouchers\Listeners\AttachVoucherToReservationEmail::class,
    ],
    ```

- [ ] **T6.4 Feature test for email attachment**
  - `tests/Feature/EmailAttachmentTest.php`
  - `Mail::fake()`, confirm a reservation, assert `\Reach\StatamicResrv\Mail\ReservationConfirmed` was sent with a PDF attachment matching `voucher-*.pdf` and the embedded `voucher-qr` CID.
  - Also assert the un-enabled-collection case sends the email WITHOUT any voucher attachment.

- [ ] **T6.5 Cross-package sanity**
  - Run Resrv's own PHPUnit suite (`cd ../statamic-resrv && vendor/bin/phpunit`) to confirm Phase 0 changes don't regress anything.

## Phase 7 — Lifecycle (cancel / refund / expired)

- [ ] **T7.1 `VoucherStateMachine` service**
  - `src/Services/VoucherStateMachine.php`
  - Methods:
    - `statusOf(Voucher $v): VoucherStatus` — lazy expiration check: if `$v->status === Issued && now > expires_at`, returns `Expired` (no DB write).
    - `markUsed(Voucher $v, ?string $userId): void` — only from `Issued`; dispatches `VoucherUsed`.
    - `unMark(Voucher $v, ?string $userId): void` — only from `Used`; dispatches `VoucherUnmarked`.
    - `invalidate(Voucher $v, string $reason): void` — from any non-final state; dispatches `VoucherInvalidated`.
  - Wraps DB updates in a transaction.

- [ ] **T7.2 `InvalidateVoucherOnCancellation` listener**
  - Listens to `\Reach\StatamicResrv\Events\ReservationCancelled`, `ReservationRefunded`, `ReservationExpired`.
  - Looks up the voucher, calls `VoucherStateMachine::invalidate($voucher, $reasonFromEventClass)`.
  - Skips silently if no voucher exists.

- [ ] **T7.3 Lifecycle event classes**
  - `src/Events/VoucherUsed.php`, `VoucherUnmarked.php`, `VoucherInvalidated.php` — all carry the voucher (+ user id where applicable).

- [ ] **T7.4 Feature tests**
  - `tests/Feature/CancellationInvalidatesVoucherTest.php`
  - Cancel → voucher status `Invalidated`, reason `cancelled`.
  - Refund → voucher status `Invalidated`, reason `refunded`.
  - ReservationExpired → voucher status `Invalidated`, reason `expired-reservation`.
  - `tests/Feature/ExpirationTest.php` — voucher past `expires_at` reports `Expired` via `statusOf()` without DB mutation.

## Phase 8 — CP routes, controllers, Blade

- [ ] **T8.1 `routes/cp.php`**
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

- [ ] **T8.2 `VoucherCpController`**
  - `src/Http/Controllers/VoucherCpController.php`
  - `indexCp()` → Blade `cp.vouchers.index`.
  - `index(Request $r)` → JSON listing, supports filters `collection`, `status`, pagination.
  - `scanCp()` → Blade `cp.vouchers.scan`.
  - `lookup(LookupRequest $r)` — validate token via `VoucherTokenSigner`, find voucher, write a `scan` audit row with the resulting `result`, return JSON `{voucher, reservation, status_banner}`. Status banner content driven by `VoucherStateMachine::statusOf()`.
  - `markUsed(MarkUsedRequest $r)` — calls `VoucherStateMachine::markUsed()`, writes `mark-used` audit row, returns updated voucher.
  - `unMark(MarkUsedRequest $r)` — calls `VoucherStateMachine::unMark()`, writes `un-mark` audit row.
  - `resend(Voucher $v)` — re-dispatch `\Reach\StatamicResrv\Mail\ReservationConfirmed` to the customer (our `AttachVoucherToReservationEmail` listener fires automatically). Audit-log a `resend` action.
  - All endpoints return JSON.

- [ ] **T8.3 Blade views**
  - `resources/views/cp/vouchers/index.blade.php` — `@extends('statamic::layout')`, mounts `<vouchers-list>`.
  - `resources/views/cp/vouchers/scan.blade.php` — `@extends('statamic::layout')`, mounts `<voucher-scanner>`.

- [ ] **T8.4 CP nav**
  - In `VouchersProvider::createNavigation()` (or boot), register a "Vouchers" item in the "Resrv" section with children "Scan" and "List", each `->can('use resrv')`. Mirror the pattern in `Reach\StatamicResrv\Providers\ResrvProvider::createNavigation()`.

- [ ] **T8.5 Feature tests**
  - `tests/Feature/CpScanFlowTest.php`
  - Per endpoint: 200 on happy path, 403 without `use resrv`, 422 for malformed/invalid tokens.
  - Verify audit-log row inserted for lookup, mark-used, un-mark, resend.

## Phase 9 — Frontend (Vue 2 + html5-qrcode)

- [ ] **T9.1 `package.json` + `vite.config.js`**
  - Copy Resrv's `vite.config.js` shape: entries `resources/js/resrv-vouchers.js`, public dir `resources/dist`, `@vitejs/plugin-vue2`.
  - Dependencies: `vue@^2.7`, `html5-qrcode@^2.3`, `axios` (probably from Statamic globally — verify).
  - Scripts: `build`, `dev`.

- [ ] **T9.2 `resources/js/resrv-vouchers.js`**
  - ```js
    import Statamic from 'statamic'
    import VoucherScanner from './components/VoucherScanner.vue'
    import VouchersList from './components/VouchersList.vue'
    Statamic.booting(() => {
        Statamic.$components.register('voucher-scanner', VoucherScanner)
        Statamic.$components.register('vouchers-list', VouchersList)
    })
    ```

- [ ] **T9.3 `VoucherScanner.vue`**
  - Mounts an `html5-qrcode` scanner in `<div id="qr-reader">`.
  - On successful decode, calls `axios.post(cp_url('/resrv-vouchers/lookup'), { token })`.
  - Renders a result card: customer name, reservation reference, dates, party size, status banner (color-coded by `VoucherStatus`).
  - Buttons:
    - When `Issued`: "Mark as used" → `PATCH /resrv-vouchers/mark-used`.
    - When `Used`: "Un-mark" → `PATCH /resrv-vouchers/un-mark`.
    - Always: "Scan another".
  - Text-input fallback below the camera viewport with a "Validate" button.
  - Visible "switch camera" toggle if multiple cameras detected.

- [ ] **T9.4 `VouchersList.vue`**
  - Table fed by `GET /resrv-vouchers/list`. Columns: customer, collection, status, expires, used_at, actions.
  - Filters: collection (select), status (multi-select).
  - Resend action → `POST /resrv-vouchers/resend/{id}`. Show toast on success.

- [ ] **T9.5 Vite manifest registration**
  - In `VouchersProvider`, set `protected array $vite = ['publicDirectory' => 'resources/dist', 'hotFile' => '...', 'input' => ['resources/css/resrv-vouchers.css', 'resources/js/resrv-vouchers.js']];` to match Statamic 5's addon-vite contract. Check the Resrv `vite.config.js` and `ResrvProvider` for the exact shape.

- [ ] **T9.6 Manual UAT**
  - Cannot be unit-tested. After Phase 10 lands, open the CP on a phone, allow camera, scan a real generated QR. Document any quirks in README troubleshooting section.

## Phase 10 — VoucherUsed → attended email

- [ ] **T10.1 Wire up state-machine events**
  - Verify `VoucherStateMachine::markUsed()` dispatches `VoucherUsed` only on successful transition. Same for `unMark()`/`VoucherUnmarked`. Already in T7.1; this task is a verification.

- [ ] **T10.2 `SendAttendedEmailOnVoucherUsed` listener**
  - `src/Listeners/SendAttendedEmailOnVoucherUsed.php`
  - On `VoucherUsed`, queue `Mail\VoucherAttended` to the customer email.
  - Don't send if the voucher's reservation has no customer email (defensive — shouldn't happen).

- [ ] **T10.3 `Mail\VoucherAttended`**
  - Extends `Reach\StatamicResrv\Mail\Mailable` to inherit theme components.
  - Markdown template `resources/views/email/vouchers/attended.blade.php` (publishable).
  - `applyResrvEmailConfig(config('resrv-vouchers.email.attended'))` for subject/from/template override.

- [ ] **T10.4 Wire listener**
  - In `VouchersProvider::$listen`:
    ```php
    \Reach\StatamicResrvVouchers\Events\VoucherUsed::class => [
        \Reach\StatamicResrvVouchers\Listeners\SendAttendedEmailOnVoucherUsed::class,
    ],
    ```

- [ ] **T10.5 Feature tests**
  - Marking used dispatches `VoucherUsed` and queues `VoucherAttended` to the customer (`Mail::assertQueued`).
  - Un-marking does NOT trigger the attended email.
  - Markdown template renders with customer name + reservation reference + (optionally) date range.

## Phase 11 — Install command, README, polish

- [ ] **T11.1 `InstallVouchers` console command**
  - `src/Console/Commands/InstallVouchers.php` signature `resrv-vouchers:install`.
  - Steps: publish config tag, publish views tag, run migrations, then `$this->info('...')` summary including: "Add collection handles to `config/resrv-vouchers.php` `enabled_collections`. Ensure `reachweb/statamic-resrv` >= the version that ships `BuildingReservationEmail`."
  - Register in `VouchersProvider::$commands`.

- [ ] **T11.2 `README.md`**
  - Sections: Requirements (Resrv version, Statamic 5, PHP 8.2), Installation (`composer require ... && php artisan resrv-vouchers:install`), Configuration (`enabled_collections`, `grace_days`, `signing_key`), Email customization (publish + add include directive), CP usage (scanner + list), Troubleshooting (camera-permission HTTPS note, scanning fails over `http://` in production).

- [ ] **T11.3 Document the hook in Resrv**
  - Add a short section to Resrv's `CLAUDE.md` documenting the new `BuildingReservationEmail` event for future addons. Reference Vouchers as the canonical consumer.

- [ ] **T11.4 Final test run**
  - `vendor/bin/pest` in `statamic-resrv-vouchers` — all green.
  - `vendor/bin/phpunit` in `statamic-resrv` — all green.
  - `vendor/bin/pint` clean in both packages.

- [ ] **T11.5 Manual cross-browser UAT**
  - Mobile Safari (iOS): scan works, camera permission prompt clear.
  - Chrome Android: scan works.
  - Desktop Chrome / Firefox: text-input fallback works when camera denied/absent.

---

## Acceptance criteria (treat as the gate)

- All 31 tasks checked.
- PEST suite green; Resrv PHPUnit suite green.
- Confirming a reservation in an enabled collection triggers an email with an inline QR PNG and a PDF attachment.
- Scanning that QR in `/cp/resrv-vouchers/scan` displays the reservation; "Mark as used" flips status, sends the attended email, audit-logs the action.
- Cancellation/refund invalidates the voucher; expired vouchers report as expired without a cron.
- A second admin can "Un-mark" a used voucher without triggering a new customer email.

## Out of scope (do not implement without re-asking the user)

- Multiple QRs per reservation (per-attendee).
- Public-facing (non-CP) "scan my own ticket" page.
- Voucher transfer between customers.
- Custom voucher artwork / branded PDF layouts beyond a simple QR + summary.
- A separate `use resrv-vouchers` permission.
- Replacing Resrv's confirmation template (instead, we provide an `@include` snippet).

---

## Done log (append-only — one line per completed task)

<!-- e.g. T0.1 done 2026-05-15: BuildingReservationEmail event added; PHPUnit green. -->
