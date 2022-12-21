<?php
/**
 * Tests covering the Delete command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Commands\{Delete, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDelete
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\Delete
 */
class TestDelete extends TestCase {

	use PrivateAccess;

	/**
	 * Delete instance.
	 * 
	 * @var Delete
	 */
	private $delete;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->delete = new Delete();
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->delete );
	}
}
