<?php
/**
 * Tests covering the CreateRepository command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Commands\{CreateRepository, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestCreateRepository
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\CreateRepository
 */
class TestCreateRepository extends TestCase {

	use PrivateAccess;

	/**
	 * CreateRepository instance.
	 * 
	 * @var CreateRepository
	 */
	private $create_repository_repository;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->create_repository = new CreateRepository();
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->create_repository );
	}
}
