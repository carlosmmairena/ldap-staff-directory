<?php
/**
 * LDAP_ED_Connector::search_paged() — RFC 2696 paged search. The fixture has
 * 518 person entries specifically to force more than one page: the page size
 * is hardcoded to 500 in class-ldap-connector.php.
 *
 * @package LDAP_Staff_Directory
 */

require_once __DIR__ . '/ConnectorTestCase.php';

class ConnectorPaginationTest extends ConnectorTestCase {

	public function test_get_users_aggregates_across_more_than_one_page() {
		$users = $this->make_connector()->get_users();

		// If the paging cookie were dropped after the first page, this would be
		// capped at 500 (the hardcoded page size) instead of the full fixture.
		$this->assertGreaterThan( 500, count( $users ) );
		$this->assertCount( 518, $users );
	}

	public function test_get_departments_aggregates_across_more_than_one_page() {
		$result = $this->make_connector()->get_departments();

		$this->assertIsArray( $result );
		$total_from_departments = array_sum( array_column( $result['departments'], 'count' ) )
			+ $result['no_department_count'];

		$this->assertSame( 518, $total_from_departments );
	}
}
