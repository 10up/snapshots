<?php
/**
 * Tests for the Logging trait.
 * 
 * @package TenUp\Snapshots
 */
 
namespace TenUp\Snapshots\Tests\Log;

use TenUp\Snapshots\Log\LoggerInterface;
use TenUp\Snapshots\Log\Logging;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class LoggingTest
 * 
 * @package TenUp\Snapshots\Tests\Log
 * 
 * @coversDefaultClass \TenUp\Snapshots\Log\Logging
 */
class TestLogging extends TestCase {
	use Logging;

	/**
	 * Test logger.
	 * 
	 * @var object
	 */
	protected $test_logger;

	/**
	 * Test setup.
	 */
	public function set_up() {
		$this->test_logger = new class implements LoggerInterface {
			/**
			 * Calls to the logger.
			 * 
			 * @var array
			 */
			public $calls = [];
			
			public function log( string $message, $type = 'info' ) {
				$this->calls[] = compact( 'message', 'type' );
			}
		};
	}

	/**
	 * @covers ::get_logger
	 * @covers ::set_logger
	 */
	public function test_set_and_get_logger() {
		$this->set_logger( $this->test_logger );
		$this->assertEquals( $this->test_logger, $this->get_logger() );
	}

	/**
	 * @covers ::log
	 */
	public function test_log() {
		$this->set_logger( $this->test_logger );
		$this->log( 'test message' );
		$this->assertEquals( [ 'message' => 'test message', 'type' => 'info' ], $this->test_logger->calls[0] );
	}
}	
