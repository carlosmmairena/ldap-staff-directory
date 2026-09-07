## ADDED Requirements

### Requirement: Local WordPress environment via wp-env
The project SHALL provide a local WordPress environment managed by `wp-env` (`.wp-env.json`), with no custom `Dockerfile` or `docker-compose.yml`, for manual visual QA of the plugin's shortcode, Elementor widget, and Beaver Builder module.

#### Scenario: Starting the local environment
- **WHEN** a developer runs `wp-env start` from the plugin root
- **THEN** a WordPress site with the plugin active is reachable locally, without any project-owned Docker image being built

### Requirement: PHP ldap extension available in wp-env containers
Since none of wp-env's base images ship the PHP `ldap` extension that `LDAP_ED_Connector` depends on directly, the project SHALL provision it automatically into every wp-env container (`wordpress`, `tests-wordpress`, `cli`, `tests-cli`) whenever `wp-env start` runs, with no manual step required.

#### Scenario: Fresh wp-env start ends with a working extension everywhere
- **WHEN** `wp-env start` completes (including its `afterStart` lifecycle script)
- **THEN** `php -m` reports `ldap` loaded in all four containers, and a PHP `ldap_bind()` call against `openldap-test` succeeds from any of them

#### Scenario: Re-running is a no-op when already installed
- **WHEN** the extension is already present in a container
- **THEN** provisioning skips reinstalling and restarting that container

### Requirement: Disposable OpenLDAP test directory
The project SHALL provide a disposable OpenLDAP container (`openldap-test`), started via `docker run` (not docker-compose), seeded from a version-controlled LDIF fixture file (`tests/fixtures/directory.ldif`), and attached to the Docker network created by `wp-env` so WordPress containers can reach it by hostname.

#### Scenario: Seeding is deterministic
- **WHEN** `openldap-test` is (re)started
- **THEN** it loads its dataset exclusively from `tests/fixtures/directory.ldif`, producing the same directory contents every time, with no dependency on the maintainer's real LDAP server

#### Scenario: Fixture covers connector edge cases
- **WHEN** `tests/fixtures/directory.ldif` is authored
- **THEN** it SHALL include at least one entry with no `department` attribute, entries spanning multiple distinct `department` values, and more than 500 person entries in total, so that `exclude_no_department`, `excluded_departments`, and LDAP paged-search (RFC 2696) behavior are each exercisable

#### Scenario: exclude_disabled is out of scope for this fixture
- **WHEN** `build_person_filter()` applies `exclude_disabled` (`(!(userAccountControl:1.2.840.113556.1.4.803:=2))`)
- **THEN** this SHALL NOT be exercised against `openldap-test`, because that matching rule is Active-Directory-specific and unsupported by standard OpenLDAP; it remains a manual verification against the real LDAP/AD server

### Requirement: Layered automated test suite
The project SHALL provide a PHPUnit suite (`phpunit.xml.dist`, one file, three testsuites: `unit`, `wp`, `ldap`) that can run any single layer independently and all layers together, using `yoast/phpunit-polyfills` via Composer for cross-version compatibility.

#### Scenario: Unit layer needs no WordPress or LDAP
- **WHEN** the `unit` testsuite runs (covering `ldap_ed_split_server_scheme()` and the Sodium encrypt/decrypt helpers)
- **THEN** it completes successfully without WordPress being loaded and without any LDAP connection available

#### Scenario: wp layer needs no live LDAP connection
- **WHEN** the `wp` testsuite runs (covering `LDAP_ED_Cache` fully, and `LDAP_ED_Ajax`'s nonce/`manage_options` guards) with `openldap-test` stopped
- **THEN** it exercises WordPress-dependent behavior (transients, options, nonces, capability checks) and passes regardless — `LDAP_ED_Ajax` has no dependency-injection seam for its connector, so AJAX paths that actually reach `LDAP_ED_Connector` (`test_connection`, `get_departments`) are covered by the `ldap` testsuite instead, against the real `openldap-test` container, rather than adding a mocking seam to plugin source for testability alone (verified: `docker stop openldap-test` then `--testsuite wp` still passes 12/12)

#### Scenario: ldap layer exercises the real connector
- **WHEN** the `ldap` testsuite runs (covering `LDAP_ED_Connector`)
- **THEN** it binds and searches against the running `openldap-test` container and asserts, among other things, that `get_departments()` never applies `excluded_departments` or `exclude_no_department` regardless of current settings, and that `exclude_no_department`/`excluded_departments` filtering itself behaves correctly — `exclude_disabled` is excluded from this testsuite (see the fixture scenario above)

### Requirement: Sequential test wizard
The project SHALL provide a single entry-point script (`bin/test.sh`) that runs exactly four steps in fixed order — Docker availability, `wp-env start`, `openldap-test` up and seeded, PHPUnit — reporting pass/fail per step, and SHALL stop at the first failing step without prompting the user for input.

#### Scenario: All steps pass
- **WHEN** a developer runs `bin/test.sh` with Docker running and no prior state
- **THEN** all four steps report success in order and the script exits with status 0

#### Scenario: A step fails
- **WHEN** any step fails (verified by forcing step 3: temporarily renaming `bin/ldap-test-env.sh` away)
- **THEN** `bin/test.sh` prints which step failed, does not attempt subsequent steps (confirmed: PHPUnit never ran), does not prompt for input, exits with a non-zero status (confirmed: exit code 1), and the cleanup trap still removes the `tests-*` trio (confirmed) — teardown isn't conditional on success

#### Scenario: Re-running is idempotent
- **WHEN** `bin/test.sh` is run again while `openldap-test` is already running from a previous invocation
- **THEN** the script reuses or restarts the existing container deterministically instead of failing on a name conflict, and without asking the user anything

#### Scenario: PHPUnit-only containers are removed on exit, everything else persists
- **WHEN** `bin/test.sh` exits, for any reason (all steps passed, a step failed, or the script was interrupted)
- **THEN** wp-env's `tests-wordpress`/`tests-cli`/`tests-mysql` containers are removed; `openldap-test` and `wordpress`/`cli`/`mysql` (the QA-visual environment) are left running, reused by the next invocation

#### Scenario: Removing the QA-visual environment is offered, never automatic
- **WHEN** `bin/test.sh` exits on an interactive terminal (`stdin`/`stdout` both a TTY)
- **THEN** the script asks once whether to also remove `wordpress`/`cli`/`mysql`, and removes them only on an affirmative answer (`y`/`yes`/`s`/`si`/`sí`, case-insensitive) — this is the one exception to the wizard's no-prompts rule, scoped to this single question

#### Scenario: The prompt never blocks a non-interactive run
- **WHEN** `bin/test.sh` runs without a TTY (CI, or any piped/redirected invocation)
- **THEN** it skips the QA-visual removal question entirely and leaves `wordpress`/`cli`/`mysql` as they are, without waiting on input

### Requirement: CI parity
The `phpunit` CI job SHALL invoke `bin/test.sh` directly, so that the sequence executed in GitHub Actions is identical to the sequence a developer runs locally, alongside the existing `lint` job (`plugin-check-action`).

#### Scenario: Local and CI run the same steps
- **WHEN** `bin/test.sh` runs in a GitHub Actions job versus on a developer's machine
- **THEN** both executions perform the same four steps in the same order, with no CI-specific branching in the script's control flow
