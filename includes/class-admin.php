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

	/** Tabs of the settings page, in display order. */
	const TABS = array( 'connection', 'employees', 'display' );

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

	/**
	 * Register the plugin option via the Settings API.
	 *
	 * The settings page no longer relies on add_settings_section()/add_settings_field()/
	 * do_settings_sections() — each of the 3 tabs is its own <form> with hand-built markup
	 * (see admin/views/settings-page.php), because the tabbed/card layout, popovers, and the
	 * conditional Extension Attribute reveal don't map cleanly onto the Settings API's
	 * automatic <table class="form-table"> renderer. register_setting() is what actually
	 * matters here — it's what routes each tab's POST through sanitize_settings().
	 */
	public function register_settings() {
		register_setting(
			'ldap_ed_settings_group',
			LDAP_ED_OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize and validate settings before saving.
	 *
	 * Each of the 3 settings tabs submits its own <form>, carrying a hidden
	 * `ldap_ed_settings[_tab]` marker. Only the fields belonging to the submitted tab are
	 * re-sanitized from $input; every other tab's fields are copied through unchanged from
	 * the existing option, so saving one tab never resets the other two.
	 */
	public function sanitize_settings( $input ) {
		$existing = get_option( LDAP_ED_OPTION_KEY, array() );
		$tab      = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';
		if ( ! in_array( $tab, self::TABS, true ) ) {
			// Unknown/absent tab marker (e.g. a legacy or programmatic save) — sanitize
			// every field, matching the previous single-form behavior.
			$tab = '';
		}

		$clean = $existing;

		if ( '' === $tab || 'connection' === $tab ) {
			$clean = array_merge( $clean, ldap_ed_sanitize_connection_fields( $input, $existing ) );

			// If the connection target changed, the known-departments snapshot may no longer
			// reflect the right server — clear it so the admin must re-discover before excluding.
			// Compare domains (not the raw stored value) so a legacy "scheme://host" value being
			// normalized to "host" on this same save isn't mistaken for an actual server change.
			$existing_domain    = ldap_ed_split_server_scheme( $existing['server'] ?? '' )['domain'];
			$connection_changed = (
				$existing_domain !== $clean['server'] ||
				( $existing['bind_dn'] ?? '' ) !== $clean['bind_dn'] ||
				( $existing['base_ou'] ?? '' ) !== $clean['base_ou']
			);
			if ( $connection_changed ) {
				delete_option( LDAP_ED_KNOWN_DEPARTMENTS_KEY );
			}
		}

		if ( '' === $tab || 'employees' === $tab ) {
			$clean['exclude_disabled'] = isset( $input['exclude_disabled'] ) ? '1' : '0';

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
		}

		$display_changed = false;
		if ( '' === $tab || 'display' === $tab ) {
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

			$clean['per_page']      = absint( $input['per_page'] ?? 20 );
			$clean['enable_search'] = isset( $input['enable_search'] ) ? '1' : '0';

			$ext_attr                = sanitize_text_field( $input['extension_attr'] ?? 'ipPhone' );
			$clean['extension_attr'] = '' !== $ext_attr ? $ext_attr : 'ipPhone';

			$allowed_department_orders = array( 'alpha', 'count_desc' );
			$raw_department_order      = sanitize_text_field( $input['department_order'] ?? 'alpha' );
			$clean['department_order'] = in_array( $raw_department_order, $allowed_department_orders, true )
				? $raw_department_order
				: 'alpha';

			$clean['cache_ttl'] = absint( $input['cache_ttl'] ?? 60 );

			$display_changed = ( $existing['extension_attr'] ?? '' ) !== $clean['extension_attr'];
		}

		// Cache purge is scoped to what actually changed: Connection and Employees affect what
		// gets fetched from (or filtered out of) LDAP, so they always purge. Display only
		// changes how already-cached data is rendered — purge only when extension_attr changed,
		// since that's the one Display field that changes which LDAP attribute gets read.
		if ( '' === $tab || 'connection' === $tab || 'employees' === $tab || $display_changed ) {
			( new LDAP_ED_Cache() )->purge();
		}

		return $clean;
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

		$it_request_template = __(
			"I need the following details to connect our WordPress employee directory to LDAP:\n1) Server address\n2) Do we use LDAP or LDAPS (secure)?\n3) A read-only service account (Bind DN) and its password\n4) The folder (OU) where employee accounts live\n5) If the SSL certificate is self-signed, the CA certificate file",
			'ldap-staff-directory'
		);

		wp_localize_script(
			'ldap-ed-admin',
			'ldapEdAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ldap_ed_admin_nonce' ),
				'i18n'    => array(
					'testing'                     => __( 'Testing…', 'ldap-staff-directory' ),
					'clearing'                    => __( 'Clearing…', 'ldap-staff-directory' ),
					'cacheCleared'                => __( 'Cache cleared.', 'ldap-staff-directory' ),
					'loadingDepartments'          => __( 'Loading…', 'ldap-staff-directory' ),
					'refreshDepartments'          => __( 'Refresh department list', 'ldap-staff-directory' ),
					'noDepartmentsFound'          => __( 'No departments found in LDAP.', 'ldap-staff-directory' ),
					/* translators: %d is replaced client-side with the number of employees with no department assigned. */
					'noDepartmentLabelWithCount'  => __( 'Exclude employees with no department assigned (%d)', 'ldap-staff-directory' ),
					'noDepartmentLabel'           => __( 'Exclude employees with no department assigned', 'ldap-staff-directory' ),
					'showPassword'                => __( 'Show password', 'ldap-staff-directory' ),
					'hidePassword'                => __( 'Hide password', 'ldap-staff-directory' ),
					'moreInfo'                    => __( 'More info', 'ldap-staff-directory' ),
					'closeInfo'                   => __( 'Close', 'ldap-staff-directory' ),
					'itRequestTemplate'           => $it_request_template,
					'itRequestCopied'             => __( 'Request copied to clipboard.', 'ldap-staff-directory' ),
					'itRequestCopyFailed'         => __( "Couldn't copy automatically — copy the text manually.", 'ldap-staff-directory' ),
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
	// Layout helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a "label | field" row, matching the card-row layout used across all 3 tabs.
	 * Not used for checkboxes or multi-checkbox groups, which render their own inline label.
	 *
	 * @param string   $label Field label text (already translated).
	 * @param string   $for   Field id, for the <label for="">.
	 * @param callable $field_cb Callback that echoes the field's own markup (input/select).
	 * @param string   $info  Optional help text shown in a popover next to the label.
	 */
	public function row( string $label, string $for, callable $field_cb, string $info = '' ): void {
		ob_start();
		call_user_func( $field_cb );
		$field_html = ob_get_clean();

		printf(
			'<div class="ldap-ed-row"><div class="ldap-ed-row__label"><label for="%1$s">%2$s</label>%3$s</div><div class="ldap-ed-row__field">%4$s</div></div>',
			esc_attr( $for ),
			esc_html( $label ),
			$info ? $this->info_button( $for, $info ) : '',
			$field_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field_cb builds its own escaped markup, identical to the render_field_* methods called directly elsewhere.
		);
	}

	/** Renders the `[?]` popover trigger button used next to jargon-heavy field labels. */
	public function info_button( string $for, string $text ): string {
		return sprintf(
			'<button type="button" class="ldap-ed-info-btn" data-help="%1$s" aria-controls="%2$s-help" aria-expanded="false" aria-label="%3$s"><span aria-hidden="true">?</span></button>',
			esc_attr( $text ),
			esc_attr( $for ),
			esc_attr__( 'More info', 'ldap-staff-directory' )
		);
	}

	/**
	 * Render a collapsed-by-default "Advanced settings" accordion. Its body is hidden via the
	 * `hidden` attribute (never removed from the DOM, never `disabled`), so the fields inside
	 * still travel with the form on submit or on a live "Test Connection" serialize.
	 *
	 * @param string   $id      Unique id for this accordion instance.
	 * @param callable $body_cb Callback that echoes the accordion body's field rows.
	 */
	public function advanced_section( string $id, callable $body_cb ): void {
		ob_start();
		call_user_func( $body_cb );
		$body = ob_get_clean();

		printf(
			'<div class="ldap-ed-advanced" id="%1$s"><button type="button" class="ldap-ed-advanced__toggle" aria-expanded="false" aria-controls="%1$s-body"><span>%2$s</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button><div class="ldap-ed-advanced__body" id="%1$s-body" hidden>%3$s</div></div>',
			esc_attr( $id ),
			esc_html__( 'Advanced settings', 'ldap-staff-directory' ),
			$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $body_cb builds its own escaped markup via row()/render_field_* calls.
		);
	}

	private function get_option( $key, $default = '' ) {
		$settings = get_option( LDAP_ED_OPTION_KEY, array() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * The scheme to show as selected: the explicitly saved value, else inferred from a
	 * legacy scheme prefix still embedded in `server`, else 'ldaps'. Shared by the scheme
	 * select and the port placeholder so they always agree on which default port applies.
	 */
	private function get_effective_scheme(): string {
		$saved = (string) $this->get_option( 'scheme', '' );
		if ( '' !== $saved ) {
			return $saved;
		}
		$split = ldap_ed_split_server_scheme( (string) $this->get_option( 'server', '' ) );
		return $split['scheme'] ?? 'ldaps';
	}

	/**
	 * Shows a persistent admin error when WordPress security keys have been regenerated,
	 * making the stored encrypted bind password unreadable until re-entered.
	 *
	 * Only displayed to users with manage_options capability.
	 * Only triggered when a 'sod::' encrypted password is stored AND the salt fingerprint changed.
	 * Links directly to the Connection tab with the bind password field targeted, so the admin
	 * doesn't have to know where to go to fix it.
	 */
	public function maybe_show_salt_rotation_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = get_option( LDAP_ED_OPTION_KEY, array() );
		$pass     = $settings['bind_pass'] ?? '';
		if ( 0 !== strncmp( $pass, 'sod::', 5 ) || ! ldap_ed_salts_have_changed() ) {
			return;
		}

		$url  = admin_url( 'options-general.php?page=ldap-staff-directory&tab=connection#ldap_ed_bind_pass' );
		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 're-enter the LDAP bind password', 'ldap-staff-directory' )
		);

		$message = sprintf(
			/* translators: %s: link labeled "re-enter the LDAP bind password", pointing at the Connection tab. */
			__( 'LDAP Staff Directory: WordPress security keys were changed. Please %s in Settings → LDAP Staff Directory.', 'ldap-staff-directory' ),
			$link
		);

		printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public function render_field_scheme() {
		$current = $this->get_effective_scheme();
		$options = array(
			'ldap'  => __( 'LDAP', 'ldap-staff-directory' ),
			'ldaps' => __( 'LDAPS', 'ldap-staff-directory' ),
		);

		printf(
			'<select id="ldap_ed_scheme" name="%1$s[scheme]">',
			esc_attr( LDAP_ED_OPTION_KEY )
		);
		foreach ( $options as $ldap_ed_scheme_value => $ldap_ed_scheme_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $ldap_ed_scheme_value ),
				selected( $current, $ldap_ed_scheme_value, false ),
				esc_html( $ldap_ed_scheme_label )
			);
		}
		printf( '</select>' );
	}

	public function render_field_server() {
		$split = ldap_ed_split_server_scheme( (string) $this->get_option( 'server', '' ) );
		printf(
			'<input type="text" id="ldap_ed_server" name="%1$s[server]" value="%2$s" class="regular-text" placeholder="directory.example.com">',
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $split['domain'] )
		);
	}

	public function render_field_port() {
		$default_port = 'ldap' === $this->get_effective_scheme() ? 389 : 636;
		$saved_port   = $this->get_option( 'port', '' );
		$is_default   = '' === $saved_port || in_array( absint( $saved_port ), array( 389, 636 ), true );

		printf(
			'<input type="number" id="ldap_ed_port" name="%1$s[port]" value="%2$s" class="small-text" min="1" max="65535" placeholder="%3$d" data-default-ldap="389" data-default-ldaps="636">',
			esc_attr( LDAP_ED_OPTION_KEY ),
			$is_default ? '' : esc_attr( absint( $saved_port ) ),
			absint( $default_port )
		);
	}

	public function render_field_bind_dn() {
		printf(
			'<input type="text" id="ldap_ed_bind_dn" name="%1$s[bind_dn]" value="%2$s" class="regular-text" placeholder="cn=admin,dc=example,dc=com"><p class="description">%3$s</p>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'bind_dn' ) ),
			esc_html__( 'This is the service account WordPress uses to connect — not your personal user account.', 'ldap-staff-directory' )
		);
	}

	public function render_field_bind_pass() {
		// Never echo the saved password back into the page.
		printf(
			'<span class="ldap-ed-password-wrap"><input type="password" id="ldap_ed_bind_pass" name="%1$s[bind_pass]" value="" class="regular-text" autocomplete="new-password" placeholder="%2$s"><button type="button" class="ldap-ed-password-toggle" data-target="ldap_ed_bind_pass" aria-pressed="false" aria-label="%3$s"></button></span><p class="description">%4$s</p>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr__( '(leave blank to keep current)', 'ldap-staff-directory' ),
			esc_attr__( 'Show password', 'ldap-staff-directory' ),
			esc_html__( 'The password for that service account.', 'ldap-staff-directory' )
		);
	}

	public function render_field_base_ou() {
		printf(
			'<input type="text" id="ldap_ed_base_ou" name="%1$s[base_ou]" value="%2$s" class="regular-text" placeholder="ou=employees,dc=example,dc=com"><p class="description">%3$s</p>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'base_ou' ) ),
			esc_html__( 'The folder (organizational unit) where employee accounts live.', 'ldap-staff-directory' )
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
			esc_html__( 'Exclude disabled accounts', 'ldap-staff-directory' ),
			esc_html__( 'Only applies to Active Directory. Leave unchecked if your server is OpenLDAP or another type.', 'ldap-staff-directory' )
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
				esc_html__( 'No departments loaded yet. Click "Refresh department list" above to discover departments from LDAP.', 'ldap-staff-directory' )
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

	public function render_field_ca_cert() {
		printf(
			'<input type="text" id="ldap_ed_ca_cert" name="%1$s[ca_cert]" value="%2$s" class="regular-text" placeholder="/etc/ssl/certs/ca.pem"><p class="description">%3$s</p>',
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'ca_cert' ) ),
			esc_html__( 'Full server path to the CA certificate file. Used when SSL verification is enabled.', 'ldap-staff-directory' )
		);
	}

	public function render_field_extension_attr() {
		printf(
			'<label for="ldap_ed_extension_attr" class="ldap-ed-extension-block__label">%1$s</label><input type="text" id="ldap_ed_extension_attr" name="%2$s[extension_attr]" value="%3$s" class="regular-text" placeholder="ipPhone"><p class="description">%4$s</p>',
			esc_html__( 'LDAP extension attribute', 'ldap-staff-directory' ),
			esc_attr( LDAP_ED_OPTION_KEY ),
			esc_attr( $this->get_option( 'extension_attr', 'ipPhone' ) ),
			esc_html__( 'Use ipPhone for Active Directory / IP-PBX systems, or enter the attribute name if your server uses a different schema. If you\'re not sure, ask whoever manages your LDAP server.', 'ldap-staff-directory' )
		);
	}

	/**
	 * Renders the "fields to show" checkboxes and, nested right below, the Extension Attribute
	 * input — visible only when the "Extension" checkbox is checked (JS-driven; PHP renders it
	 * present-but-hidden so it still submits/serializes when the admin has it checked).
	 */
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

		printf( '<div class="ldap-ed-fields-row">' );
		foreach ( $items as $key => $label ) {
			printf(
				'<label class="ldap-ed-field-checkbox"><input type="checkbox" class="ldap-ed-field-checkbox__input" data-field="%2$s" name="%1$s[fields][]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( LDAP_ED_OPTION_KEY ),
				esc_attr( $key ),
				checked( in_array( $key, (array) $saved, true ), true, false ),
				esc_html( $label )
			);
		}
		printf( '</div>' );

		$extension_active = in_array( 'extension', (array) $saved, true );
		printf(
			'<div class="ldap-ed-extension-block" id="ldap-ed-extension-block"%s>',
			$extension_active ? '' : ' hidden'
		);
		printf(
			'<p class="ldap-ed-extension-block__hint">%s</p>',
			esc_html__( 'Shown because you checked "Extension" above.', 'ldap-staff-directory' )
		);
		$this->render_field_extension_attr();
		printf( '</div>' );
	}

	public function render_field_per_page() {
		printf(
			'<input type="number" id="ldap_ed_per_page" name="%1$s[per_page]" value="%2$d" class="small-text" min="1" max="500">',
			esc_attr( LDAP_ED_OPTION_KEY ),
			absint( $this->get_option( 'per_page', 20 ) )
		);
	}

	public function render_field_department_order() {
		$saved   = $this->get_option( 'department_order', 'alpha' );
		$options = array(
			'alpha'      => __( 'Alphabetical (A–Z)', 'ldap-staff-directory' ),
			'count_desc' => __( 'By contact count (highest first)', 'ldap-staff-directory' ),
		);

		printf(
			'<select id="ldap_ed_department_order" name="%1$s[department_order]">',
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

	public function render_field_cache_ttl() {
		printf(
			'<input type="number" id="ldap_ed_cache_ttl" name="%1$s[cache_ttl]" value="%2$d" class="small-text" min="1"> %3$s',
			esc_attr( LDAP_ED_OPTION_KEY ),
			absint( $this->get_option( 'cache_ttl', 60 ) ),
			esc_html__( 'minutes', 'ldap-staff-directory' )
		);
	}
}
