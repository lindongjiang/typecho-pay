# typecho-cloudmantou

Monorepo for the Typecho payment plugin and the active VOID theme customization.

## Layout

```text
plugins/TypechoPay/   TypechoPay plugin package
themes/VOID/          VOID theme package
scripts/deploy.sh     Deploy both packages into a Typecho installation
```

The runtime package boundaries stay unchanged:

- `plugins/TypechoPay` deploys to `usr/plugins/TypechoPay`
- `themes/VOID` deploys to `usr/themes/VOID`

Do not commit the full Typecho CMS tree, uploaded files, local config, cache, or server-only secrets.

## Deploy

```sh
TYPECHO_HOST=example.com TYPECHO_ROOT=/www/wwwroot/example.com/typecho ./scripts/deploy.sh
```

Optional variables:

- `TYPECHO_USER`, default `ubuntu`
- `SSH_OPTS`, default empty
- `RSYNC_OPTS`, default `-az --delete`

The script excludes `.git`, `vendor`, `node_modules`, cache files, and local screenshots.

## Tests

Run plugin tests:

```sh
for test in plugins/TypechoPay/tests/*Test.php; do php "$test"; done
```

Run theme tests:

```sh
for test in themes/VOID/tests/*Test.php; do php "$test"; done
```
