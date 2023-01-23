<?php
/**
 * WP_CLI-based logger.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Log;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * WP_CLI-based logger.
 *
 * @package TenUp\WPSnapshots\Log
 */
class WPCLILogger implements LoggerInterface {

	/**
	 * Logs a message.
	 *
	 * @param string $message Message to log.
	 * @param string $type Type of message.
	 */
	public function log( string $message, $type = 'info' ) {
		$wp_cli = wp_cli();

		switch ( $type ) {
			case 'error':
				$wp_cli::line( $wp_cli::colorize( '%R' . $message . '%n' ) );
				break;
			case 'warning':
				$wp_cli::line( $wp_cli::colorize( '%Y' . $message . '%n' ) );
				break;
			case 'success':
				$wp_cli::line( $wp_cli::colorize( '%G' . $message . '%n' ) );
				break;
			default:
				$wp_cli::line( $wp_cli::colorize( '%B' . $message . '%n' ) );
				break;
		}
	}
}
