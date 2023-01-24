<?php
/**
 * WPCLIDumper class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};
use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use ZipArchive;

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
	 * ScrubberFactory instance.
	 *
	 * @var ScrubberFactory
	 */
	private $scrubber_factory;

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
	 * @param ScrubberFactory $scrubber_factory ScrubberFactory instance.
	 * @param Database        $wordpress_database Database instance.
	 * @param LoggerInterface $logger LoggerInterface instance.
	 */
	public function __construct( Trimmer $trimmer, SnapshotFiles $snapshot_files, ScrubberFactory $scrubber_factory, Database $wordpress_database, LoggerInterface $logger ) {
		$this->trimmer            = $trimmer;
		$this->snapshot_files     = $snapshot_files;
		$this->scrubber_factory   = $scrubber_factory;
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
		global $wpdb;

		if ( ! empty( $args['small'] ) ) {
			$this->trimmer->trim();
		}

		$snapshot_directory = $this->snapshot_files->get_file_path( '', $id );

		if ( ! $this->snapshot_files->directory_exists( $id ) ) {
			$this->snapshot_files->create_directory( $id );
		}

		$result_file = $this->snapshot_files->get_file_path( 'data.sql', $id );

		// Build command.
		$command = "db export {$result_file} --tables=";

		/**
		 * We only export tables with WP prefix
		 */
		$this->log( 'Getting WordPress tables...' );

		$tables = $this->wordpress_database->get_tables();

		foreach ( $tables as $table ) {
			// We separate the users/meta table for scrubbing
			if ( 0 < $args['scrub'] && $wpdb->users === $table ) {
				continue;
			}

			if ( 2 === $args['scrub'] && $wpdb->usermeta === $table ) {
				continue;
			}

			$command .= $table . ',';
		}

		$this->log( 'Exporting database...' );

		$this->log(
			wp_cli()::runcommand(
				$command,
				[
					'launch'     => false,
					'return'     => true,
					'exit_error' => false,
				]
			)
		);

		$scrubber = $this->scrubber_factory->create( $args['scrub'] ?? 2 );

		if ( $scrubber ) {
			$scrubber->scrub();
		}

		$this->log( 'Compressing database backup...', 1 );

		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new WPSnapshotsException( 'ZipArchive class not found. Please install the PHP zip extension.' );
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $snapshot_directory . '/data.sql.zip', ZipArchive::CREATE ) ) {
			throw new WPSnapshotsException( 'Could not create zip file.' );
		}

		$zip->addFile( $result_file, 'data.sql' );

		if ( true !== $zip->close() ) {
			throw new WPSnapshotsException( 'Could not close zip file.' );
		}

		$this->log( 'Removing uncompressed database backup...' );

		$this->snapshot_files->delete_file( $id . '/data.sql' );
	}
}
