<?php
/**
 * Tests for the SnapshotMetaFromFileSystem class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\DynamoDBConnector;
use TenUp\WPSnapshots\Snapshots\SnapshotMeta;
use TenUp\WPSnapshots\Snapshots\SnapshotMetaFromFileSystem;
use TenUp\WPSnapshots\SnapshotsFileSystem;
use TenUp\WPSnapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSnapshotMetaFromFileSystem
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\SnapshotMetaFromFileSystem
 */
class TestSnapshotMetaFromFileSystem extends TestCase {

	use PrivateAccess, DirectoryFiltering;

	/**
	 * Test instance
	 * 
	 * @var SnapshotMetaFromFileSystem
	 */
	private $meta;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->meta = ( new Plugin() )->get_instance( SnapshotMetaFromFileSystem::class );
		$this->set_up_directory_filtering();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_directory_filtering();
	}

	/**
	 * @covers ::__construct
	 * @covers \TenUp\WPSnapshots\Snapshots\SnapshotMeta::__construct
	 */
	public function test_constructor() {
		$this->assertInstanceOf( SnapshotMetaFromFileSystem::class, $this->meta );
		$this->assertInstanceOf( SnapshotMeta::class, $this->meta );

		$this->assertInstanceOf( DynamoDBConnector::class, $this->get_private_property( $this->meta, 'db' ) );
		$this->assertInstanceOf( SnapshotsFileSystem::class, $this->get_private_property( $this->meta, 'snapshots_file_system' ) );
	}

	/**
	 * @covers ::save_local
	 * @covers ::get_local
	 */
	public function test_save_and_getlocal() {
		$this->meta->save_local( 'test-id', [ 'test' => 'data', 'repository' => '10up', 'contains_files' => true ] );

		$this->assertFileExists( $this->get_directory_path() . '/test-id/meta.json' );

		$this->assertEquals( '{"test":"data","repository":"10up","contains_files":true}', file_get_contents( $this->get_directory_path() . '/test-id/meta.json' ) );
		$this->assertGreaterThan( 0, filesize( $this->get_directory_path() . '/test-id/meta.json' ) );

		$this->assertEquals( [ 'test' => 'data', 'repository' => '10up', 'contains_files' => true ], $this->meta->get_local( 'test-id', '10up' ) );
	}

	/** @covers ::get_remote */
	public function test_get_remote_when_snapshot_is_empty() {
		/**
		 * DynamoDBClient mock.
		 * 
		 * @var MockObject|DynamoDBConnector $mock_db
		 */
		$mock_db = $this->getMockBuilder( DynamoDBConnector::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_db->method( 'get_snapshot' )
			->willReturn( [] );

		$this->set_private_property( $this->meta, 'db', $mock_db );

		$this->assertEquals( [], $this->meta->get_remote( 'test-id', 'test-repo', 'test-region' ) );
	}

	/** @covers ::get_remote */
	public function test_get_remote_when_snapshot_is_not_empty() {
		/**
		 * DynamoDBClient mock.
		 * 
		 * @var MockObject|DynamoDBConnector $mock_db
		 */
		$mock_db = $this->getMockBuilder( DynamoDBConnector::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_db->method( 'get_snapshot' )
			->willReturn( [ 'test' => 'data' ] );

		$this->set_private_property( $this->meta, 'db', $mock_db );

		$this->assertEquals( [ 'test' => 'data', 'contains_files' => true, 'contains_db' => true, 'repository' => 'test-repo' ], $this->meta->get_remote( 'test-id', 'test-repo', 'test-region' ) );
	}

	/** @covers ::get_remote */
	public function test_get_remote_when_snapshot_is_not_empty_and_contains_files_and_contains_db_are_false() {
		/**
		 * DynamoDBClient mock.
		 * 
		 * @var MockObject|DynamoDBConnector $mock_db
		 */
		$mock_db = $this->getMockBuilder( DynamoDBConnector::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_db->method( 'get_snapshot' )
			->willReturn( [ 'test' => 'data', 'contains_files' => false, 'contains_db' => false ] );

		$this->set_private_property( $this->meta, 'db', $mock_db );

		$this->assertEquals( [ 'test' => 'data', 'contains_files' => false, 'contains_db' => false, 'repository' => 'test-repo' ], $this->meta->get_remote( 'test-id', 'test-repo', 'test-region' ) );
	}

}