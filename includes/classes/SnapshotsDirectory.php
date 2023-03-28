<?php
/**
 * SnapshotsDirectory class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots;

use Exception;
use Phar;
use PharData;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Infrastructure\SharedService;
use TenUp\Snapshots\Log\{LoggerInterface, Logging};
use WP_Filesystem_Base;

use function TenUp\Snapshots\Utils\snapshots_add_trailing_slash;
use function TenUp\Snapshots\Utils\snapshots_apply_filters;
use function TenUp\Snapshots\Utils\snapshots_remove_trailing_slash;

/**
 * SnapshotsDirectory class.
 *
 * @package TenUp\Snapshots
 */
class SnapshotsDirectory implements SharedService {

	use Logging;

	/**
	 * The FileSystem instance.
	 *
	 * @var FileSystem
	 */
	private $file_system;

	/**
	 * Class constructor.
	 *
	 * @param FileSystem      $file_system The FileSystem instance.
	 * @param LoggerInterface $logger The Logger instance.
	 */
	public function __construct( FileSystem $file_system, LoggerInterface $logger ) {
		$this->file_system = $file_system;
		$this->set_logger( $logger );
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
	 * @throws SnapshotsException If unable to read file.
	 */
	public function get_file_contents( string $file_name, string $id = null ) : string {
		$file = $this->get_file_path( $file_name, $id );

		try {
			$contents = $this->get_wp_filesystem()->get_contents( $file );
			if ( false === $contents ) {
				throw new SnapshotsException( 'Unable to read file: ' . $file );
			}
		} catch ( Exception $e ) {
			throw new SnapshotsException( 'Unable to read file: ' . $file );
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
	 * @throws SnapshotsException If unable to write to file.
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

		if ( ! $this->get_wp_filesystem()->put_contents( $file, $contents ) || ! $this->get_wp_filesystem()->is_readable( $file ) ) {
			throw new SnapshotsException( 'Unable to write to file: ' . $file );
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
	 * @throws SnapshotsException If the snapshot directory cannot be created.
	 */
	public function create_directory( $id = null, $hard = false ) {
		$snapshots_directory = snapshots_add_trailing_slash( $this->get_directory() );

		if ( ! $this->get_wp_filesystem()->exists( $snapshots_directory ) ) {
			try {
				if ( ! $this->get_wp_filesystem()->mkdir( $snapshots_directory ) ) {
					throw new SnapshotsException( 'Could not create snapshot directory' );
				}
			} catch ( Exception $e ) {
				throw new SnapshotsException( 'Could not create snapshot directory: ' . $e->getMessage() );
			}
		}

		if ( ! $this->get_wp_filesystem()->is_writable( $snapshots_directory ) ) {
			throw new SnapshotsException( 'Snapshot directory is not writable' );
		}

		if ( ! empty( $id ) ) {
			if ( $hard && $this->get_wp_filesystem()->exists( $snapshots_directory . $id . '/' ) ) {
				if ( ! $this->get_wp_filesystem()->rmdir( $snapshots_directory . $id . '/', true ) ) {
					throw new SnapshotsException( 'Could not remove existing snapshot directory' );
				}
			}

			if ( ! $this->get_wp_filesystem()->exists( $snapshots_directory . $id . '/' ) ) {
				if ( ! $this->get_wp_filesystem()->mkdir( $snapshots_directory . $id . '/' ) ) {
					throw new SnapshotsException( 'Could not create snapshot directory' );
				}
			}

			if ( ! $this->get_wp_filesystem()->is_writable( $snapshots_directory . $id . '/' ) ) {
				throw new SnapshotsException( 'Snapshot directory is not writable' );
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
	 * @throws SnapshotsException If unable to read file.
	 */
	public function get_file_lines( string $file, string $id = '' ) : array {
		$file = $this->get_file_path( $file, $id );

		$lines = $this->get_wp_filesystem()->get_contents_array( $file );

		if ( ! is_array( $lines ) ) {
			throw new SnapshotsException( 'Unable to read file: ' . $file );
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
	 * @throws SnapshotsException If there is an error.
	 */
	public function unzip_snapshot_files( string $id, string $destination ) : array {
		$errors = [];

		try {
			// Recursively delete everything in the wp-content directory except plugins/snapshots.
			$this->file_system->delete_directory_contents( $destination, false, [ TENUP_SNAPSHOTS_DIR ] );

			$gzipped_tar_file = $this->get_file_path( 'files.tar.gz', $id );
			exec( 'tar -C "' . $destination . '" -xf "' . $gzipped_tar_file . '"' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- exec is required because using PharData->extractTo results in a 'Cannot extract ".", internal error' error that is not fixable.
		} catch ( Exception $e ) {
			$errors[] = $e->getMessage();
		}

		return $errors;
	}

	/**
	 * Returns the path to the tmp directory to use for WP snapshots.
	 *
	 * @param string $subpath Optional. Subpath to append to the tmp directory path.
	 * @return string
	 */
	public function get_tmp_dir( string $subpath = '' ) : string {
		$snapshots_directory = $this->get_directory();

		/**
		 * Filters the path to the tmp directory to use for WP snapshots.
		 *
		 * @param string $tmp_dir Path to the tmp directory to use for WP snapshots.
		 */
		$tmp_dir = snapshots_apply_filters( 'snapshots_tmp_dir', trailingslashit( $snapshots_directory ) . 'tmp' );

		if ( ! empty( $subpath ) ) {
			$tmp_dir = trailingslashit( $tmp_dir ) . preg_replace( '/^\//', '', $subpath );
		}

		return $tmp_dir;
	}

	/**
	 * Gets the snapshots directory.
	 *
	 * @param ?string $id Snapshot ID.
	 * @return string $file Snapshots directory.
	 *
	 * @throws SnapshotsException If unable to create directory.
	 */
	public function get_directory( ?string $id = null ) : string {

		$directory = getenv( 'TENUP_SNAPSHOTS_DIR' );
		$directory = ! empty( $directory ) ? rtrim( $directory, '/' ) . '/' : rtrim( $_SERVER['HOME'], '/' ) . '/.wpsnapshots/';

		/**
		 * Filters the wpsnapshots directory.
		 *
		 * @param string $file Snapshots directory.
		 */
		$directory = snapshots_apply_filters( 'snapshots_directory', $directory );

		if ( ! $this->get_wp_filesystem()->is_dir( $directory ) && ( ! $this->get_wp_filesystem()->mkdir( $directory, 0755 ) || ! $this->get_wp_filesystem()->is_writable( $directory ) ) ) {
			throw new SnapshotsException( 'Unable to create ' . $directory );
		}

		return snapshots_remove_trailing_slash( $directory . ( ! empty( $id ) ? '/' . preg_replace( '/^\//', '', $id ) : '' ) );
	}
}
