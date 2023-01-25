<?php
/**
 * Tests covering the Download command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\SnapshotMeta;
use TenUp\WPSnapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\Download;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDownload
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Download
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

		$this->command = ( new Plugin() )->get_instance( Download::class );

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

	/** @covers ::get_local_meta */
	public function test_get_local_meta() {
		$this->assertEquals(
			[],
			$this->call_private_method( $this->command, 'get_local_meta', [ 'test-id' ] )
		);
	}

	/** @covers ::get_id */
	public function test_get_id() {
		$this->set_private_property( $this->command, 'args', [ 'test-id' ] );
		$this->assertEquals( 'test-id', $this->call_private_method( $this->command, 'get_id' ) );
	}

	/** @covers ::get_meta */
	public function test_get_meta() {
		$this->set_private_property( $this->command, 'args', [ 'test-id' ] );
		
		/**
		 * @var SnapshotMeta|MockObject $mock_snapshot_meta
		 */
		$mock_snapshot_meta = $this->createMock( SnapshotMeta::class );
		$mock_snapshot_meta->method( 'get_remote_meta' )->willReturn(
			[
				'project' => 'test-project',
				'repository' => 'test-repository',
				'contains_db' => true,
				'contains_files' => true,
			]
		);
		$this->set_private_property( $this->command, 'snapshot_meta', $mock_snapshot_meta );

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
}
