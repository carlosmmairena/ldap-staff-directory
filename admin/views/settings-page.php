<?php
/**
 * Admin settings page view.
 *
 * Renders 3 independently-submitted tabs (Connection / Employees / Fields). Each tab is its
 * own <form> posting to options.php via the same 'ldap_ed_settings_group' — sanitize_settings()
 * uses the hidden `_tab` marker to know which subset of fields to re-sanitize, preserving the
 * other two tabs untouched. See LDAP_ED_Admin::sanitize_settings().
 *
 * @package LDAP_Staff_Directory
 * @var LDAP_ED_Admin $this
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ldap_ed_active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: only selects which tab renders as active, whitelisted below, never used to change state.
if ( ! in_array( $ldap_ed_active_tab, LDAP_ED_Admin::TABS, true ) ) {
	$ldap_ed_active_tab = 'connection';
}

$ldap_ed_base_url = admin_url( 'options-general.php?page=ldap-staff-directory' );

$ldap_ed_tabs = array(
	'connection' => __( 'Connection', 'ldap-staff-directory' ),
	'employees'  => __( 'Employees', 'ldap-staff-directory' ),
	'display'    => __( 'Fields', 'ldap-staff-directory' ),
);
?>
<div class="wrap ldap-ed-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

	<h2 class="nav-tab-wrapper ldap-ed-tabs" id="ldap-ed-tabs" role="tablist">
		<?php foreach ( $ldap_ed_tabs as $ldap_ed_tab_id => $ldap_ed_tab_label ) : ?>
			<a
				href="<?php echo esc_url( add_query_arg( 'tab', $ldap_ed_tab_id, $ldap_ed_base_url ) ); ?>"
				class="nav-tab<?php echo $ldap_ed_tab_id === $ldap_ed_active_tab ? ' nav-tab-active' : ''; ?>"
				role="tab"
				data-tab="<?php echo esc_attr( $ldap_ed_tab_id ); ?>"
				aria-selected="<?php echo $ldap_ed_tab_id === $ldap_ed_active_tab ? 'true' : 'false'; ?>"
			><?php echo esc_html( $ldap_ed_tab_label ); ?></a>
		<?php endforeach; ?>
	</h2>

	<!-- ===== Connection tab ===== -->
	<div class="ldap-ed-tabpanel" id="ldap-ed-tabpanel-connection" data-tabpanel="connection" role="tabpanel"<?php echo 'connection' === $ldap_ed_active_tab ? '' : ' hidden'; ?>>
		<form method="post" action="options.php" id="ldap-ed-connection-form">
			<?php settings_fields( 'ldap_ed_settings_group' ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( add_query_arg( 'tab', 'connection', $ldap_ed_base_url ) ); ?>">
			<input type="hidden" name="<?php echo esc_attr( LDAP_ED_OPTION_KEY ); ?>[_tab]" value="connection">

			<p class="ldap-ed-intro"><?php esc_html_e( "Connect WordPress to your organization's LDAP or Active Directory server. These details are usually provided by whoever manages that server — you don't need to know LDAP to fill them in.", 'ldap-staff-directory' ); ?></p>

			<div class="ldap-ed-callout">
				<p class="ldap-ed-callout__title"><?php esc_html_e( "Don't have these details handy?", 'ldap-staff-directory' ); ?></p>
				<p><?php esc_html_e( "Ask whoever manages your organization's directory. You'll need: the server address, a service account with its password, and the folder (OU) where employee accounts live.", 'ldap-staff-directory' ); ?></p>
				<button type="button" class="button" id="ldap-ed-copy-request-btn"><?php esc_html_e( 'Copy request for IT', 'ldap-staff-directory' ); ?></button>
				<span id="ldap-ed-copy-result" class="ldap-ed-test-result" aria-live="polite"></span>
			</div>

			<div class="ldap-ed-card">
				<?php
				$this->row( __( 'Scheme', 'ldap-staff-directory' ), 'ldap_ed_scheme', array( $this, 'render_field_scheme' ) );
				$this->row( __( 'Server', 'ldap-staff-directory' ), 'ldap_ed_server', array( $this, 'render_field_server' ) );
				$this->row( __( 'Port', 'ldap-staff-directory' ), 'ldap_ed_port', array( $this, 'render_field_port' ) );
				$this->row( __( 'Bind DN', 'ldap-staff-directory' ), 'ldap_ed_bind_dn', array( $this, 'render_field_bind_dn' ), __( 'The service account WordPress logs in with, e.g. cn=admin,dc=example,dc=com. Provided by whoever manages your LDAP server — not a personal employee account.', 'ldap-staff-directory' ) );
				$this->row( __( 'Bind Password', 'ldap-staff-directory' ), 'ldap_ed_bind_pass', array( $this, 'render_field_bind_pass' ), __( "The password for that service account. It's never shown here once saved — leaving this blank keeps the password you already have.", 'ldap-staff-directory' ) );
				$this->row( __( 'Base OU', 'ldap-staff-directory' ), 'ldap_ed_base_ou', array( $this, 'render_field_base_ou' ), __( 'The part of the directory to search for employees, e.g. ou=employees,dc=example,dc=com.', 'ldap-staff-directory' ) );
				?>
			</div>

			<div class="ldap-ed-test-row">
				<button type="button" id="ldap-ed-test-btn" class="button button-primary"><?php esc_html_e( 'Test Connection', 'ldap-staff-directory' ); ?></button>
				<div id="ldap-ed-test-result" class="ldap-ed-test-result" aria-live="polite"></div>
			</div>

			<?php
			$this->advanced_section(
				'ldap-ed-advanced-connection',
				function () {
					$this->row( __( 'CA Certificate Path (.pem)', 'ldap-staff-directory' ), 'ldap_ed_ca_cert', array( $this, 'render_field_ca_cert' ) );
					printf( '<div class="ldap-ed-row ldap-ed-row--checkbox">' );
					$this->render_field_verify_ssl();
					printf( '</div>' );
				}
			);
			?>

			<?php submit_button( __( 'Save Connection settings', 'ldap-staff-directory' ) ); ?>
		</form>
	</div>

	<!-- ===== Employees tab ===== -->
	<div class="ldap-ed-tabpanel" id="ldap-ed-tabpanel-employees" data-tabpanel="employees" role="tabpanel"<?php echo 'employees' === $ldap_ed_active_tab ? '' : ' hidden'; ?>>
		<form method="post" action="options.php" id="ldap-ed-employees-form">
			<?php settings_fields( 'ldap_ed_settings_group' ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( add_query_arg( 'tab', 'employees', $ldap_ed_base_url ) ); ?>">
			<input type="hidden" name="<?php echo esc_attr( LDAP_ED_OPTION_KEY ); ?>[_tab]" value="employees">

			<p class="ldap-ed-intro"><?php esc_html_e( 'Choose which employees appear in the public directory. These filters are applied before the list is shown to site visitors.', 'ldap-staff-directory' ); ?></p>

			<div class="ldap-ed-card ldap-ed-card--tight">
				<?php $this->render_field_exclude_disabled(); ?>
			</div>

			<div class="ldap-ed-departments-section">
				<div class="ldap-ed-departments-section__header">
					<h2><?php esc_html_e( 'Exclude departments', 'ldap-staff-directory' ); ?></h2>
					<button type="button" id="ldap-ed-refresh-departments-btn" class="button button-secondary"><?php esc_html_e( 'Refresh department list', 'ldap-staff-directory' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( "Names and counts come straight from your LDAP server. Check the ones you don't want to show.", 'ldap-staff-directory' ); ?></p>
				<?php $this->render_field_excluded_departments(); ?>
			</div>

			<div class="ldap-ed-card ldap-ed-card--tight">
				<?php $this->render_field_exclude_no_department(); ?>
			</div>

			<?php submit_button( __( 'Save Employees settings', 'ldap-staff-directory' ) ); ?>
		</form>
	</div>

	<!-- ===== Fields tab ===== -->
	<div class="ldap-ed-tabpanel" id="ldap-ed-tabpanel-display" data-tabpanel="display" role="tabpanel"<?php echo 'display' === $ldap_ed_active_tab ? '' : ' hidden'; ?>>
		<form method="post" action="options.php" id="ldap-ed-display-form">
			<?php settings_fields( 'ldap_ed_settings_group' ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( add_query_arg( 'tab', 'display', $ldap_ed_base_url ) ); ?>">
			<input type="hidden" name="<?php echo esc_attr( LDAP_ED_OPTION_KEY ); ?>[_tab]" value="display">

			<p class="ldap-ed-intro"><?php esc_html_e( 'Choose what information shows for each person and how the list is organized for your visitors.', 'ldap-staff-directory' ); ?></p>

			<div class="ldap-ed-card">
				<h2 class="ldap-ed-card__title"><?php esc_html_e( 'Fields to show', 'ldap-staff-directory' ); ?></h2>
				<?php $this->render_field_fields(); ?>
			</div>

			<div class="ldap-ed-card">
				<?php
				$this->row( __( 'Items per page', 'ldap-staff-directory' ), 'ldap_ed_per_page', array( $this, 'render_field_per_page' ) );
				printf( '<div class="ldap-ed-row ldap-ed-row--checkbox">' );
				$this->render_field_enable_search();
				printf( '</div>' );
				$this->row( __( 'Department order', 'ldap-staff-directory' ), 'ldap_ed_department_order', array( $this, 'render_field_department_order' ) );
				?>
			</div>

			<?php
			$this->advanced_section(
				'ldap-ed-advanced-display',
				function () {
					$this->row( __( 'Cache TTL', 'ldap-staff-directory' ), 'ldap_ed_cache_ttl', array( $this, 'render_field_cache_ttl' ) );
				}
			);
			?>

			<div class="ldap-ed-card ldap-ed-card--tight">
				<h2 class="ldap-ed-card__title"><?php esc_html_e( 'Cache', 'ldap-staff-directory' ); ?></h2>
				<p class="description"><?php esc_html_e( 'User data is cached to reduce LDAP queries. Click below to force a refresh.', 'ldap-staff-directory' ); ?></p>
				<button type="button" id="ldap-ed-clear-cache-btn" class="button button-secondary">
					<?php esc_html_e( 'Clear Cache', 'ldap-staff-directory' ); ?>
				</button>
				<div id="ldap-ed-cache-result" class="ldap-ed-test-result" aria-live="polite"></div>
			</div>

			<div class="ldap-ed-footer">
				<?php submit_button( __( 'Save Fields settings', 'ldap-staff-directory' ), 'primary', 'submit', false ); ?>
				<div class="ldap-ed-usage-hint">
					<span><?php esc_html_e( 'Insert with:', 'ldap-staff-directory' ); ?></span>
					<code>[ldap_directory]</code>
				</div>
			</div>
		</form>
	</div>
</div><!-- /.wrap -->
