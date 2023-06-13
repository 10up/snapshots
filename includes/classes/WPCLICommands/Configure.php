<?php
/**
 * Configure command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands;

use Exception;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\WPCLI\WPCLICommand;

use function TenUp\Snapshots\Utils\wp_cli;

/**
 * Configure command
 *
 * @package TenUp\Snapshots\WPCLI
 */
final class Configure extends WPCLICommand {

	/**
	 * Configures Snapshots for your environment.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$repository_name = $this->get_repository_name( true, 0 );

			$info = $this->get_updated_repository_info();

			$this->config->set_repositories( $info );

			$this->config->save();
			$config = [
				'profile'    => $info[ $repository_name ]['profile'],
				'repository' => $repository_name,
				'region'     => $info[ $repository_name ]['region'],
				'role_arn'   => $info[ $repository_name ]['role_arn'],
			];

			try {
				$this->log( 'Testing repository connection...' );
				$this->storage_connector->test( $config );
			} catch ( Exception $e ) {
				wp_cli()::error( 'Your Snapshots configuration is saved, but we were unable to connect to the repository. Please check your AWS credentials.' );
			}

			wp_cli()::success( 'Snapshots configuration saved.' );
		} catch ( SnapshotsException $e ) {
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
			'shortdesc' => 'Configures Snapshots for your environment.',
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
				[
					'type'        => 'assoc',
					'name'        => 'role_arn',
					'description' => 'AWS role ARN. Defaults to none',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'profile',
					'description' => 'The AWS profile to use. Defaults to \'default\'.',
					'optional'    => true,
				],
			],
			'when'      => 'before_wp_load',
			'longdesc'  => '## EXAMPLES' . PHP_EOL . PHP_EOL . 'wp snapshots configure 10up' . PHP_EOL . 'wp snapshots configure 10up --region=us-west-1 --user_name=John --user_email=john.doe@example.com',
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

		$role_arn = $this->get_role_arn_arg();

		$profile = '';
		if ( 'none' === $role_arn ) {
			$profile  = $this->get_profile();
			$role_arn = '';
		}

		$region   = $this->get_region_arg();

		$repositories[ $repository_name ] = [
			'region'     => $region,
			'repository' => $repository_name,
			'profile'    => $profile,
			'role_arn'   => $role_arn,
			'user_name'  => $this->get_user_name(),
			'user_email' => $this->get_user_email(),
		];

		return $repositories;
	}

	/**
	 * Gets the region.
	 *
	 * @return string
	 */
	private function get_region_arg() : string {
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

	/**
	 * Gets the profile.
	 *
	 * @return string
	 */
	private function get_profile() : ?string {
		return $this->get_assoc_arg(
			'profile',
			[

				'prompt'  => 'Which AWS authentication profile should be used for this profile? If you are unsure, use `default`',
				'default' => 'default',
			]
		);
	}

	/**
	 * Gets the role ARN
	 *
	 * @return string
	 */
	private function get_role_arn_arg() : ?string {
		return $this->get_assoc_arg(
			'role_arn',
			[

				'prompt'  => "Optionally, provide a role ARN to use for authentication. If you don't know what this, leave it blank",
				'default' => 'none',
			]
		);
	}
}
