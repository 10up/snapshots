<?php
/**
 * Push command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands;

/**
 * Push command
 *
 * @package TenUp\Snapshots\WPCLI
 */
final class Push extends Create {

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	public static function get_command() : string {
		return 'push';
	}

	/**
	 * Runs the command.
	 *
	 * @param bool $contains_db Whether the snapshot contains a database.
	 * @param bool $contains_files Whether the snapshot contains files.
	 *
	 * @return string
	 */
	public function run( bool $contains_db, bool $contains_files ) : string {
		// Run the parent run method.
		$id = parent::run( $contains_db, $contains_files );

		$this->log( 'Pushing snapshot to remote repository...' );

		$repository_name = $this->get_repository_name();
		$region          = $this->get_assoc_arg( 'region' );

		$this->storage_connector->put_snapshot( $id, $repository_name, $region );
		$this->db_connector->insert_snapshot( $id, $repository_name, $region, $this->snapshot_meta->get_local( $id, $repository_name ) );

		return $id;
	}

	/**
	 * Returns the success message.
	 *
	 * @param string $id The snapshot ID.
	 */
	protected function get_success_message( string $id ) : string {
		return sprintf( 'Snapshot %s pushed.', $id );
	}
}
