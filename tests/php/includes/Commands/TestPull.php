<?php
/**
 * Tests covering the Pull command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Commands\{Pull, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestPull
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\Pull
 */
class TestPull extends TestCase {

	use PrivateAccess;

	/**
	 * Pull instance.
	 * 
	 * @var Pull
	 */
	private $pull;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->pull = new Pull();
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->pull );
	}
}
