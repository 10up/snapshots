<?php
/**
 * Tests covering the Create command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\FileZipper;
use TenUp\WPSnapshots\Snapshots\SnapshotMeta;
use TenUp\WPSnapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\Create;
use TenUp\WPSnapshots\WPCLICommands\Create\WPCLIDBExport;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestCreate
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Create
 */
class TestCreate extends TestCase {

	use PrivateAccess, WPCLIMocking, CommandTests;

	/**
	 * Create instance.
	 * 
	 * @var Create
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Plugin() )->get_instance( Create::class );

		$this->set_up_wp_cli_mock();

	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
	}

	/**
	 * Test that the command instance extends WPCLICommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPCLICommand::class, $this->command );
		$this->test_command_tests();
	}

	/** @covers ::get_command */
	public function test_get_command() {
		$this->assertEquals( 'create', $this->call_private_method( $this->command, 'get_command' ) );
	}

	/**
	 * @covers ::execute
	 * @covers ::get_create_args
	 * @covers ::run
	 */
	public function test_execute() {
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
		$mock_dumper = $this->createMock( WPCLIDBExport::class );

		/**
		 * SnapshotMeta mock.
		 * 
		 * @var MockObject $mock_snapshot_meta
		 */
		$mock_snapshot_meta = $this->createMock( SnapshotMeta::class );

		$this->set_private_property( $this->command, 'file_zipper', $mock_file_zipper );
		$this->set_private_property( $this->command, 'dumper', $mock_dumper );
		$this->set_private_property( $this->command, 'snapshot_meta', $mock_snapshot_meta );

		$mock_dumper->expects( $this->once() )
			->method( 'dump' );

		$mock_snapshot_meta->expects( $this->once() )
			->method( 'generate' );

		$this->command->execute( [], [ 'include_db' => true ] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			6,
			[
				[
					'Your name (enter x to cancel):'
				],
				[
					'Your email (enter x to cancel):',
				],
				[
					'Snapshot Description (e.g. Local environment) (enter x to cancel):',
				],
				[
					'Project Slug (letters, numbers, _, and - only) (enter x to cancel):',
				],
				[
					'Repository Slug (letters, numbers, _, and - only) (enter x to cancel):',
				],
				[
					'Saving database...'
				]
			]
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::run
	 * @covers ::get_create_args
	 * @covers ::get_success_message
	 */
	public function test_execute_with_args_passed_in() {
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
		$mock_dumper = $this->createMock( WPCLIDBExport::class );

		/**
		 * SnapshotMeta mock.
		 * 
		 * @var MockObject $mock_snapshot_meta
		 */
		$mock_snapshot_meta = $this->createMock( SnapshotMeta::class );

		$this->set_private_property( $this->command, 'file_zipper', $mock_file_zipper );
		$this->set_private_property( $this->command, 'dumper', $mock_dumper );
		$this->set_private_property( $this->command, 'snapshot_meta', $mock_snapshot_meta );

		$this->command->execute( [], [
			'author_name' => 'Test Author',
			'description' => 'Description!',
			'author_email' => 'email@email.com',
			'exclude' => 'files,directory/file.txt',
			'include_db' => true,
			'include_files' => true,
			'repository' => 'my-repo',
			'slug' => 'Slug3',
			'small' => true,
		] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'success',
			1
		);
	}

	/** @covers ::execute */
	public function test_execute_throws_when_db_and_files_not_included() {
		$this->command->execute( [], [ 'include_db' => false, 'include_files' => false ] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'error',
			1,
			[
				[
					'You must include either the database or files in the snapshot.'
				]
			]
		);
	}

	/** @covers ::validate_slug */
	public function test_validate_slug() {
		$this->assertTrue( $this->call_private_method( $this->command, 'validate_slug', [ 'slug' ] ) );

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Input must be letters, numbers, _, and - only.' );

		$this->call_private_method( $this->command, 'validate_slug', [ 'slug!' ] );
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
		$mock_dumper = $this->createMock( WPCLIDBExport::class );

		/**
		 * SnapshotMeta mock.
		 * 
		 * @var MockObject $mock_snapshot_meta
		 */
		$mock_snapshot_meta = $this->createMock( SnapshotMeta::class );

		$this->set_private_property( $this->command, 'file_zipper', $mock_file_zipper );
		$this->set_private_property( $this->command, 'dumper', $mock_dumper );
		$this->set_private_property( $this->command, 'snapshot_meta', $mock_snapshot_meta );

		$test_id = 'test-id';
		$test_args = ['contains_db' => true, 'contains_files' => true];

		$mock_file_zipper->expects( $this->once() )
			->method( 'zip_files' )
			->with( $test_id, array_merge( $test_args, [ 'db_size' => 0 ] ) );

		$mock_dumper->expects( $this->once() )
			->method( 'dump' )
			->with( $test_id, $test_args );

		$mock_snapshot_meta->expects( $this->once() )
			->method( 'generate' )
			->with( $test_id, array_merge( $test_args, [ 'db_size' => 0, 'files_size' => 0 ] ) );

		$this->command->create( $test_args, $test_id );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_will_throw_exception_if_nothing_to_create() {
		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Snapshot must contain either database or files.' );

		$this->command->create( [], 'test-id' );
	}	
}
