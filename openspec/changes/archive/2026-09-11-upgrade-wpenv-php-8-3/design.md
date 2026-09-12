## Context

`.wp-env.json` pins `phpVersion: "8.3"` → currently `"7.4"`. PHP 7.4 has been EOL since Nov 2022, so Docker Hub's `wordpress:php7.4-apache` image was never rebuilt after that and is frozen on whatever Debian release was current then: Bullseye. Bullseye's LTS window closed 2026-08-31, so `deb.debian.org` no longer serves it — `apt-get update` inside `wordpress`/`tests-wordpress` now 404s, breaking `bin/install-php-ldap-ext.sh`'s `afterStart` hook and therefore every `wp-env start`.

This was scoped in `openspec/changes/archive/2026-08-28-testing-infrastructure/` (see its design.md, Decisions 2/5/6), which fixed `phpVersion: "7.4"` and built `bin/test.sh`, `bin/install-php-ldap-ext.sh`, `bin/ldap-test-env.sh` around it. This change doesn't touch that architecture — it only moves the pinned PHP version to one with an actively-maintained Docker image, and brings the Composer toolchain along with it.

A tactical alternative (patch `sources.list` to `archive.debian.org` inside the Bullseye container) was explored and rejected: it keeps the test environment tied to a PHP version that's been EOL for four years and only delays the same class of problem.

## Goals / Non-Goals

**Goals:**
- Unbreak `wp-env start` / `bin/test.sh` / CI by moving off an EOL Debian base.
- Keep the toolchain (Composer, PHPUnit) aligned with whatever PHP version wp-env actually runs, so this doesn't quietly drift again.
- Land a PHP version whose Docker image gets rebuilt on OS security updates for the foreseeable future (Bookworm, supported to ~2028).

**Non-Goals:**
- Raising the plugin's declared minimum PHP version (`Requires PHP: 7.4` / `composer.json` `"php": ">=7.4"` stay). This change only affects what the *test environment* runs, not what the plugin claims to support.
- Testing a matrix of PHP versions in CI (still a single pinned version, just a newer one).
- Any change to `bin/install-php-ldap-ext.sh`'s logic — its Debian branch is OS-version-agnostic; it just needs a non-EOL OS under it, which this change provides by construction.
- Auditing the full plugin for every PHP 7.4→8.3 deprecation as a formal task — see Risks below for what was already checked and what implementation should re-verify by running the suite.

## Decisions

**1. Target PHP 8.3, not 8.1/8.2/8.4.**
8.1 is the earliest version whose Docker image sits on Bookworm rather than Bullseye, so 8.1/8.2/8.3 are all viable on that axis. 8.3 is chosen because it's the latest version with an active, non-`RC` Docker Hub `-apache` image at the time of this change, giving the longest runway before this same problem recurs. 8.4 was considered but skipped for this change to avoid stacking a very recent PHP release on top of an already-overdue Debian bump — can be revisited in its own change later.

