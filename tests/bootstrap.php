<?php
/**
 * PHPUnit bootstrap — shared by all three testsuites (unit/wp/ldap).
 *
 * Deliberately always loads the WordPress test suite, even for `unit` tests
 * that don't touch WordPress — see design.md, Decision 4 (single config,
 * no "run without Docker" optimization for a single-maintainer project).
 *
 * @package LDAP_Staff_Directory
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin before WordPress finishes bootstrapping the test install.
 */
function ldap_ed_tests_load_plugin() {
	require dirname( __DIR__ ) . '/ldap-staff-directory.php';
}
tests_add_filter( 'muplugins_loaded', 'ldap_ed_tests_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
