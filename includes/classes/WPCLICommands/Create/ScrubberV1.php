<?php
/**
 * Medium security database scrubber.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands\Create;

use TenUp\WPSnapshots\{SnapshotFiles, FileSystem};
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Log\LoggerInterface;
use TenUp\WPSnapshots\Log\Logging;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Class ScrubberV1
 *
 * @package TenUp\WPSnapshots
 */
class ScrubberV1 implements ScrubberInterface {

	use Logging;

	/**
	 * SnapshotFiles.
	 *
	 * @var SnapshotFiles
	 */
	private $snapshot_files;

	/**
	 * FileSystem instance
	 *
	 * @var FileSystem
	 */
	private $file_system;

	/**
	 * ScrubberV1 constructor.
	 *
	 * @param SnapshotFiles   $snapshot_files File system.
	 * @param FileSystem      $file_system File system.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( SnapshotFiles $snapshot_files, FileSystem $file_system, LoggerInterface $logger ) {
		$this->snapshot_files = $snapshot_files;
		$this->file_system    = $file_system;
		$this->set_logger( $logger );
	}

	/**
	 * Scrubs the database dump.
	 *
	 * @param array  $args Snapshot arguments.
	 * @param string $id Snapshot ID.
	 *
	 * @throws WPSnapshotsException If unable to open users export or data.sql.
	 */
	public function scrub( array $args, string $id ) : void {
		global $wpdb;

		$users_sql_path = $this->snapshot_files->get_file_path( 'data-users.sql', $id );
		$this->export_users_table( $users_sql_path );

		$all_hashed_passwords = [];
		$all_emails           = [];

		$this->log( 'Getting users...' );

		$user_rows = $wpdb->get_results( "SELECT user_pass, user_email FROM $wpdb->users", 'ARRAY_A' );

		foreach ( $user_rows as $user_row ) {
			$all_hashed_passwords[] = $user_row['user_pass'];
			if ( $user_row['user_email'] ) {
				$all_emails[] = $user_row['user_email'];
			}
		}

		$sterile_password = wp_hash_password( 'password' );
		$sterile_email    = 'user%d@example.com';

		$this->log( 'Opening users export...' );

		$users_handle = fopen( $this->snapshot_files->get_file_path( 'data-users.sql', $id ), 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$data_handle  = fopen( $this->snapshot_files->get_file_path( 'data.sql', $id ), 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( ! $users_handle || ! $data_handle ) {
			throw new WPSnapshotsException( 'Could not scrub users.' );
		}

		$buffer = '';
		$i      = 0;

		$this->log( 'Writing scrubbed user data and merging exports...' );

		while ( ! feof( $users_handle ) ) {
			$chunk = fread( $users_handle, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread

			foreach ( $all_hashed_passwords as $password ) {
				$chunk = str_replace( "'$password'", "'$sterile_password'", $chunk );
			}

			foreach ( $all_emails as $index => $email ) {
				$chunk = str_replace(
					"'$email'",
					sprintf( "'$sterile_email'", $index ),
					$chunk
				);
			}

			$buffer .= $chunk;

			if ( 0 === $i % 10000 ) {
				fwrite( $data_handle, $buffer ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
				$buffer = '';
			}

			$i++;
		}

		if ( ! empty( $buffer ) ) {
			fwrite( $data_handle, $buffer ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
			$buffer = '';
		}

		fclose( $data_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $users_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		$this->log( 'Removing old users SQL...' );

		$this->file_system->get_wp_filesystem()->delete( $users_sql_path );
	}

	/**
	 * Exports the users table.
	 *
	 * @param string $users_sql_path Users SQL path.
	 */
	private function export_users_table( string $users_sql_path ) : void {
		global $wpdb;

		$command = sprintf( 'db export %s --tables=%s', $users_sql_path, $wpdb->users );
		wp_cli()::runcommand(
			$command,
			[
				'launch' => true,
				'return' => 'all',
			]
		);
	}

}
