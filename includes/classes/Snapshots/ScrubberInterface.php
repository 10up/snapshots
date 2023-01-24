<?php
/**
 * Interface for a database scrubber.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

/**
 * Interface ScrubberInterface
 *
 * @package TenUp\WPSnapshots
 */
interface ScrubberInterface {

	/**
	 * Scrubs the database dump.
	 */
	public function scrub() : void;
}
