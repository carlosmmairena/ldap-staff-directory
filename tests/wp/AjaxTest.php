<?php
/**
 * LDAP_ED_Ajax: nonce + capability guards only — these fail before ever
 * instantiating LDAP_ED_Connector, so no LDAP connection is needed here.
 * See design.md, Decision 8, for why the connector-dependent paths
 * (test_connection, get_departments happy path) live in tests/ldap instead.
 *
 * @package LDAP_Staff_Directory
 */

class AjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * @dataProvider provide_actions
	 */
	public function test_rejects_request_without_a_valid_nonce( string $action ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['nonce'] = 'not-a-valid-nonce';

		try {
			$this->_handleAjax( $action );
			$this->fail( 'Expected wp_die() to short-circuit the handler.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertStringContainsString( '-1', (string) $e->getMessage() );
		}
	}

	/**
	 * @dataProvider provide_actions
	 */
	public function test_rejects_user_without_manage_options( string $action ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['nonce'] = wp_create_nonce( 'ldap_ed_admin_nonce' );

		try {
			$this->_handleAjax( $action );
			$this->fail( 'Expected wp_send_json_error() to short-circuit the handler.' );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
		}
	}

	public function provide_actions(): array {
		return array(
			'test_connection' => array( 'ldap_ed_test_connection' ),
			'clear_cache'     => array( 'ldap_ed_clear_cache' ),
			'get_departments' => array( 'ldap_ed_get_departments' ),
		);
	}

	public function test_clear_cache_purges_with_a_valid_admin_request() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['nonce'] = wp_create_nonce( 'ldap_ed_admin_nonce' );

		( new LDAP_ED_Cache() )->set( array( 'seed' => true ) );

		try {
			$this->_handleAjax( 'ldap_ed_clear_cache' );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertTrue( $response['success'] );
		}

		$this->assertFalse( ( new LDAP_ED_Cache() )->get() );
	}
}
