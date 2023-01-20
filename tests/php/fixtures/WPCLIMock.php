<?php
/**
 * WPCLI Mock class
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Fixtures;

/**
 * Class WPCLIMock
 *
 * @package TenUp\WPSnapshots\Tests\Fixtures
 */
class WPCLIMock {
	/**
	 * Test case.
	 * 
	 * @var object
	 */
	protected $test_case;

	/**
	 * Mock method calls.
	 * 
	 * @var array
	 */
	static protected $wpcli_mock_calls = [];

	/**
	 * Constructor.
	 * 
	 * @param object $test_case Test case.
	 */
	public function __construct( $test_case ) {
		$this->test_case = $test_case;
	}

	public static function __callStatic( $name, $arguments ) {
		if ( in_array( $name, [ 'success', 'error', 'confirm', 'halt', 'line', 'readline', 'add_command', 'format_items', 'prompt', 'runcommand' ] ) ) {
			self::$wpcli_mock_calls[ $name ][] = $arguments;
			return;
		}

		if ( method_exists( 'WP_CLI', $name ) ) {
			return call_user_func_array( [ 'WP_CLI', $name ], $arguments );
		}

		if ( function_exists( '\\WP_CLI\\Utils\\' . $name ) ) {
			return call_user_func_array( '\\WP_CLI\\Utils\\' . $name, $arguments );
		}
	}

	/**
	 * Get the mock method calls.
	 * 
	 * @param string $method Method name to get calls for.
	 * 
	 * @return array
	 */
	public function get_wpcli_mock_calls( string $method ) : array {
		return self::$wpcli_mock_calls[ $method ] ?? [];
	}

	/**
	 * Reset the mock method calls.
	 */
	public function reset_wpcli_mock_calls() {
		self::$wpcli_mock_calls = [];
	}

	/**
	 * Tests that a method was called.
	 * 
	 * @param string $method Method name.
	 * @param int    $times  Number of times the method should have been called.
	 * @param ?array $args   Arguments to check.
	 */
	public function assertMethodCalled( string $method, int $times = 1, ?array $args = null ) {
		$calls = $this->get_wpcli_mock_calls( $method );
		$this->test_case->assertCount( $times, $calls, "Method $method was called $times times." );

		if ( ! is_null( $args ) ) {
			foreach ( $args as $index => $arg ) {
				$this->test_case->assertEquals( $arg, $calls[$index], "Method $method was called with the correct arguments." );
			}
		}

        return $calls;
	}
	
	/**
	 * Readline mock.
	 */
	public function readline( ...$args ) {
		self::$wpcli_mock_calls['readline'][] = $args;
		return 'Y';
	}
};