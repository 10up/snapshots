<?php
/**
 * Tests covering the Search command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\DynamoDBConnector;
use TenUp\WPSnapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\Search;
use TenUp\WPSnapshots\WPSnapshotsConfig\WPSnapshotsConfigFromFileSystem;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSearch
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Search
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

		$this->command = ( new Plugin() )->get_instance( Search::class );

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
		$this->expectException( WPSnapshotsException::class );
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
		 * @var WPSnapshotsConfigFromFileSystem|MockObject $mock_wpsnapshots_config
		 */
		$mock_wpsnapshots_config = $this->createMock( WPSnapshotsConfigFromFileSystem::class );
		$mock_wpsnapshots_config->method( 'get_repository_settings' )->willReturn(
			[
				'region' => 'test',
				'repository' => 'test',
			]
		);
		$this->set_private_property( $this->command, 'config', $mock_wpsnapshots_config );

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
						'table',
						[
							$time => [
								'id' => '',
								'project' => '',
								'contains_files' => 'Yes',
								'contains_db' => 'Yes',
								'description' => '',
								'author' => '',
								'size' => '240.47 KB',
								'multisite' => 'No',
								'created' => gmdate( $date_format, $time ),
							],
						],
						[
							'id',
							'project',
							'contains_files',
							'contains_db',
							'description',
							'author',
							'size',
							'multisite',
							'created',
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
