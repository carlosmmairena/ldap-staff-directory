<?php
/**
 * LDAP_ED_Connector::get_departments() must NEVER apply excluded_departments /
 * exclude_no_department — it's the only way an admin can re-discover (and later
 * un-exclude) a department that's already hidden from the public directory.
 * Documented invariant in class-ldap-connector.php (get_departments() docblock).
 *
 * @package LDAP_Staff_Directory
 */

require_once __DIR__ . '/ConnectorTestCase.php';

class ConnectorExclusionTest extends ConnectorTestCase {

	public function test_get_departments_lists_the_expected_counts() {
		$result = $this->make_connector()->get_departments();

		$counts = array();
		foreach ( $result['departments'] as $dept ) {
			$counts[ $dept['name'] ] = $dept['count'];
		}

		$this->assertSame(
			array(
				'Engineering' => 2,
				'Marketing'   => 1,
				'Operations'  => 510,
				'Research'    => 1,
				'Sales'       => 1,
				'Support'     => 1,
			),
			$counts
		);
		$this->assertSame( 2, $result['no_department_count'] );
	}

	public function test_get_departments_ignores_excluded_departments_setting() {
		$with_exclusions    = $this->make_connector( array( 'excluded_departments' => array( 'Engineering', 'Operations' ) ) )->get_departments();
		$without_exclusions = $this->make_connector()->get_departments();

		$this->assertSame( $without_exclusions, $with_exclusions );
	}

	public function test_get_departments_ignores_exclude_no_department_setting() {
		$with_exclusion    = $this->make_connector( array( 'exclude_no_department' => '1' ) )->get_departments();
		$without_exclusion = $this->make_connector()->get_departments();

		$this->assertSame( $without_exclusion, $with_exclusion );
		$this->assertSame( 2, $with_exclusion['no_department_count'] );
	}
}
