# Changelog

## Unreleased

### Breaking

- CP access (routes and nav) is now gated by the addon's own `use resrv vouchers` permission instead of Resrv's `use resrv`. Grant the new permission (Roles → Permissions → Resrv Vouchers Permissions) to any role that should scan, list, or resend vouchers — having `use resrv` alone no longer grants voucher access. Voucher access is now grantable independently of Resrv access.
