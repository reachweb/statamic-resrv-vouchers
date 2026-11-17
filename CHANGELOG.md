# Changelog

## Unreleased

### Added

- A **Vouchers** control-panel dashboard widget with quick-action buttons to open the scanner and the vouchers list. Add it via the `widgets` array in `config/statamic/cp.php` (`'type' => 'vouchers'`); it renders only for users with the `use resrv vouchers` permission.
- The scan result card now shows the purchased entry's title and, when the reservation was booked with a rate, the rate label.
- A confirmation email for a live reservation that has no voucher yet, for example because issuing it at confirmation failed, now issues the voucher and attaches it. To repair a missing voucher, resend the confirmation from Resrv's reservation page.

### Changed

- "Mark as used" and "Scan another" on the scan result card are now primary buttons.
- Vouchers are now issued synchronously when Resrv confirms a reservation, so a queue worker is only needed for the attended email. A failure to issue one is logged ("Failed to generate voucher for reservation") and never interrupts Resrv's confirmation.
- Only Resrv's confirmation email gets the voucher. Other emails that fire `BuildingReservationEmail`, such as the payment request email Resrv v6.5 added, are ignored.

### Fixed

- With a real queue (database, Redis…), confirmation emails could go out without the voucher: Resrv sends the email right after the response, which can be before a queue worker has issued the voucher, and nothing emailed it afterwards.
- A booking cancelled or refunded while its voucher was being issued could keep a valid voucher that scanned as usable. A voucher is now issued only if the reservation is still confirmed (or partner) when it is written, checked under the same row lock Resrv's cancellation takes.
- The inline QR snippet (`statamic-resrv-vouchers::email.vouchers.partials.qr`) printed a broken image in confirmation emails for bookings without a voucher. It now renders only when the QR was embedded in that email, so you can drop any `@if` you wrapped around the include. If you published the add-on's email templates, your copy at `resources/views/vendor/statamic-resrv-vouchers/email/vouchers/partials/qr.blade.php` still has the old snippet: delete it to use the package's, or wrap its contents in `@if (! empty($voucherQrEmbedded))` … `@endif`. Re-publishing doesn't replace an existing file without `--force`, and `--force` would also overwrite your attended email template.

### Breaking

- The un-mark flow has been removed: marking a voucher as used is now final. Gone are the CP "Un-mark" button, the `PATCH /cp/resrv-vouchers/un-mark` endpoint, `VoucherStateMachine::unMark()`, and the `VoucherUnmarked` event. Existing `un-mark` rows in the `resrv_voucher_scans` audit table are unaffected.
- CP access (routes and nav) is now gated by the addon's own `use resrv vouchers` permission instead of Resrv's `use resrv`. Grant the new permission (Roles → Permissions → Resrv Vouchers Permissions) to any role that should scan, list, or resend vouchers — having `use resrv` alone no longer grants voucher access. Voucher access is now grantable independently of Resrv access.
