## MODIFIED Requirements

### Requirement: Local WordPress environment via wp-env
The project SHALL provide a local WordPress environment managed by `wp-env` (`.wp-env.json`), with no custom `Dockerfile` or `docker-compose.yml`, for manual visual QA of the plugin's shortcode, Elementor widget, and Beaver Builder module. The `phpVersion` pinned in `.wp-env.json` SHALL be a version whose official Docker image is still actively rebuilt on a supported Debian base (not EOL) at the time it is set, so `wp-env start` does not depend on an unmaintained OS image for its Docker layer.

#### Scenario: Starting the local environment
- **WHEN** a developer runs `wp-env start` from the plugin root
- **THEN** a WordPress site with the plugin active is reachable locally, without any project-owned Docker image being built

#### Scenario: Pinned PHP version is not tied to an EOL Debian base
- **WHEN** `.wp-env.json`'s `phpVersion` is read (currently `8.3`, Debian Bookworm-based)
- **THEN** `apt-get update` succeeds unmodified inside the `wordpress`/`tests-wordpress` containers — no sources.list patching or archive-mirror workaround is needed for `bin/install-php-ldap-ext.sh` to run

### Requirement: Layered automated test suite
The project SHALL provide a PHPUnit suite (`phpunit.xml.dist`, one file, three testsuites: `unit`, `wp`, `ldap`) that can run any single layer independently and all layers together, using `yoast/phpunit-polyfills` via Composer for cross-version compatibility. `composer.json`'s `require-dev` PHPUnit version and `config.platform.php` SHALL both support the PHP version currently pinned in `.wp-env.json`, so Composer resolves the same runtime that actually executes the suite.

#### Scenario: Unit layer needs no WordPress or LDAP
- **WHEN** the `unit` testsuite runs (covering `ldap_ed_split_server_scheme()` and the Sodium encrypt/decrypt helpers)
- **THEN** it completes successfully without WordPress being loaded and without any LDAP connection available

#### Scenario: wp layer needs no live LDAP connection
- **WHEN** the `wp` testsuite runs (covering `LDAP_ED_Cache` fully, and `LDAP_ED_Ajax`'s nonce/`manage_options` guards) with `openldap-test` stopped
- **THEN** it exercises WordPress-dependent behavior (transients, options, nonces, capability checks) and passes regardless — `LDAP_ED_Ajax` has no dependency-injection seam for its connector, so AJAX paths that actually reach `LDAP_ED_Connector` (`test_connection`, `get_departments`) are covered by the `ldap` testsuite instead, against the real `openldap-test` container, rather than adding a mocking seam to plugin source for testability alone (verified: `docker stop openldap-test` then `--testsuite wp` still passes 12/12)

#### Scenario: ldap layer exercises the real connector
- **WHEN** the `ldap` testsuite runs (covering `LDAP_ED_Connector`)
- **THEN** it binds and searches against the running `openldap-test` container and asserts, among other things, that `get_departments()` never applies `excluded_departments` or `exclude_no_department` regardless of current settings, and that `exclude_no_department`/`excluded_departments` filtering itself behaves correctly — `exclude_disabled` is excluded from this testsuite (see the fixture scenario above)

#### Scenario: Composer platform config matches the pinned wp-env PHP version
- **WHEN** `composer install` runs (locally or in `bin/test.sh` step 4)
- **THEN** `composer.json`'s `config.platform.php` equals `.wp-env.json`'s `phpVersion` (currently `8.3`), and the resolved `phpunit/phpunit` version fully supports that PHP version — Composer never silently resolves dependencies against a stale PHP floor while the containers run a newer one
