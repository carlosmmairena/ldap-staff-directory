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

		// Extract department list from the full (unfiltered) set for correct chip counts.
		$ldap_ed_department_order = $settings['department_order'] ?? 'alpha';
		$ldap_ed_departments      = $this->extract_departments( $all_users, $ldap_ed_department_order );
		$ldap_ed_all_count        = count( $all_users );

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
