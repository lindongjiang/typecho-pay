# Changelog

## Unreleased

- Fixed PayPay Dynamic QR active query path to `/v2/codes/payments/{merchantPaymentId}`.
- Added active order query for PayPay, WeChat Pay, and Alipay.
- Changed `/action/typechopay?do=query` to query the upstream gateway while orders are pending.
- Added QR payment page rendering with polling.
- Added signed payment payload expiry, gateway coverage, and one-time nonce consumption.
- Added `pay_entitlements` and minimal paid-reading access grants.
- Hardened paid state transitions to only allow payable statuses into `paid`.
- Added provider event metadata fields for payment event auditing.

## 0.1.0 - 2026-06-25

- Added TypechoPay plugin entry and activation/deactivation flow.
- Added order and notification-event tables.
- Added unified `/action/typechopay` create, notify, query, and return handlers.
- Added PayPay Dynamic QR direct HMAC client.
- Added WeChat Pay Native SDK adapter and APIv3 notification verification.
- Added Alipay Page Pay / Precreate SDK adapter and async notification verification.
- Added article shortcode with HMAC-signed payment entry fields.
- Added admin order list and implementation documentation.
