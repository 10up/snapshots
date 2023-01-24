<?php
/**
 * System class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Infrastructure\SharedService;

/**
 * System class.
 *
 * @package TenUp\WPSnapshots
 */
class System implements SharedService {

	/**
	 * Wrapper for the PHP exec() function.
	 *
	 * @param string $command The command to execute.
	 * @param array  $output The output of the command.
	 * @param int    $return_var The return value of the command.
	 *
	 * @return bool True if the command executed successfully, false otherwise.
	 */
	public function exec( string $command, array &$output = null, int &$return_var = null ) : bool {
		exec( $command, $output, $return_var ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

		return 0 === $return_var;
	}
}
