<?php
/**
 * Tests covering the Search command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Commands\{Search, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSearch
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\Search
 */
class TestSearch extends TestCase {

	use PrivateAccess;

	/**
	 * Search instance.
	 * 
	 * @var Search
	 */
	private $search;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->search = new Search();
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->search );
	}
}
