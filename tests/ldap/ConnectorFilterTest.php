<?php
/**
 * LDAP_ED_Connector::get_users() filter behavior against the real openldap-test
 * fixture (tests/fixtures/directory.ldif): excluded_departments, exclude_no_department.
 *
 * @package LDAP_Staff_Directory
 */

require_once __DIR__ . '/ConnectorTestCase.php';

class ConnectorFilterTest extends ConnectorTestCase {

	public function test_get_users_returns_everyone_with_no_exclusions_configured() {
		$users = $this->make_connector()->get_users();

		$this->assertIsArray( $users );
		// 6 named entries + 2 with no department + 510 generated bulk entries.
		$this->assertCount( 518, $users );
	}

	public function test_exclude_no_department_removes_only_entries_missing_the_attribute() {
		$users = $this->make_connector( array( 'exclude_no_department' => '1' ) )->get_users();

		$this->assertCount( 516, $users );
		foreach ( $users as $user ) {
			$this->assertNotSame( '', $user['department'], 'No returned user should have an empty department.' );
		}
	}

	public function test_excluded_departments_removes_only_the_named_department() {
		$users = $this->make_connector( array( 'excluded_departments' => array( 'Engineering' ) ) )->get_users();

		$this->assertCount( 516, $users );
		foreach ( $users as $user ) {
			$this->assertNotSame( 'Engineering', $user['department'] );
		}
	}

	public function test_excluded_departments_and_exclude_no_department_combine() {
		$users = $this->make_connector(
			array(
				'excluded_departments'  => array( 'Engineering', 'Sales' ),
				'exclude_no_department' => '1',
			)
		)->get_users();

		// 518 total - 2 Engineering - 1 Sales - 2 no-department = 513.
		$this->assertCount( 513, $users );
	}

	public function test_a_user_carries_the_expected_fields() {
		$users = $this->make_connector()->get_users();
		$ada   = current(
			array_filter( $users, static fn( $u ) => 'ada.lovelace@example.test' === $u['email'] )
		);

		$this->assertNotFalse( $ada, 'Fixture user Ada Lovelace was not found.' );
		$this->assertSame( 'Ada Lovelace', $ada['name'] );
		$this->assertSame( 'Principal Engineer', $ada['title'] );
		$this->assertSame( 'Engineering', $ada['department'] );
		$this->assertSame( '+1 555 0101', $ada['phone'] );
		$this->assertSame( '1001', $ada['extension'] );
	}
}
