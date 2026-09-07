<?php
/**
 * Department detail template — rendered when a department is selected
 * (ldap_dept present). Shows a breadcrumb back to the department menu, the
 * active department's header, and the existing filtered/paginated employee
 * grid.
 *
 * Variables available from class-shortcode.php:
 *   $ldap_ed_users         — array of user arrays for the current page
 *   $ldap_ed_fields        — array of field keys to display
 *   $ldap_ed_per_page      — int, items per page
 *   $ldap_ed_enable_search — bool, show the employee search input
 *   $ldap_ed_current_dept  — string, active department (always non-empty here)
 *   $ldap_ed_search_query  — string, active search term
 *   $ldap_ed_current_page  — int, current page number
 *   $ldap_ed_total_pages   — int, total page count
 *   $ldap_ed_total_count   — int, total filtered employee count (this department)
 *   $ldap_ed_prev_url      — string|null, URL for previous page (null = disabled)
 *   $ldap_ed_next_url      — string|null, URL for next page (null = disabled)
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ldap_ed_dept_avatar = $this->get_initials_and_color( $ldap_ed_current_dept, true );
?>
<div class="ldap-directory-wrap">

	<a
		href="<?php echo esc_url( remove_query_arg( array( 'ldap_dept', 'ldap_page' ) ) ); ?>"
		class="ldap-breadcrumb"
	>
		<?php echo $this->get_icon_svg( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG markup, no user input ?>
		<?php esc_html_e( 'Departments', 'ldap-staff-directory' ); ?>
	</a>

	<div class="ldap-dept-header">
		<div class="ldap-dept-header-info">
			<h2 class="ldap-dept-header-name"><?php echo esc_html( $ldap_ed_current_dept ); ?></h2>
			<p class="ldap-dept-header-sub">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of employees in this department */
					_n( '%s employee in this department', '%s employees in this department', $ldap_ed_total_count, 'ldap-staff-directory' ),
					absint( $ldap_ed_total_count )
				)
			);
			?>
			</p>
		</div>
		<span
			class="ldap-dept-header-badge"
			aria-hidden="true"
			style="--ldap-avatar-bg:<?php echo esc_attr( $ldap_ed_dept_avatar['color'] ); ?>"
		><?php echo esc_html( $ldap_ed_dept_avatar['initials'] ); ?></span>
	</div>

	<?php if ( $ldap_ed_enable_search ) : ?>
	<div class="ldap-search-wrap">
		<form
			method="get"
			action="<?php echo esc_url( remove_query_arg( array( 'ldap_page', 'ldap_search', 'ldap_dept' ) ) ); ?>"
			class="ldap-search-form"
			role="search"
		>
			<label for="ldap-search-input" class="screen-reader-text">
				<?php esc_html_e( 'Search employees', 'ldap-staff-directory' ); ?>
			</label>
			<input type="hidden" name="ldap_dept" value="<?php echo esc_attr( $ldap_ed_current_dept ); ?>">
			<input
				type="search"
				id="ldap-search-input"
				name="ldap_search"
				class="ldap-search"
				placeholder="<?php
				echo esc_attr(
					sprintf(
						/* translators: %s: department name */
						__( 'Search in %s…', 'ldap-staff-directory' ),
						$ldap_ed_current_dept
					)
				);
				?>"
				aria-label="<?php esc_attr_e( 'Search employee', 'ldap-staff-directory' ); ?>"
				value="<?php echo esc_attr( $ldap_ed_search_query ); ?>"
			>
		</form>
	</div>
	<?php endif; ?>

	<div class="ldap-directory-grid" aria-live="polite">

		<?php if ( empty( $ldap_ed_users ) ) : ?>
			<p class="ldap-no-results"><?php esc_html_e( 'No employees found.', 'ldap-staff-directory' ); ?></p>
		<?php else : ?>
			<?php foreach ( $ldap_ed_users as $ldap_ed_user ) :
				$ldap_ed_name   = $ldap_ed_user['name'] ?? '';
				$ldap_ed_avatar = $this->get_initials_and_color( $ldap_ed_name );
				$ldap_initials  = $ldap_ed_avatar['initials'];

				// Fall back to first letter of email when name is absent.
				if ( '' === $ldap_initials && ! empty( $ldap_ed_user['email'] ) ) {
					$ldap_initials = strtoupper( substr( $ldap_ed_user['email'], 0, 1 ) );
				}

				$ldap_avatar_bg = $ldap_ed_avatar['color'];
			?>
			<article class="ldap-staff-card">
				<div
					class="ldap-card-avatar"
					aria-hidden="true"
					style="--ldap-avatar-bg:<?php echo esc_attr( $ldap_avatar_bg ); ?>"
				><?php echo esc_html( $ldap_initials ); ?></div>

				<?php if ( in_array( 'name', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['name'] ) ) : ?>
				<h3 class="ldap-name"><?php echo esc_html( $ldap_ed_user['name'] ); ?></h3>
				<?php endif; ?>

				<?php if ( in_array( 'title', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['title'] ) ) : ?>
				<p class="ldap-title">
					<?php echo $this->get_icon_svg( 'briefcase' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG markup, no user input ?>
					<?php echo esc_html( $ldap_ed_user['title'] ); ?>
				</p>
				<?php endif; ?>

				<?php if ( in_array( 'department', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['department'] ) ) : ?>
				<p class="ldap-department">
					<span class="ldap-dept-badge"><?php echo esc_html( $ldap_ed_user['department'] ); ?></span>
				</p>
				<?php endif; ?>

				<?php if ( in_array( 'email', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['email'] ) ) : ?>
				<a class="ldap-email" href="mailto:<?php echo esc_attr( $ldap_ed_user['email'] ); ?>">
					<?php echo $this->get_icon_svg( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG markup, no user input ?>
					<?php echo esc_html( $ldap_ed_user['email'] ); ?>
				</a>
				<?php endif; ?>

				<?php if ( in_array( 'phone', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['phone'] ) ) : ?>
				<a class="ldap-phone" href="tel:<?php echo esc_attr( $ldap_ed_user['phone'] ); ?>">
					<?php echo $this->get_icon_svg( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG markup, no user input ?>
					<?php echo esc_html( $ldap_ed_user['phone'] ); ?>
				</a>
				<?php endif; ?>

				<?php if ( in_array( 'extension', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['extension'] ) ) : ?>
				<span class="ldap-extension">
					<?php echo $this->get_icon_svg( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG markup, no user input ?>
					<?php echo esc_html( $ldap_ed_user['extension'] ); ?>
				</span>
				<?php endif; ?>
			</article>
			<?php endforeach; ?>
		<?php endif; ?>

	</div><!-- /.ldap-directory-grid -->

	<?php if ( $ldap_ed_total_pages > 1 ) : ?>
	<nav class="ldap-pagination" aria-label="<?php esc_attr_e( 'Directory pagination', 'ldap-staff-directory' ); ?>">

		<?php if ( null !== $ldap_ed_prev_url ) : ?>
		<a href="<?php echo esc_url( $ldap_ed_prev_url ); ?>" class="ldap-btn ldap-prev">
			&laquo; <?php esc_html_e( 'Previous', 'ldap-staff-directory' ); ?>
		</a>
		<?php else : ?>
		<span class="ldap-btn ldap-prev" aria-disabled="true">
			&laquo; <?php esc_html_e( 'Previous', 'ldap-staff-directory' ); ?>
		</span>
		<?php endif; ?>

		<span class="ldap-page-info">
		<?php if ( $ldap_ed_total_count > 0 ) :
			$ldap_ed_from = absint( ( $ldap_ed_current_page - 1 ) * $ldap_ed_per_page + 1 );
			$ldap_ed_to   = absint( min( $ldap_ed_current_page * $ldap_ed_per_page, $ldap_ed_total_count ) );
			echo esc_html(
				sprintf(
					/* translators: 1: first record number on page, 2: last record number on page, 3: total filtered records, 4: department name */
					__( 'Showing %1$s–%2$s of %3$s in %4$s', 'ldap-staff-directory' ),
					absint( $ldap_ed_from ),
					absint( $ldap_ed_to ),
					absint( $ldap_ed_total_count ),
					$ldap_ed_current_dept
				)
			);
		endif; ?>
		</span>

		<?php if ( null !== $ldap_ed_next_url ) : ?>
		<a href="<?php echo esc_url( $ldap_ed_next_url ); ?>" class="ldap-btn ldap-next">
			<?php esc_html_e( 'Next', 'ldap-staff-directory' ); ?> &raquo;
		</a>
		<?php else : ?>
		<span class="ldap-btn ldap-next" aria-disabled="true">
			<?php esc_html_e( 'Next', 'ldap-staff-directory' ); ?> &raquo;
		</span>
		<?php endif; ?>

	</nav>
	<?php endif; ?>

</div><!-- /.ldap-directory-wrap -->
