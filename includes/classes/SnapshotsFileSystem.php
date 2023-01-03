<?php
/**
 * SnapshotsFileSystem class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use Exception;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\{Shared, Service};
use WP_Filesystem_Direct;

/**
 * SnapshotsFileSystem class.
 *
 * @package TenUp\WPSnapshots
 */
class SnapshotsFileSystem implements Shared, Service {

	/**
	 * The WP_Filesystem_Direct instance.
	 *
	 * @var WP_Filesystem_Direct
	 */
	private $wp_filesystem;

	/**
	 * SnapshotsFileSystem constructor.
	 */
	public function __construct() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Initialize the filesystem.
		WP_Filesystem();

		$this->wp_filesystem = new WP_Filesystem_Direct( [] );
	}

	/**
	 * Deletes a file in the snapshot directory.
	 *
	 * @param string $name Unique identifier for the file.
	 * @param string $id   Snapshot ID.
	 */
	public function delete_file( string $name, string $id = ''  ) {
		$file = $this->get_file_path( $name, $id );

		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}

	/**
	 * Gets the contents of a file.
	 *
	 * @param string $name Unique identifier for the file.
	 * @return string $contents File contents.
	 *
	 * @throws WPSnapshotsException If unable to read file.
	 */
	public function get_file_contents( string $name ) : string {
		$file = $this->get_file_path( $name );

		try {
			$contents = $this->wp_filesystem->get_contents( $file );
			if ( false === $contents ) {
				throw new WPSnapshotsException( 'Unable to read file: ' . $file );
			}
		} catch ( Exception $e ) {
			throw new WPSnapshotsException( 'Unable to read file: ' . $file );
		}

		return $contents;
	}

	/**
	 * Puts contents into a file.
	 *
	 * @param string $name Unique identifier for the file.
	 * @param string $contents File contents.
	 * @param bool   $append Whether to append to the file.
	 *
	 * @throws WPSnapshotsException If unable to write to file.
	 */
	public function update_file_contents( string $name, string $contents, bool $append = false ) {
		$file = $this->get_file_path( $name );

		if ( $append ) {
			$existing_contents = $this->wp_filesystem->get_contents( $file );

			if ( $existing_contents ) {
				$contents = $existing_contents . $contents;
			}
		}

		if ( ! $this->wp_filesystem->put_contents( $file, $contents ) ) {
			throw new WPSnapshotsException( 'Unable to write to file: ' . $file );
		}
	}

	/**
	 * Returns whether a snapshot directory exists.
	 *
	 * @param string $id Snapshot ID.
	 * @return bool
	 */
	public function directory_exists( string $id ) : bool {
		return is_dir( $this->get_directory( $id ) );
	}

	/**
	 * Create snapshots directories. Providing an id creates the subdirectory as well.
	 *
	 * @param  string $id   Optional ID. Setting this will create the snapshot directory.
	 * @param  bool   $hard Overwrite an existing snapshot
	 *
	 * @throws WPSnapshotsException If the snapshot directory cannot be created.
	 */
	public function create_directory( $id = null, $hard = false ) {
		$snapshots_directory = trailingslashit( $this->get_directory() );

		if ( ! file_exists( $snapshots_directory ) ) {
			try {
				if ( ! mkdir( $snapshots_directory, 0755 ) ) {
					throw new WPSnapshotsException( 'Could not create snapshot directory' );
				}
			} catch ( Exception $e ) {
				throw new WPSnapshotsException( 'Could not create snapshot directory: ' . $e->getMessage() );
			}
		}

		if ( ! is_writable( $snapshots_directory ) ) {
			throw new WPSnapshotsException( 'Snapshot directory is not writable' );
		}

		if ( ! empty( $id ) ) {
			if ( $hard && file_exists( $snapshots_directory . $id . '/' ) ) {
				array_map( 'unlink', glob( $snapshots_directory . $id . '/*.*' ) );
				if ( ! rmdir( $snapshots_directory . $id . '/' ) ) {
					throw new WPSnapshotsException( 'Could not remove existing snapshot directory' );
				}
			}

			if ( ! file_exists( $snapshots_directory . $id . '/' ) ) {
				if ( ! mkdir( $snapshots_directory . $id . '/', 0755 ) ) {
					throw new WPSnapshotsException( 'Could not create snapshot directory' );
				}
			}

			if ( ! is_writable( $snapshots_directory . $id . '/' ) ) {
				throw new WPSnapshotsException( 'Snapshot directory is not writable' );
			}
		}
	}

	/**
	 * Gets the contents of a file as lines.
	 *
	 * @param string $file File name.
	 * @param string $id  Snapshot ID.
	 * @return array $lines File contents as lines.
	 * 
	 * @throws WPSnapshotsException If unable to read file.
	 */
	public function get_file_lines( string $file, string $id = '' ) : array {
		$file = $this->get_file_path( $file, $id );

		$lines = $this->wp_filesystem->get_contents_array( $file );

		if ( ! is_array( $lines ) ) {
			throw new WPSnapshotsException( 'Unable to read file: ' . $file );
		}

		return $lines;
	}

	/**
	 * Gets the size of a file.
	 *
	 * @param string $name Unique identifier for the file.
	 * @param string $id  Snapshot ID.
	 * @return int $size File size.
	 */
	public function get_file_size( string $name, string $id = '' ) : int {
		$file = $this->get_file_path( $name, $id );

		return $this->wp_filesystem->size( $file );
	}

	/**
	 * Gets the path to a file in the snapshot directory.
	 *
	 * @param string $name Unique identifier for the file.
	 * @param string $id  Snapshot ID.
	 * @return string $file Path to the file.
	 */
	private function get_file_path( string $name = '', string $id = '' ) : string {
		$directory = $this->get_directory( $id );

		return $directory . '/' . $name;
	}

	/**
	 * Gets the snapshots directory.
	 *
	 * @param string $id Snapshot ID.
	 * @return string $file Snapshots directory.
	 *
	 * @throws WPSnapshotsException If unable to create directory.
	 */
	private function get_directory( string $id = '' ) : string {

		/**
		 * Filters the configuration directory.
		 *
		 * @param string $file Snapshots directory.
		 */
		$directory = apply_filters(
			'wpsnapshots_directory',
			strlen( getenv( 'WPSNAPSHOTS_DIR' ) ) > 0 ? getenv( 'WPSNAPSHOTS_DIR' ) : ABSPATH . '/.wpsnapshots'
		);

		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) ) {
			throw new WPSnapshotsException( 'Unable to create ' . $directory );
		}

		return untrailingslashit( $directory . ( ! empty( $id ) ? '/' . $id : '' ) );
	}
}