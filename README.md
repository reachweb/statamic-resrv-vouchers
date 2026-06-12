# Statamic Resrv Vouchers

QR-code voucher generation, email delivery, and CP scanning for Statamic Resrv reservations.

When a reservation is confirmed in a voucher-enabled collection, the addon:

- Generates a signed-token QR code for the reservation.
- Attaches an inline PNG (for the email body) and a single-page PDF to Resrv's existing confirmation email.
- Exposes a CP page where staff can scan the QR with a phone camera (or look the voucher up by reservation code, booking reference, or pasted token), validate it, and mark it used.
- Sends a "thank you for attending" email when the voucher is marked used (when the customer has an email on file).
- Invalidates the voucher when the underlying reservation is cancelled, refunded, or expires.

## Requirements

- **Statamic** 6.x
- **PHP** 8.4+ — the Vouchers package itself allows 8.3, but Resrv v6 requires 8.4, so that is the effective floor.
- **Laravel** 12.x or 13.x
- **Resrv** v6. It ships the `Reach\StatamicResrv\Events\BuildingReservationEmail` event the addon listens to in order to attach the QR / PDF to the confirmation mailable.
- **`use resrv` permission** for any CP user who should scan, list, or resend vouchers. No new permission is introduced — the addon piggy-backs on Resrv's existing one.
- **A running queue worker.** Voucher generation and the attended email are queued jobs. Without a worker no vouchers are created and no attended emails go out — alternatively set `QUEUE_CONNECTION=sync` for fully synchronous behavior.

## How it works

The addon is purely event-driven and never modifies Resrv. When Resrv confirms a reservation, a queued listener issues exactly one voucher per reservation (re-fired events are a no-op). While Resrv builds its confirmation email, a synchronous listener attaches the voucher PDF and embeds the inline QR PNG into that same mailable — there is no separate voucher email. At the venue, staff scan the QR (or look the voucher up by reservation code or booking reference) on a CP page; marking the voucher used triggers the attended email. Cancelling, refunding, or expiring the reservation invalidates the voucher, and any voucher past `date_end + grace_days` reports as expired on its own.

## Installation

```bash
composer require reachweb/statamic-resrv-vouchers
php artisan resrv-vouchers:install     # also available as: php please resrv-vouchers:install
```

The install command, in order:

1. Publishes `config/resrv-vouchers.php` (tag `resrv-vouchers-config`).
2. Runs the addon's migrations (`resrv_vouchers` and `resrv_voucher_scans` tables).
3. Asks *"Publish the email templates? (only needed if you wish to edit them)"* — publishes to `resources/views/vendor/statamic-resrv-vouchers/email/` (tag `resrv-vouchers-emails`).
4. Asks *"Publish the language files? (only needed if you wish to edit them)"* — publishes to `lang/vendor/statamic-resrv-vouchers/` (tag `resrv-vouchers-language`).

After installing:

- Add the collection handles you want vouchers issued for to `enabled_collections` in `config/resrv-vouchers.php`.
- If you want the QR inline in the email body (the PDF is always attached), add the include snippet to Resrv's published confirmation template — see [Email customization](#email-customization).
- Make sure a queue worker is running on the host.

## Configuration

Edit `config/resrv-vouchers.php` after installation:

```php
return [
    // Reservations in these collection handles are voucher-eligible.
    'enabled_collections' => ['accommodation', 'activities'],

    // Days added on top of `reservation.date_end` before a voucher reports as expired.
    'grace_days' => 1,

    // HMAC signing key. Falls back to APP_KEY when null.
    'signing_key' => env('RESRV_VOUCHERS_SIGNING_KEY'),

    'email' => [
        'attended' => [
            'subject'  => null,                      // override default "Thank you for attending!" subject
            'from'     => ['address' => null, 'name' => null],
            'markdown' => null,                      // override the default template handle
        ],
    ],
];
```

`enabled_collections` defaults to an empty array — reservations in collections not listed here are silently skipped, no voucher is created, and the confirmation email goes out unchanged.

Tokens have the form `base64url(uuid) . '.' . base64url(hmac_sha256(uuid, key))` and are verified in constant time. The key falls back to `APP_KEY` (the `base64:` prefix is handled) when `RESRV_VOUCHERS_SIGNING_KEY` is unset; set a dedicated key in your `.env` if you want to be able to rotate it without rotating `APP_KEY`.

