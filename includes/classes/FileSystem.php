<?php
/**
 * SnapshotsFiles class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\SharedService;
use WP_Filesystem_Base;

/**
 * SnapshotsFiles class.
 *
 * @package TenUp\WPSnapshots
 */
class FileSystem implements SharedService {

	/**
	 * The WP_Filesystem_Direct instance.
	 *
	 * @var ?WP_Filesystem_Base
	 */
	private $wp_filesystem;

	/**
	 * Gets the WP_Filesystem instance.
	 *
	 * @return WP_Filesystem_Base $wp_filesystem WP_Filesystem instance.
	 */
	public function get_wp_filesystem() {
		global $wp_filesystem;

		if ( ! $this->wp_filesystem ) {
			if ( ! $wp_filesystem ) {
				WP_Filesystem( false, false, true );
			}

			$this->wp_filesystem = $wp_filesystem;
		}

		return $this->wp_filesystem;
	}

	/**
	 * Recursively syncs all files from one directory to another without deleting what's already in the destination.
	 *
	 * @param string $source Source directory.
	 * @param string $destination Destination directory.
	 * @param bool   $delete_source Whether to delete the source directory after syncing.
	 *
	 * @return string[]
	 *
	 * @throws WPSnapshotsException If unable to sync files.
	 */
	public function sync_files( string $source, string $destination, bool $delete_source = false ) : array {
		$files = $this->get_wp_filesystem()->dirlist( $source );

		if ( ! $files ) {
			throw new WPSnapshotsException( 'Unable to read source directory: ' . $source );
		}

		$errors = $this->sync_files_recursive( $files, $source, $destination );

		if ( $delete_source ) {
			if ( ! $this->get_wp_filesystem()->rmdir( $source, true ) ) {
				throw new WPSnapshotsException( 'Unable to delete source directory: ' . $source );
			}
		}

		return $errors;
	}

	/**
	 * Recursively syncs a list of files to another location.
	 *
	 * @param array[] $files List of files to sync.
	 * @param string  $source Source directory.
	 * @param string  $destination Destination directory.
	 *
	 * @return string[]
	 *
	 * @throws WPSnapshotsException If unable to sync files.
	 */
	private function sync_files_recursive( array $files, string $source, string $destination ) : array {
		$errors = [];

		foreach ( $files as $file ) {
			try {
				$source_file      = trailingslashit( $source ) . $file['name'];
				$destination_file = trailingslashit( $destination ) . $file['name'];

				if ( 'f' === $file['type'] ) {
					if ( ! $this->get_wp_filesystem()->is_readable( $source_file ) ) {
						continue;
					}

					// Copy the file.
					$copied = $this->get_wp_filesystem()->copy( $source_file, $destination_file, true );

					if ( ! $copied ) {
						throw new WPSnapshotsException( 'Unable to copy file: ' . $source_file . ' to ' . $destination_file );
					}
				} elseif ( 'd' === $file['type'] ) {
					// Create the directory.
					$this->get_wp_filesystem()->mkdir( $destination_file );

					// Sync the files in the directory.
					$next_files = $this->get_wp_filesystem()->dirlist( $source_file );

					if ( false === $next_files ) {
						throw new WPSnapshotsException( 'Unable to read source directory: ' . $source );
					}

					$next_errors = $this->sync_files_recursive( $next_files, $source_file, $destination_file );

					if ( ! empty( $next_errors ) ) {
						$errors = array_merge( $errors, $next_errors );
					}
				}
			} catch ( WPSnapshotsException $e ) {
				$errors[] = $e->getMessage();
			}
		}

		return $errors;
	}

	/**
	 * Recursively deletes files and subdirectories in a directory.
	 *
	 * @param string $directory Directory to delete.
	 * @param bool   $delete_root Delete the root directory.
	 * @param array  $excluded_files Files or directories to exclude from deletion.
	 *
	 * @return bool
	 *
	 * @throws WPSnapshotsException If unable to delete directory.
	 */
	public function delete_directory_contents( string $directory, bool $delete_root = true, array $excluded_files = [] ) : bool {
		$files = $this->get_wp_filesystem()->dirlist( $directory );

		foreach ( $files as $file ) {
			if ( in_array( $file['name'], $excluded_files, true ) || in_array( trailingslashit( $directory ) . $file['name'], $excluded_files, true ) ) {
				continue;
			}

			if ( $this->get_wp_filesystem()->is_dir( $directory . '/' . $file['name'] ) ) {
				$this->delete_directory_contents( $directory . '/' . $file['name'], $delete_root, $excluded_files );
			} else {
				$this->get_wp_filesystem()->delete( $directory . '/' . $file['name'] );
			}
		}

		if ( $delete_root ) {
			if ( ! empty( $excluded_files ) ) {
				throw new WPSnapshotsException( 'Cannot delete root directory because files were excluded from deletion: ' . $directory );
			}

			$this->get_wp_filesystem()->rmdir( $directory );
		}

		return true;
	}

	/**
	 * Unzips a file.
	 *
	 * @param string $file File to unzip.
	 * @param string $destination Destination to unzip to.
	 *
	 * @throws WPSnapshotsException If unable to unzip file.
	 */
	public function unzip_file( string $file, string $destination ) {
		if ( ! function_exists( 'unzip_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$result = unzip_file( $file, $destination );

		if ( true === $result ) {
			return;
		}

		if ( is_wp_error( $result ) && 'incompatible_archive' === $result->get_error_code() && strpos( $file, '.sql.gz' ) !== false ) {
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

			gzclose( $gzipped );

			$this->get_wp_filesystem()->put_contents( trailingslashit( $destination ) . str_replace( '.gz', '', basename( $file ) ), $data );
		} elseif ( is_wp_error( $result ) ) {
			throw new WPSnapshotsException( $result->get_error_message() );
		} else {
			throw new WPSnapshotsException( 'Unable to unzip file.' );
		}

		return $result;
	}
}
