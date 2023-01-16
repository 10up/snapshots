<?php
/**
 * FileZipper class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Infrastructure\{Service, Shared};
use TenUp\WPSnapshots\Log\Logging;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use ZipArchive;

/**
 * Class FileZipper
 *
 * @package TenUp\WPSnapshots
 */
class FileZipper implements Shared, Service {

	use Logging;

	/**
	 * SnapshotsFileSystem instance.
	 *
	 * @var SnapshotsFileSystem
	 */
	private $snapshots_file_system;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotsFileSystem $snapshots_file_system SnapshotsFileSystem instance.
	 */
	public function __construct( SnapshotsFileSystem $snapshots_file_system ) {
		$this->snapshots_file_system = $snapshots_file_system;
	}

	/**
	 * Zips up the files in the wp-content directory.
	 *
	 * @param string $id  Snapshot ID.
	 * @param array  $args Snapshot arguments.
	 *
	 * @throws WPSnapshotsException If ZipArchive class not found.
	 */
	public function zip_files( string $id, array $args ) {
		$this->log( 'Saving files...' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new WPSnapshotsException( 'ZipArchive class not found. Please install the PHP Zip extension.' );
		}

		// Zip all files in the wp-content directory except the excludes.
		$zip = new ZipArchive();

		$zip_file = $this->snapshots_file_system->get_file_path( 'files.zip', $id );

		$open_result = true !== $zip->open( $zip_file, ZipArchive::CREATE );
		if ( $open_result ) {
			throw new WPSnapshotsException( 'Could not create zip file. Error: ' . $zip->getStatusString() );
		}

		$excludes = $this->get_excludes( $args );

		$files = $this->get_files_to_zip( $excludes );

		foreach ( $files as $file ) {
			if ( file_exists( $file ) && is_file( $file ) ) {
				$zip->addFile( $file, $this->get_zip_file_path( $file ) );
			} elseif ( is_dir( $file ) ) {
				$zip->addEmptyDir( $this->get_zip_file_path( $file ) );
			}
		}

		if ( ! $zip->close() ) {
			throw new WPSnapshotsException( 'Could not close zip file. Error: ' . $zip->getStatusString() );
		}
	}

	/**
	 * Unzips the files in the wp-content directory.
	 *
	 * @param string $id Snapshot ID.
	 *
	 * @throws WPSnapshotsException If ZipArchive class not found.
	 */
	public function unzip_snapshot_files( string $id ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			throw new WPSnapshotsException( 'WP_CONTENT_DIR is not defined.' );
		}

		// Copy this plugin to a temporary location.
		$this->snapshots_file_system->move_directory( WPSNAPSHOTS_DIR, '/tmp/wpsnapshots-plugin' );

		$zip_file = $this->snapshots_file_system->get_file_path( 'files.zip', $id );

		$zip = new ZipArchive();

		$open_result = true !== $zip->open( $zip_file );
		if ( $open_result ) {
			throw new WPSnapshotsException( 'Could not open zip file. Error: ' . $zip->getStatusString() );
		}

		$zip->extractTo( WP_CONTENT_DIR );

		if ( ! $zip->close() ) {
			throw new WPSnapshotsException( 'Could not close zip file. Error: ' . $zip->getStatusString() );
		}

		// Move the plugin back to its original location.
		$this->snapshots_file_system->move_directory( '/tmp/wpsnapshots-plugin', dirname( WPSNAPSHOTS_DIR ) );
	}

	/**
	 * Gets the files to zip.
	 *
	 * @param array $excludes Excludes.
	 *
	 * @return array
	 *
	 * @throws WPSnapshotsException If WP_CONTENT_DIR is not defined.
	 */
	private function get_files_to_zip( array $excludes ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			throw new WPSnapshotsException( 'WP_CONTENT_DIR is not defined.' );
		}

		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( WP_CONTENT_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $this->should_exclude( $file, $excludes ) ) {
				continue;
			}

			$files[] = $file->getPathname();
		}

		return $files;
	}

	/**
	 * Gets the path to the file in the zip.
	 *
	 * @param string $file File path.
	 *
	 * @return string
	 *
	 * @throws WPSnapshotsException If WP_CONTENT_DIR is not defined.
	 */
	private function get_zip_file_path( string $file ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			throw new WPSnapshotsException( 'WP_CONTENT_DIR is not defined.' );
		}

		$zip_file_path = str_replace( WP_CONTENT_DIR, '', $file );

		// Remove leading slash.
		if ( '/' === substr( $zip_file_path, 0, 1 ) ) {
			$zip_file_path = substr( $zip_file_path, 1 );
		}

		return $zip_file_path;
	}

	/**
	 * Gets the excludes.
	 *
	 * @param array $args Snapshot arguments.
	 *
	 * @return array
	 */
	private function get_excludes( array $args ) {
		return $args['excludes'] ?? [];
	}

	/**
	 * Checks if a file should be excluded.
	 *
	 * @param SplFileInfo $file     File.
	 * @param array       $excludes Excludes.
	 *
	 * @return bool
	 */
	private function should_exclude( SplFileInfo $file, array $excludes ) {
		$should_exclude = false;

		foreach ( $excludes as $exclude ) {
			if ( $file->isDir() && $exclude === $file->getFilename() ) {
				$should_exclude = true;
				break;
			}

			if ( $file->isFile() && $exclude === $file->getFilename() ) {
				$should_exclude = true;
				break;
			}
		}

		return $should_exclude;
	}
}
