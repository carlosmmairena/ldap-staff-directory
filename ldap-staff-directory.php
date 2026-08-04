<?php
/**
 * Plugin Name: LDAP Staff Directory
 * Plugin URI:  https://wordpress.org/plugins/ldap-staff-directory/
 * Description: Connects to LDAP or LDAPS to display an employee directory from an OU. Supports Elementor, Beaver Builder and a native shortcode.
 * Version:     1.2.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:      Carlos Mairena
 * Author URI:  https://carlosmmairena.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ldap-staff-directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'LDAP_ED_VERSION',     '1.2.1' );
define( 'LDAP_ED_FILE',        __FILE__ );
define( 'LDAP_ED_DIR',         plugin_dir_path( __FILE__ ) );
define( 'LDAP_ED_URL',         plugin_dir_url( __FILE__ ) );
define( 'LDAP_ED_OPTION_KEY',  'ldap_ed_settings' );
define( 'LDAP_ED_CACHE_KEY',   'ldap_ed_users' );
define( 'LDAP_ED_STALE_KEY',   'ldap_ed_users_stale' );
define( 'LDAP_ED_KNOWN_DEPARTMENTS_KEY', 'ldap_ed_known_departments' );

// -------------------------------------------------------------------------
// Sodium crypto helpers — bind password encryption (added 1.0.4)
// -------------------------------------------------------------------------

/**
 * Derives a 32-byte Sodium secretbox key from WordPress's AUTH/SECURE_AUTH salts.
 *
 * The 'ldap-ed-v1' BLAKE2b sub-key provides domain separation so this derivation
 * is unique to this plugin even if another plugin uses the same WP salts.
 *
 * WARNING: Rotating WordPress security keys (wp-config.php) changes this key.
 * ldap_ed_salts_have_changed() detects this before ldap_bind() fails silently.
 *
 * @return string 32-byte binary key.
 */
function ldap_ed_derive_sodium_key(): string {
	// Prefix a plugin-specific domain label to the message for key separation.
	// BLAKE2b key parameter must be 16–64 bytes or empty; using empty (unkeyed)
	// with a domain-prefixed message is the standard alternative.
	return sodium_crypto_generichash(
		'ldap-staff-directory:v1:' . wp_salt( 'auth' ) . wp_salt( 'secure_auth' ),
		'',
		SODIUM_CRYPTO_SECRETBOX_KEYBYTES
	);
}

/**
 * Encrypts the LDAP bind password using XSalsa20-Poly1305.
 *
 * Stores a SHA-256 fingerprint of the current AUTH_KEY alongside the ciphertext
 * so salt rotation can be detected before ldap_bind() fails.
 *
 * @param string $plain Plaintext password.
 * @return string Encoded string prefixed with 'sod::'.
 */
function ldap_ed_encrypt_pass( string $plain ): string {
	$key   = ldap_ed_derive_sodium_key();
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$enc   = sodium_crypto_secretbox( $plain, $nonce, $key );
	if ( function_exists( 'sodium_memzero' ) ) {
		sodium_memzero( $key );
	}
	update_option( 'ldap_ed_salt_fingerprint', hash( 'sha256', wp_salt( 'auth' ) ), false );
	return 'sod::' . base64_encode( $nonce . $enc );
}

/**
 * Decrypts the LDAP bind password.
 *
 * Values without the 'sod::' prefix are returned as-is (legacy plaintext migration path).
 * Returns '' when MAC verification fails (caused by WP salt rotation).
 *
 * @param string $stored Value from wp_options.
 * @return string Decrypted plaintext password, or '' on authentication failure.
 */
