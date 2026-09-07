#!/usr/bin/env bash
# Regenerates the bulk block of tests/fixtures/directory.ldif (department=Operations,
# >500 entries to force RFC 2696 paged search in LDAP_ED_Connector::search_paged()).
#
# Usage: bin appended manually after the hand-authored edge-case entries — see
# tests/fixtures/directory.ldif's header comment for how it was assembled.
set -euo pipefail

COUNT="${1:-510}"

for i in $(seq -w 1 "$COUNT"); do
	cat <<-EOF
	dn: cn=Bulk User ${i},ou=people,dc=example,dc=test
	objectClass: inetOrgPerson
	objectClass: ldapEdTestPerson
	cn: Bulk User ${i}
	sn: User${i}
	displayName: Bulk User ${i}
	mail: bulk.user.${i}@example.test
	title: Staff Engineer
	department: Operations
	telephoneNumber: +1 555 1${i}

	EOF
done
