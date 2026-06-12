# Changelog

## Unreleased

### Breaking

- The un-mark flow has been removed: marking a voucher as used is now final. Gone are the CP "Un-mark" button, the `PATCH /cp/resrv-vouchers/un-mark` endpoint, `VoucherStateMachine::unMark()`, and the `VoucherUnmarked` event. Existing `un-mark` rows in the `resrv_voucher_scans` audit table are unaffected.
- CP access (routes and nav) is now gated by the addon's own `use resrv vouchers` permission instead of Resrv's `use resrv`. Grant the new permission (Roles → Permissions → Resrv Vouchers Permissions) to any role that should scan, list, or resend vouchers — having `use resrv` alone no longer grants voucher access. Voucher access is now grantable independently of Resrv access.
