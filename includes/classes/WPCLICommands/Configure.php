<?php
/**
 * Configure command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\WPCLI\WPCLICommand;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Configure command
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class Configure extends WPCLICommand {

	/**
	 * Configures WP Snapshots for your environment.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$this->config->set_user_name( $this->get_user_name() );
			$this->config->set_user_email( $this->get_user_email() );
			$this->config->set_repositories( $this->get_updated_repository_info() );

			$this->config->save();

			wp_cli()::success( 'WP Snapshots configuration saved.' );
		} catch ( WPSnapshotsException $e ) {
			wp_cli()::error( $e->getMessage() );
		}
	}

	/**
	 * Gets the command parameters.
	 *
	 * @return array
	 */
	protected function get_command_parameters() : array {
		return [
			'longdesc'  => '## EXAMPLES' . PHP_EOL . PHP_EOL . 'wp snapshots configure 10up' . PHP_EOL . 'wp snapshots configure 10up --region=us-west-1 --aws_key=123 --aws_secret=456 --user_name=John --user_email=john.doe@example.com',
			'shortdesc' => 'Configures WP Snapshots for your environment.',
			'synopsis'  => [
				[
					'type'        => 'positional',
					'name'        => 'repository',
					'description' => 'The name of the repository to configure.',
					'optional'    => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'region',
					'description' => 'The AWS region to use. Defaults to us-west-1.',
					'optional'    => true,
					'default'     => 'us-west-1',
				],
				[
					'type'        => 'assoc',
					'name'        => 'user_name',
					'description' => 'The username to use. If it\'s not provided, user will be prompted for it.',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'user_email',
					'description' => 'The user email to use. If it\'s not provided, user will be prompted for it.',
					'optional'    => true,
				],
			],
		];
	}

	/**
	 * Gets the command.
	 *
	 * @return string
	 */
	protected function get_command() : string {
		return 'configure';
	}

	/**
	 * Gets updated repository info.
	 *
	 * @return array
	 */
	private function get_updated_repository_info() : array {
		$repository_name = $this->get_repository_name( true, 0 );
		$repositories    = $this->config->get_repositories();

		if ( ! empty( $repositories[ $repository_name ] ) ) {
			wp_cli()::confirm( 'This repository is already configured. Do you want to overwrite the existing configuration?' );
		}

		$repositories[ $repository_name ] = [
			'region'     => $this->get_region(),
			'repository' => $repository_name,
		];

		return $repositories;
	}

	/**
	 * Gets the region.
	 *
	 * @return string
	 */
	private function get_region() : string {
		return $this->get_assoc_arg(
			'region',
			[
				'prompt'  => 'AWS region',
				'default' => 'us-west-1',
			]
		);
	}

	/**
	 * Gets the user name.
	 *
	 * @return string
	 */
	private function get_user_name() : string {
		return $this->get_assoc_arg( 'user_name', [ 'prompt' => 'Your name' ] );
	}

	/**
	 * Gets the user email.
	 *
	 * @return string
	 */
	private function get_user_email() : string {
		return $this->get_assoc_arg( 'user_email', [ 'prompt' => 'Your email' ] );
	}
}
