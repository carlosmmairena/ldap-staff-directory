<?php
/**
 * Pure-logic tests for ldap_ed_split_server_scheme() — no LDAP, no WP options touched.
 *
 * @package LDAP_Staff_Directory
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class SplitServerSchemeTest extends TestCase {

	/**
	 * @dataProvider provide_servers
	 */
	public function test_split_server_scheme( string $raw, ?string $expected_scheme, string $expected_domain ) {
		$result = ldap_ed_split_server_scheme( $raw );

		$this->assertSame( $expected_scheme, $result['scheme'] );
		$this->assertSame( $expected_domain, $result['domain'] );
	}

	public function provide_servers(): array {
		return array(
			'bare domain, no scheme'  => array( 'dc1.example.test', null, 'dc1.example.test' ),
			'legacy ldaps:// prefix'  => array( 'ldaps://dc1.example.test', 'ldaps', 'dc1.example.test' ),
			'legacy ldap:// prefix'   => array( 'ldap://dc1.example.test', 'ldap', 'dc1.example.test' ),
			'uppercase scheme'        => array( 'LDAPS://dc1.example.test', 'ldaps', 'dc1.example.test' ),
			'non-ldap scheme ignored' => array( 'https://dc1.example.test', null, 'dc1.example.test' ),
			'surrounding whitespace'  => array( '  ldap://dc1.example.test  ', 'ldap', 'dc1.example.test' ),
			'empty string'            => array( '', null, '' ),
		);
	}
}
