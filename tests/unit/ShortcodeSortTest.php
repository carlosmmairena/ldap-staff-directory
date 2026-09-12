<?php
/**
 * Pure-logic tests for LDAP_ED_Shortcode::sort_users() — the employee_order
 * setting's comparator. No LDAP, no WP options touched; sort_users() is
 * accessed via reflection since it's a private helper.
 *
 * @package LDAP_Staff_Directory
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ShortcodeSortTest extends TestCase {

	private LDAP_ED_Shortcode $shortcode;

	public function set_up() {
		parent::set_up();
		$this->shortcode = new LDAP_ED_Shortcode();
	}

	/**
	 * Calls the private sort_users() method via reflection.
	 */
	private function sort( array $users, string $order ): array {
		$method = new ReflectionMethod( LDAP_ED_Shortcode::class, 'sort_users' );
		$method->setAccessible( true );
		return $method->invoke( $this->shortcode, $users, $order );
	}

	private function names( array $users ): array {
		return array_column( $users, 'name' );
	}

	public function test_name_asc_is_the_default_and_matches_the_previous_hardcoded_behavior() {
		$users = array(
			array( 'name' => 'Beatriz Soto', 'title' => '' ),
			array( 'name' => 'Andrés Vega', 'title' => '' ),
			array( 'name' => 'Carlos Mora', 'title' => '' ),
		);

		$this->assertSame(
			array( 'Andrés Vega', 'Beatriz Soto', 'Carlos Mora' ),
			$this->names( $this->sort( $users, 'name_asc' ) )
		);
	}

	public function test_name_desc_reverses_the_order() {
		$users = array(
			array( 'name' => 'Beatriz Soto', 'title' => '' ),
			array( 'name' => 'Andrés Vega', 'title' => '' ),
			array( 'name' => 'Carlos Mora', 'title' => '' ),
		);

		$this->assertSame(
			array( 'Carlos Mora', 'Beatriz Soto', 'Andrés Vega' ),
			$this->names( $this->sort( $users, 'name_desc' ) )
		);
	}

	public function test_title_asc_sorts_by_title() {
		$users = array(
			array( 'name' => 'Ana', 'title' => 'Gerente' ),
			array( 'name' => 'Beto', 'title' => 'Analista' ),
			array( 'name' => 'Coco', 'title' => 'Director' ),
		);

		$this->assertSame(
			array( 'Beto', 'Coco', 'Ana' ),
			$this->names( $this->sort( $users, 'title_asc' ) )
		);
	}

	public function test_title_desc_reverses_the_title_order() {
		$users = array(
			array( 'name' => 'Ana', 'title' => 'Gerente' ),
			array( 'name' => 'Beto', 'title' => 'Analista' ),
			array( 'name' => 'Coco', 'title' => 'Director' ),
		);

		$this->assertSame(
			array( 'Ana', 'Coco', 'Beto' ),
			$this->names( $this->sort( $users, 'title_desc' ) )
		);
	}

	public function test_title_tie_breaks_by_name_ascending_under_title_asc() {
		$users = array(
			array( 'name' => 'Beatriz Soto', 'title' => 'Analista' ),
			array( 'name' => 'Andrés Vega', 'title' => 'Analista' ),
		);

		$this->assertSame(
			array( 'Andrés Vega', 'Beatriz Soto' ),
			$this->names( $this->sort( $users, 'title_asc' ) )
		);
	}

	public function test_title_tie_still_breaks_by_name_ascending_under_title_desc() {
		// Tie-break direction never inherits the primary (title) direction.
		$users = array(
			array( 'name' => 'Beatriz Soto', 'title' => 'Analista' ),
			array( 'name' => 'Andrés Vega', 'title' => 'Analista' ),
		);

		$this->assertSame(
			array( 'Andrés Vega', 'Beatriz Soto' ),
			$this->names( $this->sort( $users, 'title_desc' ) )
		);
	}

	public function test_empty_titles_group_together_and_sort_by_name_ascending() {
		$users = array(
			array( 'name' => 'Zoraida Paz', 'title' => '' ),
			array( 'name' => 'Ana Ruiz', 'title' => 'Directora' ),
			array( 'name' => 'Andrés Vega', 'title' => '' ),
		);

		// Empty string sorts before any non-empty title under strcmp(), so the
		// untitled pair leads, ordered between themselves by name ascending.
		$this->assertSame(
			array( 'Andrés Vega', 'Zoraida Paz', 'Ana Ruiz' ),
			$this->names( $this->sort( $users, 'title_asc' ) )
		);
	}

	public function test_unknown_order_value_falls_back_to_name_asc() {
		$users = array(
			array( 'name' => 'Beatriz Soto', 'title' => '' ),
			array( 'name' => 'Andrés Vega', 'title' => '' ),
		);

		$this->assertSame(
			array( 'Andrés Vega', 'Beatriz Soto' ),
			$this->names( $this->sort( $users, 'not_a_real_option' ) )
		);
	}

	public function test_sort_is_stable_across_a_page_boundary() {
		$users = array(
			array( 'name' => 'Dana', 'title' => '' ),
			array( 'name' => 'Ana', 'title' => '' ),
			array( 'name' => 'Cami', 'title' => '' ),
			array( 'name' => 'Beto', 'title' => '' ),
		);

		$sorted = $this->sort( $users, 'name_asc' );

		// Same sorted array sliced as two pages of 2 must be gap-free and
		// duplicate-free — i.e. the same single ordering paginate_users() slices.
		$page1 = array_slice( $sorted, 0, 2 );
		$page2 = array_slice( $sorted, 2, 2 );

		$this->assertSame( array( 'Ana', 'Beto' ), $this->names( $page1 ) );
		$this->assertSame( array( 'Cami', 'Dana' ), $this->names( $page2 ) );
	}
}
