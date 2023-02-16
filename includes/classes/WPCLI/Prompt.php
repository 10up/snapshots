<?php
/**
 * Provides prompts via WP-CLI.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLI;

use TenUp\Snapshots\Exceptions\WPSnapshotsInputValidationException;
use TenUp\Snapshots\Infrastructure\SharedService;

use function TenUp\Snapshots\Utils\tenup_snapshots_apply_filters;
use function TenUp\Snapshots\Utils\wp_cli;

/**
 * Provides prompts via WP-CLI.
 *
 * @package TenUp\Snapshots
 */
class Prompt implements SharedService {

	/**
	 * Gets an argument from associative args or prompts for it if it is not set.
	 *
	 * @param array $assoc_args Associative arguments passed to the command.
	 * @param array $config Configuration for the argument.

	 * @return array
	 */
	public function get_arg_or_prompt( array $assoc_args, array $config ) : array {
		if ( isset( $config['key'] ) && isset( $assoc_args[ $config['key'] ] ) && ! empty( $assoc_args[ $config['key'] ] ) ) {
			if ( isset( $config['sanitize_callback'] ) ) {
				$assoc_args[ $config['key'] ] = call_user_func( $config['sanitize_callback'], $assoc_args[ $config['key'] ] );
			}

			return $assoc_args;
		}

		$args = array_merge(
			[
				'key'               => '',
				'prompt'            => '',
				'default'           => '',
				'validate_callback' => null,
				'sanitize_callback' => null,
			],
			$config
		);

		$key               = $args['key'];
		$prompt            = $args['prompt'];
		$default           = $args['default'];
		$validate_callback = $args['validate_callback'];
		$sanitize_callback = $args['sanitize_callback'];

		$full_prompt = $prompt;
		$append      = '';

		if ( empty( $default ) ) {
			$append = ' (enter x to cancel)';
		}

		if ( ! empty( $default ) ) {
			$append = " (default $default; x to cancel)";
		}

		wp_cli()::line( $full_prompt . $append . ':' );

		$result = trim( $this->readline( '' ) );

		if ( 'x' === $result ) {
			wp_cli()::halt( 0 );
		}

		if ( ! $result ) {
			if ( empty( $default ) ) {
				return $this->get_arg_or_prompt( $assoc_args, $args );
			} else {
				$result = $default;
			}
		}

		if ( is_callable( $sanitize_callback ) ) {
			$result = call_user_func( $sanitize_callback, $result );
		}

		if ( is_callable( $validate_callback ) ) {
			try {
				call_user_func( $validate_callback, $result );
			} catch ( WPSnapshotsInputValidationException $e ) {
				wp_cli()::line( $e->getMessage() );
				return $this->get_arg_or_prompt( $assoc_args, $args );
			}
		}

		$assoc_args[ $key ] = $result;

		return $assoc_args;
	}

	/**
	 * Gets a flag value or prompts if it's not set.
	 *
	 * @param array  $assoc_args Associative array of arguments.
	 * @param string $flag Flag to get.
	 * @param string $prompt Prompt to display.
	 * @param bool   $default Default bool result.
	 *
	 * @return bool
	 */
	public function get_flag_or_prompt( array $assoc_args, string $flag, string $prompt, bool $default = true ) : bool {
		if ( array_key_exists( $flag, $assoc_args ) ) {
			return wp_cli()::get_flag_value( $assoc_args, $flag );
		}

		$answer = null;

		/**
		 * Filters acceptable get_flag_or_prompt_answers.
		 *
		 * @param array $answers Acceptable answers.
		 */
		$acceptable_answers = tenup_snapshots_apply_filters( 'tenup_snapshots_get_flag_or_prompt_answers', [ 'y', 'n', 'Y', 'N', '' ] );

		do {
			$answer = $this->readline( $prompt . ' ' . ( true === $default ? '[Y/n]:' : '[y/N]:' ) . ' ' );
		} while ( is_array( $acceptable_answers ) && ! in_array( $answer, [ 'y', 'n', 'Y', 'N', '' ], true ) );

		if ( '' === $answer ) {
			return $default;
		}

		return in_array( $answer, [ 'y', 'Y', '' ], true );
	}

	/**
	 * Wrapper for PHP readline.
	 *
	 * @param string $prompt Prompt to display.
	 * @param string $default Default value.
	 * @param ?mixed $validator Validator function.
	 * @return string
	 */
	public function readline( string $prompt = '', $default = '', $validator = null ) : string {

		/**
		 * Filters the readline callable.
		 *
		 * @param callable $readline Readline callable.
		 */
		$readline = tenup_snapshots_apply_filters( 'tenup_snapshots_readline', 'readline' );

		$result = $readline( $prompt );

		if ( empty( trim( $result ) ) ) {
			$result = $default;
		}

		if ( is_callable( $validator ) ) {
			try {
				$result = call_user_func( $validator, $result );
			} catch ( WPSnapshotsInputValidationException $e ) {
				wp_cli()::line( $e->getMessage() );
				return $this->readline( $prompt, $validator );
			}
		}

		return $result;
	}
}
