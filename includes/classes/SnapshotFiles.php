<?php
/**
 * SnapshotFiles class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use Exception;
use PharData;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\SharedService;
use WP_Filesystem_Base;

/**
 * SnapshotFiles class.
 *
 * @package TenUp\WPSnapshots
 */
class SnapshotFiles implements SharedService {

	/**
	 * The FileSystem instance.
	 *
	 * @var FileSystem
	 */
	private $file_system;

	/**
	 * Class constructor.
	 *
	 * @param FileSystem $file_system The FileSystem instance.
	 */
	public function __construct( FileSystem $file_system ) {
		$this->file_system = $file_system;
	}

	/**
	 * Deletes a file in the snapshot directory.
	 *
	 * @param string $file_name Name for the file.
	 * @param string $id   Snapshot ID.
	 */
	public function delete_file( string $file_name, string $id = '' ) {
		$file = $this->get_file_path( $file_name, $id );

		if ( $this->get_wp_filesystem()->exists( $file ) ) {
			$this->get_wp_filesystem()->delete( $file );
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
		return $this->get_wp_filesystem()->exists( $this->get_file_path( $file_name, $id ) );
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

		if ( ! $this->get_wp_filesystem()->exists( $snapshots_directory ) ) {
			try {
				if ( ! $this->get_wp_filesystem()->mkdir( $snapshots_directory ) ) {
					throw new WPSnapshotsException( 'Could not create snapshot directory' );
				}
			} catch ( Exception $e ) {
				throw new WPSnapshotsException( 'Could not create snapshot directory: ' . $e->getMessage() );
			}
		}

		if ( ! $this->get_wp_filesystem()->is_writable( $snapshots_directory ) ) {
			throw new WPSnapshotsException( 'Snapshot directory is not writable' );
		}

		if ( ! empty( $id ) ) {
			if ( $hard && $this->get_wp_filesystem()->exists( $snapshots_directory . $id . '/' ) ) {
				if ( ! $this->get_wp_filesystem()->rmdir( $snapshots_directory . $id . '/', true ) ) {
					throw new WPSnapshotsException( 'Could not remove existing snapshot directory' );
				}
			}

			if ( ! $this->get_wp_filesystem()->exists( $snapshots_directory . $id . '/' ) ) {
				if ( ! $this->get_wp_filesystem()->mkdir( $snapshots_directory . $id . '/' ) ) {
					throw new WPSnapshotsException( 'Could not create snapshot directory' );
				}
			}

			if ( ! $this->get_wp_filesystem()->is_writable( $snapshots_directory . $id . '/' ) ) {
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
		return $this->file_system->get_wp_filesystem();
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
	 * Unzips the files in the wp-content directory.
	 *
	 * @param string $id Snapshot ID.
	 * @param string $destination Destination directory.
	 *
	 * @return string[] Errors.
	 *
	 * @throws WPSnapshotsException If there is an error.
	 */
	public function unzip_snapshot_files( string $id, string $destination ) : array {
		// Recursively delete everything in the wp-content directory except plugins/snapshots-command.
		$this->file_system->delete_directory_contents( $destination, false, [ 'snapshots-command' ] );

		$zip_file = $this->get_file_path( 'files.tar.gz', $id );

		$this->get_wp_filesystem()->mkdir( '/tmp' );
		$this->get_wp_filesystem()->mkdir( '/tmp/files' );

		if ( ! function_exists( 'unzip_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Unzip the files.
		$unzip_result = unzip_file( $zip_file, '/tmp/files' );

		if ( is_wp_error( $unzip_result ) ) {
			// Delete /tmp/files and re-create it.
			$this->get_wp_filesystem()->rmdir( '/tmp/files', true );
			$this->get_wp_filesystem()->mkdir( '/tmp/files' );

			$phar = new PharData( $zip_file );
			$phar->decompress();
			$phar->extractTo( '/tmp/files' );

			// Delete the uncompressed file.
			$this->get_wp_filesystem()->delete( $zip_file );
		}

		$errors = $this->file_system->sync_files( '/tmp/files', $destination, true );

		return $errors;
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

		if ( ! $this->get_wp_filesystem()->is_dir( $directory ) && ! $this->get_wp_filesystem()->mkdir( $directory ) ) {
			throw new WPSnapshotsException( 'Unable to create ' . $directory );
		}

		return untrailingslashit( $directory . ( ! empty( $id ) ? '/' . $id : '' ) );
	}
}
