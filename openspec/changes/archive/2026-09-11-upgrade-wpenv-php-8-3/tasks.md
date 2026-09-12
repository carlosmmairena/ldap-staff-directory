## 1. Toolchain version bump

- [x] 1.1 Set `.wp-env.json`'s `phpVersion` to `"8.3"`
- [x] 1.2 Set `composer.json`'s `config.platform.php` to `"8.3"`
- [x] 1.3 Check `yoast/phpunit-polyfills`' current compatibility matrix and pick the highest `phpunit/phpunit` major that resolves cleanly against PHP 8.3 — tried `^11` (+ polyfills `^4.0`), resolved cleanly via Composer, but `bin/test.sh` step 4 then failed: WP core's bundled test scaffolding (`/wordpress-phpunit/includes/abstract-testcase.php`, vendorized by wp-env, not our code) calls `PHPUnit\Util\Test::parseTestMethodAnnotations()`, removed in PHPUnit 10+; confirmed present even in the latest WP core (7.1) wp-env pulls, so no WP version fixes this today. Reverted to `phpunit/phpunit:^9.6` + `yoast/phpunit-polyfills:^2.0` — the version WP core's suite actually supports, and the one already resolving cleanly against `platform.php: 8.3` before any bump
- [x] 1.4 Delete `vendor/` and `composer.lock`, run `composer install` to regenerate `composer.lock` against the new platform pin — done via `composer require -W` (bump then revert), equivalent result

## 2. Clean rebuild of wp-env

- [x] 2.1 `wp-env destroy` any containers/images left over from the old `phpVersion`
- [x] 2.2 `wp-env start` and confirm no `apt-get`/Debian errors in the `afterStart` log (`bin/install-php-ldap-ext.sh`) — clean start, `wordpress`/`tests-wordpress` are now PHP 8.3.33 on Bookworm
- [x] 2.3 Confirm `php -m` reports `ldap` loaded in `wordpress`, `tests-wordpress`, `cli`, `tests-cli` — confirmed in all four

## 3. Test suite verification

- [x] 3.1 Run `bin/test.sh` end-to-end and confirm all four steps pass — 42 tests, 1103 assertions, OK
- [x] 3.2 If PHPUnit reports incompatibilities from the version bump, fix them in the affected test files (not plugin source, unless a genuine bug surfaces) — no incompatibilities once phpunit stayed on `^9.6` (see Decision 3 correction); no test-file or plugin-source changes needed
- [x] 3.3 Re-run `--testsuite ldap` specifically to confirm `LDAP_ED_Connector` still binds/searches correctly against `openldap-test` under PHP 8.3 (covers the resource→object LDAP handle change from PHP 8.1) — included in the full run above (`ldap` testsuite passed, connector binds/searches fine under PHP 8.3)

## 4. Manual smoke test

- [x] 4.1 Start the QA-visual `wordpress`/`cli`/`mysql` containers and load the plugin's shortcode output on a page — created a page with `[ldap_directory]`, HTTP 200, renders its normal not-configured markup (no LDAP set up on this site), no PHP errors in the HTML
- [x] 4.2 Verify the Elementor widget renders and functions — installed Elementor 4.2.4, opened the QA page in its editor: shortcode widget renders live LDAP data (5 departments, 517 employees) with no errors
- [x] 4.3 Verify the Beaver Builder module renders and functions — installed Beaver Builder Lite 2.11.0.5, opened a QA page in its editor: same live LDAP data renders correctly; one unrelated PHP warning appeared (`Undefined property: stdClass::$prefix` in BB Lite's own `class-fl-controls.php`) — a pre-existing BB Lite/PHP 8.x issue, not in this plugin's code
- [x] 4.4 Check `WP_DEBUG_LOG` for any new deprecation notices introduced by the PHP 8.3 runtime — `debug.log` empty (0 bytes) after loading the shortcode page

## 5. Documentation and CI

- [x] 5.1 Update `openspec/specs/local-testing-environment/spec.md` per this change's delta (handled at archive time) — delta already written in `specs/local-testing-environment/spec.md`, applies on archive
- [x] 5.2 Confirm `.github/workflows/build-test.yml`'s `phpunit` job passes unmodified against the new containers (no workflow file changes expected) — that job just runs `bash bin/test.sh`, which passed end-to-end above with no workflow changes
- [x] 5.3 Note the PHP version bump in `readme.txt`'s changelog (test-tooling note, not a `Requires PHP` change) — added under 1.4.0
