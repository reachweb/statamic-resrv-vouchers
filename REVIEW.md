# Security & correctness review — `reachweb/statamic-resrv-vouchers`

Senior Laravel/Statamic review of the security-critical paths: signed-token generation,
email attachment, and CP scan-and-redeem. **Review only — no code was changed.**

Method: every security dimension was reviewed independently, and each candidate finding was
then cross-examined by two adversarial verifiers (one "is the bug technically real at this
line", one "does the framework/runtime already neutralize it"). False positives were dropped.
Three load-bearing framework claims were verified directly against the vendored code
(`vendor/laravel/framework` v13.14.0) and the sibling Resrv package.

Scope reviewed: `src/` (all 1,333 LOC), `routes/cp.php`, both migrations, `config/`, and the
Vue/Inertia CP pages.

## Summary

| # | Severity | Finding | File |
|---|----------|---------|------|
| C1 | **Critical** | Non-atomic `issued→used` transition → double-redemption + duplicate "attended" email | `src/Services/VoucherStateMachine.php` |
| H1 | **High** | QR/PDF render failure aborts Resrv's confirmation email (no fail-safe) | `src/Listeners/AttachVoucherToReservationEmail.php` |
| M1 | **Medium** | `voucherPayload` ships full Reservation + Customer models (payment ids, full PII) to the browser | `src/Http/Controllers/VoucherCpController.php` |
| L1 | Low | Lazy-expiry ignored by the listing column **and** the Status filter ("Expired" filter is dead) | `src/Resources/VoucherResource.php`, `src/Filters/VoucherStatus.php` |
| L2 | Low | `QrRenderer::pdf` ignores `tempnam`/`file_put_contents` failures → opaque FPDF error | `src/Services/QrRenderer.php` |
| L3 | Low | `resend()` returns the full Voucher model | `src/Http/Controllers/VoucherCpController.php` |
| L4 | Low | Dead code: `VoucherScan::getUserAttribute` accessor (latent N+1) | `src/Models/VoucherScan.php` |

The headline paths the brief was most worried about — **token forgery/replay**,
**authorization/IDOR**, **XSS**, **issuance idempotency**, and **the state machine rejecting
expired/invalidated/used vouchers** — were reviewed in depth and are **sound** (see
[Verified safe](#verified-safe-no-action-needed)). The one true vulnerability is the redemption
race (C1).

---

## Critical

### C1 — `markUsed` is a non-atomic read-then-write: the same voucher can be redeemed twice

**`src/Services/VoucherStateMachine.php:22-33` and `:51-60`** (entered via
`VoucherCpController::applyTransition`, `src/Http/Controllers/VoucherCpController.php:139-163`).

`markUsed()` guards with an in-memory read and then writes unconditionally:

```php
$this->guardTransition($voucher, [VoucherStatus::Issued], VoucherStatus::Used); // reads statusOf() — line 53
$voucher->forceFill([...'status' => VoucherStatus::Used...])->save();           // UPDATE ... WHERE id = ?
VoucherUsed::dispatch($voucher, $userId);
```

There is **no transaction, no `lockForUpdate`, and no conditional `UPDATE ... WHERE
status = 'issued'`**. There is no version/optimistic-lock column on the model. Each HTTP request
loads its **own** model instance (`voucherFromToken()` → `Voucher::find($uuid)`,
`VoucherCpController.php:249`), so two concurrent scans both read `status = Issued`, both pass
the guard, and both `save()`:

```
A: load (Issued)   B: load (Issued)
A: guard → pass    B: guard → pass
A: save → Used     B: save → Used        (overwrites used_at / used_by_user_id)
A: VoucherUsed     B: VoucherUsed         (fires twice)
```

The unique indexes on `reservation_id`/`token` do **not** help — both updates target the same
existing row by primary key, so no constraint is violated. Consequences:

1. **Double-redemption of a single-use asset** — both requests return `200` with the success
   payload and both audit-log `result = 'success'` (`VoucherCpController.php:160`). Two parties
   can be admitted on one voucher (one shared QR screenshot, two phones at the door, or a single
   double-tap before the first response returns).
2. **Duplicate customer email** — `VoucherUsed` fires twice, so `SendAttendedEmailOnVoucherUsed`
   (`:20`) queues two "thanks for attending" emails.
3. **Corrupted audit trail** — `used_by_user_id`/`used_at` reflect the last writer, not the true
   first redeemer, defeating the scan log's purpose.

This directly violates the atomicity guarantee stated in `CLAUDE.md` ("the issued→used
transition is atomic … so two simultaneous scans can't both mark the same voucher used").

**Fix** — make the transition a single-winner conditional UPDATE, and only dispatch when the row
actually changed:

```php
public function markUsed(Voucher $voucher, ?string $userId): void
{
    $this->guardTransition($voucher, [VoucherStatus::Issued], VoucherStatus::Used); // keep: lazy-expiry + clear 422

    $affected = Voucher::query()
        ->whereKey($voucher->getKey())
        ->where('status', VoucherStatus::Issued->value)   // only the first caller matches
        ->update([
            'status'          => VoucherStatus::Used->value,
            'used_at'         => now(),
            'used_by_user_id' => $userId,
        ]);

    if ($affected === 0) {
        throw new InvalidVoucherTransitionException('Voucher is no longer issued.');
    }

    $voucher->refresh();
    VoucherUsed::dispatch($voucher, $userId);
}
```

The loser gets `affected === 0` → `InvalidVoucherTransitionException`, which the controller
already maps to a `422` `invalid-transition` (`VoucherCpController.php:154-158`). The retained
`guardTransition()` still rejects the lazy-expired case (DB column `issued`, `isExpired() === true`)
before the UPDATE. Conditional-update is driver-portable across the supported SQLite/MySQL/Postgres;
a `DB::transaction` + `lockForUpdate` read is an equivalent alternative.

> **Severity note (transparency):** both adversarial verifiers independently confirmed this bug
> and the exact interleaving, but argued for **High** rather than Critical on the grounds that a
> voucher is a single-use pass (not a monetary balance), the token-signing path is untouched, and
> the race requires two near-simultaneous *authenticated* scans (no remote forgery). That is a
> fair argument. It is kept at **Critical** here because it is a genuine double-redemption of the
> product's core single-use asset and it breaks the one guarantee the brief explicitly asked to
> confirm. Whatever the label, it is the #1 fix.

---

## High

### H1 — A QR/PDF rendering failure aborts Resrv's confirmation email (listener is not fail-safe)

**`src/Listeners/AttachVoucherToReservationEmail.php:26-37`**

This listener is **synchronous** (no `ShouldQueue`) and runs inside Resrv's email build:
`ReservationConfirmed::build()` → `BuildingReservationEmail::dispatch()` (verified in
`../statamic-resrv/src/Mail/`). It calls `$this->renderer->png()` and `$this->renderer->pdf()`
with **no `try/catch`**. Any exception from rendering — endroid `Builder::build()`, FPDF
`Image()`/`Output()`, a failed temp write (see L2), or an `iconv`/`mb_convert_encoding` edge case
in `QrRenderer::safeText()` — propagates out of `handle()` → out of the synchronous event
dispatch → out of `build()`, and **aborts the entire confirmation email**.

`CLAUDE.md` is explicit: "a voucher-generation failure never blocks Resrv's confirmation email
(BuildingReservationEmail listener **must fail safe**)." It currently does not. Impact: a single
render glitch means a paid, confirmed customer receives **no** confirmation email at all — strictly
worse than a missing attachment, and hard to diagnose.

**Fix** — wrap the render+attach block; log and return so the email still sends without the voucher:

```php
public function handle(BuildingReservationEmail $event): void
{
    if (! $event->reservation) { return; }
    $voucher = Voucher::query()->where('reservation_id', $event->reservation->id)->first();
    if (! $voucher) { return; }

    try {
        $pngBytes = $this->renderer->png($voucher->token);
        $pdfBytes = $this->renderer->pdf($voucher, $pngBytes);
        $event->mailable->attachData($pdfBytes, "voucher-{$voucher->id}.pdf", ['mime' => 'application/pdf']);
        $event->mailable->withSymfonyMessage(fn (Email $email) => $email->embed($pngBytes, 'voucher-qr', 'image/png'));
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Failed to attach voucher to reservation email', [
            'voucher_id'     => $voucher->id,
            'reservation_id' => $event->reservation->id,
            'exception'      => $e->getMessage(),
        ]);
    }
}
```

---

## Medium

### M1 — `voucherPayload` serializes full Reservation + Customer models (payment ids, full PII) to the browser

**`src/Http/Controllers/VoucherCpController.php:169-191`** (returned by both `lookup` and
`markUsed`).

The payload returns raw Eloquent models, not a field whitelist: the full `$voucher`, the full
`$voucher->reservation` (only the `entry` append is hidden via `makeHidden`, `:172`), and the
eager-loaded `reservation.customer`. Verified against the sibling models:

- `Reservation` (`../statamic-resrv/src/Models/Reservation.php`) has no `$hidden`, so JSON
  includes `payment_id`, `payment_gateway`, `customer_id`, `item_id`, `rate_id`, and the
  `price`/`payment`/`payment_surcharge`/`total` money columns.
- `Customer` has `$guarded = []` and no `$hidden`, with `data` cast `AsCollection` — so the entire
  checkout-form blob (phone, billing/postal address, any custom fields), plus email/id/timestamps,
  serializes verbatim.

`Scan.vue` (`:171-201`) consumes only `voucher.id`, `status`, `status_banner`,
`reservation.reference`, `reservation.quantity`, `customer.data.first_name`/`last_name`,
`entry_title`, `rate`, `dates`, and `token`. Everything else is shipped to the browser unused.
All CP routes are permission-gated and staff are trusted, so this is not an auth bypass — but it
is a need-to-know/least-privilege violation: a door-scanner role receives payment-gateway
transaction ids and every guest's full billing PII. The index endpoint already deliberately
narrows to a whitelist (`VoucherResource.php:41-49`), which shows the narrow set is the intended
exposure.

**Fix** — return an explicit scalar whitelist (keeps `Scan.vue`'s render shape identical; keeps
the intentionally-exposed `token`):

```php
return [
    'voucher'       => ['id' => $voucher->id],
    'token'         => $voucher->token,
    'reservation'   => $voucher->reservation ? [
        'reference' => $voucher->reservation->reference,
        'quantity'  => $voucher->reservation->quantity,
        'customer'  => ['data' => [
            'first_name' => $voucher->reservation->customer?->data['first_name'] ?? null,
            'last_name'  => $voucher->reservation->customer?->data['last_name'] ?? null,
        ]],
    ] : null,
    'status'        => $status->value,
    'status_banner' => $status->banner(),
    'entry_title'   => $this->entryTitle($voucher->reservation),
    'rate'          => $this->rateLabel($voucher->reservation),
    'dates'         => ['start' => /* … */, 'end' => /* … */],
];
```

---

## Low

### L1 — Lazy-expiry is ignored by the listing column and the Status filter; the "Expired" filter is dead

**`src/Resources/VoucherResource.php:43`** and **`src/Filters/VoucherStatus.php:34-41`**

Lazy expiry (`VoucherStateMachine::statusOf()`, `:15-16`) never writes `'expired'` to the DB — an
expired voucher keeps `status = 'issued'` in the column. But:

- The listing emits `'status' => $voucher->status->value` (the raw column), so an expired voucher
  renders as a green **ISSUED** badge (`Index.vue:33`), while the scanner correctly shows
  **Expired** for the same voucher (`voucherPayload` routes through `statusOf()`, `:174`).
- The filter does `whereIn('status', $values['status'])` on the same column. The "Expired"
  checkbox (offered via `VoucherStatusEnum::cases()`) therefore **matches zero rows**, and
  "Issued" silently includes actually-expired vouchers.

No state-corruption or double-spend risk — `markUsed` still routes through
`guardTransition → statusOf` and rejects expired tokens — so this is a CP display/usability
defect only.

**Fix** — derive the effective status from the state machine in the resource, e.g.
`'status' => ($voucher->status === VoucherStatus::Issued && $voucher->isExpired()) ? 'expired' : $voucher->status->value`
(no extra query — `isExpired()` reads the already-loaded `expires_at`). For the filter, translate
the selections to expiry-aware predicates: `expired` → `status = 'issued' AND expires_at < now()`;
`issued` → `status = 'issued' AND (expires_at IS NULL OR expires_at >= now())`.

### L2 — `QrRenderer::pdf` ignores `tempnam`/`file_put_contents` failures → opaque FPDF error

**`src/Services/QrRenderer.php:25-26`**

`tempnam()` can return `false` (non-writable `sys_get_temp_dir`, exhausted inodes) and
`file_put_contents()` can return `false` (disk full, permissions); neither is checked. On
failure, `$tmp` coerces to `''` and `FPDF::Image()` (`:36`) throws a generic "Can't open image
file", masking the real cause (temp storage). On the hot email path this is one of the exceptions
that H1's missing `try/catch` would let abort the confirmation email; once H1 is fixed, the only
remaining harm is a misleading log message — hence Low.

**Fix** — check both return values before entering the `try/finally`:

```php
$tmp = tempnam(sys_get_temp_dir(), 'voucher-qr-');
if ($tmp === false) {
    throw new \RuntimeException('Unable to create temp file for voucher QR.');
}
if (file_put_contents($tmp, $pngBytes) === false) {
    @unlink($tmp);
    throw new \RuntimeException('Unable to write voucher QR to temp file.');
}
```

### L3 — `resend()` returns the full Voucher model

**`src/Http/Controllers/VoucherCpController.php:136`**

On success, `resend()` returns `response()->json(['voucher' => $voucher])`. The `token` is `$hidden`
on the model so it is **not** leaked, and the remaining columns are non-sensitive booking metadata
on a permission-gated route — so this is response-shape hygiene, not a security issue. Prefer an
intentional shape, e.g. `['voucher' => ['id' => $voucher->id]]` or `['message' => 'Email sent.']`.

### L4 — Dead code: `VoucherScan::getUserAttribute` accessor (latent N+1)

**`src/Models/VoucherScan.php:20-23`**

`getUserAttribute()` does `User::find($this->user_id)` on every access, but nothing reads
`$scan->user` anywhere in `src/`, `resources/js/`, or `tests/` (`logScan` only *writes* `user_id`).
As an accessor (not a relation) it cannot be eager-loaded, so any future scan listing that reads
`->user` silently incurs one lookup per row. Remove it until a feature needs it; if `scan→user`
is needed later, model it as a real relation so it can be eager-loaded.

> Note: `Voucher::scans()` is **not** dead — it is exercised by `tests/Unit/VoucherModelTest.php:79`
> (`expect($voucher->scans)->toHaveCount(1)`). Keep it. (One verifier initially proposed removing
> it; that would have broken the test — corrected here.)

---

## Verified safe (no action needed)

These were reviewed specifically because the brief flagged them; each is sound.

- **Token integrity / forgery / replay** (`src/Services/VoucherTokenSigner.php`). HMAC-SHA256 is
  computed over the decoded UUID bytes; `verify()` recomputes the expected MAC and compares with
  `hash_equals` (constant-time). base64url encode/decode round-trips and rejects malformed input
  (`base64_decode(..., true)`); `explode('.', $token, 2)` is safe. A token cannot be forged or
  altered without the key, and a valid-but-nonexistent UUID resolves to `null` and is handled.
  Key resolution is correct: `signing_key ?: app.key`, `base64:` prefix decoded, empty-key guard
  in the constructor. UUID v4 unpredictability is **not** load-bearing — the HMAC is the security
  boundary — but it is unpredictable regardless.
- **Authorization / IDOR.** Every CP route is behind `can:use resrv vouchers` (`routes/cp.php`),
  mirrored by the nav `->can()` and the widget gate. `mark-used` resolves the voucher from a
  **signed token only** (`voucherFromToken` → HMAC verify), so a 6-char booking reference cannot
  redeem a voucher. `resend/{voucher}` binds the UUID primary key, not the reference. The
  permission is registered via `Permission::extend` exactly once. POST/PATCH endpoints get
  Statamic's CP CSRF protection.
- **Issuance idempotency.** `VoucherGenerator::generateFor` uses
  `firstOrCreate(['reservation_id' => …], …)` backed by `unique('reservation_id')`. Verified in
  the vendored framework (v13.14.0): `firstOrCreate` delegates to `createOrFirst`, which catches
  `UniqueConstraintViolationException` and re-fetches by `reservation_id`
  (`Builder.php:710-735`). So concurrent/re-fired `ReservationConfirmed` events and job retries
  converge on exactly one row with no unhandled exception — driver-independent, beyond the
  best-effort `ShouldBeUnique` lock. *(Recommended, not required: add a Feature test that fires
  generation twice for one `reservation_id` and asserts a single row, and a bounded
  `public int $uniqueFor` on the listener to cap the lock TTL. This guarantee rests on framework
  behavior absent before Laravel 11.)*
- **State machine.** `markUsed` allows only `[Issued]`; `invalidate` allows only
  `[Issued, Expired]`. A **Used** voucher therefore survives a later cancel/refund/expire — the
  `InvalidateVoucherOnCancellation` listener swallows the resulting
  `InvalidVoucherTransitionException`. Expired (lazy) and invalidated vouchers reject `mark-used`
  with `422`. Reasons are recorded correctly per event. (Beyond the audit double-write inherited
  from C1, the transition rules themselves are correct.)
- **Queue resilience.** `GenerateVoucherForReservation` is `ShouldBeUnique`, `tries=3` with
  backoff, swallows the eligibility exception, and logs in `failed()`. `SendAttendedEmailOnVoucherUsed`
  no-ops cleanly when the customer/email is missing. The only double-email path is downstream of
  C1 (fixed once `VoucherUsed` fires once).
- **XSS.** No `v-html` anywhere in `Scan.vue`, `Index.vue`, or the widget; all dynamic values go
  through escaped `{{ }}` interpolation. The `mailto:` href in `Index.vue:37` is low-risk (email
  from a trusted reservation; not executable).
- **N+1 / indexes / mass assignment.** The listing eager-loads `reservation.customer` and the
  resource only reads eager-loaded fields — no N+1. Indexes are adequate: `unique` on
  `reservation_id` and `token`, `status` indexed, composite `[voucher_id, action]` on scans, no FK
  by design (documented). `$guarded = []` on both models is a smell but not currently exploitable —
  no model is created from raw request input (`firstOrCreate`/`forceFill`/`logScan` all use
  controlled arrays). Consider `$fillable` allowlists for defense-in-depth.

## Dismissed (false positives, for transparency)

- **`expiresAt` assumes non-null `date_end`.** Refuted: Resrv declares `date_end` `NOT NULL`
  (`../statamic-resrv/.../create_reservations_table.php:22`) and dereferences it unconditionally in
  its own model, so a confirmed reservation cannot have a null `date_end`. A guard would be dead
  code.
- **`QrRenderer::pdf` double-renders the QR.** Refuted: the only caller
  (`AttachVoucherToReservationEmail`) always passes the pre-rendered PNG; `resend` does not call
  `pdf()` at all (it re-dispatches the mailable). The fallback branch is never exercised and the
  token is never empty.
