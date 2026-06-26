# Changelog

## Unreleased

## 0.3.0 - 2026-06-26

- Added admin product and card-code inventory management panel.
- Added encrypted card-code import using AES-256-GCM and per-product duplicate fingerprints.
- Added card-code reservation before gateway creation and idempotent delivery after payment success.
- Added release of reserved card-code stock when orders fail, expire, cancel, or close.
- Added secure card delivery page guarded by poll token or order ownership.
- Added `CardCodeCipherTest` for encrypted card-code round trips.
- Added product-mode payment entries that resolve current server-side product price by `product` or `product_id`.
- Added product snapshot columns to orders and created product, deliverable, fulfillment, card batch, and card inventory tables.
- Split payment state into `payment_status` and fulfillment state into `fulfillment_status` while keeping legacy `status`.
- Added a minimal fulfillment manager for `post_access` and `content_block` entitlement delivery.
- Changed guest recovery to claim both guest orders and guest entitlements after login.
- Removed runtime schema mutation from business requests and moved schema upgrades behind an activation-time schema version.
- Stopped reusing pending orders so each order keeps a stable poll token for its lifetime.
- Parsed Alipay yuan amounts with string arithmetic instead of floating-point conversion.
- Fixed PayPay transaction webhook parsing for `merchant_order_id`, `order_id`, `order_amount`, `merchant_id`, and `notification_type`.
- Added per-order poll tokens for query/return access control and removed `return_to` from public query responses.
- Added click-time payment entry preparation so cached article HTML no longer embeds short-lived nonce values.
- Kept orders in `processing` when payment creation result is unknown instead of marking them failed.
- Synced provider terminal states such as expired, cancelled, failed, closed, revoked, and trade closed.
- Added server-side duplicate purchase checks, stricter business target validation, guest entitlement claim, and formatted amount display.
- Fixed PayPay setup links to point to PayPay OPA/Dynamic QR documentation.
- Clarified that Alipay currently supports ordinary public-key mode only.
- Clarified CNY shortcode amounts are written in cents and converted to yuan for Alipay.
- Added minimal GitHub Actions CI for dependency install, PHP lint, and signer tests.
- Suppressed third-party Alipay SDK deprecation notices while loading SDK files on PHP 8.
- Declared the PHP cURL extension and excluded `vendor/` from CI lint.
- Generated settings-help callback URLs with Typecho `Common::url()`.

## 0.2.0 - 2026-06-25

- Reorganized plugin config fields by Basic, PayPay, WeChat Pay, and Alipay sections
- Added detailed descriptions and help text for all configuration fields
- Added direct links to payment platform dashboards (PayPay, WeChat, Alipay)
- Created settings-help.php admin panel with callback URLs display
- Added comprehensive setup guides for each payment gateway
- Added shortcode usage documentation and FAQ section
- Updated README with new admin menu structure and setup instructions

## 0.1.1 - 2026-06-25

- Fixed PayPay Dynamic QR active query path to `/v2/codes/payments/{merchantPaymentId}`.
- Added active order query for PayPay, WeChat Pay, and Alipay.
- Changed `/action/typechopay?do=query` to query the upstream gateway while orders are pending.
- Added QR payment page rendering with polling.
- Added signed payment payload expiry, gateway coverage, and one-time nonce consumption.
- Added `pay_entitlements` and minimal paid-reading access grants.
- Fixed entitlement access SQL grouping and hid purchase buttons for already-owned content.
- Added signed `return_to` handling so paid QR pages redirect back instead of reloading POST.
- Added `paid_pending_grant` / `grant_failed` recovery states and admin entitlement regrant action.
- Added server-side throttling for active gateway queries.
- Hardened paid state transitions so orders enter `paid` only after entitlement grant succeeds.
- Added provider event metadata fields for payment event auditing.
- Fixed attributed `[typechopay_content ...]` parsing, Composer SDK autoloading, and currency-incompatible gateway buttons.

## 0.1.0 - 2026-06-25

- Added TypechoPay plugin entry and activation/deactivation flow.
- Added order and notification-event tables.
- Added unified `/action/typechopay` create, notify, query, and return handlers.
- Added PayPay Dynamic QR direct HMAC client.
- Added WeChat Pay Native SDK adapter and APIv3 notification verification.
- Added Alipay Page Pay / Precreate SDK adapter and async notification verification.
- Added article shortcode with HMAC-signed payment entry fields.
- Added admin order list and implementation documentation.
