<?php
/**
 * Tests for the SnapshotCreator class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\Snapshots\FileZipper;
use TenUp\WPSnapshots\Snapshots\SnapshotCreator;
use TenUp\WPSnapshots\Snapshots\SnapshotMeta;
use TenUp\WPSnapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\WPSnapshots\WPCLICommands\Create\WPCLIDumper;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Class TestSnapshotCreator
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\SnapshotCreator
 */
class TestSnapshotCreator extends TestCase {

	use PrivateAccess, WPCLIMocking, DirectoryFiltering;

	/**
	 * Test instance
	 * 
	 * @var SnapshotCreator
	 */
	private $snapshot_creator;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->snapshot_creator = ( new Plugin() )->get_instance( SnapshotCreator::class );
		$this->set_up_wp_cli_mock();
		$this->set_up_directory_filtering();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
		$this->tear_down_directory_filtering();
	}

	/**
	 * @covers ::__construct
	 */
	public function test_constructor() {
		$this->assertInstanceOf( SnapshotCreator::class, $this->snapshot_creator );
	}

	/**
	 * @covers ::create
	 */
	public function test_create() {
		/**
		 * FileZipper mock
		 * 
		 * @var MockObject $mock_file_zipper
		 */
		$mock_file_zipper = $this->createMock( FileZipper::class );

		/**
		 * DBDumper mock.
		 * 
		 * @var MockObject $mock_dumper
		 */
		$mock_dumper = $this->createMock( WPCLIDumper::class );

		/**
		 * SnapshotFiles mock
		 * 
		 * @var MockObject $mock_snapshot_files
		 */
		$mock_snapshot_files = $this->createMock( SnapshotFiles::class );
		$mock_snapshot_files->method( 'get_file_size' )
			->willReturn( 1000 );

		/**
		 * SnapshotMeta mock.
		 * 
		 * @var MockObject $mock_snapshot_meta
		 */
		$mock_snapshot_meta = $this->createMock( SnapshotMeta::class );


		$this->set_private_property( $this->snapshot_creator, 'file_zipper', $mock_file_zipper );
		$this->set_private_property( $this->snapshot_creator, 'dumper', $mock_dumper );
		$this->set_private_property( $this->snapshot_creator, 'snapshot_files', $mock_snapshot_files );
		$this->set_private_property( $this->snapshot_creator, 'meta', $mock_snapshot_meta );

		$test_id = 'test-id';
		$test_args = ['contains_db' => true, 'contains_files' => true];

		$mock_file_zipper->expects( $this->once() )
			->method( 'zip_files' )
			->with( $test_id, array_merge( $test_args, [ 'db_size' => 1000 ] ) );

		$mock_dumper->expects( $this->once() )
			->method( 'dump' )
			->with( $test_id, $test_args );

		$mock_snapshot_meta->expects( $this->once() )
			->method( 'generate' )
			->with( $test_id, array_merge( $test_args, [ 'db_size' => 1000, 'files_size' => 1000 ] ) );

		$this->snapshot_creator->create( $test_args, $test_id );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_will_throw_exception_if_nothing_to_create() {
		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Snapshot must contain either database or files.' );

		$this->snapshot_creator->create( [], 'test-id' );
	}
}

