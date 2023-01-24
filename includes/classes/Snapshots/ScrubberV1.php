<?php
/**
 * Medium security database scrubber.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\Log\LoggerInterface;
use TenUp\WPSnapshots\Log\Logging;

/**
 * Class ScrubberV1
 *
 * @package TenUp\WPSnapshots
 */
class ScrubberV1 implements ScrubberInterface {

	use Logging;

	/**
	 * File system.
	 *
	 * @var SnapshotFiles
	 */
	private $snapshot_files;

	/**
	 * ScrubberV1 constructor.
	 *
	 * @param SnapshotFiles   $snapshot_files File system.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( SnapshotFiles $snapshot_files, LoggerInterface $logger ) {
		$this->snapshot_files = $snapshot_files;
		$this->set_logger( $logger );
	}

	/**
	 * Scrubs the database dump.
	 */
	public function scrub() : void {
		
	}

}
