# Changelog

## Unreleased

### Added

- A **Vouchers** control-panel dashboard widget with quick-action buttons to open the scanner and the vouchers list. Add it via the `widgets` array in `config/statamic/cp.php` (`'type' => 'vouchers'`); it renders only for users with the `use resrv vouchers` permission.
- The scan result card now shows the purchased entry's title and, when the reservation was booked with a rate, the rate label.

### Changed

- "Mark as used" and "Scan another" on the scan result card are now primary buttons.
- Vouchers are now issued synchronously when Resrv confirms a reservation, so a queue worker is only needed for the attended email. A failure to issue one is logged ("Failed to generate voucher for reservation") and never interrupts Resrv's confirmation.
- The voucher is attached only to Resrv's confirmation email. Resrv v6.5 also fires `BuildingReservationEmail` for the payment request email, which now never carries a voucher.

### Fixed

- With a real queue (database, Redis…), confirmation emails could go out without the voucher: Resrv sends the email right after the response, which can be before a queue worker has issued the voucher, and nothing emailed it afterwards.

### Breaking

- The un-mark flow has been removed: marking a voucher as used is now final. Gone are the CP "Un-mark" button, the `PATCH /cp/resrv-vouchers/un-mark` endpoint, `VoucherStateMachine::unMark()`, and the `VoucherUnmarked` event. Existing `un-mark` rows in the `resrv_voucher_scans` audit table are unaffected.
- CP access (routes and nav) is now gated by the addon's own `use resrv vouchers` permission instead of Resrv's `use resrv`. Grant the new permission (Roles → Permissions → Resrv Vouchers Permissions) to any role that should scan, list, or resend vouchers — having `use resrv` alone no longer grants voucher access. Voucher access is now grantable independently of Resrv access.
