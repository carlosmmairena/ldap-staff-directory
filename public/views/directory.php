<?php
/**
 * Public directory template.
 *
 * Variables available from class-shortcode.php:
 *   $ldap_ed_users         — array of user arrays for the current page
 *   $ldap_ed_fields        — array of field keys to display
 *   $ldap_ed_per_page      — int, items per page
 *   $ldap_ed_enable_search — bool, show search input and filter bar
 *   $ldap_ed_departments   — array [ 'Department' => count ] sorted alphabetically
 *   $ldap_ed_current_dept  — string, active department filter ('' = none)
 *   $ldap_ed_search_query  — string, active search term
 *   $ldap_ed_current_page  — int, current page number
 *   $ldap_ed_total_pages   — int, total page count
 *   $ldap_ed_total_count   — int, total filtered employee count
 *   $ldap_ed_all_count     — int, total unfiltered employee count
 *   $ldap_ed_prev_url      — string|null, URL for previous page (null = disabled)
 *   $ldap_ed_next_url      — string|null, URL for next page (null = disabled)
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Palette for avatar backgrounds — one color is deterministically assigned per employee.
$ldap_avatar_palette = array(
	'#4f7df3', '#7c5cbf', '#0e9b8a',
	'#2e9e4f', '#c0392b', '#d35400',
	'#1a7bbf', '#8e44ad',
);
?>
<div class="ldap-directory-wrap">

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
			<?php if ( '' !== $ldap_ed_current_dept ) : ?>
			<input type="hidden" name="ldap_dept" value="<?php echo esc_attr( $ldap_ed_current_dept ); ?>">
			<?php endif; ?>
			<input
				type="search"
				id="ldap-search-input"
				name="ldap_search"
				class="ldap-search"
				placeholder="<?php esc_attr_e( 'Search employee…', 'ldap-staff-directory' ); ?>"
				aria-label="<?php esc_attr_e( 'Search employee', 'ldap-staff-directory' ); ?>"
				value="<?php echo esc_attr( $ldap_ed_search_query ); ?>"
			>
		</form>
	</div>

	<?php if ( count( $ldap_ed_departments ) >= 2 ) : ?>
	<div class="ldap-dept-filters" role="list" aria-label="<?php esc_attr_e( 'Filter by department', 'ldap-staff-directory' ); ?>">

		<?php // "All" chip — active when no department filter is set. ?>
		<?php $ldap_ed_all_url = esc_url( add_query_arg( 'ldap_page', 1, remove_query_arg( array( 'ldap_dept', 'ldap_page' ) ) ) ); ?>
		<?php if ( '' === $ldap_ed_current_dept ) : ?>
		<span class="ldap-dept-chip is-active" role="listitem" aria-pressed="true">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: total employee count */
					__( 'All (%s)', 'ldap-staff-directory' ),
					absint( $ldap_ed_all_count )
				)
			);
			?>
		</span>
		<?php else : ?>
		<a
			href="<?php echo $ldap_ed_all_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already passed through esc_url() above ?>"
			class="ldap-dept-chip"
			role="listitem"
			aria-pressed="false"
		>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: total employee count */
					__( 'All (%s)', 'ldap-staff-directory' ),
					absint( $ldap_ed_all_count )
				)
			);
			?>
		</a>
		<?php endif; ?>

		<?php foreach ( $ldap_ed_departments as $ldap_ed_dept_name => $ldap_ed_dept_count ) : ?>
			<?php
			$ldap_ed_is_active  = ( 0 === strcasecmp( $ldap_ed_dept_name, $ldap_ed_current_dept ) );
			$ldap_ed_chip_url   = esc_url( add_query_arg( array( 'ldap_dept' => $ldap_ed_dept_name, 'ldap_page' => 1 ) ) );
			$ldap_ed_clear_url  = esc_url( add_query_arg( 'ldap_page', 1, remove_query_arg( array( 'ldap_dept', 'ldap_page' ) ) ) );
			$ldap_ed_chip_label = esc_html( $ldap_ed_dept_name ) . ' (' . absint( $ldap_ed_dept_count ) . ')';
			?>
			<?php if ( $ldap_ed_is_active ) : ?>
			<span class="ldap-dept-chip is-active" role="listitem" aria-pressed="true">
				<?php echo $ldap_ed_chip_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() + absint() applied above ?>
				<a
					href="<?php echo $ldap_ed_clear_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already passed through esc_url() above ?>"
					class="ldap-dept-chip-clear"
					aria-label="<?php esc_attr_e( 'Clear department filter', 'ldap-staff-directory' ); ?>"
				>×</a>
			</span>
			<?php else : ?>
			<a
				href="<?php echo $ldap_ed_chip_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already passed through esc_url() above ?>"
				class="ldap-dept-chip"
				role="listitem"
				aria-pressed="false"
			>
				<?php echo $ldap_ed_chip_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() + absint() applied above ?>
			</a>
			<?php endif; ?>
		<?php endforeach; ?>

	</div>
	<?php endif; ?>
	<?php endif; ?>

	<div class="ldap-directory-grid" aria-live="polite">

		<?php if ( empty( $ldap_ed_users ) ) : ?>
			<p class="ldap-no-results"><?php esc_html_e( 'No employees found.', 'ldap-staff-directory' ); ?></p>
		<?php else : ?>
			<?php foreach ( $ldap_ed_users as $ldap_ed_user ) :
				// Compute initials (up to 2 characters) from the display name.
				$ldap_name     = $ldap_ed_user['name'] ?? '';
				$ldap_parts    = preg_split( '/\s+/', trim( $ldap_name ), 2 );
				$ldap_initials = strtoupper( substr( $ldap_parts[0] ?? '', 0, 1 ) );
				if ( ! empty( $ldap_parts[1] ) ) {
					$ldap_initials .= strtoupper( substr( $ldap_parts[1], 0, 1 ) );
				}
				// Fall back to first letter of email when name is absent.
				if ( '' === $ldap_initials && ! empty( $ldap_ed_user['email'] ) ) {
					$ldap_initials = strtoupper( substr( $ldap_ed_user['email'], 0, 1 ) );
				}

				// Deterministic background color derived from the name.
				$ldap_color_idx = abs( crc32( $ldap_name ) ) % count( $ldap_avatar_palette );
				$ldap_avatar_bg = $ldap_avatar_palette[ $ldap_color_idx ];
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
				<p class="ldap-title"><?php echo esc_html( $ldap_ed_user['title'] ); ?></p>
				<?php endif; ?>

				<?php if ( in_array( 'department', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['department'] ) ) : ?>
				<p class="ldap-department">
					<span class="ldap-dept-badge"><?php echo esc_html( $ldap_ed_user['department'] ); ?></span>
				</p>
				<?php endif; ?>

				<?php if ( in_array( 'email', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['email'] ) ) : ?>
				<a class="ldap-email" href="mailto:<?php echo esc_attr( $ldap_ed_user['email'] ); ?>">
					<?php echo esc_html( $ldap_ed_user['email'] ); ?>
				</a>
				<?php endif; ?>

				<?php if ( in_array( 'phone', $ldap_ed_fields, true ) && ! empty( $ldap_ed_user['phone'] ) ) : ?>
				<a class="ldap-phone" href="tel:<?php echo esc_attr( $ldap_ed_user['phone'] ); ?>">
					<?php echo esc_html( $ldap_ed_user['phone'] ); ?>
				</a>
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
			if ( '' !== $ldap_ed_current_dept ) :
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
			else :
				echo esc_html(
					sprintf(
						/* translators: 1: first record number on page, 2: last record number on page, 3: total records */
						__( 'Showing %1$s–%2$s of %3$s', 'ldap-staff-directory' ),
						absint( $ldap_ed_from ),
						absint( $ldap_ed_to ),
						absint( $ldap_ed_total_count )
					)
				);
			endif;
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
