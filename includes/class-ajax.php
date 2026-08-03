<?php
/**
 * AJAX handlers — test connection and clear cache.
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDAP_ED_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_ldap_ed_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_ldap_ed_clear_cache',     array( $this, 'clear_cache' ) );
		add_action( 'wp_ajax_ldap_ed_get_departments', array( $this, 'get_departments' ) );
	}

	/** AJAX: Test LDAP connection with current saved settings. */
	public function test_connection() {
		check_ajax_referer( 'ldap_ed_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'ldap-staff-directory' ) ), 403 );
		}

		$settings  = get_option( LDAP_ED_OPTION_KEY, array() );
		$connector = new LDAP_ED_Connector( $settings );
		$result    = $connector->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/** AJAX: Clear the users transient cache. */
	public function clear_cache() {
		check_ajax_referer( 'ldap_ed_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'ldap-staff-directory' ) ), 403 );
		}

		( new LDAP_ED_Cache() )->purge();

		wp_send_json_success( array( 'message' => __( 'Cache cleared successfully.', 'ldap-staff-directory' ) ) );
	}

	/**
	 * AJAX: Discover distinct department values (and their counts) currently present in LDAP.
	 * Never applies excluded_departments — this is the only way to re-discover an already
	 * excluded department in order to un-exclude it. Persists the result as a snapshot so
	 * the settings page can render the exclusion checklist without a live LDAP call.
	 */
	public function get_departments() {
		check_ajax_referer( 'ldap_ed_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'ldap-staff-directory' ) ), 403 );
		}

		$settings  = get_option( LDAP_ED_OPTION_KEY, array() );
		$connector = new LDAP_ED_Connector( $settings );
		$result    = $connector->get_departments();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_option( LDAP_ED_KNOWN_DEPARTMENTS_KEY, $result, false );

		wp_send_json_success( $result );
	}
}
