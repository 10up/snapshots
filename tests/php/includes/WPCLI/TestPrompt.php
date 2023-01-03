<?php
/**
 * Tests for the prompt instance.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\WPCLI;

use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\WPSnapshots\WPCLI\Prompt;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;


/**
 * Class TestPrompt
 * 
 * @package TenUp\WPSnapshots\Tests\WPCLI
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLI\Prompt
 */
class TestPrompt extends TestCase {

	use WPCLIMocking;

	/**
	 * Prompt instance.
	 * 
	 * @var Prompt
	 */
	private $prompt;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->prompt = ( new Plugin() )->get_instance( Prompt::class );

		$this->set_up_wp_cli_mock();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
	}

	/** @covers ::get_arg_or_prompt */
	public function test_returns_assoc_args_if_already_present() {
		$args = [ 'test' => 'value' ];

		$this->assertEquals( $args, $this->prompt->get_arg_or_prompt( $args, [ 'key' => 'test' ] ) );
	}

	/**
	 * @covers ::get_arg_or_prompt
	 * @covers ::readline
	 */
	public function test_calls_readline_if_not_set() {
		$args = [];

		$readline_mock = function() {
			return function() {
				return 'value';
			};
		};
	
		add_filter( 'wpsnapshots_readline', $readline_mock );

		$actual_args = $this->prompt->get_arg_or_prompt( $args, [ 'key' => 'test' ] );

		$this->assertEquals( [ 'test' => 'value' ], $actual_args );

		$this->get_wp_cli_mock()->assertMethodCalled( 'line' );

		remove_filter( 'wpsnapshots_readline', $readline_mock );
	}

	/**
	 * @covers ::get_arg_or_prompt
	 */
	public function test_sanitize_callback_is_applied() {
		$args = [];

		$readline_mock = function() {
			return function() {
				return 'value    value      ';
			};
		};
	
		add_filter( 'wpsnapshots_readline', $readline_mock );
		$sanitize_callback_called = false;

		$actual_args = $this->prompt->get_arg_or_prompt(
			$args,
			[
				'key' => 'test',
				'sanitize_callback' => function( $unfiltered_value ) use ( &$sanitize_callback_called ) {
					$sanitize_callback_called = true;
					return preg_replace( '/\s+/', ' ', $unfiltered_value );
				}
			]
		);

		$this->assertTrue( $sanitize_callback_called );
		$this->assertEquals( [ 'test' => 'value value' ], $actual_args );

		$this->get_wp_cli_mock()->assertMethodCalled( 'line' );

		remove_filter( 'wpsnapshots_readline', $readline_mock );
	}
}


