<?php
/**
 * Utility functions
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Utils;

use WP_CLI;

/**
 * Throws a WP_CLI error or an exception.
 *
 * @param string $message Error message.
 *
 * @throws WPSnapshotsException If WP_CLI is not defined.
 */
function error( string $message ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::error( $message );
	} else {
		throw new WPSnapshotsException( $message );
	}
}

/**
 * Shows a WP_CLI debug message if WP_CLI is available. Otherwise, logs it to the error log.
 *
 * @param string $message Debug message.
 */
function debug( string $message ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::debug( $message );
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG_LOG constant is checked.
	}
}
