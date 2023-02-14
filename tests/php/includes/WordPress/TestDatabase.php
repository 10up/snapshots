<?php
/**
 * Tests for the Database class.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\WordPress;

use TenUp\Snapshots\Exceptions\WPSnapshotsException;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use TenUp\Snapshots\WordPress\Database;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDatabase
 *
 * @package TenUp\Snapshots\Tests
 * 
 * @coversDefaultClass \TenUp\Snapshots\WordPress\Database
 */
class TestDatabase extends TestCase {
	
    use PrivateAccess;

	/**
	 * Database instance.
	 * 
	 * @var Database
	 */
	private $wordpress_database;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->wordpress_database = ( new Snapshots() )->get_instance( Database::class );
	}

	public function test_constructor() {
		$this->assertInstanceOf( Database::class, $this->wordpress_database );
	}

	/**
	 * @covers ::get_tables
	 * @covers ::get_wpdb
	 */	
	public function test_get_tables() {
		global $wpdb;

		// Insert table if it doesn't exist.
		$wpdb->query( "CREATE TABLE IF NOT EXISTS test_table (id INT)" );

		$tables = $this->wordpress_database->get_tables();

		$this->assertIsArray( $tables );
		$this->assertNotEmpty( $tables );

		$this->assertFalse( in_array( 'test_table', $tables, true ) );

		$tables = $this->wordpress_database->get_tables( false );

		$this->assertTrue( in_array( 'test_table', $tables, true ) );

		// Drop table if it exists.
		$wpdb->query( "DROP TABLE IF EXISTS test_table" );
	}
	
	/**
	 * @covers ::rename_table
	 * @covers ::get_wpdb
	 */
	public function test_rename_table() {
		global $wpdb;

		// Insert table without the wp_ prefix.
		$wpdb->query( "CREATE TABLE IF NOT EXISTS test_table (id INT)" );

		$this->wordpress_database->rename_table( 'test_table', 'test_table_renamed' );

		$tables = $this->wordpress_database->get_tables( false );

		$this->assertFalse( in_array( 'test_table', $tables, true ) );
		$this->assertTrue( in_array( 'test_table_renamed', $tables, true ) );

		// Clean up.
		$wpdb->query( "DROP TABLE IF EXISTS test_table_renamed" );
		$wpdb->query( "DROP TABLE IF EXISTS test_table" );

	}

	/** @covers ::get_blog_prefix */
	public function test_get_block_prefix() {
		$prefix = $this->wordpress_database->get_blog_prefix();

		$this->assertIsString( $prefix );

		$this->assertEquals( 'wp_', $prefix );
	}


}