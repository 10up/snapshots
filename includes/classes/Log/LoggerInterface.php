<?php
/**
 * Logger interface.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Log;

use TenUp\Snapshots\Infrastructure\SharedService;

/**
 * Logger interface.
 *
 * @package TenUp\Snapshots\Log
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
