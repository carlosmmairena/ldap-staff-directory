<?php
/**
 * Tests for the Sodium bind-password encrypt/decrypt helpers.
 *
 * These call wp_salt()/update_option() internally, so WordPress must be loaded —
 * but they touch no LDAP connection, hence "unit" not "wp".
 *
 * @package LDAP_Staff_Directory
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class CryptoTest extends TestCase {

	public function test_encrypt_then_decrypt_round_trips() {
		$plain = 'correct horse battery staple';

		$encrypted = ldap_ed_encrypt_pass( $plain );

		$this->assertStringStartsWith( 'sod::', $encrypted );
		$this->assertNotSame( $plain, $encrypted );
		$this->assertSame( $plain, ldap_ed_decrypt_pass( $encrypted ) );
	}

	public function test_legacy_plaintext_passes_through_unchanged() {
		// Values without the 'sod::' prefix are the pre-1.0.4 plaintext migration path.
		$this->assertSame( 'legacy-plaintext-pass', ldap_ed_decrypt_pass( 'legacy-plaintext-pass' ) );
	}

	public function test_decrypt_returns_empty_string_when_mac_verification_fails() {
		// Well-formed 'sod::' prefix, but payload doesn't decrypt under the current key
		// (mirrors what a WordPress salt rotation looks like from the connector's view).
		$corrupted = 'sod::' . base64_encode( str_repeat( "\0", 40 ) );

		$this->assertSame( '', ldap_ed_decrypt_pass( $corrupted ) );
	}

	public function test_each_encryption_uses_a_fresh_nonce() {
		$plain = 'same-password-twice';

		$first  = ldap_ed_encrypt_pass( $plain );
		$second = ldap_ed_encrypt_pass( $plain );

		$this->assertNotSame( $first, $second, 'Ciphertext must differ across calls even for the same plaintext.' );
		$this->assertSame( $plain, ldap_ed_decrypt_pass( $first ) );
		$this->assertSame( $plain, ldap_ed_decrypt_pass( $second ) );
	}
}