## Email customization

The QR is attached to Resrv's existing confirmation email as a PDF on every send. To also show the QR inline in the email body, publish Resrv's confirmation template and include this addon's snippet:

```bash
php artisan vendor:publish --tag=resrv-emails   # Resrv's publish tag, not the addon's
```

Then add the include to `resources/views/vendor/statamic-resrv/email/reservations/confirmed.blade.php` wherever you want the QR to appear:

```blade
@include('statamic-resrv-vouchers::email.vouchers.partials.qr')
```

The snippet uses `<img src="cid:voucher-qr">` against the inline image the addon embeds at send time — no extra wiring required. Its caption ("Show this QR code at check-in.") is translatable via the `statamic-resrv-vouchers::email.qr_caption` language key.

To customize the "attended" email's subject, from, or markdown template, set the relevant `email.attended` key in `config/resrv-vouchers.php`. The mailable extends `Reach\StatamicResrv\Mail\Mailable`, so it picks up Resrv's published theme components automatically. If you published the addon's email templates during install, the attended template itself is editable at `resources/views/vendor/statamic-resrv-vouchers/email/vouchers/attended.blade.php`.

## CP usage

Both CP pages live under the **Resrv → Vouchers** nav section and require the `use resrv` permission:

- **Vouchers / List** (`/cp/resrv-vouchers`) — a standard CP listing (sorted by issue date, newest first) with columns for ID, status, reservation reference, customer, expiry, used-at, and issued-at. It supports a pinned status filter (with active-filter badge), search by voucher ID or reservation reference, column sorting, per-user column preferences, and pagination. Re-sending the confirmation email (with the voucher attached) is available through the internal resend endpoint (see [Developer reference](#developer-reference)); it goes through Resrv's email dispatcher and honors Resrv's reservation-email settings, so if the confirmation email is disabled there the resend fails with *"Email could not be sent."* A per-row resend action in the listing is planned.
- **Vouchers / Scan** (`/cp/resrv-vouchers/scan`) — an html5-qrcode camera scanner plus a text fallback with a **Find voucher** button. The fallback accepts whatever the guest can read off their confirmation email — the numeric **reservation code** or the six-character **booking reference** — as well as a raw pasted token; matching is exact (use the Vouchers list for fuzzy search), case-insensitive for references, whitespace is trimmed, and Enter submits, so a USB (keyboard-wedge) barcode scanner pointed at the field works without any camera. The camera does not auto-start: staff press **Start camera** (manual entry never needs camera permission), and **Stop camera** / **Switch camera** buttons appear while it runs (the latter only when the device has more than one camera). On a successful decode or lookup the page shows a result card: status badge, a color-coded banner (*"Voucher is valid."* / *"Voucher has already been used."* / *"Voucher has been invalidated."* / *"Voucher has expired."*), and the reservation's reference, guest name, dates, and quantity. Buttons gate by status: **Mark as used** when the voucher is `issued`, **Un-mark** when it is `used`, **Scan another** always — the card updates directly from the action's response, so a mark-used/un-mark never writes an extra scan row to the audit log.

Every lookup, mark-used, un-mark, and resend is audit-logged — see [Developer reference](#developer-reference).

## Voucher lifecycle

| Trigger | Result |
| --- | --- |
| Resrv `ReservationConfirmed` (eligible collection) | Voucher created with `status=issued`, expires at `date_end + grace_days`. Queued; idempotent — one voucher per reservation, re-fired events are a no-op. |
| Resrv `BuildingReservationEmail` | Synchronous listener attaches the voucher PDF (A6 page: QR, guest name, reference, date range) and embeds the inline PNG. No voucher → the email sends normally without attachments. |
| CP "Mark as used" | `status=used`, audit-logged, attended email queued to the customer (skipped if the customer has no email). |
| CP "Un-mark" | `status=issued`, audit-logged, **no** customer email. |
| Resrv `ReservationCancelled` / `Refunded` / `Expired` | `status=invalidated`, reason recorded (`cancelled` / `refunded` / `expired-reservation`). A voucher already `used` stays used — the customer did attend. Repeat events are a no-op. |
| `now() > expires_at` while `issued` | Reports as `expired` (lazily — no DB write, no cron). Attempting "Mark as used" on an expired voucher fails with a 422. |

Voucher generation and the attended email run on the queue — see [Requirements](#requirements).

## Troubleshooting

- **Vouchers aren't generated / attended emails don't arrive** — no queue worker is running. Start one (`php artisan queue:work`) or set `QUEUE_CONNECTION=sync`.
- **"Mark as used" returns an error on a valid-looking voucher** — the voucher is past `expires_at` (lazily expired) or has been invalidated; neither can be marked used. Check the status banner on the scan result.
- **Camera access blocked on `http://`** — browsers require HTTPS (or `localhost`) for `getUserMedia`. Deploy behind TLS or use the manual entry fallback (reservation code, booking reference, or token).
- **`npm run cp:build` errors about `@statamic/cms` not found** — the file: dep resolves from the host site's `vendor/` (`file:../../../vendor/statamic/cms/resources/dist-package`). Run `composer install` in the host first, then `npm install --install-links` so npm copies the package rather than symlinking it (transitive deps like `@vitejs/plugin-vue` resolve from the addon's own `node_modules` only when copied).
- **Email arrives without the inline QR but with the PDF attachment** — you have not published / included the QR snippet in Resrv's confirmation template. The PDF attachment is always sent regardless. Add `@include('statamic-resrv-vouchers::email.vouchers.partials.qr')` to your published `confirmed.blade.php`.
- **Voucher generation seems to be missing for some reservations** — confirm the reservation's collection handle is listed in `config/resrv-vouchers.php` `enabled_collections`. Vouchers are silently skipped for any reservation whose entry lives outside that list.

## Developer reference

**Events you can listen to** (all in `Reach\StatamicResrvVouchers\Events`):

- `VoucherUsed` — fired when a voucher transitions to `used` (carries the voucher + acting user id).
- `VoucherUnmarked` — fired when a used voucher is reverted to `issued`.
- `VoucherInvalidated` — fired when a voucher is invalidated (carries the reason).

The addon's integration surface with Resrv is consumed, never modified: it listens to `ReservationConfirmed`, `BuildingReservationEmail`, and `ReservationCancelled` / `ReservationRefunded` / `ReservationExpired`.

**Audit table** — every scan-page and listing action writes a row to `resrv_voucher_scans`: `action` ∈ `scan` | `mark-used` | `un-mark` | `resend`, `result` ∈ `success` | `not-found` | `already-used` | `invalidated` | `expired` | `invalid-transition` | `not-sent`, plus the acting user id, IP address, and user agent.

**Voucher table** — `resrv_vouchers`: string UUID primary key, unique `reservation_id` (the one-voucher-per-reservation guarantee) and `token`, indexed `status`, `expires_at`, `used_at` / `used_by_user_id`, `invalidated_reason`.

**CP endpoints** (internal — may change without notice; all behind `can:use resrv`):

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/cp/resrv-vouchers/list` | Paginated voucher JSON for the listing |
| POST | `/cp/resrv-vouchers/lookup` | Find a voucher by signed token, reservation code, or booking reference (`query` param); returns voucher + reservation + status banner + canonical token |
| PATCH | `/cp/resrv-vouchers/mark-used` | Transition `issued` → `used` |
| PATCH | `/cp/resrv-vouchers/un-mark` | Transition `used` → `issued` |
| POST | `/cp/resrv-vouchers/resend/{voucher}` | Re-send the confirmation email with the voucher attached |

**Frontend development** — only needed when developing the addon itself; production installs ship the prebuilt `resources/dist/build/`. The `@statamic/cms` dependency resolves from the host site's vendor directory, so install with `npm install --install-links` (copies instead of symlinking). Then `npm run cp:dev` for Vite HMR or `npm run cp:build` to produce the deployable build the Statamic CP picks up via the `protected $vite` declaration in `VouchersProvider`. Stack: Vite 8, Tailwind v4, Vue 3 Inertia pages registered via `Statamic.$inertia.register(...)`.

## Tests

```bash
vendor/bin/pest             # full suite
vendor/bin/pest --filter X  # single test
vendor/bin/pint             # code style
```

The suite extends `Statamic\Testing\AddonTestCase` and runs against SQLite in-memory with the Resrv sibling addon resolved as `dev-main` via the `../statamic-resrv` path repo.

## License

Proprietary. Licensed for use on Reach Web client projects; not for redistribution. Support: info@reach.gr.
