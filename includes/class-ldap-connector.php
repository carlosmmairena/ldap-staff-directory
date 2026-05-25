<?php
/**
 * LDAP Connector — handles connection, binding, and user retrieval.
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDAP_ED_Connector {

	/** @var array Plugin settings. */
	private $settings;

	/** @var resource|false LDAP connection handle. */
	private $connection = false;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings (from get_option()).
	 */
	public function __construct( array $settings = array() ) {
		$defaults = array(
			'server'            => '',
			'port'              => 636,
			'bind_dn'           => '',
			'bind_pass'         => '',
			'base_ou'           => '',
			'verify_ssl'        => '1',
			'ca_cert'           => '',
			'fields'            => array( 'name', 'email', 'title', 'department' ),
			'exclude_disabled'  => '0',
		);

		$this->settings = wp_parse_args( $settings, $defaults );
	}

	/**
	 * Open the LDAP connection.
	 *
	 * @return true|\WP_Error
	 */
	public function connect() {
		$server = sanitize_text_field( $this->settings['server'] );
		$port   = absint( $this->settings['port'] );

		if ( empty( $server ) ) {
			return new \WP_Error( 'ldap_no_server', __( 'LDAP server address is not configured.', 'ldap-staff-directory' ) );
		}

		// Disable SSL certificate verification when requested (self-signed certs).
		if ( empty( $this->settings['verify_ssl'] ) || '0' === (string) $this->settings['verify_ssl'] ) {
			// Must be set before ldap_connect().
			putenv( 'LDAPTLS_REQCERT=never' );
			if ( defined( 'LDAP_OPT_X_TLS_REQUIRE_CERT' ) ) {
				ldap_set_option( null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER );
			}
		} elseif ( ! empty( $this->settings['ca_cert'] ) && file_exists( $this->settings['ca_cert'] ) ) {
			if ( defined( 'LDAP_OPT_X_TLS_CACERTFILE' ) ) {
				ldap_set_option( null, LDAP_OPT_X_TLS_CACERTFILE, $this->settings['ca_cert'] );
			}
		}

		$this->connection = @ldap_connect( $server, $port ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $this->connection ) {
			return new \WP_Error( 'ldap_connect_failed', __( 'Could not create LDAP connection handle.', 'ldap-staff-directory' ) );
		}

		ldap_set_option( $this->connection, LDAP_OPT_PROTOCOL_VERSION, 3 );
		ldap_set_option( $this->connection, LDAP_OPT_REFERRALS, 0 );
		ldap_set_option( $this->connection, LDAP_OPT_NETWORK_TIMEOUT, 10 );

		return true;
	}

	/**
	 * Bind to the LDAP server.
	 *
	 * @return true|\WP_Error
	 */
	public function bind() {
		if ( ! $this->connection ) {
			$result = $this->connect();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$bind_dn   = sanitize_text_field( $this->settings['bind_dn'] );
		$bind_pass = ldap_ed_decrypt_pass( $this->settings['bind_pass'] );

		// Guard: empty bind_pass after decryption means salt rotation broke the stored value.
		if ( 0 === strncmp( $this->settings['bind_pass'], 'sod::', 5 ) && '' === $bind_pass ) {
			return new \WP_Error(
				'ldap_decrypt_failed',
				__( 'LDAP bind password could not be decrypted. WordPress security keys may have been regenerated — please re-enter the password in Settings → LDAP Staff Directory.', 'ldap-staff-directory' )
			);
		}

		$bound = @ldap_bind( $this->connection, $bind_dn, $bind_pass ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $bound ) {
			$error = ldap_error( $this->connection );
			return new \WP_Error(
				'ldap_bind_failed',
				/* translators: %s: LDAP error message */
				sprintf( __( 'LDAP bind failed: %s', 'ldap-staff-directory' ), $error )
			);
		}

		return true;
	}

	/**
	 * Search the LDAP directory and return an array of user data.
	 *
	 * Uses RFC 2696 paged results (LDAP_CONTROL_PAGEDRESULTS) to retrieve all
	 * records regardless of the server's MaxPageSize limit (AD default: 1000).
	 *
	 * @return array|\WP_Error
	 */
	public function get_users() {
		$bind_result = $this->bind();
		if ( is_wp_error( $bind_result ) ) {
			return $bind_result;
		}

		$base_ou = sanitize_text_field( $this->settings['base_ou'] );

		// Optionally exclude disabled Active Directory accounts (userAccountControl bit 1).
		if ( '1' === (string) $this->settings['exclude_disabled'] ) {
			$filter = '(&(objectClass=person)(mail=*)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
		} else {
			$filter = '(&(objectClass=person)(mail=*))';
		}

		$attributes = array( 'cn', 'displayname', 'mail', 'title', 'department', 'telephonenumber' );
		$users      = array();
		$cookie     = '';

		do {
			$ldap_controls = array(
				array(
					'oid'        => LDAP_CONTROL_PAGEDRESULTS,
					'iscritical' => false,
					'value'      => array(
						'size'   => 500,
						'cookie' => $cookie,
					),
				),
			);

			$result = @ldap_search( $this->connection, $base_ou, $filter, $attributes, 0, 0, 0, LDAP_DEREF_NEVER, $ldap_controls ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( ! $result ) {
				// On first-page failure return WP_Error (same as pre-pagination behavior).
				// On subsequent pages, stop and return users gathered so far.
				if ( empty( $users ) ) {
					$error = ldap_error( $this->connection );
					return new \WP_Error(
						'ldap_search_failed',
						/* translators: %s: LDAP error message */
						sprintf( __( 'LDAP search failed: %s', 'ldap-staff-directory' ), $error )
					);
				}
				break;
			}

			$entries = ldap_get_entries( $this->connection, $result );

			// Guard: stop if server returns an empty page (avoids infinite loop on broken cookies).
			if ( 0 === $entries['count'] ) {
				ldap_free_result( $result );
				break;
			}

			for ( $i = 0; $i < $entries['count']; $i++ ) {
				$entry   = $entries[ $i ];
				$users[] = array(
					'name'       => $this->get_entry_value( $entry, 'displayname' )
					                ?? $this->get_entry_value( $entry, 'cn' )
					                ?? '',
					'email'      => $this->get_entry_value( $entry, 'mail' ) ?? '',
					'title'      => $this->get_entry_value( $entry, 'title' ) ?? '',
					'department' => $this->get_entry_value( $entry, 'department' ) ?? '',
					'phone'      => $this->get_entry_value( $entry, 'telephonenumber' ) ?? '',
				);
			}

			$errcode           = null;
			$matcheddn         = null;
			$errmsg            = null;
			$referrals         = null;
			$response_controls = array();
			ldap_parse_result( $this->connection, $result, $errcode, $matcheddn, $errmsg, $referrals, $response_controls );
			$cookie = $response_controls[ LDAP_CONTROL_PAGEDRESULTS ]['value']['cookie'] ?? '';

			ldap_free_result( $result );
		} while ( '' !== $cookie );

		// Sort alphabetically by name.
		usort( $users, function ( $a, $b ) {
			return strcmp( $a['name'], $b['name'] );
		} );

		$this->disconnect();
		return $users;
	}

	/**
	 * Test the LDAP connection and return a result summary.
	 *
	 * @return array { success: bool, message: string, count?: int }
	 */
	public function test_connection() {
		$users = $this->get_users();

		if ( is_wp_error( $users ) ) {
			return array(
				'success' => false,
				'message' => $users->get_error_message(),
			);
		}

		return array(
			'success' => true,
			/* translators: %d: number of users found */
			'message' => sprintf( __( 'Connection successful. %d user(s) found.', 'ldap-staff-directory' ), count( $users ) ),
			'count'   => count( $users ),
		);
	}

	/**
	 * Safely extract the first value for an LDAP attribute.
	 *
	 * @param array  $entry     LDAP entry array.
	 * @param string $attribute Attribute name (lowercase).
	 * @return string|null
	 */
	private function get_entry_value( array $entry, string $attribute ) {
		if ( isset( $entry[ $attribute ][0] ) && '' !== $entry[ $attribute ][0] ) {
			return $entry[ $attribute ][0];
		}
		return null;
	}

	/**
	 * Close the LDAP connection.
	 */
	private function disconnect() {
		if ( $this->connection ) {
			// ldap_unbind() is the correct close function.
			@ldap_unbind( $this->connection ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->connection = false;
		}
	}
}
