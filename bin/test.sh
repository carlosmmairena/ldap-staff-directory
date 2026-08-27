#!/usr/bin/env bash
# Sequential test wizard — 4 fixed steps, no prompts, stops at the first
# failure. Identical command in local dev and CI. The one exception is the
# cleanup prompt at the very end (interactive terminals only) asking whether
# to also tear down the QA-visual environment — see design.md, Decision 5.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Removes wp-env's tests-* trio (tests-wordpress/tests-cli/tests-mysql) on any
# exit, success or failure — that trio exists only for PHPUnit. openldap-test is
# NOT removed here: it's reused across runs. wordpress/cli/mysql (QA visual) are
# never removed automatically — only offered, interactively, at the very end.
cleanup() {
	echo ""
	echo "Limpiando entorno de testing (wp-env tests-*)..."

	local tests_cli project role
	tests_cli="$(docker ps -a --format '{{.Names}}' | grep -m1 -E -- '-tests-cli-1$' || true)"
	if [ -z "$tests_cli" ]; then
		echo "  (nada que limpiar)"
		return
	fi
	project="${tests_cli%-tests-cli-1}"
	for role in tests-wordpress tests-cli tests-mysql; do
		docker rm -f -v "${project}-${role}-1" >/dev/null 2>&1 || true
	done
	echo "  ✓ limpio (openldap-test se deja corriendo — se reusa entre corridas)"

	# CI (and any other non-interactive invocation) has no stdin to prompt on —
	# skip silently and leave wordpress/cli/mysql as they are.
	if [ ! -t 0 ] || [ ! -t 1 ]; then
		return
	fi

	local reply
	read -r -p $'\n¿Eliminar también wordpress/cli/mysql (entorno de QA visual)? [y/N] ' reply || return
	reply="$(printf '%s' "$reply" | tr '[:upper:]' '[:lower:]')"
	case "$reply" in
	y | yes | s | si | sí)
		for role in wordpress cli mysql; do
			docker rm -f -v "${project}-${role}-1" >/dev/null 2>&1 || true
		done
		echo "  ✓ entorno de QA visual eliminado"
		;;
	*)
		echo "  dejando wordpress/cli/mysql corriendo"
		;;
	esac
}
trap cleanup EXIT

step() { echo ""; echo "[$1/4] $2"; }
ok()   { echo "  ✓ $1"; }
fail() { echo "  ✗ $1" >&2; exit 1; }

step 1 "Docker disponible"
if ! docker info >/dev/null 2>&1; then
	fail "Docker no está corriendo — no se puede continuar"
fi
ok "Docker corriendo"

step 2 "wp-env"
if ! npx --yes @wordpress/env start; then
	fail "wp-env start falló"
fi
ok "wp-env arriba (incluye instalar la extensión ldap de PHP, ver bin/install-php-ldap-ext.sh)"

step 3 "openldap-test"
if ! "$ROOT_DIR/bin/ldap-test-env.sh"; then
	fail "openldap-test no quedó listo"
fi
ok "openldap-test listo"

step 4 "PHPUnit"
if [ ! -d "$ROOT_DIR/vendor" ]; then
	echo "  (vendor/ no existe, corriendo composer install...)"
	if ! npx --yes @wordpress/env run cli --env-cwd=wp-content/plugins/ldap-staff-directory -- composer install --no-interaction; then
		fail "composer install falló"
	fi
fi
if ! npx --yes @wordpress/env run tests-cli --env-cwd=wp-content/plugins/ldap-staff-directory -- vendor/bin/phpunit; then
	fail "PHPUnit reportó fallos"
fi
ok "PHPUnit OK"

echo ""
echo "✓ Todo pasó."
