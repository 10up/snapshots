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
	protected function get_command() : string {
		return 'push';
	}

	/**
	 * Runs the command.
	 *
	 * @return string
	 */
	public function run() : string {
		$id = $this->get_args()[0] ?? null;

		if ( ! $id ) {
			// Run the parent run method.
			$id = parent::run();
		}

		$this->log( 'Pushing snapshot to remote repository...' );

		$repository_name = $this->get_repository_name();
		$region          = $this->get_region();
		$profile         = $this->get_profile_for_repository();

		$this->storage_connector->put_snapshot( $id, $this->get_aws_config() );
		$this->db_connector->insert_snapshot( $id, $this->get_aws_config(), $this->snapshot_meta->get_local( $id, $repository_name ) );

		return $id;
	}

	/**
	 * Provides command parameters.
	 *
	 * @inheritDoc
	 */
	protected function get_command_parameters() : array {
		$parameters = parent::get_command_parameters();

		// Add anid parameter to push a snapshot that already exists locally.
		$parameters['synopsis'][] = [
			'type'        => 'positional',
			'name'        => 'snapshot_id',
			'description' => 'ID of local snapshot to push',
			'optional'    => true,
		];

		return $parameters;
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
