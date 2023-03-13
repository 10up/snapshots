<?php
/**
 * Tests covering the Search command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\DynamoDBConnector;
use TenUp\Snapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\Snapshots\WPCLI\WPCLICommand;
use TenUp\Snapshots\WPCLICommands\Search;
use TenUp\Snapshots\SnapshotsConfig\SnapshotsConfigFromFileSystem;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSearch
 *
 * @package TenUp\Snapshots\Tests\Commands
 *
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Search
 */
class TestSearch extends TestCase {

	use PrivateAccess, WPCLIMocking, CommandTests;

	/**
	 * Search instance.
	 *
	 * @var Search
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Snapshots() )->get_instance( Search::class );

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
		$this->assertEquals( 'search', $this->call_private_method( $this->command, 'get_command' ) );
	}

	/** @covers ::get_search_string */
	public function test_get_search_string() {
		$this->command->set_args( [ 'test' ] );

		$this->assertEquals( 'test', $this->call_private_method( $this->command, 'get_search_string' ) );
	}

	/** @covers ::get_search_string */
	public function test_get_search_string_throws_if_empty() {
		$this->expectException( SnapshotsException::class );
		$this->expectExceptionMessage( 'Please provide a search string.' );

		$this->call_private_method( $this->command, 'get_search_string' );
	}

	/**
	 * @covers ::set_assoc_arg
	 */
	public function test_get_repository_name() {
		$this->command->set_assoc_arg( 'repository', 'test' );

		$this->assertEquals( 'test', $this->call_private_method( $this->command, 'get_repository_name' ) );
	}

	/** @covers ::search */
	public function test_search() {
		$return_array = [ 'test' ];

		/**
		 * @var DynamoDBConnector|MockObject $mock_db_connector
		 */
		$mock_db_connector = $this->createMock( DynamoDBConnector::class );
		$mock_db_connector->method( 'search' )->willReturn( $return_array );
		$this->set_private_property( $this->command, 'db_connector', $mock_db_connector );

		/**
		 * @var SnapshotsConfigFromFileSystem|MockObject $mock_snapshots_config
		 */
		$mock_snapshots_config = $this->createMock( SnapshotsConfigFromFileSystem::class );
		$mock_snapshots_config->method( 'get_repository_settings' )->willReturn(
			[
				'region' => 'test',
				'repository' => 'test',
			]
		);
		$this->set_private_property( $this->command, 'config', $mock_snapshots_config );

		$this->command->set_assoc_arg( 'repository', 'test' );
		$this->command->set_args( [ 'test' ] );

		$actual_results = $this->call_private_method( $this->command, 'search', [ 'test-region' ] );

		$this->assertEquals( $return_array, $actual_results );
	}

	/**
	 * @covers ::display_results
	 * @covers ::format_row
	 * @covers ::get_output_format
	 * @covers ::format_bytes
	 */
	public function test_display_results() {
		$time = time();

		$this->command->set_assoc_arg( 'format', 'yaml' );

		$this->call_private_method(
			$this->command,
			'display_results',
			[
				[
					[
						'time' => $time,
						'contains_files' => 'Yes',
						'contains_db' => 'Yes',
						'files_size' => 123123,
						'db_size' => 123123,

					],
				],
			]
		);

		$date_format = 'F j, Y, g:i a';

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'format_items',
				1,
				[
					[
						'yaml',
						[
							$time => [
								'ID' => '',
								'Project' => '',
								'Contains files' => 'Yes',
								'Contains database' => 'Yes',
								'Description' => '',
								'Author' => '',
								'Size' => '240.47 KB',
								'Multisite' => 'No',
								'Created' => gmdate( $date_format, $time ),
							],
						],
						[
							'ID',
							'Project',
							'Contains files',
							'Contains database',
							'Description',
							'Author',
							'Size',
							'Multisite',
							'Created',
						],
					],
				]
			);
	}

	/**  @covers ::display_results */
	public function test_display_results_logs_message_if_no_results() {
		$this->call_private_method( $this->command, 'display_results', [ [] ] );

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'line',
				1,
				[ [ 'No snapshots found.' ] ]
			);
	}
}