function ldap_ed_decrypt_pass( string $stored ): string {
	if ( 0 !== strncmp( $stored, 'sod::', 5 ) ) {
		return $stored; // Legacy plaintext — transparent backwards-compatible migration.
	}
	$key   = ldap_ed_derive_sodium_key();
	$data  = base64_decode( substr( $stored, 5 ) );
	$nonce = substr( $data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$plain = sodium_crypto_secretbox_open( substr( $data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );
	if ( function_exists( 'sodium_memzero' ) ) {
		sodium_memzero( $key );
	}
	return false === $plain ? '' : $plain;
}

/**
 * Returns true when WordPress salts have been regenerated since the last password save.
 *
 * Compares the stored SHA-256 fingerprint of AUTH_KEY against the current value.
 * Used by the admin notice and by ldap_bind() guard to surface a clear error.
 *
 * @return bool
 */
function ldap_ed_salts_have_changed(): bool {
	$stored = get_option( 'ldap_ed_salt_fingerprint', '' );
	return '' !== $stored && ! hash_equals( $stored, hash( 'sha256', wp_salt( 'auth' ) ) );
}

/**
 * Splits a possibly scheme-prefixed LDAP server value into its scheme and domain parts.
 *
 * Legacy installs store `server` with the scheme embedded (e.g. "ldaps://host.com").
 * Strips a recognized ldap://, ldaps://, http://, or https:// prefix so callers always
 * get a clean domain. The returned scheme is null unless the prefix was ldap or ldaps —
 * this lets callers infer a default scheme from a legacy value without silently flipping
 * a working ldap:// install to ldaps just because an http(s) URL was pasted by mistake.
 *
 * @param string $raw_server Raw stored/submitted server value.
 * @return array{scheme:string|null,domain:string}
 */
function ldap_ed_split_server_scheme( string $raw_server ): array {
	$value = trim( $raw_server );

	if ( preg_match( '#^(ldaps?|https?)://#i', $value, $matches ) ) {
		$prefix = strtolower( $matches[1] );
		return array(
			'scheme' => in_array( $prefix, array( 'ldap', 'ldaps' ), true ) ? $prefix : null,
			'domain' => substr( $value, strlen( $matches[0] ) ),
		);
	}

	return array(
		'scheme' => null,
		'domain' => $value,
	);
}

/**
 * Sanitizes the LDAP connection fields (scheme, server, port, bind DN/password, base OU,
 * SSL options) from a raw settings input array, applying the same rules whether the caller
 * is persisting a real save (LDAP_ED_Admin::sanitize_settings()) or previewing an unsaved
 * "Test Connection" click (LDAP_ED_Ajax::test_connection()) — keeping both paths in sync.
 *
 * Returned in the same shape stored in `ldap_ed_settings` (bind_pass encrypted), since
 * LDAP_ED_Connector always decrypts internally at bind() time regardless of caller.
 *
 * @param array $input    Raw input, keyed like `ldap_ed_settings` (e.g. $_POST['ldap_ed_settings']).
 * @param array $existing Currently saved `ldap_ed_settings` option, used for fallbacks.
 * @return array{scheme:string,server:string,port:int,bind_dn:string,bind_pass:string,base_ou:string,verify_ssl:string,ca_cert:string}
 */
function ldap_ed_sanitize_connection_fields( array $input, array $existing ): array {
	$clean = array();

	$allowed_schemes = array( 'ldap', 'ldaps' );
	$raw_scheme      = sanitize_text_field( $input['scheme'] ?? 'ldaps' );
	$clean['scheme'] = in_array( $raw_scheme, $allowed_schemes, true ) ? $raw_scheme : 'ldaps';

	// Server is domain-only — strip any scheme prefix pasted out of habit.
	$server_split    = ldap_ed_split_server_scheme( $input['server'] ?? '' );
	$clean['server'] = sanitize_text_field( $server_split['domain'] );

	// An empty port means "use the default for the chosen scheme".
	$raw_port      = trim( (string) ( $input['port'] ?? '' ) );
	$default_port  = 'ldap' === $clean['scheme'] ? 389 : 636;
	$clean['port'] = '' === $raw_port ? $default_port : absint( $raw_port );

	$clean['bind_dn']    = sanitize_text_field( $input['bind_dn'] ?? '' );
	$clean['base_ou']    = sanitize_text_field( $input['base_ou'] ?? '' );
	$clean['verify_ssl'] = isset( $input['verify_ssl'] ) ? '1' : '0';
	$clean['ca_cert']    = sanitize_text_field( $input['ca_cert'] ?? '' );

	// Only replace the password if a new one was supplied — encrypt it at rest.
	// A blank submission (the field is never pre-filled) means "keep the existing value".
	$plain_pass         = ! empty( $input['bind_pass'] ) ? $input['bind_pass'] : '';
	$clean['bind_pass'] = '' !== $plain_pass
		? ldap_ed_encrypt_pass( $plain_pass )
		: ( $existing['bind_pass'] ?? '' );

	return $clean;
}

/**
 * Flush rewrite rules on deactivation (nothing registered currently, kept for future use).
 */
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Autoload plugin classes.
 *
 * @param string $class Class name.
 */
function ldap_ed_autoload( $class ) {
	$map = array(
		'LDAP_ED_Connector'         => LDAP_ED_DIR . 'includes/class-ldap-connector.php',
		'LDAP_ED_Cache'             => LDAP_ED_DIR . 'includes/class-cache.php',
		'LDAP_ED_Admin'             => LDAP_ED_DIR . 'includes/class-admin.php',
		'LDAP_ED_Ajax'              => LDAP_ED_DIR . 'includes/class-ajax.php',
		'LDAP_ED_Shortcode'         => LDAP_ED_DIR . 'includes/class-shortcode.php',
		'LDAP_ED_Elementor_Widget'  => LDAP_ED_DIR . 'elementor/class-elementor-widget.php',
		'LDAP_ED_BB_Module'         => LDAP_ED_DIR . 'beaver-builder/class-bb-module.php',
	);

	if ( isset( $map[ $class ] ) && file_exists( $map[ $class ] ) ) {
		require_once $map[ $class ];
	}
}
spl_autoload_register( 'ldap_ed_autoload' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function ldap_ed_init() {
	// Core classes.
	new LDAP_ED_Admin();
	new LDAP_ED_Ajax();
	new LDAP_ED_Shortcode();

	// Page builder integrations (only when builders are active).
	if ( did_action( 'elementor/loaded' ) ) {
		add_action( 'elementor/widgets/register', 'ldap_ed_register_elementor_widget' );
	}

	if ( class_exists( 'FLBuilder' ) ) {
		add_action( 'init', 'ldap_ed_register_bb_module', 20 );
	}
}
add_action( 'plugins_loaded', 'ldap_ed_init' );

/**
 * Register the Elementor widget.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function ldap_ed_register_elementor_widget( $widgets_manager ) {
	require_once LDAP_ED_DIR . 'elementor/class-elementor-widget.php';
	$widgets_manager->register( new LDAP_ED_Elementor_Widget() );
}

/**
 * Register the Beaver Builder module.
 */
function ldap_ed_register_bb_module() {
	require_once LDAP_ED_DIR . 'beaver-builder/class-bb-module.php';
}
