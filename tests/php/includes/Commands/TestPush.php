<?php
/**
 * Tests covering the Push command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Commands\{Push, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestPush
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\Push
 */
class TestPush extends TestCase {

	use PrivateAccess;

	/**
	 * Push instance.
	 * 
	 * @var Push
	 */
	private $push;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->push = new Push();
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->push );
	}
}
