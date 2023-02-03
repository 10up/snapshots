<?php
/**
 * Logger interface.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Log;

use TenUp\WPSnapshots\Infrastructure\SharedService;

/**
 * Logger interface.
 *
 * @package TenUp\WPSnapshots\Log
 */
interface LoggerInterface extends SharedService {

	/**
	 * Log a message.
	 *
	 * @param string $message Message to log.
	 * @param string $type Type of message.
	 */
	public function log( string $message, $type = 'info' );
}
