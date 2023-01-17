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
use WP_Filesystem_Base;

/**
 * SnapshotsFileSystem class.
 *
 * @package TenUp\WPSnapshots
 */
class SnapshotsFileSystem implements Shared, Service {

	/**
	 * The WP_Filesystem_Direct instance.
	 *
	 * @var ?WP_Filesystem_Base
	 */
	private $wp_filesystem;

	/**
	 * Deletes a file in the snapshot directory.
	 *
	 * @param string $file_name Name for the file.
	 * @param string $id   Snapshot ID.
	 */
	public function delete_file( string $file_name, string $id = '' ) {
		$file = $this->get_file_path( $file_name, $id );

		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}

	/**
	 * Gets the contents of a file.
	 *
	 * @param string  $file_name Name for the file.
	 * @param ?string $id   Snapshot ID.
	 * @return string $contents File contents.
	 *
	 * @throws WPSnapshotsException If unable to read file.
	 */
	public function get_file_contents( string $file_name, string $id = null ) : string {
		$file = $this->get_file_path( $file_name, $id );

		try {
			$contents = $this->get_wp_filesystem()->get_contents( $file );
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
	 * @param string  $file_name Name for the file.
	 * @param string  $contents File contents.
	 * @param bool    $append Whether to append to the file.
	 * @param ?string $id Snapshot ID.
	 *
	 * @throws WPSnapshotsException If unable to write to file.
	 */
	public function update_file_contents( string $file_name, string $contents, bool $append = false, string $id = null ) {
		$file = $this->get_file_path( $file_name, $id );

		if ( ! is_null( $id ) && ! $this->directory_exists( $id ) ) {
			$this->create_directory( $id );
		}

		if ( $append ) {
			$existing_contents = $this->get_wp_filesystem()->get_contents( $file );

			if ( $existing_contents ) {
				$contents = $existing_contents . $contents;
			}
		}

		if ( ! $this->get_wp_filesystem()->put_contents( $file, $contents ) ) {
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
	 * Returns whether a file exists in the snapshot directory.
	 *
	 * @param string $file_name Name for the file.
	 * @param string $id   Snapshot ID.
	 * @return bool
	 */
	public function file_exists( string $file_name, string $id = '' ) : bool {
		return file_exists( $this->get_file_path( $file_name, $id ) );
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

		$lines = $this->get_wp_filesystem()->get_contents_array( $file );

		if ( ! is_array( $lines ) ) {
			throw new WPSnapshotsException( 'Unable to read file: ' . $file );
		}

		return $lines;
	}

	/**
	 * Gets the size of a file.
	 *
	 * @param string $file_name Name for the file.
	 * @param string $id  Snapshot ID.
	 * @return int $size File size.
	 */
	public function get_file_size( string $file_name, string $id = '' ) : int {
		$file = $this->get_file_path( $file_name, $id );

		return $this->get_wp_filesystem()->size( $file );
	}

	/**
	 * Gets the WP_Filesystem instance.
	 *
	 * @return WP_Filesystem_Base $wp_filesystem WP_Filesystem instance.
	 */
	public function get_wp_filesystem() {
		global $wp_filesystem;

		if ( ! $this->wp_filesystem ) {
			if ( ! $wp_filesystem ) {
				WP_Filesystem( null, null, true );
			}

			$this->wp_filesystem = $wp_filesystem;
		}

		return $this->wp_filesystem;
	}

	/**
	 * Gets the path to a file in the snapshot directory.
	 *
	 * @param string  $file_name Name for the file.
	 * @param ?string $id  Snapshot ID.
	 * @return string $file Path to the file.
	 */
	public function get_file_path( string $file_name = '', ?string $id = null ) : string {
		$directory = $this->get_directory( $id );

		return $directory . '/' . $file_name;
	}

	/**
	 * Moves a directory.
	 *
	 * @param string $source Source directory.
	 * @param string $destination Destination directory.
	 * @return bool
	 */
	public function move_directory( string $source, string $destination ) : bool {
		return $this->get_wp_filesystem()->copy( $source, $destination, true );
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
		$this->get_wp_filesystem()->copy( WPSNAPSHOTS_DIR, '/tmp/wpsnapshots-plugin' );

		register_shutdown_function(
			function () {
				// Move the plugin back to its original location.
				$this->get_wp_filesystem()->copy( '/tmp/wpsnapshots-plugin', dirname( WPSNAPSHOTS_DIR ) );
			}
		);

		$this->get_wp_filesystem()->delete( WP_CONTENT_DIR, true );
		$this->get_wp_filesystem()->mkdir( WP_CONTENT_DIR );

		$zip_file = $this->get_file_path( 'files.tar.gz', $id );

		$this->unzip_file( $zip_file, WP_CONTENT_DIR );
	}

	/**
	 * Unzips a gz file. ZipArchive does not work.
	 *
	 * @param string $file File to unzip.
	 * @param string $destination Destination to unzip to.
	 *
	 * @throws WPSnapshotsException If there is an error unzipping.
	 */
	public function unzip_file( string $file, string $destination ) {
		$gzipped = gzopen( $file, 'rb' );
		if ( ! $gzipped ) {
			throw new WPSnapshotsException( 'Could not open gzipped file.' );
		}

		$data = '';
		while ( ! gzeof( $gzipped ) ) {
			$unzipped_content = gzread( $gzipped, 4096 );
			if ( false === $unzipped_content ) {
				throw new WPSnapshotsException( 'Could not read gzipped file.' );
			}

			$data .= $unzipped_content;
		}

		$this->get_wp_filesystem()->put_contents( $destination, $data );
	}

	/**
	 * Gets the snapshots directory.
	 *
	 * @param ?string $id Snapshot ID.
	 * @return string $file Snapshots directory.
	 *
	 * @throws WPSnapshotsException If unable to create directory.
	 */
	private function get_directory( ?string $id = null ) : string {

		/**
		 * Filters the configuration directory.
		 *
		 * @param string $file Snapshots directory.
		 */
		$directory = apply_filters(
			'wpsnapshots_directory',
			defined( 'WPSNAPSHOTS_DIR' ) ? WPSNAPSHOTS_DIR : ABSPATH . '/.wpsnapshots'
		);

		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) ) {
			throw new WPSnapshotsException( 'Unable to create ' . $directory );
		}

		return untrailingslashit( $directory . ( ! empty( $id ) ? '/' . $id : '' ) );
	}
}
