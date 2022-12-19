<?php
/**
 * Tests covering the Download command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Commands\{Download, WPSnapshotsCommand};
use TenUp\WPSnapshots\Tests\Utils\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDownload
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Commands\Download
 */
class TestDownload extends TestCase {

	use PrivateAccess;

	/**
	 * Download instance.
	 * 
	 * @var Download
	 */
	private $download;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->download = new Download();
	}

	/**
	 * Test that the command instance extends WPSnapshotsCommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPSnapshotsCommand::class, $this->download );
	}
}
