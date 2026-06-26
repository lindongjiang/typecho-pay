# GitHub Record

Date: 2026-06-25

## 2026-06-26 Product/Fulfillment Foundation

### Change

Added the v0.3 product and fulfillment foundation so paid-reading can evolve toward card-code and multi-deliverable products without coupling stock delivery into payment gateways.

### Scope

- Added `ProductService` so new `[typechopay product="..."]` / `[typechopay product_id="..."]` entries resolve current server-side product price at click time.
- Kept legacy `amount/currency` shortcodes working as a compatibility path, while documenting their stale-cache price limitation.
- Added product snapshot fields to orders: `product_id`, `product_key`, `product_version`, and `product_snapshot_json`.
- Split order state into `payment_status` and `fulfillment_status` while retaining legacy `status`.
- Added `pay_products`, `pay_product_deliverables`, `pay_fulfillments`, `pay_card_batches`, and `pay_card_items` schema.
- Added `FulfillmentManager` and fulfillment records for `post_access` / `content_block` entitlement delivery.
- Changed guest login recovery to claim both guest orders and entitlements.
- Removed runtime schema mutation from `OrderService`; schema changes now run through activation-time versioned migration.
- Stopped reusing pending orders, which keeps each order poll token stable for its lifetime.
- Replaced Alipay callback/query CNY amount parsing with string-based yuan-to-fen conversion.

### Boundary

Card-code tables are present for the next phase, but `CardCodeHandler`, admin import, atomic reservation, stock release, low-stock notifications, and refund/revoke behavior are not implemented in this commit.

### Verification

Run after pulling this change:

```sh
composer validate --no-check-lock --strict
composer install --no-dev --prefer-dist --no-interaction --no-progress
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
for test in tests/*Test.php; do php "$test"; done
```

After deployment to an already-enabled Typecho site, disable and re-enable the plugin once, or otherwise invoke the activation migration, so schema version `4` and the new columns/tables are created.

## 2026-06-25 Configuration Compliance Follow-Up

### Change

Aligned payment setup documentation with the current gateway implementation and added minimal GitHub Actions CI.

### Scope

- Replaced the incorrect PayPay onboarding link with PayPay OPA / Dynamic QR documentation guidance.
- Clarified that Alipay currently supports ordinary public-key mode only, not public-key certificate mode.
- Clarified CNY shortcode amounts are written in cents and converted to yuan before calling Alipay.
- Updated plugin metadata version to `0.2.0`.
- Split the duplicated `0.1.0` changelog section into `0.1.1` and `0.1.0`.
- Removed config-page pseudo section fields so plugin settings do not persist meaningless section-marker values.
- Added `.github/workflows/ci.yml` to install dependencies, lint PHP files, and run `tests/*Test.php`.
- Suppressed third-party Alipay SDK deprecation notices while loading SDK files on PHP 8.
- Declared PHP `ext-curl`, installed curl in CI, and excluded `vendor/` from CI lint.
- Changed the settings help page to generate callback and return URLs through Typecho `Common::url()`.

### Verification

Local static checks:

- `git diff --check`
- Confirmed no incorrect PayPay onboarding links or pseudo section settings remain in `Plugin.php`.

Server-side checks should be run after pulling the pushed commit:

```sh
composer validate --no-check-lock --strict
composer install --no-dev --prefer-dist --no-interaction --no-progress
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
for test in tests/*Test.php; do php "$test"; done
```

## 2026-06-26 Payment Hardening Follow-Up

### Change

Hardened the payment lifecycle before real-money sandbox testing.

### Scope

- Made PayPay transaction webhooks compatible with official `merchant_order_id`, `order_id`, `order_amount`, `merchant_id`, and `notification_type` fields.
- Added per-order poll tokens and ownership checks for query and return endpoints.
- Changed article payment forms to call `do=prepare`, so fresh order data is generated at click time instead of embedding short-lived nonce values in cached HTML.
- Kept unknown create outcomes in `processing`; only definite local/configuration failures are marked `failed`.
- Synced provider terminal statuses to local orders.
- Added duplicate purchase prevention, stricter business target validation, formatted amount display, guest entitlement claim, and hardened guest cookies.
- Added `tests/PayPayWebhookTest.php` and expanded CI to run all `tests/*Test.php`.

### Verification

Run after pulling this change:

```sh
composer validate --no-check-lock --strict
composer install --no-dev --prefer-dist --no-interaction --no-progress
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
for test in tests/*Test.php; do php "$test"; done
```

## Change

Initialized `TypechoPay` as a Typecho payment plugin under `usr/plugins/TypechoPay`.

## Scope

- Added plugin entry, action router, database schema creation, and admin order list.
- Added order service with amount/currency validation, notification events, and idempotent paid updates.
- Added PayPay, WeChat Pay, and Alipay gateway adapters.
- Added signed article payment shortcode.
- Added documentation for installation, security boundaries, architecture, callbacks, and verification.

## Security Notes

- No merchant credentials are hardcoded.
- Public payment entry fields are HMAC signed.
- Payment callbacks must pass provider signature validation before marking orders paid.
- Amount and currency are rechecked against the local order before paid status is written.
- Notification payloads are persisted for audit.

## Verification

Completed:

- Initialized this plugin directory as an independent Git repository because `/Users/mantou/Downloads/typecho` is not a Git repository.
- Created initial commit with message `feat: add typecho payment plugin skeleton`; use `git log --oneline -n 1` in this plugin directory for the current hash.

Blocked locally:

- No `php` executable was found in PATH or common Homebrew locations.
- No Typecho `config.inc.php` exists at the project root, so database migration cannot be exercised locally.

Recommended verification in a PHP-enabled environment:

```sh
php usr/plugins/TypechoPay/tests/SignerTest.php
find usr/plugins/TypechoPay -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Next Commit Message

```text
feat: add typecho payment plugin skeleton
```
