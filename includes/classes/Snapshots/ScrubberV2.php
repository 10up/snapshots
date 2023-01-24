<?php
/**
 * High security database scrubber.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};

/**
 * Class ScrubberV1
 *
 * @package TenUp\WPSnapshots
 */
class ScrubberV2 implements ScrubberInterface {

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

	/**
	 * Provides dummy users.
	 *
	 * @return array
	 */
	private function get_dummy_users() {
		static $users = [];

		if ( empty( $users ) ) {
			$file = fopen( trailingslashit( WPSNAPSHOTS_DIR ) . 'includes/data/users.csv', 'r' );

			while ( false !== ( $line = fgetcsv( $file ) ) ) {

				$user = [
					'username'   => $line[0],
					'first_name' => $line[1],
					'last_name'  => $line[2],
					'email'      => $line[3],
				];

				$users[] = $user;
			}

			fclose( $file );
		}

		return $users;
	}
}
