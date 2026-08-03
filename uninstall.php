<?php
/**
 * Runs when the plugin is uninstalled (deleted from WordPress admin).
 * Removes all plugin options and transients.
 *
 * @package LDAP_Staff_Directory
 */

// WordPress confirms uninstall context before calling this file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove settings.
delete_option( 'ldap_ed_settings' );

// Remove the users cache transient and stale fallback option.
delete_transient( 'ldap_ed_users' );
delete_option( 'ldap_ed_users_stale' );

// Remove the encryption salt fingerprint (added in 1.0.4).
delete_option( 'ldap_ed_salt_fingerprint' );

// Remove the known-departments discovery snapshot.
delete_option( 'ldap_ed_known_departments' );

// Multisite: remove per-site options.
if ( is_multisite() ) {
	$ldap_ed_sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $ldap_ed_sites as $ldap_ed_site_id ) {
		switch_to_blog( $ldap_ed_site_id );
		delete_option( 'ldap_ed_settings' );
		delete_transient( 'ldap_ed_users' );
		delete_option( 'ldap_ed_users_stale' );
		delete_option( 'ldap_ed_salt_fingerprint' );
		delete_option( 'ldap_ed_known_departments' );
		restore_current_blog();
	}
}
