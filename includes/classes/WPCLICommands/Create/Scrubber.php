<?php
/**
 * High security database scrubber.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands\Create;

use TenUp\Snapshots\Infrastructure\SharedService;
use TenUp\Snapshots\SnapshotsDirectory;
use TenUp\Snapshots\Log\{LoggerInterface, Logging};

use function TenUpWPScrubber\Helpers\scrub_users;
use function TenUpWPScrubber\Helpers\scrub_comments;

use function TenUp\Snapshots\Utils\wp_cli;

/**
 * Class Scrubber
 *
 * @package TenUp\Snapshots
 */
class Scrubber implements SharedService {

	use Logging;

	/**
	 * File system.
	 *
	 * @var SnapshotsDirectory
	 */
	private $snapshot_files;

	/**
	 * ScrubberV1 constructor.
	 *
	 * @param SnapshotsDirectory $snapshot_files File system.
	 * @param LoggerInterface    $logger Logger.
	 */
	public function __construct( SnapshotsDirectory $snapshot_files, LoggerInterface $logger ) {
		$this->snapshot_files = $snapshot_files;
		$this->set_logger( $logger );
	}

	/**
	 * Scrubs the database dump.
	 *
	 * @param string $id Snapshot ID.
	 */
	public function scrub( string $id ) : void {
		global $wpdb;

		$allowed_domains = [
			'get10up.com',
			'10up.com',
		];

		scrub_users( $allowed_domains, [], [ $this, 'log' ], false );

		scrub_comments( [ $this, 'log' ], false );

		$users_sql_path = $this->snapshot_files->get_file_path( 'data-users.sql', $id );
		$this->export_temp_table( $users_sql_path, $wpdb->users . '_temp' );

		$usermeta_sql_path = $this->snapshot_files->get_file_path( 'data-usermeta.sql', $id );
		$this->export_temp_table( $usermeta_sql_path, $wpdb->usermeta . '_temp' );

		$comments_sql_path = $this->snapshot_files->get_file_path( 'data-comments.sql', $id );
		$this->export_temp_table( $comments_sql_path, $wpdb->comments . '_temp' );

		$commentmeta_sql_path = $this->snapshot_files->get_file_path( 'data-commentmeta.sql', $id );
		$this->export_temp_table( $commentmeta_sql_path, $wpdb->commentmeta . '_temp' );

		$usermeta_sql    = $this->snapshot_files->get_file_contents( 'data-usermeta.sql', $id );
		$users_sql       = $this->snapshot_files->get_file_contents( 'data-users.sql', $id );
		$comments_sql    = $this->snapshot_files->get_file_contents( 'data-comments.sql', $id );
		$commentmeta_sql = $this->snapshot_files->get_file_contents( 'data-commentmeta.sql', $id );

		$this->log( 'Appending scrubbed SQL to dump file...' );

		file_put_contents( $this->snapshot_files->get_file_path( 'data.sql', $id ), preg_replace( '#`' . $wpdb->users . '_temp`#', $wpdb->users, $users_sql ) . preg_replace( '#`' . $wpdb->usermeta . '_temp`#', $wpdb->usermeta, $usermeta_sql ) . preg_replace( '#`' . $wpdb->commentmeta . '_temp`#', $wpdb->commentmeta, $commentmeta_sql ) . preg_replace( '#`' . $wpdb->comments . '_temp`#', $wpdb->comments, $comments_sql ), FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

		$this->log( 'Removing temporary tables...' );

		$wpdb->query( "DROP TABLE {$wpdb->usermeta}_temp" );
		$wpdb->query( "DROP TABLE {$wpdb->users}_temp" );
		$wpdb->query( "DROP TABLE {$wpdb->comments}_temp" );
		$wpdb->query( "DROP TABLE {$wpdb->commentmeta}_temp" );

		$this->log( 'Cleaning up scrub files...' );

		$this->snapshot_files->delete_file( 'data-users.sql', $id );
		$this->snapshot_files->delete_file( 'data-usermeta.sql', $id );
		$this->snapshot_files->delete_file( 'data-comments.sql', $id );
		$this->snapshot_files->delete_file( 'data-commentmeta.sql', $id );
	}

	/**
	 * Exports a table.
	 *
	 * @param string $users_sql_path Users SQL path.
	 * @param string $table Table name.
	 */
	private function export_temp_table( string $users_sql_path, string $table ) : void {
		$command = sprintf( 'db export %s --tables=%s', $users_sql_path, $table );
		wp_cli()::runcommand(
			$command,
			[
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			]
		);
	}
}