**2. `composer.json`'s `config.platform.php` moves in lockstep with `.wp-env.json`'s `phpVersion`.**
Alternative considered: drop `platform.php` entirely and let Composer detect the running PHP. Rejected — `composer install` for this dev-tooling `composer.json` can run outside any container (e.g. a maintainer's host PHP), and the explicit pin is what makes dependency resolution deterministic regardless of host PHP. Keeping it, just at the new value, preserves that determinism while fixing the drift the current setup has (`platform.php: 7.4` while wp-env silently could have run anything).

**3. Keep `phpunit/phpunit` at `^9.6` — do not bump it.**
Originally this deferred to implementation time: pick the highest major that resolves cleanly against `yoast/phpunit-polyfills`. That criterion turned out to be necessary but not sufficient. Trying `^11` (+ `yoast/phpunit-polyfills:^4.0`) resolved cleanly via Composer but broke `bin/test.sh`'s `wp`/`ldap` testsuites at runtime: WP core's own bundled test scaffolding (`/wordpress-phpunit/includes/abstract-testcase.php`, vendorized by wp-env from wordpress-develop — not code in this repo) calls `PHPUnit\Util\Test::parseTestMethodAnnotations()` inside `expectDeprecated()`, a method removed in PHPUnit 10+. This was verified against the latest WP core wp-env pulls (7.1), so it isn't a stale-WP-version problem — WP core simply doesn't support PHPUnit 10+ yet. `yoast/phpunit-polyfills` only bridges annotation-vs-attribute syntax in *our* test files; it doesn't patch WP core's scaffolding. The binding constraint is WP core's PHPUnit compatibility ceiling, not our own dependency graph, so `phpunit/phpunit` stays on `^9.6` (already resolving cleanly against `platform.php: 8.3` with no code changes needed) until WP core's test suite itself adds PHPUnit 10+ support — at which point this is a one-line follow-up, not a redesign.

**4. No plugin source changes planned up front.**
A grep for the two most common 7.4→8.3 break patterns (`is_resource()` on LDAP handles, made obsolete by PHP 8.1's LDAP objects; undeclared dynamic properties, deprecated in 8.2) found no hits in `includes/`/`admin/`. Rather than a speculative audit, the actual PHPUnit `ldap`/`wp`/`unit` suites running clean under PHP 8.3 is treated as the verification — if something surfaces, it's a small follow-up fix, not a redesign.

## Risks / Trade-offs

- **[Risk]** A currently-untested PHP 8.x deprecation or behavior change surfaces at runtime (e.g. in code paths the PHPUnit suites don't cover, like Elementor/Beaver Builder integration). → **Mitigation**: run `bin/test.sh` end-to-end plus a manual smoke test of the shortcode and both page-builder integrations against the new `wp-env` before merging; the existing `wordpress`/`cli`/`mysql` QA-visual containers (see `local-testing-environment` spec) already exist for exactly this.
- **[Risk]** `phpunit/phpunit ^10`/`^11` drops PHPUnit features/assertions the existing test files use, requiring test-file edits beyond `composer.json`. → **Mitigation**: `yoast/phpunit-polyfills` exists specifically to absorb this; if it doesn't fully cover it, treat any needed test-file edits as in-scope implementation work, not a blocker to redesign.
- **[Risk]** Docker Hub eventually stops publishing `wordpress:php8.3-apache` too (once 8.3 itself goes EOL, ~Nov 2027) and this recurs. → **Mitigation**: out of scope to solve permanently here; the `local-testing-environment` spec delta in this change adds an explicit requirement ("pinned PHP version must not be EOL-image-tied") so the next occurrence is a spec violation to fix, not a surprise.
- **[Trade-off]** Losing 7.4-specific test coverage: nothing exercises the plugin's actual declared floor (PHP 7.4) anymore once wp-env moves to 8.3. → Accepted for this change; a PHP version matrix in CI is called out as a Non-Goal and can be a separate future change if the floor needs active regression coverage.

## Migration Plan

1. Update `.wp-env.json` (`phpVersion`) and `composer.json` (`config.platform.php`, `phpunit/phpunit`) together in one commit — they're coupled, splitting them would leave an inconsistent intermediate state.
2. Delete `vendor/` and `composer.lock`, run `composer install` fresh against the new platform pin, regenerating `composer.lock`.
3. `wp-env destroy` any existing containers built from the old `phpVersion` before `wp-env start` — stale containers/images from `7.4` won't auto-upgrade in place.
4. Run `bin/test.sh` end-to-end; fix any surfaced PHPUnit incompatibilities as they appear.
5. Manual smoke test via the QA-visual `wordpress`/`cli`/`mysql` containers (shortcode + Elementor + Beaver Builder).
6. **Rollback**: revert the single commit from step 1 and repeat steps 2–3 — there's no data migration involved, only container/toolchain version pins.

## Open Questions

- Should `phpunit/phpunit` land on `^10` or `^11`? Deferred to implementation time per Decision 3 above (depends on `yoast/phpunit-polyfills`' compatibility matrix then).
- Is a CI-only PHP version matrix (testing both the 7.4 floor and 8.3) worth a follow-up change, given the plugin's declared minimum no longer matches what's exercised in CI?
