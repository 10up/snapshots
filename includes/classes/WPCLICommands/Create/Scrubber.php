<?php
/**
 * High security database scrubber.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands\Create;

use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\WPSnapshotsDirectory;
use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Class Scrubber
 *
 * @package TenUp\WPSnapshots
 */
class Scrubber implements SharedService {

	use Logging;

	/**
	 * File system.
	 *
	 * @var WPSnapshotsDirectory
	 */
	private $snapshot_files;

	/**
	 * ScrubberV1 constructor.
	 *
	 * @param WPSnapshotsDirectory $snapshot_files File system.
	 * @param LoggerInterface      $logger Logger.
	 */
	public function __construct( WPSnapshotsDirectory $snapshot_files, LoggerInterface $logger ) {
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

		// Drop tables if they exist.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->usermeta}_temp" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->users}_temp" );

		$this->log( 'Scrubbing users...' );

		$dummy_users = $this->get_dummy_users();

		$this->log( 'Duplicating users table..' );

		$wpdb->query( "CREATE TABLE {$wpdb->users}_temp LIKE $wpdb->users" );
		$wpdb->query( "INSERT INTO {$wpdb->users}_temp SELECT * FROM $wpdb->users" );

		$this->log( 'Scrub each user record..' );

		$offset = 0;

		$password = wp_hash_password( 'password' );

		$user_ids = [];

		while ( true ) {
			$users = $wpdb->get_results( $wpdb->prepare( "SELECT ID, user_login FROM {$wpdb->users}_temp LIMIT 1000 OFFSET %d", $offset ), 'ARRAY_A' );

			if ( empty( $users ) ) {
				break;
			}

			if ( 1000 <= $offset ) {
				usleep( 100 );
			}

			foreach ( $users as $user ) {
				$user_id = (int) $user['ID'];

				$user_ids[] = $user_id;

				$dummy_user = $dummy_users[ $user_id % 1000 ];

				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->users}_temp SET user_pass=%s, user_email=%s, user_url='', user_activation_key='', display_name=%s WHERE ID=%d",
						$password,
						$dummy_user['email'],
						$user['user_login'],
						$user['ID']
					)
				);
			}

			$offset += 1000;
		}

		$users_sql_path = $this->snapshot_files->get_file_path( 'data-users.sql', $id );
		$this->export_temp_table( $users_sql_path, $wpdb->users . '_temp' );

		$this->log( 'Duplicating user meta table...' );

		$wpdb->query( "CREATE TABLE {$wpdb->usermeta}_temp LIKE $wpdb->usermeta" );
		$wpdb->query( "INSERT INTO {$wpdb->usermeta}_temp SELECT * FROM $wpdb->usermeta" );

		// Just truncate these fields
		$wpdb->query( "UPDATE {$wpdb->usermeta}_temp SET meta_value='' WHERE meta_key='description' OR meta_key='session_tokens'" );

		$user_ids_count = count( $user_ids );
		for ( $i = 0; $i < $user_ids_count; $i++ ) {
			if ( 1 < $i && 0 === $i % 1000 ) {
				usleep( 100 );
			}

			$user_id = $user_ids[ $i ];

			$dummy_user = $dummy_users[ $user_id % 1000 ];

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta}_temp SET meta_value=%s WHERE meta_key='first_name' AND user_id=%d",
					$dummy_user['first_name'],
					(int) $user_id
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta}_temp SET meta_value=%s WHERE meta_key='last_name' AND user_id=%d",
					$dummy_user['last_name'],
					$user_id
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta}_temp SET meta_value=%s WHERE meta_key='nickname' AND user_id=%d",
					$dummy_user['first_name'],
					$user_id
				)
			);
		}

		// Export the temp table
		$usermeta_sql_path = $this->snapshot_files->get_file_path( 'data-usermeta.sql', $id );
		$this->export_temp_table( $usermeta_sql_path, $wpdb->usermeta . '_temp' );

		$usermeta_sql = $this->snapshot_files->get_file_contents( 'data-usermeta.sql', $id );

		$this->log( 'Appending scrubbed SQL to dump file...' );

		$users_sql = $this->snapshot_files->get_file_contents( 'data-users.sql', $id );

		file_put_contents( $this->snapshot_files->get_file_path( 'data.sql', $id ), preg_replace( '#`' . $wpdb->users . '_temp`#', $wpdb->users, $users_sql ) . preg_replace( '#`' . $wpdb->usermeta . '_temp`#', $wpdb->usermeta, $usermeta_sql ), FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

		$this->log( 'Removing temporary tables...' );

		$wpdb->query( "DROP TABLE {$wpdb->usermeta}_temp" );
		$wpdb->query( "DROP TABLE {$wpdb->users}_temp" );

		$this->log( 'Removing old users and usermeta SQL...' );

		$this->snapshot_files->delete_file( 'data-users.sql', $id );
		$this->snapshot_files->delete_file( 'data-usermeta.sql', $id );
	}

	/**
	 * Provides dummy users.
	 *
	 * @return array
	 */
	private function get_dummy_users() {
		static $users = [];

		if ( empty( $users ) ) {
			$file = fopen( trailingslashit( WPSNAPSHOTS_DIR ) . 'includes/data/users.csv', 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

			$line = fgetcsv( $file );
			while ( false !== $line ) {

				$user = [
					'username'   => $line[0],
					'first_name' => $line[1],
					'last_name'  => $line[2],
					'email'      => $line[3],
				];

				$users[] = $user;

				$line = fgetcsv( $file );
			}

			fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		}

		return $users;
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
