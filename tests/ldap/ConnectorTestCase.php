<?php
/**
 * Shared setup for LDAP_ED_Connector tests running against the real
 * openldap-test container (bin/ldap-test-env.sh) — not picked up by PHPUnit
 * directly, since it doesn't match the "*Test.php" suffix.
 *
 * @package LDAP_Staff_Directory
 */

class ConnectorTestCase extends WP_UnitTestCase {

	protected function make_connector( array $overrides = array() ): LDAP_ED_Connector {
		$settings = array_merge(
			array(
				'server'     => 'openldap-test',
				'scheme'     => 'ldap',
				'port'       => 389,
				'bind_dn'    => 'cn=admin,dc=example,dc=test',
				'bind_pass'  => 'admin-test-only', // plaintext — ldap_ed_decrypt_pass() passes it through unchanged.
				'base_ou'    => 'ou=people,dc=example,dc=test',
				'verify_ssl' => '0',
			),
			$overrides
		);

		return new LDAP_ED_Connector( $settings );
	}
}
