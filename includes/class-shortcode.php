<?php
/**
 * Shortcode [ldap_directory] — renders the employee directory.
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDAP_ED_Shortcode {

	/**
	 * Deterministic avatar background colors, shared between employee and
	 * department avatars. Color is picked via crc32( $name ) % count( palette ).
	 */
	private const AVATAR_PALETTE = array(
		'#4f7df3', '#7c5cbf', '#0e9b8a',
		'#2e9e4f', '#c0392b', '#d35400',
		'#1a7bbf', '#8e44ad',
	);

	/**
	 * Inline SVG icon paths (Lucide), keyed by icon name. Static, trusted
	 * markup — never built from user input.
	 */
	private const ICONS = array(
		'briefcase'     => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
		'mail'          => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'phone'         => '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/>',
		'building'      => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
		'arrow-left'    => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
	);

	public function __construct() {
		add_shortcode( 'ldap_directory', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register front-end CSS and JS, and eagerly enqueue when the shortcode is
	 * present in the current singular post's content.
	 */
	public function register_assets() {
		wp_register_style(
			'ldap-ed-public',
			LDAP_ED_URL . 'public/css/directory.css',
			array(),
			LDAP_ED_VERSION
		);

		wp_register_script(
			'ldap-ed-public',
			LDAP_ED_URL . 'public/js/directory.js',
			array(),
			LDAP_ED_VERSION,
			true
		);

		// Enqueue early (in <head>) when the shortcode is directly in the post content.
		// Page builder integrations fall back to the enqueue inside render().
		global $post;
		if ( is_singular() && $post instanceof WP_Post && has_shortcode( $post->post_content, 'ldap_directory' ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Enqueue the registered assets.
	 * Safe to call multiple times — WordPress ignores duplicate enqueues.
	 */
	private function enqueue_assets() {
		wp_enqueue_style( 'ldap-ed-public' );
		wp_enqueue_script( 'ldap-ed-public' );
	}

	/**
	 * Render the directory HTML.
	 *
	 * Renders one of two views depending on whether a department is selected:
	 * - No department (ldap_dept empty): department menu (department-menu.php).
	 * - Department present: department detail — filtered/paginated employee
	 *   grid (directory.php), unchanged from the pre-existing behavior.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ) {
		// Enqueue assets here as a fallback for page builders (Elementor, Beaver Builder)
		// that do not store shortcodes in post_content and therefore bypass register_assets().
		$this->enqueue_assets();

		$settings = get_option( LDAP_ED_OPTION_KEY, array() );

		$atts = shortcode_atts(
			array(
				'search'   => $settings['enable_search'] ?? '1',
				'per_page' => $settings['per_page'] ?? 20,
				'fields'   => implode( ',', (array) ( $settings['fields'] ?? array( 'name', 'email', 'title', 'department' ) ) ),
			),
			$atts,
			'ldap_directory'
		);

		// Resolve field list from the shortcode attribute.
		$allowed_fields       = array( 'name', 'email', 'title', 'department', 'phone', 'extension' );
		$ldap_ed_fields       = array_intersect(
			array_map( 'trim', explode( ',', $atts['fields'] ) ),
			$allowed_fields
		);

		$ldap_ed_per_page      = max( 1, absint( $atts['per_page'] ) );
		$ldap_ed_enable_search = filter_var( $atts['search'], FILTER_VALIDATE_BOOLEAN );

		// Read and sanitize query params for server-side filtering and pagination.
		$query_params         = $this->get_query_params();
		$ldap_ed_search_query = $query_params['search'];
		$ldap_ed_current_dept = $query_params['dept'];
		$ldap_ed_current_page = $query_params['page'];

		// Retrieve full user list from cache or LDAP.
		$all_users = $this->get_users( $settings );
		if ( is_wp_error( $all_users ) ) {
			return '<p class="ldap-ed-error">' . esc_html( $all_users->get_error_message() ) . '</p>';
		}

		// Extract department list (with counts) from the full, unfiltered set.
		$ldap_ed_department_order = $settings['department_order'] ?? 'alpha';
		$ldap_ed_departments      = $this->extract_departments( $all_users, $ldap_ed_department_order );
		$ldap_ed_all_count        = count( $all_users );

		// No department selected: render the department menu. No filtering or
		// pagination runs for this state — it's a flat list of all departments.
		if ( '' === $ldap_ed_current_dept ) {
			ob_start();
			include LDAP_ED_DIR . 'public/views/department-menu.php';
			return ob_get_clean();
		}

		// Apply department and search filters in PHP.
		$filtered_users = $this->filter_users( $all_users, $ldap_ed_search_query, $ldap_ed_current_dept );

		// Paginate the filtered result.
		$pagination            = $this->paginate_users( $filtered_users, $ldap_ed_current_page, $ldap_ed_per_page );
		$ldap_ed_users         = $pagination['users'];
		$ldap_ed_total_count   = $pagination['total'];
		$ldap_ed_total_pages   = $pagination['total_pages'];
		$ldap_ed_current_page  = $pagination['current_page']; // may be clamped if out of range

		// Build Previous / Next URLs (null when the button should be disabled).
		$ldap_ed_prev_url = $ldap_ed_current_page > 1 ? $this->build_nav_url( $ldap_ed_current_page - 1 ) : null;
		$ldap_ed_next_url = $ldap_ed_current_page < $ldap_ed_total_pages ? $this->build_nav_url( $ldap_ed_current_page + 1 ) : null;

		ob_start();
		include LDAP_ED_DIR . 'public/views/directory.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Shared display helpers (avatars, icons) — used by both views' templates.

	/**
	 * Computes deterministic initials and an avatar background color for a
	 * name (employee or department). Same color formula for both: crc32( $name )
	 * modulo the shared 8-color palette.
	 *
	 * @param string $name            Employee or department name.
	 * @param bool   $force_two_chars When true, a single-word name still yields
	 *                                2-letter initials (used for department
	 *                                abbreviations). Employee avatars keep the
	 *                                pre-existing 1-letter behavior by default.
	 * @return array{initials:string,color:string}
	 */
	public function get_initials_and_color( string $name, bool $force_two_chars = false ): array {
		$parts    = preg_split( '/\s+/', trim( $name ), 2 );
		$initials = strtoupper( substr( $parts[0] ?? '', 0, 1 ) );

		if ( ! empty( $parts[1] ) ) {
			$initials .= strtoupper( substr( $parts[1], 0, 1 ) );
		} elseif ( $force_two_chars ) {
			$initials .= strtoupper( substr( $parts[0] ?? '', 1, 1 ) );
		}

		$color = self::AVATAR_PALETTE[ abs( crc32( $name ) ) % count( self::AVATAR_PALETTE ) ];

		return array(
			'initials' => $initials,
			'color'    => $color,
		);
	}

	/**
	 * Returns inline SVG markup for a static, trusted icon (Lucide-style).
	 * Never built from user input — safe to echo without escaping.
	 *
	 * @param string $name Icon key, see self::ICONS.
	 * @return string SVG markup, or '' when the icon name is unknown.
	 */
	public function get_icon_svg( string $name ): string {
		if ( ! isset( self::ICONS[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="ldap-icon ldap-icon-%1$s" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
			esc_attr( $name ),
			self::ICONS[ $name ]
		);
	}

	// -------------------------------------------------------------------------
	// Server-side filtering / pagination helpers

	/**
	 * Read and sanitize the ldap_page, ldap_search, and ldap_dept query params.
	 *
	 * @return array { page: int, search: string, dept: string }
	 */
	private function get_query_params(): array {
		$page   = max( 1, absint( wp_unslash( $_GET['ldap_page'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_GET['ldap_search'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$dept   = sanitize_text_field( wp_unslash( $_GET['ldap_dept'] ?? '' ) );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return compact( 'page', 'search', 'dept' );
	}

	/**
	 * Filter users by department and/or search query.
	 *
	 * @param array  $users  Full user array from cache.
	 * @param string $search Search string (empty = no filter).
	 * @param string $dept   Department name (empty = no filter).
	 * @return array Filtered array (re-indexed).
	 */
	private function filter_users( array $users, string $search, string $dept ): array {
		if ( '' !== $dept ) {
			$users = array_values(
				array_filter(
					$users,
					static function ( $user ) use ( $dept ) {
						return 0 === strcasecmp( trim( $user['department'] ?? '' ), $dept );
					}
				)
			);
		}

		if ( '' !== $search ) {
			$users = array_values(
				array_filter(
					$users,
					static function ( $user ) use ( $search ) {
						foreach ( array( 'name', 'email', 'title', 'department', 'phone', 'extension' ) as $field ) {
							if ( false !== stripos( $user[ $field ] ?? '', $search ) ) {
								return true;
							}
						}
						return false;
					}
				)
			);
		}

		return $users;
	}

	/**
	 * Extract unique, non-empty departments from all users with their employee counts.
	 *
	 * @param array  $all_users Full (unfiltered) user array.
	 * @param string $order     'alpha' (default) or 'count_desc'.
	 * @return array Associative array [ 'Department Name' => count ].
	 */
	private function extract_departments( array $all_users, string $order = 'alpha' ): array {
		$counts = array();
		foreach ( $all_users as $user ) {
			$dept = trim( $user['department'] ?? '' );
			if ( '' === $dept ) {
				continue;
			}
			if ( ! isset( $counts[ $dept ] ) ) {
				$counts[ $dept ] = 0;
			}
			++$counts[ $dept ];
		}

		if ( 'count_desc' === $order ) {
			arsort( $counts );
		} else {
			ksort( $counts );
		}

		return $counts;
	}

	/**
	 * Slice a filtered user array for the requested page.
	 *
	 * @param array $users    Filtered user array.
	 * @param int   $page     Requested page (1-based).
	 * @param int   $per_page Items per page.
	 * @return array { users: array, total: int, total_pages: int, current_page: int, offset: int }
	 */
	private function paginate_users( array $users, int $page, int $per_page ): array {
		$total       = count( $users );
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
		$total_pages = max( 1, $total_pages );
		$page        = min( max( 1, $page ), $total_pages );
		$offset      = ( $page - 1 ) * $per_page;
		return array(
			'users'        => array_slice( $users, $offset, $per_page ),
			'total'        => $total,
			'total_pages'  => $total_pages,
			'current_page' => $page,
			'offset'       => $offset,
		);
	}

	/**
	 * Build a pagination URL for the given page number, preserving all current
	 * ldap_* query params except ldap_page which is replaced.
	 *
	 * @param int $page Target page number.
	 * @return string URL (not escaped — caller must use esc_url()).
	 */
	private function build_nav_url( int $page ): string {
		return add_query_arg( 'ldap_page', $page, remove_query_arg( 'ldap_page' ) );
	}

	// -------------------------------------------------------------------------

	/**
	 * Return users from cache or fetch fresh from LDAP.
	 *
	 * @param array $settings Plugin settings.
	 * @return array|\WP_Error
	 */
	private function get_users( array $settings ) {
		$ttl   = absint( $settings['cache_ttl'] ?? 60 ) * 60; // convert minutes → seconds
		$cache = new LDAP_ED_Cache( LDAP_ED_CACHE_KEY, $ttl );
		$users = $cache->get();

		if ( false !== $users ) {
			return $users;
		}

		$connector = new LDAP_ED_Connector( $settings );
		$users     = $connector->get_users();

		if ( is_wp_error( $users ) ) {
			// LDAP unreachable — serve stale data if available to avoid showing
			// an error to visitors while the server is temporarily down.
			$stale = $cache->get_stale();
			return ( false !== $stale ) ? $stale : $users;
		}

		$cache->set( $users );
		return $users;
	}
}
