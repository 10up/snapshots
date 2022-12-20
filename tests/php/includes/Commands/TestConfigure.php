<?php
/**
 * Tests covering the Configure command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Config;
use TenUp\WPSnapshots\Commands\{Configure, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class ConfigureTest
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\Configure
 */
class TestConfigure extends TestCase {

	use PrivateAccess;

	/**
	 * Configure instance.
	 * 
	 * @var Configure
	 */
	private $configure;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$config = new Config();
		$this->configure = new Configure( $config );
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->configure );
		$this->assertInstanceOf( Config::class, $this->get_private_property( $this->configure, 'config' ) );
	}

	/**
	 * Test command result has correct data structure.
	 * 
	 * @covers ::execute
	 */
	public function test_command_result() {
		$filter_directory = function() {
			return '/tmp';
		};

		add_filter( 'wpsnapshots_config_directory', $filter_directory );

		$result = $this->configure->execute(
			['10up'],
			[
				'user_name' => 'Jane Doe',
				'user_email' => 'jane.doe@example.com',
				'region' => 'us-east-1',
				'aws_key' => '123',
				'aws_secret' => '456',
			]
		);

		$result = json_decode( file_get_contents( '/tmp/config.json' ), true );

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

		// Clean up.
		unlink( '/tmp/config.json' );
		remove_filter( 'wpsnapshots_config_directory', $filter_directory );
	}

}
