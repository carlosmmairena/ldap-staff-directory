<?php
/**
 * LDAP_ED_Cache: transient (TTL) + option (stale) fallback behavior.
 *
 * @package LDAP_Staff_Directory
 */

class CacheTest extends WP_UnitTestCase {

	private $cache;

	public function set_up() {
		parent::set_up();
		$this->cache = new LDAP_ED_Cache( 'ldap_ed_cache_test', 3600 );
	}

	public function test_set_then_get_returns_the_data() {
		$this->cache->set( array( 'name' => 'Ada Lovelace' ) );

		$this->assertSame( array( 'name' => 'Ada Lovelace' ), $this->cache->get() );
		$this->assertTrue( $this->cache->has() );
	}

	public function test_get_stale_survives_after_set() {
		$this->cache->set( array( 'name' => 'Grace Hopper' ) );

		$this->assertSame( array( 'name' => 'Grace Hopper' ), $this->cache->get_stale() );
	}

	public function test_flush_removes_fresh_but_preserves_stale() {
		$this->cache->set( array( 'name' => 'Alan Turing' ) );

		$this->cache->flush();

		$this->assertFalse( $this->cache->get() );
		$this->assertFalse( $this->cache->has() );
		$this->assertSame( array( 'name' => 'Alan Turing' ), $this->cache->get_stale() );
	}

	public function test_purge_removes_both_fresh_and_stale() {
		$this->cache->set( array( 'name' => 'Katherine Johnson' ) );

		$this->cache->purge();

		$this->assertFalse( $this->cache->get() );
		$this->assertFalse( $this->cache->get_stale() );
	}

	public function test_empty_cache_has_no_fresh_or_stale_data() {
		$this->assertFalse( $this->cache->get() );
		$this->assertFalse( $this->cache->get_stale() );
		$this->assertFalse( $this->cache->has() );
	}
}
