<?php
/**
 * Department menu template — root view of the [ldap_directory] shortcode,
 * rendered when no department is selected (ldap_dept absent/empty).
 *
 * Variables available from class-shortcode.php:
 *   $ldap_ed_departments   — array [ 'Department' => count ], already ordered
 *                            per the department_order setting
 *   $ldap_ed_all_count     — int, total employees across all departments
 *   $ldap_ed_enable_search — bool, show the department search input
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ldap_ed_dept_total = count( $ldap_ed_departments );
?>
<div class="ldap-directory-wrap ldap-department-menu">

	<div class="ldap-menu-header">
		<h2 class="ldap-menu-title"><?php esc_html_e( 'Staff Directory', 'ldap-staff-directory' ); ?></h2>
		<p class="ldap-menu-sub">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: number of departments, 2: total number of employees */
				_n( '%1$s department · %2$s employees', '%1$s departments · %2$s employees', $ldap_ed_dept_total, 'ldap-staff-directory' ),
				absint( $ldap_ed_dept_total ),
				absint( $ldap_ed_all_count )
			)
		);
		?>
		</p>
	</div>

	<?php if ( $ldap_ed_enable_search ) : ?>
	<div class="ldap-search-wrap ldap-dept-search-wrap">
		<label for="ldap-dept-search-input" class="screen-reader-text">
			<?php esc_html_e( 'Search departments', 'ldap-staff-directory' ); ?>
		</label>
		<input
			type="search"
			id="ldap-dept-search-input"
			class="ldap-search ldap-dept-search"
			placeholder="<?php esc_attr_e( 'Search department…', 'ldap-staff-directory' ); ?>"
			aria-label="<?php esc_attr_e( 'Search department', 'ldap-staff-directory' ); ?>"
		>
	</div>
	<?php endif; ?>

	<?php if ( empty( $ldap_ed_departments ) ) : ?>
		<p class="ldap-no-results"><?php esc_html_e( 'No departments found.', 'ldap-staff-directory' ); ?></p>
	<?php else : ?>

	<p class="ldap-menu-eyebrow"><?php esc_html_e( 'Departments', 'ldap-staff-directory' ); ?></p>

	<div class="ldap-dept-menu-list">
		<?php foreach ( $ldap_ed_departments as $ldap_ed_dept_name => $ldap_ed_dept_count ) :
			$ldap_ed_dept_avatar = $this->get_initials_and_color( $ldap_ed_dept_name, true );
			$ldap_ed_dept_url    = esc_url( add_query_arg( array( 'ldap_dept' => $ldap_ed_dept_name, 'ldap_page' => 1 ) ) );
		?>
		<a
			href="<?php echo $ldap_ed_dept_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already passed through esc_url() above ?>"
			class="ldap-dept-row"
			data-name="<?php echo esc_attr( $ldap_ed_dept_name ); ?>"
		>
			<span
				class="ldap-dept-row-avatar"
				aria-hidden="true"
				style="--ldap-avatar-bg:<?php echo esc_attr( $ldap_ed_dept_avatar['color'] ); ?>"
			><?php echo esc_html( $ldap_ed_dept_avatar['initials'] ); ?></span>
			<span class="ldap-dept-row-info">
				<span class="ldap-dept-row-name"><?php echo esc_html( $ldap_ed_dept_name ); ?></span>
				<span class="ldap-dept-row-count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of employees in this department */
						_n( '%s employee', '%s employees', $ldap_ed_dept_count, 'ldap-staff-directory' ),
						absint( $ldap_ed_dept_count )
					)
				);
				?>
				</span>
			</span>
			<?php echo $this->get_icon_svg( 'chevron-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG markup, no user input ?>
		</a>
		<?php endforeach; ?>
	</div>

	<p class="ldap-no-results ldap-no-results--search ldap-dept-no-results" hidden>
		<?php esc_html_e( 'No departments found.', 'ldap-staff-directory' ); ?>
	</p>

	<?php endif; ?>

</div><!-- /.ldap-directory-wrap -->
