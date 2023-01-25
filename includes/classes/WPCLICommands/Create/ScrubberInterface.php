<?php
/**
 * Interface for a database scrubber.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands\Create;

/**
 * Interface ScrubberInterface
 *
 * @package TenUp\WPSnapshots
 */
interface ScrubberInterface {

	/**
	 * Scrubs the database dump.
	 *
	 * @param array  $args Snapshot arguments.
	 * @param string $id Snapshot ID.
	 *
	 * @return void
	 */
	public function scrub( array $args, string $id ) : void;
}
