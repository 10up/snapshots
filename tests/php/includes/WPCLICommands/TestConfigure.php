<?php
/**
 * Tests covering the Configure command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\S3StorageConnector;
use TenUp\WPSnapshots\Tests\Fixtures\{CommandTests, PrivateAccess, DirectoryFiltering, WPCLIMocking};
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\Configure;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestConfigure
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Configure
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

		$this->command = ( new Plugin() )->get_instance( Configure::class );

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
	 * @covers ::maybe_test_credentials
	 */
	public function test_command_result() {
		$this->command->execute(
			[ '10up' ],
			$this->get_test_args()
		);

		$result = json_decode( file_get_contents( '/wpsnapshots-tmp/config.json' ), true );

		$this->assertEquals(
			[
				'user_name' => 'Jane Doe',
				'user_email' => 'jane.doe@example.com',
				'repositories' => [
					'10up' => [
						'repository' => '10up',
						'access_key_id' => '123',
						'secret_access_key' => '456',
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
				[ 'WP Snapshots configuration saved.' ]
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
				[ 'Please provide a repository name.' ]
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
					'access_key_id' => '123',
					'secret_access_key' => '456',
					'region' => 'us-east-1'
				]
			]
		];

		file_put_contents( '/wpsnapshots-tmp/config.json', json_encode( $config ) );

		$this->command->execute(
			[ '10up' ],
			$this->get_test_args()
		);

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'confirm',
				1,
				[ 'This repository is already configured. Do you want to overwrite the existing configuration?' ]
			);

		// Check that the file has the expected output.
		$result = json_decode( file_get_contents( '/wpsnapshots-tmp/config.json' ), true );

		$this->assertEquals( $config, $result );
	}

	/**
	 * Test that the command runs successfully when testing credentials.
	 * 
	 * @covers ::execute
	 * @covers ::maybe_test_credentials
	 * @covers ::get_aws_key
	 * @covers ::get_aws_secret
	 * @covers ::get_region
	 * @covers ::should_test_credentials
	 * @covers ::get_user_name
	 * @covers ::get_user_email
	 */
	public function test_command_result_test_credentials() {
		/** @var MockObject $storage_mock */
		$storage_mock = $this->createMock( S3StorageConnector::class );
		$storage_mock->method( 'test_connection' );

		$this->set_private_property( $this->command, 'storage', $storage_mock );

		$this->command->execute(
			[ '10up' ],
			array_merge(
				$this->get_test_args(),
				[
					'skip_test' => false,
				]
			)
		);

		$this->get_wp_cli_mock()
			->assertMethodCalled(
				'success',
				1,
				[ 'WP Snapshots configuration verified and saved.' ]
			);
	}

	/**
	 * Test that an error message shows when the test connection fails.
	 * 
	 * @covers ::execute
	 */
	public function test_command_result_test_credentials_fail() {
		/** @var MockObject $storage_mock */
		$storage_mock = $this->createMock( S3StorageConnector::class );
		$storage_mock->method( 'test_connection' )
			->willThrowException( new WPSnapshotsException( 'WP Snapshots configuration verification failed.' ) );

		$this->set_private_property( $this->command, 'storage', $storage_mock );

		$this->command->execute(
			[ '10up' ],
			array_merge(
				$this->get_test_args(),
				[
					'skip_test' => false,
				]
			)
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
				[ 'WP Snapshots configuration verification failed.' ]
			);
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
				5
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
			'aws_key' => '123',
			'aws_secret' => '456',
			'skip_test' => true,
		];
	}
}
