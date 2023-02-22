<?php
/**
 * Tests covering the Download command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\S3StorageConnector;
use TenUp\Snapshots\Snapshots\SnapshotMeta;
use TenUp\Snapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\Snapshots\WPCLI\WPCLICommand;
use TenUp\Snapshots\WPCLICommands\Download;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDownload
 *
 * @package TenUp\Snapshots\Tests\Commands
 *
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Download
 */
class TestDownload extends TestCase {

	use PrivateAccess, WPCLIMocking, CommandTests;

	/**
	 * Download instance.
	 *
	 * @var Download
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Snapshots() )->get_instance( Download::class );

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
		$this->assertEquals( 'download', $this->call_private_method( $this->command, 'get_command' ) );
	}

	/**
	 * @covers ::get_id
	 * @covers ::get_args
	 */
	public function test_get_id() {
		$this->set_private_property( $this->command, 'args', [ 'test-id' ] );
		$this->assertEquals( 'test-id', $this->call_private_method( $this->command, 'get_id' ) );
	}

	/**
	 * @covers ::get_meta
	 * @covers ::get_assoc_args
	 * @covers ::get_assoc_arg
	 * @covers ::get_repository_name
	 * @covers ::get_default_arg_value
	 */
	public function test_get_meta() {
		$this->set_private_property( $this->command, 'args', [ 'test-id' ] );

		/**
		 * @var SnapshotMeta|MockObject $mock_snapshot_meta
		 */
		$mock_snapshot_meta = $this->createMock( SnapshotMeta::class );
		$mock_snapshot_meta->method( 'get_remote' )->willReturn(
			[
				'project' => 'test-project',
				'repository' => 'test-repository',
				'contains_db' => true,
				'contains_files' => true,
			]
		);
		$this->set_private_property( $this->command, 'snapshot_meta', $mock_snapshot_meta );
		$this->command->set_assoc_arg( 'repository', '10up' );

		add_filter( 'snapshots_readline', function() {
			return function() {
				return 'Y';
			};
		} );

		$this->assertEquals(
			[
				'project' => 'test-project',
				'repository' => 'test-repository',
				'contains_db' => true,
				'contains_files' => true,
			],
			$this->call_private_method( $this->command, 'get_meta' )
		);
	}

	/** @covers ::get_formatted_size */
	public function test_get_formatted_size() {
		$this->assertEquals( '1 KB', $this->call_private_method(
			$this->command,
			'get_formatted_size',
			[
				[
					'size' => 1024,
					'contains_files' => true,
					'contains_db' => true,
				]
			]
		) );

		$this->assertEquals( '1 KB', $this->call_private_method(
			$this->command,
			'get_formatted_size',
			[
				[
					'files_size' => 1024,
					'db_size' => 0,
				]
			]
		) );

		$this->assertEquals( '1 KB', $this->call_private_method(
			$this->command,
			'get_formatted_size',
			[
				[
					'files_size' => 0,
					'db_size' => 1024,
				]
			]
		) );

		$this->assertEquals( '2 KB', $this->call_private_method(
			$this->command,
			'get_formatted_size',
			[
				[
					'files_size' => 1024,
					'db_size' => 1024,
				]
			]
		) );
	}

	/** @covers ::execute */
	public function test_execute() {
		$meta = [
			'project' => 'test-project',
			'repository' => 'test-repository',
			'contains_db' => true,
			'contains_files' => true,
			'size' => 1024,
		];

		/**
		 * @var SnapshotMeta|MockObject $mock_snapshot_meta
		 */
		$mock_snapshot_meta = $this->createMock( SnapshotMeta::class );
		$mock_snapshot_meta->method( 'get_remote' )->willReturn( $meta );
		$this->set_private_property( $this->command, 'snapshot_meta', $mock_snapshot_meta );

		/**
		 * @var S3StorageConnector|MockObject $mock_storage_connector
		 */
		$mock_storage_connector = $this->createMock( S3StorageConnector::class );
		$this->set_private_property( $this->command, 'storage_connector', $mock_storage_connector );

		$mock_snapshot_meta->expects( $this->once() )->method( 'get_remote' )->with( 'test-id' );
		$mock_snapshot_meta->expects( $this->once() )->method( 'save_local' )->with( 'test-id' );

		$mock_storage_connector->expects( $this->once() )->method( 'download_snapshot' )->with(
			'test-id',
			$meta,
			'default',
			'test-repo',
			'test-region'
		);

		add_filter( 'snapshots_readline', function() {
			return function() {
				return 'Y';
			};
		} );

		$this->command->execute( [ 'test-id' ], [ 'region' => 'test-region', 'repository' => 'test-repo' ] );

		// Confirm success message.
		$this->get_wp_cli_mock()->assertMethodCalled( 'success', 1, [ [ 'Snapshot downloaded.' ] ]);
	}
}
