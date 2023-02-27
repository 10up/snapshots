<?php
/**
 * Download command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands;

use Exception;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\WPCLI\WPCLICommand;

use function TenUp\Snapshots\Utils\wp_cli;

/**
 * Download command
 *
 * @package TenUp\Snapshots\WPCLI
 */
final class Download extends WPCLICommand {

	/**
	 * Callback for the command.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$meta = $this->get_meta();

			$this->log( 'Downloading snapshot (' . $this->get_formatted_size( $meta ) . ')...' );

			$this->storage_connector->download_snapshot( $this->get_id(), $meta, $this->get_profile_for_repository(), $this->get_repository_name(), $this->get_region() );
			$this->snapshot_meta->save_local( $this->get_id(), $meta );

			wp_cli()::success( 'Snapshot downloaded.' );
		} catch ( Exception $e ) {
			wp_cli()::error( $e->getMessage() );
		}
	}

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	protected function get_command() : string {
		return 'download';
	}

	/**
	 * Provides command parameters.
	 *
	 * @inheritDoc
	 */
	protected function get_command_parameters() : array {
		return [
			'shortdesc' => 'Download a snapshot from the repository.',
			'synopsis'  => [
				[
					'type'        => 'positional',
					'name'        => 'snapshot_id',
					'description' => 'Snapshot ID to download.',
					'optional'    => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'repository',
					'description' => 'Repository to use.',
					'optional'    => true,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_files',
					'description' => 'Include files in the download.',
					'optional'    => true,
					'default'     => true,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_db',
					'description' => 'Include database in the download.',
					'optional'    => true,
					'default'     => true,
				],
			],
			'when'      => 'before_wp_load',
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
	 * Gets the snapshot meta.
	 *
	 * @return array
	 *
	 * @throws SnapshotsException If there is no snapshot meta, meta is invalid, or user has not chosen to include either files or db.
	 */
	private function get_meta() : array {
		$id = $this->get_id();

		$meta = $this->snapshot_meta->get_remote( $id, $this->get_profile_for_repository(), $this->get_repository_name(), $this->get_region() );

		if ( empty( $meta ) ) {
			throw new SnapshotsException( 'Snapshot does not exist.' );
		}

		if ( empty( $meta['project'] ) ) {
			throw new SnapshotsException( 'Missing critical snapshot data.' );
		}

		/**
		 * Backwards compat. Add repository to meta before we started saving it.
		 */
		if ( empty( $meta['repository'] ) ) {
			$meta['repository'] = $this->get_repository_name();
		}

		if ( true === $meta['contains_db'] ) {
			$include_db = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_db', 'Include database in the download?' );
		} else {
			$include_db = false;
		}

		if ( true === $meta['contains_files'] ) {
			$include_files = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_files', 'Include files in the download?' );
		} else {
			$include_files = false;
		}

		if ( ! $include_files && ! $include_db ) {
			throw new SnapshotsException( 'You must include either files or database in the download.' );
		}

		$meta['contains_files'] = $include_files;
		$meta['contains_db']    = $include_db;

		return $meta;
	}

	/**
	 * Gets the formatted size.
	 *
	 * @param array $meta Snapshot meta.
	 * @return string
	 */
	private function get_formatted_size( array $meta ) : string {
		$formatted_size = '';

		if ( empty( $meta['files_size'] ) && empty( $meta['db_size'] ) ) {
			if ( $meta['contains_files'] && $meta['contains_db'] ) {
				$formatted_size = $this->format_bytes( $meta['size'] );
			}
		} else {
			$size = (int) ( $meta['files_size'] ?? 0 ) + (int) ( $meta['db_size'] ?? 0 );

			$formatted_size = $this->format_bytes( $size );
		}

		return $formatted_size;
	}
}
