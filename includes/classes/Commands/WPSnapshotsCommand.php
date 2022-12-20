<?php
/**
 * Abstract WPSnapshotsCommand class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Commands;

use WP_CLI;

/**
 * Abstract class WPSnapshotsCommand
 *
 * @package TenUp\WPSnapshots\Commands
 */
abstract class WPSnapshotsCommand {

	/**
	 * Args passed to the command.
	 *
	 * @var array
	 */
	protected $args = [];

	/**
	 * Associative args passed to the command.
	 *
	 * @var array
	 */
	protected $assoc_args = [];

	/**
	 * Callback for the command.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	abstract public function execute( array $args, array $assoc_args );

	/**
	 * Gets an argument from associative args or prompts for it if it is not set.
	 *
	 * @param string $key Key of the argument.
	 * @param string $prompt Prompt to display if the argument is not set.
	 * @param string $default Default value of the argument.
	 *
	 * @return string
	 */
	protected function get_arg_or_prompt( string $key, string $prompt, string $default = '' ) : string {
		if ( ! empty( $this->assoc_args[ $key ] ) ) {
			return $this->assoc_args[ $key ];
		}

		$full_prompt = $prompt;
		$append      = '';

		if ( empty( $default ) ) {
			$append = ' (enter x to cancel)';
		}

		if ( ! empty( $default ) ) {
			$append = " (default $default; x to cancel)";
		}

		WP_CLI::line( $full_prompt . $append . ':' );
		$line = trim( readline( '' ) );

		if ( 'x' === $line ) {
			WP_CLI::halt( 0 );
		}

		if ( ! $line && empty( $default ) ) {
			return $this->get_arg_or_prompt( $key, $prompt, $default );
		}

		return $line ? $line : $default;
	}

	/**
	 * Gets a flag from associative args.
	 *
	 * @param string $key Key of the flag.
	 * @param bool   $default Default value of the flag.
	 * @return bool
	 */
	protected function get_flag( string $key, bool $default = false ) : bool {
		if ( isset( $this->assoc_args[ $key ] ) ) {
			return true;
		}

		return $default;
	}
}
