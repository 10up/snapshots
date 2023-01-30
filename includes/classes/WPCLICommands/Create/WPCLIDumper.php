<?php
/**
 * WPCLIDumper class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands\Create;

use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};
use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Snapshots\{DumperInterface, Trimmer};

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Class WPCLIDumper
 *
 * @package TenUp\WPSnapshots
 */
class WPCLIDumper implements DumperInterface {

	use Logging;

	/**
	 * Trimmer instance.
	 *
	 * @var Trimmer
	 */
	private $trimmer;

	/**
	 * SnapshotFiles instance.
	 *
	 * @var SnapshotFiles
	 */
	private $snapshot_files;

	/**
	 * Scrubber instance.
	 *
	 * @var Scrubber
	 */
	private $scrubber;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private $wordpress_database;

	/**
	 * Class constructor.
	 *
	 * @param Trimmer         $trimmer Trimmer instance.
	 * @param SnapshotFiles   $snapshot_files SnapshotFiles instance.
	 * @param Scrubber        $scrubber Scrubber instance.
	 * @param Database        $wordpress_database Database instance.
	 * @param LoggerInterface $logger LoggerInterface instance.
	 */
	public function __construct( Trimmer $trimmer, SnapshotFiles $snapshot_files, Scrubber $scrubber, Database $wordpress_database, LoggerInterface $logger ) {
		$this->trimmer            = $trimmer;
		$this->snapshot_files     = $snapshot_files;
		$this->scrubber           = $scrubber;
		$this->wordpress_database = $wordpress_database;
		$this->set_logger( $logger );
	}

	/**
	 * Creates a DB dump using WP-CLI.
	 *
	 * @param string $id The snapshot ID.
	 * @param array  $args The snapshot arguments.
	 *
	 * @throws WPSnapshotsException If an error occurs.
	 */
	public function dump( string $id, array $args ) {
		if ( ! empty( $args['small'] ) ) {
			$this->trimmer->trim( is_multisite() ? get_sites() : null );
		}

		$this->snapshot_files->create_directory( $id );

		$this->run_command( $id, $args );

		$this->scrub( $id );

		$this->log( 'Compressing database backup...' );

		if ( ! class_exists( 'PharData' ) ) {
			throw new WPSnapshotsException( 'PharData class not found.' );
		}

		$sql_file = $this->snapshot_files->get_file_path( 'data.sql', $id );

		// Gzip in pieces to avoid memory issues.
		$file_handle = fopen( $sql_file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$gzip_handle = gzopen( $sql_file . '.gz', 'wb' );

		while ( ! feof( $file_handle ) ) {
			$buffer = fread( $file_handle, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread
			gzwrite( $gzip_handle, $buffer );
		}

		fclose( $file_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		gzclose( $gzip_handle );

		$this->log( 'Removing uncompressed database backup...' );

		$this->snapshot_files->delete_file( 'data.sql', $id );
	}

	/**
	 * Runs the WP-CLI command to dump the database.
	 *
	 * @param string $id The snapshot ID.
	 * @param array  $args The snapshot arguments.
	 */
	private function run_command( string $id, array $args ) {
		global $wpdb;

		$result_file = $this->snapshot_files->get_file_path( 'data.sql', $id );

		// Build command.
		$command = "db export {$result_file} --tables=";

		/**
		 * We only export tables with WP prefix
		 */
		$this->log( 'Getting WordPress tables...' );

		$tables = $this->wordpress_database->get_tables();

		foreach ( $tables as $table ) {
			if ( $wpdb->users === $table ) {
				continue;
			}

			if ( $wpdb->usermeta === $table ) {
				continue;
			}

			$command .= $table . ',';
		}

		$this->log( 'Exporting database...' );

		wp_cli()::runcommand(
			$command,
			[
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			]
		);
	}

	/**
	 * Scrubs the database dump.
	 *
	 * @param string $id The snapshot ID.
	 *
	 * @throws WPSnapshotsException If an error occurs.
	 */
	private function scrub( string $id ) {
		try {
			$this->scrubber->scrub( $id );
		} catch ( WPSnapshotsException $e ) {

			// Delete the file if it exists.
			if ( $this->snapshot_files->file_exists( 'data.sql', $id ) ) {
				$this->snapshot_files->delete_file( 'data.sql', $id );
			}

			throw $e;
		}
	}
}
