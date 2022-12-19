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
}
