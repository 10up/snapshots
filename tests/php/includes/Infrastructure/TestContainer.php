<?php
/**
 * Tests for the abstract Container class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Infrastructure;

use TenUp\Snapshots\FileSystem;
use TenUp\Snapshots\SnapshotsConfig\SnapshotsConfigFromFileSystem;
use TenUp\Snapshots\Infrastructure\Container;
use TenUp\Snapshots\Log\WPCLILogger;
use TenUp\Snapshots\SnapshotsDirectory;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\DynamoDBConnector;
use TenUp\Snapshots\Snapshots\FileZipper;
use TenUp\Snapshots\Snapshots\S3StorageConnector;
use TenUp\Snapshots\Snapshots\SnapshotMetaFromFileSystem;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use TenUp\Snapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\Snapshots\WordPress\Database;
use TenUp\Snapshots\WPCLI\Prompt;
use TenUp\Snapshots\WPCLICommands\Create\{Scrubber, Trimmer};
use TenUp\Snapshots\WPCLICommands\Create\WPCLIDBExport;
use TenUp\Snapshots\WPCLICommands\Pull\URLReplacerFactory;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class ContainerTest
 *
 * @package TenUp\Snapshots\Tests\Infrastructure
 *
 * @coversDefaultClass \TenUp\Snapshots\Infrastructure\Container
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
		$this->container = new Snapshots();
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
	 * @covers ::get_concrete_service_name
	 */
	public function test_register() {
		$this->container->register();

		$expected = [
			Database::class,
			DynamoDBConnector::class,
			FileSystem::class,
			FileZipper::class,
			Prompt::class,
			S3StorageConnector::class,
			Scrubber::class,
			SnapshotsDirectory::class,
			SnapshotMetaFromFileSystem::class,
			Trimmer::class,
			URLReplacerFactory::class,
			WPCLIDBExport::class,
			WPCLILogger::class,
			SnapshotsConfigFromFileSystem::class,
		];

		sort( $expected );

		$actual = array_keys( $this->get_private_property( $this->container, 'shared_instances' ) );

		sort( $actual );

		$this->assertEquals( $expected, $actual );
	}
}
