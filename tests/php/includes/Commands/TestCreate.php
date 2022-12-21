<?php
/**
 * Tests covering the Create command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Commands\{Create, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestCreate
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\Create
 */
class TestCreate extends TestCase {

	use PrivateAccess;

	/**
	 * Create instance.
	 * 
	 * @var Create
	 */
	private $create;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->create = new Create();
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->create );
	}
}
