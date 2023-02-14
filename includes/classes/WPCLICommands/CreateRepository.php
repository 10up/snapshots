<?php
/**
 * Create Repository command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands;

use Exception;
use TenUp\Snapshots\WPCLI\WPCLICommand;

use function TenUp\Snapshots\Utils\wp_cli;

/**
 * CreateRepository command
 *
 * @package TenUp\Snapshots\WPCLI
 */
final class CreateRepository extends WPCLICommand {

	/**
	 * Search for snapshots within a repository.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$repository_name = $this->get_repository_name( true, 0 );
			$region          = $this->get_assoc_arg( 'region' );

			$this->storage_connector->create_bucket( $repository_name, $region );
			$this->db_connector->create_tables( $repository_name, $region );
		} catch ( Exception $e ) {
			wp_cli()::error( $e->getMessage() );
		}

		wp_cli()::success( 'Repository created.' );
	}

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	public function get_command() : string {
		return 'create-repository';
	}

	/**
	 * Provides command parameters.
	 *
	 * @inheritDoc
	 */
	public function get_command_parameters() : array {
		return [
			'shortdesc' => 'Create new WP Snapshots repository.',
			'synopsis'  => [
				[
					'type'        => 'positional',
					'name'        => 'repository',
					'description' => 'The repository to create',
					'optional'    => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'region',
					'description' => 'The AWS region to use. Defaults to us-west-1.',
					'optional'    => true,
					'default'     => 'us-west-1',
				],
			],
		];
	}
}
