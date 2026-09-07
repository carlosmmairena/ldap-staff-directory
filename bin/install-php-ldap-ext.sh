#!/usr/bin/env bash
# Installs the PHP `ldap` extension into wp-env's containers — none of the
# base images wp-env uses ship it, but LDAP_ED_Connector calls
# ldap_connect()/ldap_bind()/etc. directly.
# Run automatically via .wp-env.json's lifecycleScripts.afterStart.
# Idempotent: skips a container that already has the extension loaded.
#
# wordpress/tests-wordpress (the official `wordpress:*-apache` image) are
# Debian; cli/tests-cli (wp-env's own image) are Alpine — different package
# managers, handled per-container below.
set -euo pipefail

# Container names are "<project>-<role>-1". "wordpress"/"cli" are substrings of
# "tests-wordpress"/"tests-cli", so a loose "*-wordpress-1$" match is ambiguous —
# anchor on the unambiguous tests-cli container to recover the exact project prefix.
TESTS_CLI="$(docker ps --format '{{.Names}}' | grep -m1 -E -- '-tests-cli-1$' || true)"
if [ -z "$TESTS_CLI" ]; then
	echo "[install-php-ldap-ext] ✗ no running wp-env containers found — run 'wp-env start' first" >&2
	exit 1
fi
PROJECT="${TESTS_CLI%-tests-cli-1}"

for role in wordpress tests-wordpress cli tests-cli; do
	CONTAINER="${PROJECT}-${role}-1"
	if ! docker ps --format '{{.Names}}' | grep -qFx "$CONTAINER"; then
		echo "[install-php-ldap-ext] skipping '$role' — no running container found"
		continue
	fi

	if docker exec "$CONTAINER" php -m 2>/dev/null | grep -qi '^ldap$'; then
		echo "[install-php-ldap-ext] ✓ $CONTAINER already has ldap"
		continue
	fi

	echo "[install-php-ldap-ext] installing ldap extension in $CONTAINER..."
	if docker exec "$CONTAINER" sh -c 'command -v apk' >/dev/null 2>&1; then
		docker exec -u root "$CONTAINER" sh -c \
			"apk add --no-cache openldap-dev >/dev/null && docker-php-ext-install ldap >/dev/null"
	else
		docker exec -u root "$CONTAINER" sh -c \
			"apt-get update -qq >/dev/null && apt-get install -y -qq libldap2-dev >/dev/null && docker-php-ext-install ldap >/dev/null"
	fi

	case "$role" in
	wordpress | tests-wordpress)
		# Apache (mod_php) only reads the extension list at process start.
		# cli/tests-cli don't need this — each `wp-env run` is a fresh `docker exec`.
		echo "[install-php-ldap-ext] restarting $CONTAINER to load it..."
		docker restart "$CONTAINER" >/dev/null
		;;
	esac

	echo "[install-php-ldap-ext] ✓ $CONTAINER ready"
done
