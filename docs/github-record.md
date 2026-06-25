# GitHub Record

Date: 2026-06-25

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
- Added `.github/workflows/ci.yml` to install dependencies, lint PHP files, and run `tests/SignerTest.php`.
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
php tests/SignerTest.php
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
