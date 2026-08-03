<?php
/**
 * Admin panel — settings page registration and rendering.
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDAP_ED_Admin {

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'add_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices',         array( $this, 'maybe_show_ldap_extension_notice' ) );
		add_action( 'admin_notices',         array( $this, 'maybe_show_salt_rotation_notice' ) );
	}

	/**
	 * Show an admin notice if the PHP LDAP extension is not loaded at runtime.
	 */
	public function maybe_show_ldap_extension_notice() {
		if ( extension_loaded( 'ldap' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'LDAP Staff Directory requires the PHP LDAP extension, which is not currently enabled on this server. The directory will not function until the extension is loaded.', 'ldap-staff-directory' )
		);
	}

	/** Add the settings sub-menu under "Settings". */
	public function add_menu() {
		add_options_page(
			__( 'LDAP Staff Directory — Settings', 'ldap-staff-directory' ),
			__( 'LDAP Directory', 'ldap-staff-directory' ),
			'manage_options',
			'ldap-staff-directory',
			array( $this, 'render_settings_page' )
		);
	}

	/** Register plugin options via Settings API. */
	public function register_settings() {
		register_setting(
			'ldap_ed_settings_group',
			LDAP_ED_OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);

		// --- LDAP Connection section ---
		add_settings_section(
			'ldap_ed_section_connection',
			__( 'LDAP Connection', 'ldap-staff-directory' ),
			'__return_false',
			'ldap-staff-directory'
		);

		// Text/number fields get label_for so the <th> label links to the input.
		$connection_text_fields = array(
			'server'    => __( 'LDAPS Server', 'ldap-staff-directory' ),
			'port'      => __( 'Port', 'ldap-staff-directory' ),
			'bind_dn'   => __( 'Bind DN', 'ldap-staff-directory' ),
			'bind_pass' => __( 'Bind Password', 'ldap-staff-directory' ),
			'base_ou'   => __( 'Base OU', 'ldap-staff-directory' ),
			'ca_cert'   => __( 'CA Certificate Path (.pem)', 'ldap-staff-directory' ),
		);

		foreach ( $connection_text_fields as $id => $label ) {
			add_settings_field(
				'ldap_ed_' . $id,
				$label,
				array( $this, 'render_field_' . $id ),
				'ldap-staff-directory',
				'ldap_ed_section_connection',
				array( 'label_for' => 'ldap_ed_' . $id )
			);
		}

		// Checkbox field — inline label in callback; skip label_for to avoid double-label.
		add_settings_field(
			'ldap_ed_verify_ssl',
			__( 'Verify SSL Certificate', 'ldap-staff-directory' ),
			array( $this, 'render_field_verify_ssl' ),
			'ldap-staff-directory',
			'ldap_ed_section_connection'
		);

		// Checkbox — no label_for.
		add_settings_field(
			'ldap_ed_exclude_disabled',
			__( 'Exclude Disabled Accounts', 'ldap-staff-directory' ),
			array( $this, 'render_field_exclude_disabled' ),
			'ldap-staff-directory',
			'ldap_ed_section_connection'
		);

		// Multi-checkbox — no label_for.
		add_settings_field(
			'ldap_ed_excluded_departments',
			__( 'Exclude Departments', 'ldap-staff-directory' ),
			array( $this, 'render_field_excluded_departments' ),
			'ldap-staff-directory',
			'ldap_ed_section_connection'
		);

		// Checkbox — no label_for.
		add_settings_field(
			'ldap_ed_exclude_no_department',
			__( 'Exclude Unassigned Employees', 'ldap-staff-directory' ),
			array( $this, 'render_field_exclude_no_department' ),
			'ldap-staff-directory',
			'ldap_ed_section_connection'
		);

		// --- Display section ---
		add_settings_section(
			'ldap_ed_section_display',
			__( 'Display Options', 'ldap-staff-directory' ),
			'__return_false',
			'ldap-staff-directory'
		);

		// Multi-checkbox — no label_for.
		add_settings_field(
			'ldap_ed_fields',
			__( 'Fields to Show', 'ldap-staff-directory' ),
			array( $this, 'render_field_fields' ),
			'ldap-staff-directory',
			'ldap_ed_section_display'
		);

		add_settings_field(
			'ldap_ed_per_page',
			__( 'Items per Page', 'ldap-staff-directory' ),
			array( $this, 'render_field_per_page' ),
			'ldap-staff-directory',
			'ldap_ed_section_display',
			array( 'label_for' => 'ldap_ed_per_page' )
		);

		// Checkbox — no label_for.
		add_settings_field(
			'ldap_ed_enable_search',
			__( 'Enable Search Bar', 'ldap-staff-directory' ),
			array( $this, 'render_field_enable_search' ),
			'ldap-staff-directory',
			'ldap_ed_section_display'
		);

		add_settings_field(
			'ldap_ed_extension_attr',
			__( 'Extension Attribute', 'ldap-staff-directory' ),
			array( $this, 'render_field_extension_attr' ),
			'ldap-staff-directory',
			'ldap_ed_section_display',
			array( 'label_for' => 'ldap_ed_extension_attr' )
		);

		add_settings_field(
			'ldap_ed_department_order',
			__( 'Department Order', 'ldap-staff-directory' ),
			array( $this, 'render_field_department_order' ),
			'ldap-staff-directory',
			'ldap_ed_section_display',
			array( 'label_for' => 'ldap_ed_department_order' )
		);

		// --- Cache section ---
		add_settings_section(
			'ldap_ed_section_cache',
			__( 'Cache', 'ldap-staff-directory' ),
			'__return_false',
			'ldap-staff-directory'
		);

		add_settings_field(
			'ldap_ed_cache_ttl',
			__( 'Cache TTL (minutes)', 'ldap-staff-directory' ),
			array( $this, 'render_field_cache_ttl' ),
			'ldap-staff-directory',
			'ldap_ed_section_cache',
			array( 'label_for' => 'ldap_ed_cache_ttl' )
		);
	}

	/** Sanitize and validate settings before saving. */
	public function sanitize_settings( $input ) {
		$clean    = array();
		$existing = get_option( LDAP_ED_OPTION_KEY, array() );

		$clean['server']        = $this->sanitize_ldap_server( $input['server'] ?? '', $existing['server'] ?? '' );
		$clean['port']          = absint( $input['port'] ?? 636 );
		$clean['bind_dn']       = sanitize_text_field( $input['bind_dn'] ?? '' );
		$clean['base_ou']       = sanitize_text_field( $input['base_ou'] ?? '' );
		$clean['verify_ssl']        = isset( $input['verify_ssl'] ) ? '1' : '0';
		$clean['ca_cert']           = sanitize_text_field( $input['ca_cert'] ?? '' );
		$clean['exclude_disabled']  = isset( $input['exclude_disabled'] ) ? '1' : '0';
		$clean['per_page']          = absint( $input['per_page'] ?? 20 );
		$clean['enable_search']     = isset( $input['enable_search'] ) ? '1' : '0';
		$ext_attr                   = sanitize_text_field( $input['extension_attr'] ?? 'ipPhone' );
		$clean['extension_attr']    = '' !== $ext_attr ? $ext_attr : 'ipPhone';
		$clean['cache_ttl']         = absint( $input['cache_ttl'] ?? 60 );

		// Departments marked for exclusion — dedupe, drop empties.
		// sanitize_textarea_field() (not sanitize_text_field()) so internal whitespace in a
		// department name is preserved exactly — this value is used for an exact-match LDAP
		// filter clause, not just display, and collapsing whitespace could make it stop
		// matching the real attribute value.
		$raw_excluded_departments      = ( ! empty( $input['excluded_departments'] ) && is_array( $input['excluded_departments'] ) )
			? $input['excluded_departments']
			: array();
		$clean['excluded_departments'] = array_values(
			array_unique( array_filter( array_map( 'sanitize_textarea_field', $raw_excluded_departments ), 'strlen' ) )
		);

		$clean['exclude_no_department'] = isset( $input['exclude_no_department'] ) ? '1' : '0';

		$allowed_department_orders = array( 'alpha', 'count_desc' );
		$raw_department_order      = sanitize_text_field( $input['department_order'] ?? 'alpha' );
		$clean['department_order'] = in_array( $raw_department_order, $allowed_department_orders, true )
			? $raw_department_order
			: 'alpha';

		// Allowed field keys.
		$allowed_fields  = array( 'name', 'email', 'title', 'department', 'phone', 'extension' );
		$clean['fields'] = array();
		if ( ! empty( $input['fields'] ) && is_array( $input['fields'] ) ) {
			foreach ( $input['fields'] as $field ) {
				if ( in_array( $field, $allowed_fields, true ) ) {
					$clean['fields'][] = $field;
				}
			}
		}

		// Only update password if a new one was supplied — encrypt it at rest.
		$plain_pass         = ! empty( $input['bind_pass'] ) ? $input['bind_pass'] : '';
		$clean['bind_pass'] = '' !== $plain_pass
			? ldap_ed_encrypt_pass( $plain_pass )
			: ( $existing['bind_pass'] ?? '' );

		// If the connection target changed, the known-departments snapshot may no longer
		// reflect the right server — clear it so the admin must re-discover before excluding.
		$connection_changed = (
			( $existing['server'] ?? '' ) !== $clean['server'] ||
			( $existing['bind_dn'] ?? '' ) !== $clean['bind_dn'] ||
			( $existing['base_ou'] ?? '' ) !== $clean['base_ou']
		);
		if ( $connection_changed ) {
			delete_option( LDAP_ED_KNOWN_DEPARTMENTS_KEY );
		}

		// Settings changed — purge both TTL transient and stale option since the
		// LDAP server or connection parameters may have changed.
		( new LDAP_ED_Cache() )->purge();

		return $clean;
	}

	/**
	 * Sanitize the LDAP server URL, allowing only ldap:// and ldaps:// schemes.
	 *
	 * @param string $raw      Raw submitted value.
	 * @param string $previous Previously saved value (fallback on invalid scheme).
	 * @return string
	 */
	private function sanitize_ldap_server( $raw, $previous ) {
		$value = trim( $raw );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '#^(ldaps?)://#i', $value, $matches ) ) {
			$scheme    = strtolower( $matches[1] );
			$remainder = substr( $value, strlen( $matches[0] ) );
			return $scheme . '://' . sanitize_text_field( $remainder );
		}

		add_settings_error(
			LDAP_ED_OPTION_KEY,
			'ldap_ed_invalid_server_scheme',
			sprintf(
				/* translators: %s: the submitted LDAP server URL */
				__( 'Invalid LDAP server URL "%s". The URL must begin with ldap:// or ldaps://. The previous value has been kept.', 'ldap-staff-directory' ),
				esc_html( $value )
			),
			'error'
		);

		return $previous;
	}

	/** Enqueue admin CSS and JS only on the plugin settings page. */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_ldap-staff-directory' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ldap-ed-admin',
			LDAP_ED_URL . 'admin/css/admin.css',
			array(),
			LDAP_ED_VERSION
		);

		wp_enqueue_script(
			'ldap-ed-admin',
			LDAP_ED_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			LDAP_ED_VERSION,
			true
		);

		wp_localize_script(
			'ldap-ed-admin',
			'ldapEdAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ldap_ed_admin_nonce' ),
				'i18n'    => array(
					'testing'                  => __( 'Testing…', 'ldap-staff-directory' ),
					'clearing'                 => __( 'Clearing…', 'ldap-staff-directory' ),
					'cacheCleared'             => __( 'Cache cleared.', 'ldap-staff-directory' ),
					'loadingDepartments'       => __( 'Loading…', 'ldap-staff-directory' ),
					'refreshDepartments'       => __( 'Refresh department list', 'ldap-staff-directory' ),
					'noDepartmentsFound'       => __( 'No departments found in LDAP.', 'ldap-staff-directory' ),
					/* translators: %d is replaced client-side with the number of employees with no department assigned. */
					'noDepartmentLabelWithCount' => __( 'Exclude employees with no department assigned (%d)', 'ldap-staff-directory' ),
					'noDepartmentLabel'        => __( 'Exclude employees with no department assigned', 'ldap-staff-directory' ),
				),
			)
		);
	}

	/** Render the main settings page. */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require LDAP_ED_DIR . 'admin/views/settings-page.php';
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	private function get_option( $key, $default = '' ) {
		$settings = get_option( LDAP_ED_OPTION_KEY, array() );
		return $settings[ $key ] ?? $default;
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_server( $args = array() ) {
		printf(
			'<input type="text" id="%1$s" name="%2$s[server]" value="%3$s" class="regular-text" placeholder="ldaps://directory.example.com">',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'server', 'ldaps://' ) )
		);
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_port( $args = array() ) {
		printf(
			'<input type="number" id="%1$s" name="%2$s[port]" value="%3$s" class="small-text" min="1" max="65535">',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'port', 636 ) )
		);
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_bind_dn( $args = array() ) {
		printf(
			'<input type="text" id="%1$s" name="%2$s[bind_dn]" value="%3$s" class="regular-text" placeholder="cn=admin,dc=example,dc=com">',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'bind_dn' ) )
		);
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_bind_pass( $args = array() ) {
		// Never echo the saved password back into the page.
		printf(
			'<input type="password" id="%1$s" name="%2$s[bind_pass]" value="" class="regular-text" autocomplete="new-password" placeholder="%3$s">',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr__( '(leave blank to keep current)', 'ldap-staff-directory' )
		);
	}

	/**
	 * Shows a persistent admin error when WordPress security keys have been regenerated,
	 * making the stored encrypted bind password unreadable until re-entered.
	 *
	 * Only displayed to users with manage_options capability.
	 * Only triggered when a 'sod::' encrypted password is stored AND the salt fingerprint changed.
	 */
	public function maybe_show_salt_rotation_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = get_option( LDAP_ED_OPTION_KEY, array() );
		$pass     = $settings['bind_pass'] ?? '';
		if ( 0 === strncmp( $pass, 'sod::', 5 ) && ldap_ed_salts_have_changed() ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'LDAP Staff Directory: WordPress security keys were changed. Please re-enter the LDAP bind password in Settings → LDAP Staff Directory.',
					'ldap-staff-directory'
				)
			);
		}
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_base_ou( $args = array() ) {
		printf(
			'<input type="text" id="%1$s" name="%2$s[base_ou]" value="%3$s" class="regular-text" placeholder="ou=employees,dc=example,dc=com">',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'base_ou' ) )
		);
	}

	public function render_field_verify_ssl() {
		printf(
			'<label><input type="checkbox" name="%1$s[verify_ssl]" value="1" %2$s> %3$s</label>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			checked( '1', $this->get_option( 'verify_ssl', '1' ), false ),
			esc_html__( 'Enable certificate verification (disable for self-signed certs)', 'ldap-staff-directory' )
		);
	}

	public function render_field_exclude_disabled() {
		printf(
			'<label><input type="checkbox" name="%1$s[exclude_disabled]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			checked( '1', $this->get_option( 'exclude_disabled', '0' ), false ),
			esc_html__( 'Exclude disabled accounts from the directory', 'ldap-staff-directory' ),
			esc_html__( 'Uses the Active Directory userAccountControl attribute. Leave unchecked for OpenLDAP and other servers.', 'ldap-staff-directory' )
		);
	}

	/**
	 * Checklist of departments discovered from LDAP (snapshot stored in
	 * LDAP_ED_KNOWN_DEPARTMENTS_KEY) — checked entries are excluded from the public directory.
	 * The discovery snapshot is refreshed only via the "Refresh department list" AJAX button,
	 * never automatically, so opening the settings page never triggers an LDAP call.
	 */
	public function render_field_excluded_departments() {
		$known = get_option( LDAP_ED_KNOWN_DEPARTMENTS_KEY, false );
		$saved = (array) $this->get_option( 'excluded_departments', array() );

		printf( '<div id="ldap-ed-departments-field">' );

		if ( false === $known || empty( $known['departments'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'No departments loaded yet. Click "Refresh department list" below to discover departments from LDAP.', 'ldap-staff-directory' )
			);
		} else {
			printf( '<div id="ldap-ed-departments-checklist" class="ldap-ed-departments-checklist">' );
			foreach ( $known['departments'] as $ldap_ed_known_dept ) {
				// Compare using the same sanitizer applied on save (sanitize_textarea_field —
				// preserves internal whitespace) so the checkbox state always matches what's
				// actually stored and used to build the LDAP exclusion filter.
				$ldap_ed_normalized_name = sanitize_textarea_field( $ldap_ed_known_dept['name'] );
				printf(
					'<label><input type="checkbox" name="%1$s[excluded_departments][]" value="%2$s" %3$s> %4$s</label>',
					esc_attr( LDAP_ED_OPTION_KEY ),
					esc_attr( $ldap_ed_known_dept['name'] ),
					checked( in_array( $ldap_ed_normalized_name, $saved, true ), true, false ),
					esc_html( $ldap_ed_known_dept['name'] . ' (' . absint( $ldap_ed_known_dept['count'] ) . ')' )
				);
			}
			printf( '</div>' );
		}

		printf(
			'<p><button type="button" id="ldap-ed-refresh-departments-btn" class="button button-secondary">%s</button></p>',
			esc_html__( 'Refresh department list', 'ldap-staff-directory' )
		);
		printf( '<div id="ldap-ed-departments-result" class="ldap-ed-test-result" aria-live="polite"></div>' );
		printf( '</div>' );
	}

	/** Checkbox — separate from the named-department checklist (LDAP can't negate-match an absent attribute). */
	public function render_field_exclude_no_department() {
		$known = get_option( LDAP_ED_KNOWN_DEPARTMENTS_KEY, false );
		$count = ( false !== $known && isset( $known['no_department_count'] ) ) ? absint( $known['no_department_count'] ) : null;

		$label = null === $count
			? __( 'Exclude employees with no department assigned', 'ldap-staff-directory' )
			: sprintf(
				/* translators: %d: number of employees with no department assigned */
				__( 'Exclude employees with no department assigned (%d)', 'ldap-staff-directory' ),
				absint( $count )
			);

		printf(
			'<label id="ldap-ed-exclude-no-department-label"><input type="checkbox" name="%1$s[exclude_no_department]" value="1" %2$s> %3$s</label>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			checked( '1', $this->get_option( 'exclude_no_department', '0' ), false ),
			esc_html( $label )
		);
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_ca_cert( $args = array() ) {
		printf(
			'<input type="text" id="%1$s" name="%2$s[ca_cert]" value="%3$s" class="regular-text" placeholder="/etc/ssl/certs/ca.pem"><p class="description">%4$s</p>',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'ca_cert' ) ),
			esc_html__( 'Full server path to the CA certificate file. Used when SSL verification is enabled.', 'ldap-staff-directory' )
		);
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_extension_attr( $args = array() ) {
		printf(
			'<input type="text" id="%1$s" name="%2$s[extension_attr]" value="%3$s" class="regular-text" placeholder="ipPhone"><p class="description">%4$s</p>',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'extension_attr', 'ipPhone' ) ),
			esc_html__( 'LDAP attribute for telephone extensions. Use ipPhone for Active Directory / IP-PBX systems, or enter the attribute name from your own schema (e.g. extensionAttribute1). Case does not matter.', 'ldap-staff-directory' )
		);
	}

	public function render_field_fields() {
		$saved = $this->get_option( 'fields', array( 'name', 'email', 'title', 'department' ) );
		$items = array(
			'name'       => __( 'Full Name', 'ldap-staff-directory' ),
			'email'      => __( 'Email', 'ldap-staff-directory' ),
			'title'      => __( 'Job Title', 'ldap-staff-directory' ),
			'department' => __( 'Department', 'ldap-staff-directory' ),
			'phone'      => __( 'Phone', 'ldap-staff-directory' ),
			'extension'  => __( 'Extension', 'ldap-staff-directory' ),
		);
		foreach ( $items as $key => $label ) {
			printf(
				'<label style="margin-right:12px"><input type="checkbox" name="%1$s[fields][]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( LDAP_ED_OPTION_KEY ),
				esc_attr( $key ),
				checked( in_array( $key, (array) $saved, true ), true, false ),
				esc_html( $label )
			);
		}
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_per_page( $args = array() ) {
		printf(
			'<input type="number" id="%1$s" name="%2$s[per_page]" value="%3$d" class="small-text" min="1" max="500">',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			absint( $this->get_option( 'per_page', 20 ) )
		);
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_department_order( $args = array() ) {
		$saved   = $this->get_option( 'department_order', 'alpha' );
		$options = array(
			'alpha'      => __( 'Alphabetical (A–Z)', 'ldap-staff-directory' ),
			'count_desc' => __( 'By contact count (highest first)', 'ldap-staff-directory' ),
		);

		printf(
			'<select id="%1$s" name="%2$s[department_order]">',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY )
		);
		foreach ( $options as $ldap_ed_order_value => $ldap_ed_order_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $ldap_ed_order_value ),
				selected( $saved, $ldap_ed_order_value, false ),
				esc_html( $ldap_ed_order_label )
			);
		}
		printf(
			'</select><p class="description">%s</p>',
			esc_html__( 'Order of the department filter chips shown to visitors on the public directory.', 'ldap-staff-directory' )
		);
	}

	public function render_field_enable_search() {
		printf(
			'<label><input type="checkbox" name="%1$s[enable_search]" value="1" %2$s> %3$s</label>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			checked( '1', $this->get_option( 'enable_search', '1' ), false ),
			esc_html__( 'Show search field above the directory', 'ldap-staff-directory' )
		);
	}

	/** @param array $args Settings field args passed by the Settings API. */
	public function render_field_cache_ttl( $args = array() ) {
		printf(
			'<input type="number" id="%1$s" name="%2$s[cache_ttl]" value="%3$d" class="small-text" min="1"> %4$s',
			esc_attr( $args['label_for'] ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			absint( $this->get_option( 'cache_ttl', 60 ) ),
			esc_html__( 'minutes', 'ldap-staff-directory' )
		);
	}
}
