## Why

Debian Bullseye's LTS window closed on 2026-08-31. `.wp-env.json` pins `phpVersion: "7.4"`, and the official `wordpress:php7.4-apache` image (frozen since PHP 7.4 itself went EOL in 2022) is built on Bullseye. `apt-get update` inside that container — run by `bin/install-php-ldap-ext.sh` to install the `ldap` extension — now 404s against `deb.debian.org`, so `wp-env start` and every test run that depends on it are broken. Moving to a PHP version with an actively-rebuilt Docker image (Bookworm-based) fixes this at the root instead of patching apt sources for an EOL release.

## What Changes

- Bump `.wp-env.json` `phpVersion` from `"7.4"` to `"8.3"`, moving the `wordpress`/`tests-wordpress`/`cli`/`tests-cli` containers onto a Debian Bookworm base with active security updates.
- Update `composer.json`'s `config.platform.php` from `"7.4"` to `"8.3"` so Composer's dependency resolution matches the real test runtime instead of silently pinning to the old floor.
- Bump `phpunit/phpunit` from `^9.6` to a version with full PHP 8.3 support (`^10` or `^11`, final pick during implementation based on `yoast/phpunit-polyfills` compatibility).
- No change to the plugin's declared minimum: `Requires PHP: 7.4` in `readme.txt` and `"php": ">=7.4"` in `composer.json` stay as-is — this only changes which PHP version is *exercised in wp-env/CI*, not the plugin's floor.
- `bin/install-php-ldap-ext.sh` needs no logic change: its Debian branch (`apt-get`/`docker-php-ext-install`) is unaffected by the PHP version, only by the base OS, which this change fixes by moving off Bullseye.

## Capabilities

### New Capabilities
(none)

### Modified Capabilities
- `local-testing-environment`: the wp-env containers' PHP version and the Composer/PHPUnit toolchain version they resolve against are changing from 7.4 to 8.3, replacing the implicit dependency on an EOL Debian base.

## Impact

- **Affected files**: `.wp-env.json`, `composer.json`, `composer.lock` (regenerated), `openspec/specs/local-testing-environment/spec.md` (delta).
- **Affected tooling**: `bin/test.sh`, `bin/install-php-ldap-ext.sh`, `bin/ldap-test-env.sh` — expected to keep working unchanged, but every step gets exercised against the new base as part of verification.
- **CI**: `.github/workflows/build-test.yml`'s `phpunit` job runs the same `bin/test.sh`, now against PHP 8.3 containers.
- **Risk surface**: PHP 7.4→8.3 language/runtime changes (e.g. `ldap_*` functions returning `LDAP\Connection`/`LDAP\Result` objects instead of resources since 8.1, dynamic-property deprecation since 8.2). A quick grep found no `is_resource()` checks on LDAP handles and no dynamic-property patterns in `includes/`/`admin/`, so no plugin source changes are expected — but this should be re-verified by actually running the suite, not just by grep.
- **Not in scope**: raising the plugin's declared minimum PHP version, testing a matrix of multiple PHP versions in CI, and the earlier-considered "patch apt sources for Bullseye" tactical fix (superseded by this root-cause change).
