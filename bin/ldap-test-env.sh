#!/usr/bin/env bash
# Starts (or reuses) the disposable openldap-test container, seeded from
# tests/fixtures/directory.ldif, attached directly to wp-env's own Docker
# network. Idempotent, no prompts — see openspec/changes/testing-infrastructure/
# design.md (Decisions 2 and 6) for why it's shaped this way.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTAINER_NAME="openldap-test"
IMAGE="osixia/openldap:1.5.0"
LDAP_DOMAIN="example.test"
LDAP_BASE_DN="dc=example,dc=test"
LDAP_ADMIN_PASSWORD="admin-test-only"

echo "[ldap-test-env] discovering wp-env's Docker network..."
# "*-wordpress-1$" would also match "*-tests-wordpress-1" (substring) — exclude it explicitly.
WP_CONTAINER="$(docker ps --format '{{.Names}}' | grep -E -- '-wordpress-1$' | grep -v -- '-tests-wordpress-1$' | head -1)"
if [ -z "$WP_CONTAINER" ]; then
	echo "[ldap-test-env] ✗ no running wp-env 'wordpress' container found — run 'wp-env start' first" >&2
	exit 1
fi
NETWORK="$(docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}' "$WP_CONTAINER")"
if [ -z "$NETWORK" ]; then
	echo "[ldap-test-env] ✗ could not determine wp-env's network from container '$WP_CONTAINER'" >&2
	exit 1
fi
echo "[ldap-test-env] using network: $NETWORK"

STATE="$(docker inspect -f '{{.State.Status}}' "$CONTAINER_NAME" 2>/dev/null || true)"

if [ "$STATE" = "running" ]; then
	echo "[ldap-test-env] ✓ $CONTAINER_NAME already running, reusing"
elif [ -n "$STATE" ]; then
	echo "[ldap-test-env] restarting existing (stopped) $CONTAINER_NAME..."
	docker start "$CONTAINER_NAME" >/dev/null
else
	echo "[ldap-test-env] creating $CONTAINER_NAME (seeded from tests/fixtures/directory.ldif)..."
	docker run -d \
		--name "$CONTAINER_NAME" \
		--network "$NETWORK" \
		-e LDAP_DOMAIN="$LDAP_DOMAIN" \
		-e LDAP_BASE_DN="$LDAP_BASE_DN" \
		-e LDAP_ADMIN_PASSWORD="$LDAP_ADMIN_PASSWORD" \
		-e LDAP_ORGANISATION="LDAP ED Test" \
		-e LDAP_TLS=false \
		-v "$ROOT_DIR/tests/fixtures/schema/ldap-ed-test.schema:/container/service/slapd/assets/config/bootstrap/schema/89-ldap-ed-test.schema" \
		-v "$ROOT_DIR/tests/fixtures/directory.ldif:/container/service/slapd/assets/config/bootstrap/ldif/50-directory.ldif" \
		"$IMAGE" \
		--copy-service >/dev/null
fi

echo "[ldap-test-env] waiting for slapd to accept binds..."
for _ in $(seq 1 30); do
	if docker exec "$CONTAINER_NAME" ldapwhoami -x -H ldap://localhost -D "cn=admin,$LDAP_BASE_DN" -w "$LDAP_ADMIN_PASSWORD" >/dev/null 2>&1; then
		echo "[ldap-test-env] ✓ ready ($CONTAINER_NAME on network $NETWORK, base $LDAP_BASE_DN)"
		exit 0
	fi
	sleep 1
done

echo "[ldap-test-env] ✗ slapd did not become ready in time" >&2
exit 1
