# Statamic Resrv Vouchers

QR-code voucher generation, email delivery, and CP scanning for Statamic Resrv reservations.

When a reservation is confirmed in a voucher-enabled collection, the addon:

- Generates a signed-token QR code for the reservation.
- Attaches an inline PNG (for the email body) and a single-page PDF to Resrv's existing confirmation email.
- Exposes a CP page where staff can scan the QR with a phone camera (or paste the token manually), validate the voucher, and mark it used.
- Sends a "thank you for attending" email when the voucher is marked used.
- Invalidates the voucher when the underlying reservation is cancelled, refunded, or expires.

## Requirements

- **Statamic** 6.x
- **PHP** 8.3+
- **Laravel** 12.x or 13.x
- **Resrv** any release that ships the `Reach\StatamicResrv\Events\BuildingReservationEmail` event. The Vouchers addon listens to this event to attach the QR / PDF to the existing confirmation mailable.
- **`use resrv` permission** for any CP user who should scan, list, or resend vouchers. No new permission is introduced — the addon piggy-backs on Resrv's existing one.

## Installation

```bash
composer require reachweb/statamic-resrv-vouchers
php artisan resrv-vouchers:install
```

The install command:

1. Publishes the `config/resrv-vouchers.php` file.
2. Runs the addon's migrations (`resrv_vouchers` and `resrv_voucher_scans` tables).
3. Optionally publishes the email templates (`resources/views/vendor/statamic-resrv-vouchers/email/`).
4. Optionally publishes the translations (`lang/vendor/statamic-resrv-vouchers/`).

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

Tokens are UUID v4 + HMAC-SHA256, base64url-encoded. Set a dedicated `RESRV_VOUCHERS_SIGNING_KEY` in your `.env` if you want to be able to rotate the signing key without rotating `APP_KEY`.

## Email customization

The QR is attached to Resrv's existing confirmation email as a PDF on every send. To also show the QR inline in the email body, publish Resrv's confirmation template and include this addon's snippet:

```bash
php artisan vendor:publish --tag=resrv-emails
```

Then add the include to `resources/views/vendor/statamic-resrv/email/reservations/confirmed.blade.php` wherever you want the QR to appear:

```blade
@include('statamic-resrv-vouchers::email.vouchers.partials.qr')
```

The snippet uses `<img src="cid:voucher-qr">` against the inline image the addon embeds at send time — no extra wiring required.

To customize the "attended" email's subject, from, or markdown template, set the relevant `email.attended` key in `config/resrv-vouchers.php`. The mailable extends `Reach\StatamicResrv\Mail\Mailable`, so it picks up Resrv's published theme components automatically.

## CP usage

Both CP pages live under the **Resrv → Vouchers** nav section:

- **Vouchers / List** (`/cp/resrv-vouchers`) — `@statamic/cms/ui` `<Listing>` with status filter, paginated, sortable, with a resend-email row action.
- **Vouchers / Scan** (`/cp/resrv-vouchers/scan`) — html5-qrcode camera scanner with a "switch camera" toggle and a paste-the-token text fallback. On a successful decode the page shows the reservation summary + a status banner. Buttons gate by status: **Mark as used** when the voucher is `issued`, **Un-mark** when it is `used`, **Scan another** always.

Both pages are Statamic 6 Inertia pages registered via `Statamic.$inertia.register(...)`. For active development run `npm install --install-links && npm run cp:dev` from the addon root — Vite HMR reloads the Vue pages without a full CP refresh.

To produce the deployable build:

```bash
npm install --install-links
npm run cp:build
```

This emits `resources/dist/build/manifest.json` and the chunked JS/CSS assets that the Statamic CP's vite plugin picks up via the `protected $vite` declaration in `VouchersProvider`.

## Voucher lifecycle

| Trigger | Result |
| --- | --- |
| Resrv `ReservationConfirmed` (eligible collection) | Voucher row created with `status=issued`, expires at `date_end + grace_days`. |
| Resrv `BuildingReservationEmail` | Listener attaches PDF + embeds inline PNG to the mailable about to be sent. |
| CP "Mark as used" | `status=used`, audit-logged, attended email queued to the customer. |
| CP "Un-mark" | `status=issued`, audit-logged, **no** customer email. |
| Resrv `ReservationCancelled` / `Refunded` / `Expired` | `status=invalidated`, reason recorded. |
| `now() > expires_at` while `issued` | Lazy `Expired` status reported by `statusOf()`; no DB write. |

Every scan/mark/un-mark/resend writes a row to `resrv_voucher_scans` (action + result + ip + user agent + actor user id), for audit.

## Troubleshooting

- **Camera access blocked on `http://`** — browsers require HTTPS (or `localhost`) for `getUserMedia`. Deploy behind TLS or use the manual token-input fallback.
- **`npm run cp:build` errors about `@statamic/cms` not found** — the file: dep resolves from the host site's `vendor/` (`file:../../../vendor/statamic/cms/resources/dist-package`). Run `composer install` in the host first, then `npm install --install-links` so npm copies the package rather than symlinking it (transitive deps like `@vitejs/plugin-vue` resolve from the addon's own `node_modules` only when copied).
- **Email arrives without the inline QR but with the PDF attachment** — you have not published / included the QR snippet in Resrv's confirmation template. The PDF attachment is always sent regardless. Add `@include('statamic-resrv-vouchers::email.vouchers.partials.qr')` to your published `confirmed.blade.php`.
- **Voucher generation seems to be missing for some reservations** — confirm the reservation's collection handle is listed in `config/resrv-vouchers.php` `enabled_collections`. Vouchers are silently skipped for any reservation whose entry lives outside that list.

## Tests

```bash
vendor/bin/pest             # full suite
vendor/bin/pest --filter X  # single test
vendor/bin/pint             # code style
```

The suite extends `Statamic\Testing\AddonTestCase`, runs against SQLite in-memory + the Resrv sibling addon (loaded via path repo in development).

## License

Proprietary.
