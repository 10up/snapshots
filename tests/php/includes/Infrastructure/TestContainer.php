<?php
/**
 * Tests for the abstract Container class.
 * 
 * @package Tenup\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Infrastructure;

use TenUp\WPSnapshots\WPSnapshotsConfig\WPSnapshotsConfigFromFileSystem;
use TenUp\WPSnapshots\Infrastructure\Container;
use TenUp\WPSnapshots\Log\WPCLILogger;
use TenUp\WPSnapshots\SnapshotsFileSystem;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\AWSAuthenticationFactory;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\WPSnapshots\WPCLI\Prompt;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class ContainerTest
 *
 * @package TenUp\WPSnapshots\Tests\Infrastructure
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Infrastructure\Container
 */
class TestContainer extends TestCase {

	use PrivateAccess, WPCLIMocking;

	/**
	 * Container instance.
	 * 
	 * @var Container
	 */
	private $container;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}
		
		$this->set_up_wp_cli_mock();
		$this->container = new Plugin();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
	}

	/**
	 * @covers ::register
	 * @covers ::get_instance
	 * @covers ::get_instance_from_parameter
	 * @covers ::validate_classes
	 * @covers ::get_concrete_service_name
	 */
	public function test_register() {
		$this->container->register();

		$this->assertEquals(
			[
                WPCLILogger::class,
				Prompt::class,
                SnapshotsFileSystem::class,
                WPSnapshotsConfigFromFileSystem::class,
                AWSAuthenticationFactory::class,
			],
			array_keys( $this->get_private_property( $this->container, 'shared_instances' ) )
		);
	}
}
