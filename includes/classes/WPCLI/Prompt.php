<?php
/**
 * Provides prompts via WP-CLI.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLI;

use TenUp\WPSnapshots\Infrastructure\{Service, Shared};

/**
 * Provides prompts via WP-CLI.
 *
 * @package TenUp\WPSnapshots
 */
class Prompt implements Shared, Service {

	/**
	 * Gets an argument from associative args or prompts for it if it is not set.
	 *
	 * @param array $assoc_args Associative arguments passed to the command.
	 * @param array $config Configuration for the argument.

	 * @return array
	 */
	public function get_arg_or_prompt( array $assoc_args, array $config ) : array {
		if ( isset( $config['key'] ) && isset( $assoc_args[ $config['key'] ] ) && ! empty( $assoc_args[ $config['key'] ] ) ) {
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

		if ( ! empty( $assoc_args[ $key ] ) ) {
			$result = $assoc_args[ $key ];
		} else {
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
	 * Wrapper for PHP readline.
	 *
	 * @param string $prompt Prompt to display.
	 * @return string
	 */
	public function readline( string $prompt = '' ) : string {

		/**
		 * Filters the readline callable.
		 *
		 * @param callable $readline Readline callable.
		 */
		$readline = apply_filters( 'wpsnapshots_readline', 'readline' );

		return $readline( $prompt );
	}
}
