<?php
/**
 * Tests for the prompt instance.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\WPCLI;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Exceptions\SnapshotsInputValidationException;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\Snapshots\WPCLI\Prompt;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;


/**
 * Class TestPrompt
 *
 * @package TenUp\Snapshots\Tests\WPCLI
 *
 * @coversDefaultClass \TenUp\Snapshots\WPCLI\Prompt
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

		$this->prompt = ( new Snapshots() )->get_instance( Prompt::class );

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

		add_filter( 'snapshots_readline', $readline_mock );

		$actual_args = $this->prompt->get_arg_or_prompt( $args, [ 'key' => 'test' ] );

		$this->assertEquals( [ 'test' => 'value' ], $actual_args );

		$this->get_wp_cli_mock()->assertMethodCalled( 'line' );

		remove_filter( 'snapshots_readline', $readline_mock );
	}

	/**
	 * @covers ::get_arg_or_prompt
	 */
	public function test_calls_readline_with_validate_callback() {
		$args = [];

		$readline_mock = function() {
			return function() {
				return 'value';
			};
		};

		add_filter( 'snapshots_readline', $readline_mock );

		$actual_args = $this->prompt->get_arg_or_prompt(
			$args,
			[
				'key' => 'test',
				'validate_callback' => function( $value ) {
					return $value === 'value';
				}
			]
		);

		$this->assertEquals( [ 'test' => 'value' ], $actual_args );

		$this->get_wp_cli_mock()->assertMethodCalled( 'line' );

		remove_filter( 'snapshots_readline', $readline_mock );
	}

	/**
	 * @covers ::readline
	 */
	public function test_readline_with_validator() {
		$args = [];

		$readline_mock = function() {
			return function() {
				return 'value ';
			};
		};

		add_filter( 'snapshots_readline', $readline_mock );

		$result = $this->prompt->readline(
			'Prompt?',
			'',
			function( $value ) {
				return trim( $value );
			}
		);

		$this->assertEquals( 'value', $result );

		remove_filter( 'snapshots_readline', $readline_mock );
	}

	/**
	 * @covers ::readline
	 */
	public function test_readline_with_validator_that_throws() {
		$calls = 0;

		$readline_mock = function() use ( &$calls ) {
			return function() use ( &$calls ) {
				return 'value' . ( $calls++ );
			};
		};

		add_filter( 'snapshots_readline', $readline_mock );

		$result = $this->prompt->readline(
			'Prompt?',
			'',
			function( $value ) {
				if ( $value !== 'value2' ) {
					throw new SnapshotsInputValidationException( 'Invalid value' );
				}

				return $value;
			}
		);

		$this->assertEquals( 'value1', $result );

		remove_filter( 'snapshots_readline', $readline_mock );
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

		add_filter( 'snapshots_readline', $readline_mock );
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

		remove_filter( 'snapshots_readline', $readline_mock );
	}

	/** @covers ::get_flag_or_prompt */
	public function test_returns_flag_if_already_present() {
		$args = [ 'test' => true ];

		add_filter( 'snapshots_readline', function() {
			return function() {
				return 'Y';
			};
		} );

		$this->assertEquals( true, $this->prompt->get_flag_or_prompt( $args, 'nonexistent_key', 'Prompt?', true ) );
	}
}


