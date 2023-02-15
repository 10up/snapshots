<?php
/**
 * Tests covering the Configure command class.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\Exceptions\WPSnapshotsException;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\S3StorageConnector;
use TenUp\Snapshots\Tests\Fixtures\{CommandTests, PrivateAccess, DirectoryFiltering, WPCLIMocking};
use TenUp\Snapshots\WPCLI\WPCLICommand;
use TenUp\Snapshots\WPCLICommands\Configure;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestConfigure
 *
 * @package TenUp\Snapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Configure
 */
class TestConfigure extends TestCase {

	use PrivateAccess, DirectoryFiltering, WPCLIMocking, CommandTests;

	/**
	 * Configure instance.
	 * 
	 * @var Configure
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Snapshots() )->get_instance( Configure::class );

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
	 * Test that the command instance extends WPCLICommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPCLICommand::class, $this->command );
	}

	/** @covers ::get_command */
	public function test_get_command() {
		$this->assertEquals( 'configure', $this->call_private_method( $this->command, 'get_command' ) );
	}

	/** @covers ::get_command_parameters */
	public function test_get_command_parameters() {
		$parameters = $this->call_private_method( $this->command, 'get_command_parameters' );

		$this->assertEquals(
			[
				'longdesc',
				'shortdesc',
				'synopsis',
			],
			array_keys( $parameters )
		);
	}

	/**
	 * Test command result has correct data structure.
	 * 
	 * @covers ::execute
	 * @covers ::get_assoc_args
	 * @covers ::get_assoc_arg
	 * @covers ::set_args
	 * @covers ::set_assoc_args
	 * @covers ::get_repository_name
	 * @covers ::get_args
	 */
	public function test_command_result() {
		$this->command->execute(
			[ '10up' ],
			$this->get_test_args()
		);

		$result = json_decode( file_get_contents( '/tenup-snapshots-tmp/config.json' ), true );

		$this->assertEquals(
			[
				'user_name' => 'Jane Doe',
				'user_email' => 'jane.doe@example.com',
				'repositories' => [
					'10up' => [
						'repository' => '10up',
						'region' => 'us-east-1'
					]
				]
			],
			$result
		);

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'success',
				1,
				[ [ 'WP Snapshots configuration saved.' ] ]
			);
	}

	/**
	 * Test that an exception is thrown if no repository is provided.
	 * 
	 * @covers ::execute
	 * @covers ::get_repository_name
	 */
	public function test_command_result_no_repository() {
		$this->command->execute(
			[],
			$this->get_test_args()
		);

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'success',
				0
			);
		
		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'error',
				1,
				[ [ 'A repository name is required. Please run the configure command or pass a --repository argument.' ] ]
			);
	}

	/**
	 * Test that the command requests confirmation if a file already exists and has the repository already configured.
	 * 
	 * @covers ::execute
	 * @covers ::get_updated_repository_info
	 */
	public function test_command_result_existing_file() {
		// Create a config file with the repository already configured.
		$config = [
			'user_name' => 'Jane Doe',
			'user_email' => 'jane.doe@example.com',
			'repositories' => [
				'10up' => [
					'repository' => '10up',
					'region' => 'us-east-1'
				]
			]
		];

		file_put_contents( '/tenup-snapshots-tmp/config.json', json_encode( $config ) );

		$this->command->execute(
			[ '10up' ],
			$this->get_test_args()
		);

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'confirm',
				1,
				[ [ 'This repository is already configured. Do you want to overwrite the existing configuration?' ] ]
			);

		// Check that the file has the expected output.
		$result = json_decode( file_get_contents( '/tenup-snapshots-tmp/config.json' ), true );

		$this->assertEquals( $config, $result );
	}

	/**
	 * Test prompts when no CLI args are passed.
	 * 
	 * @covers ::execute
	 */
	public function test_command_result_prompts() {
		$this->command->execute( [ '10up' ], [] );

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'line',
				3
			);
	}

	/**
	 * Provides test args for the command.
	 */
	private function get_test_args() {
		return [
			'user_name' => 'Jane Doe',
			'user_email' => 'jane.doe@example.com',
			'region' => 'us-east-1',
			'skip_test' => true,
		];
	}
}
