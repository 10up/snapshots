<?php
/**
 * Factory for database scrubbers.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};

/**
 * Class ScrubberFactory
 *
 * @package TenUp\WPSnapshots
 */
class ScrubberFactory implements SharedService {

	use Logging;

	/**
	 * SnapshotsFileSystem instance.
	 *
	 * @var SnapshotFiles
	 */
	private $snapshot_files;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotFiles   $snapshot_files SnapshotFiles instance.
	 * @param LoggerInterface $logger LoggerInterface instance.
	 */
	public function __construct( SnapshotFiles $snapshot_files, LoggerInterface $logger ) {
		$this->snapshot_files = $snapshot_files;
		$this->set_logger( $logger );
	}

	/**
	 * Creates a database scrubber.
	 *
	 * @param int $type ScrubberInterface type. 1 or 2.
	 * @return ?ScrubberInterface ScrubberInterface instance.
	 */
	public function create( int $type ) : ?ScrubberInterface {
		$this->log( 'Creating scrubber of type ' . $type );

		switch ( $type ) {
			case 1:
				return new ScrubberV1( $this->snapshot_files, $this->logger );
			case 2:
				return new ScrubberV2( $this->snapshot_files, $this->logger );
			default:
				return null;
		}
	}
}
