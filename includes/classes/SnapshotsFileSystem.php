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
	 * Moves a directory and all of its contents recursively.
	 *
	 * @param string $source Source directory.
	 * @param string $destination Destination directory.
	 * @return bool
	 */
	public function move_directory( string $source, string $destination ) : bool {
		$files = $this->get_wp_filesystem()->dirlist( $source );

		var_dump( $files );
		if ( ! $files ) {
			return false;
		}

		$this->get_wp_filesystem()->mkdir( $destination );

		foreach ( $files as $file ) {
			if ( '.' === $file['name'] || '..' === $file['name'] ) {
				continue;
			}

			if ( 'f' === $file['type'] ) {
				$this->get_wp_filesystem()->move( $source . '/' . $file['name'], $destination . '/' . $file['name'] );
			} elseif ( 'd' === $file['type'] ) {
				$this->move_directory( $source . '/' . $file['name'], $destination . '/' . $file['name'] );
			}
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

		// Recursively delete everything in the wp-content directory except plugins/snapshots-command.
		$this->delete_directory( WP_CONTENT_DIR, false, [ 'snapshots-command' ] );

		$zip_file = $this->get_file_path( 'files.tar.gz', $id );

		$gzipped_file = gzopen( $zip_file, 'rb' );
		$contents     = gzread( $gzipped_file, filesize( $zip_file ) );
		gzclose( $gzipped_file );

		$this->get_wp_filesystem()->mkdir( '/tmp' );
		$this->get_wp_filesystem()->mkdir( '/tmp/files' );
		$this->get_wp_filesystem()->put_contents( '/tmp/files.tar', $contents );

		if ( ! function_exists( 'unzip_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Unzip the files.
		unzip_file( '/tmp/files.tar', '/tmp/files' );

		// Move the files to the wp-content directory.
		$this->move_directory( '/tmp/files', WP_CONTENT_DIR );
	}

	/**
	 * Recursively deletes files and subdirectories in a directory.
	 *
	 * @param string $directory Directory to delete.
	 * @param bool   $delete_root Delete the root directory.
	 * @param array  $excluded_files Files or directories to exclude from deletion.
	 *
	 * @return bool
	 */
	public function delete_directory( string $directory, bool $delete_root = true, array $excluded_files = [] ) : bool {
		$files = $this->get_wp_filesystem()->dirlist( $directory );

		foreach ( $files as $file ) {
			if ( in_array( $file['name'], $excluded_files, true ) ) {
				continue;
			}

			if ( $this->get_wp_filesystem()->is_dir( $directory . '/' . $file['name'] ) ) {
				$this->delete_directory( $directory . '/' . $file['name'], true, $excluded_files );
			} else {
				$this->get_wp_filesystem()->delete( $directory . '/' . $file['name'] );
			}
		}

		if ( $delete_root ) {
			$this->get_wp_filesystem()->rmdir( $directory );
		}

		return true;
	}

	/**
	 * Unzips a file.
	 *
	 * @param string $file File to unzip.
	 * @param string $destination_directory Destination directory to unzip to.
	 *
	 * @throws WPSnapshotsException If unable to unzip file.
	 */
	public function unzip_file( string $file, string $destination_directory ) {
		if ( ! function_exists( 'unzip_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$result = unzip_file( $file, $destination_directory );

		var_dump( $result );

		if ( true === $result ) {
			return;
		}

		if ( is_wp_error( $result ) && 'incompatible_archive' === $result->get_error_code() ) {
			// Unzip gzipped files into destination directory.
			$gzipped_file = gzopen( $file, 'rb' );
			$contents     = gzread( $gzipped_file, filesize( $file ) );
			$this->get_wp_filesystem()->put_contents( '/tmp/files.tar', $contents );

			// Unzip tar file into destination directory.
			$result = $this->unzip_file( '/tmp/files.tar', $destination_directory );

			// Delete the gzipped file.
			$this->get_wp_filesystem()->delete( '/tmp/files.tar' );

			if ( true === $result ) {
				return;
			}

			if ( is_wp_error( $result ) ) {
				throw new WPSnapshotsException( $result->get_error_message() );
			} else {
				throw new WPSnapshotsException( 'Unable to unzip file.' );
			}
		} elseif ( is_wp_error( $result ) ) {
			throw new WPSnapshotsException( $result->get_error_message() );
		} else {
			throw new WPSnapshotsException( 'Unable to unzip file.' );
		}

		return $result;
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
