# Release Checklist

Use this checklist before publishing a GitHub Release.

## Version

- Update plugin version metadata if the release changes plugin behavior.
- Update `plugins/TypechoPay/CHANGELOG.md`.
- Use a tag such as `v0.6.0`.

## Validation

Run the same checks as CI:

```sh
find plugins/TypechoPay themes/VOID \
  -path '*/vendor/*' -prune -o \
  -path '*/node_modules/*' -prune -o \
  -name '*.php' -print0 | xargs -0 -n1 php -l

for test in plugins/TypechoPay/tests/*Test.php; do php "$test"; done
for test in themes/VOID/tests/*Test.php; do php "$test"; done
```

For payment-related releases, also verify on a staging Typecho site:

- Article product card renders on a paid/card article.
- Order prepare creates or reuses an active order.
- Payment success does not duplicate fulfillment on repeated callbacks.
- Delivered card page is reachable after return.
- Existing purchased visitor can reopen delivered cards.
- Sold-out card product does not create a new payable order.

## Package

Build zip packages:

```sh
VERSION=0.6.0 ./scripts/package-release.sh
```

Expected artifacts:

- `dist/TypechoPay-0.6.0.zip`
- `dist/VOID-cloudmantou-0.6.0.zip`

Check package contents:

```sh
unzip -l dist/TypechoPay-0.6.0.zip | grep 'TypechoPay/Plugin.php'
unzip -l dist/VOID-cloudmantou-0.6.0.zip | grep 'VOID/functions.php'
```

For public releases, confirm whether `TypechoPay-*.zip` contains production `vendor/`. If it does not, the release notes must say that Composer installation is required.

## Release Notes

Include:

- Supported Typecho version.
- Supported PHP version.
- Whether `vendor/` is included.
- Whether database backup is recommended.
- Whether users should disable and re-enable the plugin after upload.
- Upgrade notes for payment, schema, card inventory, and theme changes.

## Post-release

- Confirm GitHub Actions passed for the tag or release commit.
- Deploy to the live site from the monorepo checkout.
- Verify the homepage returns HTTP 200.
- Verify one paid/card article still renders the product panel.
