<?php
/**
 * FileZipper class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use ArrayIterator;
use Iterator;
use Phar;
use PharData;
use TenUp\WPSnapshots\Log\Logging;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\FileSystem;
use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\SnapshotFiles;

use function TenUp\WPSnapshots\Utils\wpsnapshots_wp_content_dir;

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
		if ( ! class_exists( 'PharData' ) ) {
			throw new WPSnapshotsException( 'PharData class not found.' );
		}

		$this->snapshot_files->create_directory( $id );

		$iterator = $this->get_build_from_iterator_iterator( $args );

		$phar_file = $this->snapshot_files->get_file_path( 'files.tar', $id );
		$phar      = new PharData( $phar_file );

		$phar->buildFromIterator( $iterator );
		$phar->compress( Phar::GZ );

		unset( $phar );
		Phar::unlinkArchive( $phar_file );

		$this->file_system->get_wp_filesystem()->delete( $phar_file );

		$this->log( 'Created files zip.' );
	}

	/**
	 * Gets an iterator of files to pass to buildFromIterator.
	 *
	 * @param array $args Snapshot arguments.
	 *
	 * @return Iterator
	 */
	private function get_build_from_iterator_iterator( array $args ) : Iterator {
		$excludes = $args['excludes'] ?? [];

		if ( $args['exclude_uploads'] ) {
			$excludes[] = 'uploads';
		}

		$excludes[] = trailingslashit( str_replace( wpsnapshots_wp_content_dir(), '', WPSNAPSHOTS_DIR ) ) . '.wpsnapshots';
		$excludes[] = trailingslashit( str_replace( wpsnapshots_wp_content_dir(), '', WPSNAPSHOTS_DIR ) ) . 'vendor/wordpress';

		$excludes = array_map(
			function( $exclude ) {
				$full_exclude = trailingslashit( wpsnapshots_wp_content_dir() ) . $exclude;
				// Remove double slashes.
				return preg_replace( '#/+#', '/', $full_exclude );
			},
			$excludes
		);

		$initial_files = $this->file_system->get_wp_filesystem()->dirlist( wpsnapshots_wp_content_dir() );
		$file_list     = $this->build_file_list_recursively( $initial_files, wpsnapshots_wp_content_dir(), $excludes );

		$this->log( 'Files to zip: ' . count( $file_list ) );

		return new ArrayIterator( $file_list );
	}

	/**
	 * Recursively builds a list of files to pass to buildFromIterator.
	 *
	 * @param array  $files    List of files.
	 * @param string $base_dir Base directory.
	 * @param array  $excludes List of files to exclude.
	 *
	 * @return array
	 */
	private function build_file_list_recursively( array $files, string $base_dir, array $excludes ) : array {
		$file_list = [];

		$wp_content_dir = wpsnapshots_wp_content_dir();

		foreach ( $files as $file ) {
			$full_path = trailingslashit( $base_dir ) . $file['name'];

			if ( in_array( $full_path, $excludes, true ) || in_array( trailingslashit( $full_path ), $excludes, true ) ) {
				continue;
			}

			foreach ( $excludes as $exclude ) {
				$path_without_wp_content = str_replace( wpsnapshots_wp_content_dir(), '', $full_path );

				if ( 0 === strpos( $path_without_wp_content, $exclude ) ) {
					continue 2;
				}

				if ( 0 === strpos( $path_without_wp_content, '/' . $exclude ) ) {
					continue 2;
				}
			}

			if ( 'f' === $file['type'] ) {
				$file_list[ str_replace( $wp_content_dir, '', $full_path ) ] = $full_path;
			} elseif ( 'd' === $file['type'] ) {
				$dir_files = $this->file_system->get_wp_filesystem()->dirlist( $full_path );
				$file_list = array_merge( $file_list, $this->build_file_list_recursively( $dir_files, $full_path, $excludes ) );
			}
		}

		return $file_list;
	}
}
