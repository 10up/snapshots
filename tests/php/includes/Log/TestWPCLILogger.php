<?php
/**
 * Tests for the WPCLILogger class.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Log;

use TenUp\Snapshots\Log\WPCLILogger;
use TenUp\Snapshots\Tests\Fixtures\WPCLIMocking;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class WPCLILoggerTest
 * 
 * @package TenUp\Snapshots\Tests\Log
 * 
 * @coversDefaultClass \TenUp\Snapshots\Log\WPCLILogger
 */
class TestWPCLILogger extends TestCase {
	
	use WPCLIMocking;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_up_wp_cli_mock();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();
		$this->tear_down_wp_cli_mock();
	}

	/** @covers ::log */
	public function test_log() {
		$logger = new WPCLILogger();
		$logger->log( 'error test', 'error' );
		$logger->log( 'warning test', 'warning' );
		$logger->log( 'success test', 'success' );
		$logger->log( 'info test', 'info' );
		
		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			4,
			[
				[ 'error test' ],
				[ 'warning test' ],
				[ 'success test' ],
				[ 'info test' ],
			]
		);
	}
}