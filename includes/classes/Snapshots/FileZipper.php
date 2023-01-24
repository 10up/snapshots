<?php
/**
 * FileZipper class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use Phar;
use PharData;
use TenUp\WPSnapshots\Log\Logging;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\FileSystem;
use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\SnapshotFiles;

/**
 * Class FileZipper
 *
 * @package TenUp\WPSnapshots
 */
class FileZipper implements SharedService {

	use Logging;

	/**
	 * SnapshotFiles instance.
	 *
	 * @var SnapshotFiles
	 */
	private $snapshot_files;

	/**
	 * FileSystem instance.
	 *
	 * @var FileSystem
	 */
	private $file_system;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotFiles $snapshot_files SnapshotFiles instance.
	 * @param FileSystem    $file_system FileSystem instance.
	 */
	public function __construct( SnapshotFiles $snapshot_files, FileSystem $file_system ) {
		$this->snapshot_files = $snapshot_files;
		$this->file_system    = $file_system;
	}

	/**
	 * Zips up the files in the wp-content directory.
	 *
	 * @param string $id  Snapshot ID.
	 * @param array  $args Snapshot arguments.
	 *
	 * @throws WPSnapshotsException If could not create zip.
	 */
	public function zip_files( string $id, array $args ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			throw new WPSnapshotsException( 'WP_CONTENT_DIR is not defined.' );
		}

		if ( ! class_exists( 'PharData' ) ) {
			throw new WPSnapshotsException( 'PharData class not found.' );
		}

		$phar_file = $this->snapshot_files->get_file_path( 'files.tar', $id );
		$phar      = new PharData( $phar_file );

		$excludes = $this->get_excludes_regex( $args );

		$phar->buildFromDirectory( WP_CONTENT_DIR, $excludes );
		$phar->compress( Phar::GZ );

		$this->file_system->get_wp_filesystem()->delete( $phar_file );
	}

	/**
	 * Gets the excludes regex
	 *
	 * @param array $args Snapshot arguments.
	 *
	 * @return string
	 */
	private function get_excludes_regex( array $args ) : string {
		$excludes = $args['excludes'] ?? [];

		if ( $args['exclude_uploads'] ) {
			$excludes[] = 'uploads';
		}

		// Build the regex string.
		$excludes_regex = '';

		foreach ( $excludes as $exclude ) {
			$excludes_regex .= sprintf( '|%s', preg_quote( $exclude, '/' ) );
		}

		// Remove the leading pipe.
		if ( '' !== $excludes_regex ) {
			$excludes_regex = substr( $excludes_regex, 1 );
		}

		return '/^((?!' . $excludes_regex . ').)*$/';
	}
}
