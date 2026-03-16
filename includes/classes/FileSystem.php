<?php
/**
 * SnapshotsDirectory class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Infrastructure\SharedService;
use WP_Filesystem_Base;

use function TenUp\Snapshots\Utils\snapshots_add_trailing_slash;

/**
 * SnapshotsDirectory class.
 *
 * @package TenUp\Snapshots
 */
class FileSystem implements SharedService {

	/**
	 * The WP_Filesystem_Direct instance.
	 *
	 * @var ?mixed
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
				if ( function_exists( 'WP_Filesystem' ) ) {
					add_filter(
						'filesystem_method',
						static function () {
							return 'direct';
						},
						PHP_INT_MAX
					);
					WP_Filesystem( false, false, true );

				} else {
					require_once TENUP_SNAPSHOTS_DIR . '/includes/lib/wp-file-system-direct-shim.php';

					$wp_filesystem = new \WP_Filesystem_Direct_Shim(); // @phpstan-ignore-line
				}
			}

			$this->wp_filesystem = $wp_filesystem;
		}

		return $this->wp_filesystem;
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
	 * @throws SnapshotsException If unable to delete directory.
	 */
	public function delete_directory_contents( string $directory, bool $delete_root = true, array $excluded_files = [] ) : bool {
		$files = $this->get_wp_filesystem()->dirlist( $directory );

		foreach ( $files as $file ) {
			if ( in_array( $file['name'], $excluded_files, true ) ) {
				continue;
			}

			if ( in_array( trailingslashit( $directory ) . $file['name'], $excluded_files, true ) ) {
				continue;
			}

			if ( $this->get_wp_filesystem()->is_dir( $directory . '/' . $file['name'] ) ) {
				$this->delete_directory_contents( $directory . '/' . $file['name'], $delete_root, $excluded_files );
			} else {
				foreach ( $excluded_files as $excluded_file ) {
					// If the file is within an excluded directory, skip it.
					if ( false !== strpos( $directory . '/' . $file['name'], $excluded_file ) ) {
						continue 2;
					}
				}

				$this->get_wp_filesystem()->delete( $directory . '/' . $file['name'] );
			}
		}

		if ( $delete_root ) {
			if ( ! empty( $excluded_files ) ) {
				// Don't delete the root directory if it contains any of the excluded files.
				foreach ( $excluded_files as $excluded_file ) {
					if ( $directory === $excluded_file || strpos( $excluded_file, $directory ) === 0 ) {
						return true;
					}

					// Skip if directory contains excluded directory.
					if ( false !== strpos( $directory, $excluded_file ) ) {
						return true;
					}
				}
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
	 * @throws SnapshotsException If unable to unzip file.
	 */
	public function unzip_file( string $file, string $destination ) {
		if ( ! function_exists( 'unzip_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$result = unzip_file( $file, $destination );

		if ( true === $result ) {
			return;
		}

		// @phpstan-ignore-next-line
		if ( is_wp_error( $result ) && 'incompatible_archive' === $result->get_error_code() && strpos( $file, '.sql.gz' ) !== false ) {
			// Unzip with gzip.
			$unzip_gzip = sprintf( 'gzip -dkf < %s > %s', escapeshellarg( $file ), escapeshellarg( snapshots_add_trailing_slash( $destination ) . basename( $file, '.gz' ) ) );
			$result     = exec( $unzip_gzip ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

			if ( false === $result ) {
				throw new SnapshotsException( 'Unable to unzip file.' );
			}
		} elseif ( is_wp_error( $result ) ) {
			throw new SnapshotsException( $result->get_error_message() );
		} else {
			throw new SnapshotsException( 'Unable to unzip file.' );
		}

		return $result;
	}
}
