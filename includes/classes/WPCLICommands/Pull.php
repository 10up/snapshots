<?php
/**
 * Pull command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands;

use Exception;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\WPCLI\WPCLICommand;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Pull command
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class Pull extends WPCLICommand {

	/**
	 * Callback for the command.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 *
	 * @throws WPSnapshotsException If there is an error.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$this->log( 'Security Warning: WP Snapshots creates copies of your codebase and database. This could result in data retention policy issues, please exercise extreme caution when using production data.', 'warning' );

			$pull_actions = $this->get_pull_actions();

			if ( ! wp_cli()::get_flag_value( $assoc_args, 'confirm' ) ) {
				wp_cli()::confirm( 'Are you sure you want to pull this snapshot? This is a potentially destructive operation. Please run a backup first.' );
			}

			foreach ( $pull_actions as $action ) {
				$action();
			}

			wp_cli()::success( 'Snapshot pulled successfully.' );
		} catch ( Exception $e ) {
			wp_cli()::error( $e->getMessage() );
		}
	}

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	public function get_command() : string {
		return 'pull';
	}

	/**
	 * Provides command parameters.
	 *
	 * @inheritDoc
	 */
	protected function get_command_parameters() : array {
		return [
			'shortdesc' => 'Pull a snapshot into a WordPress instance',
			'synopsis'  => [
				[
					'type'        => 'positional',
					'name'        => 'snapshot_id',
					'description' => 'Snapshot ID to pull.',
					'optional'    => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'repository',
					'description' => 'Repository to use. Defaults to 10up.',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'region',
					'description' => 'AWS region to use. Defaults to us-west-1.',
					'optional'    => true,
					'default'     => 'us-west-1',
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm',
					'description' => 'Confirm pull operation.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_wp_download',
					'description' => 'Confirm WordPress download.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_config_create',
					'description' => 'Confirm wp-config.php creation.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_wp_version_change',
					'description' => 'Confirm WordPress version change.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_ms_constant_update',
					'description' => 'Confirm multisite constant update.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'suppress_instructions',
					'description' => 'Suppress instructions after successful installation.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'overwrite_local_copy',
					'description' => 'Overwrite local copy of snapshot.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_files',
					'description' => 'Include files in snapshot.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_db',
					'description' => 'Include database in snapshot.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'skip_table_search_replace',
					'description' => 'Skip table search and replace.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'site_mapping',
					'description' => 'JSON or path to site mapping file.',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'main_domain',
					'description' => 'Main domain for multisite snapshots.',
					'optional'    => true,
				],
			],
		];
	}

	/**
	 * Gets the snapshot ID.
	 *
	 * @return string
	 */
	private function get_id() : string {
		return $this->get_args()[0];
	}


	/**
	 * Gets the actions required for the pull.
	 *
	 * @return array
	 *
	 * @throws WPSnapshotsException If the snapshot does not exist or is not valid.
	 */
	private function get_pull_actions() {
		global $wp_version;

		$pull_actions = [];

		$id              = $this->get_id();
		$repository_name = $this->get_repository_name();

		$remote_meta = $this->snapshot_meta->get_remote( $id, $repository_name, $this->get_assoc_arg( 'region' ) );
		$local_meta  = $this->snapshot_meta->get_local( $id, $repository_name );

		switch ( true ) {
			case ! empty( $remote_meta ) && ! empty( $local_meta ):
				$should_download = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'overwrite_local_copy', 'Snapshot already exists locally. Overwrite?', $this->get_default_arg_value( 'overwrite_local_copy' ) );
				$meta            = $should_download ? $remote_meta : $local_meta;
				break;
			case ! empty( $remote_meta ) && empty( $local_meta ):
				$meta            = $remote_meta;
				$should_download = true;
				break;
			case empty( $remote_meta ) && ! empty( $local_meta ):
				$meta            = $local_meta;
				$should_download = false;
				break;
			default:
				throw new WPSnapshotsException( 'Snapshot does not exist.' );
		}

		if ( empty( $meta ) || ( empty( $meta['contains_files'] ) && empty( $meta['contains_db'] ) ) ) {
			throw new WPSnapshotsException( 'Snapshot is not valid.' );
		}

		$include_db    = $meta['contains_db'] && $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_db', 'Include database in snapshot?' );
		$include_files = $meta['contains_files'] && $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_files', 'Include files in snapshot?' );

		if ( ! $include_db && ! $include_files ) {
			throw new WPSnapshotsException( 'You must include either the DB, the files, or both.' );
		}

		if ( $should_download ) {
			$pull_actions[] = function() {
				$this->download_snapshot();
			};
		}

		if ( $wp_version !== $meta['wp_version'] && $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'update_wp', 'This snapshot is running WordPress version ' . $meta['wp_version'] . ', and you are running version ' . $wp_version . '. Do you want to change your version to match the snapshot?' ) ) {
			$pull_actions[] = function() use ( $meta ) {
				$this->update_wp( $meta['wp_version'] );
			};
		}

		if ( $include_db ) {
			$pull_actions[] = function() {
				$this->pull_db();
			};
		}

		if ( $include_files ) {
			$pull_actions[] = function() {
				$this->pull_files();
			};
		}

		return $pull_actions;
	}

	/**
	 * Downloads the snapshot.
	 *
	 * @param bool $include_db    Whether to include the database in the snapshot.
	 * @param bool $include_files Whether to include the files in the snapshot.
	 *
	 * @throws WPSnapshotsException If the snapshot does not exist or is not valid.
	 */
	protected function download_snapshot( bool $include_db = true, bool $include_files = true ) {
		$this->log( 'Downloading snapshot...' );

		$command = 'wpsnapshots download ' . $this->get_id() . ' --quiet --repository=' . $this->get_repository_name() . ' --region=' . $this->get_assoc_arg( 'region' );

		if ( ! $include_db && ! $include_files ) {
			throw new WPSnapshotsException( 'You must include either the database or files in the snapshot.' );
		}

		if ( $include_db ) {
			$command .= ' --include_db';
		}

		if ( $include_files ) {
			$command .= ' --include_files';
		}

		wp_cli()::runcommand( $command, [ 'launch' => false ] );

		$this->log( 'Snapshot downloaded.', 'success' );
	}


	/**
	 * Pulls the DB.
	 */
	protected function pull_db() {
		$this->log( 'Pulling database...' );

		$command = 'db import ' . $this->snapshots_filesystem->get_file_path( 'data.sql.gz', $this->get_id() ) . ' --quiet';

		wp_cli()::runcommand( $command, [ 'launch' => false ] );

		$this->log( 'Database pulled.', 'success' );
	}

	/**
	 * Updates WP.
	 *
	 * @param string $wp_version The WP version to update to.
	 */
	protected function update_wp( string $wp_version ) {
		$this->log( 'Updating WordPress to version ' . $wp_version . '...' );

		$command = 'core update --version=' . $wp_version;

		wp_cli()::runcommand( $command, [ 'launch' => false ] );

		$this->log( 'WordPress updated.', 'success' );
	}

	/**
	 * Pulls files from the snapshot into the environment.
	 */
	protected function pull_files() {
		$this->log( 'Pulling files...' );

		$this->file_zipper->unzip_snapshot_files( $this->get_id() );

		$this->log( 'Files pulled.', 'success' );
	}

}
