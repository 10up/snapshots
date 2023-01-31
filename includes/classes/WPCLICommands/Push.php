<?php
/**
 * Push command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands;

/**
 * Push command
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class Push extends Create {

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	public function get_command() : string {
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
		$id = parent::run( $contains_db, $contains_files );

		$this->log( 'Pushing snapshot to remote repository...' );

		$this->storage_connector->put_snapshot( $id, $this->get_repository_name(), $this->get_assoc_arg( 'region' ) );

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
