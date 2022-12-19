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
	 * @param string $description Description of the argument.
	 * @param string $default Default value of the argument.
	 *
	 * @return string
	 */
	protected function get_arg_or_prompt( string $key, string $description, string $default = '' ) : string {
		if ( ! empty( $this->assoc_args[ $key ] ) ) {
			return $this->assoc_args[ $key ];
		}

		WP_CLI::line( sprintf( 'Enter a value for %s (default "%s")', $description, $default ) );
		$handle = fopen( 'php://stdin', 'r' );
		$line   = trim( fgets( $handle ) );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return $line ? $line : $default;
	}
}
