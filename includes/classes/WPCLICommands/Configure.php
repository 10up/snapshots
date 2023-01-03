<?php
/**
 * Configure command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands;

use TenUp\WPSnapshots\WPSnapshotsConfig\WPSnapshotsConfigInterface;
use TenUp\WPSnapshots\Snapshots\{StorageConnectorInterface, AWSAuthenticationFactory};
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\WPCLI\{Prompt, WPCLICommand};

/**
 * Configure command
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class Configure extends WPCLICommand {

	/**
	 * ConfigConnectorInterface instance.
	 *
	 * @var WPSnapshotsConfigInterface
	 */
	private $config;

	/**
	 * StorageConnectorInterface instance.
	 *
	 * @var StorageConnectorInterface
	 */
	private $storage;

	/**
	 * AWSAuthenticationFactory instance.
	 *
	 * @var AWSAuthenticationFactory
	 */
	private $aws_authentication_factory;

	/**
	 * Repository name.
	 *
	 * @var string
	 */
	private $repository_name;

	/**
	 * Configure constructor.
	 *
	 * @param Prompt                     $prompt Prompt instance.
	 * @param WPSnapshotsConfigInterface $config ConfigConnectorInterface instance.
	 * @param StorageConnectorInterface  $storage StorageConnectorInterface instance.
	 * @param AWSAuthenticationFactory   $aws_authentication_factory AWSAuthenticationFactory instance.
	 */
	public function __construct( Prompt $prompt, WPSnapshotsConfigInterface $config, StorageConnectorInterface $storage, AWSAuthenticationFactory $aws_authentication_factory ) {
		parent::__construct( $prompt );

		$this->config                     = $config;
		$this->storage                    = $storage;
		$this->aws_authentication_factory = $aws_authentication_factory;
	}

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

			$this->config->set_user_name( $this->get_user_name(), false );
			$this->config->set_user_email( $this->get_user_email(), false );
			$this->config->set_repositories( $this->get_updated_repository_info(), false );

			$this->maybe_test_credentials();

			$this->config->save();

			wp_cli()::success( $this->should_test_credentials() ? 'WP Snapshots configuration verified and saved.' : 'WP Snapshots configuration saved.' );
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
					'name'        => 'aws_key',
					'description' => 'The AWS key to use. If it\'s not provided, user will be prompted for it.',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'aws_secret',
					'description' => 'The AWS secret to use. If it\'s not provided, user will be prompted for it.',
					'optional'    => true,
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
					'type'        => 'flag',
					'name'        => 'skip_test',
					'description' => 'Whether to skip the test after configuration. Defaults to false.',
					'optional'    => true,
					'default'     => false,
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
	 * Gets the repository name.
	 *
	 * @return string
	 *
	 * @throws WPSnapshotsException If repository name is not provided.
	 */
	private function get_repository_name() {
		if ( empty( $this->repository_name ) ) {

			$args = $this->get_args();

			if ( ! is_array( $args ) || empty( $args ) ) {
				throw new WPSnapshotsException( 'Please provide a repository name.' );
			}

			$this->repository_name = reset( $args );
		}

		return $this->repository_name;
	}

	/**
	 * Gets updated repository info.
	 *
	 * @return array
	 */
	private function get_updated_repository_info() : array {
		$repository_name = $this->get_repository_name();
		$repositories    = $this->config->get_repositories();

		if ( ! empty( $repositories[ $repository_name ] ) ) {
			wp_cli()::confirm( 'This repository is already configured. Do you want to overwrite the existing configuration?' );
		}

		$repositories[ $repository_name ] = [
			'region'            => $this->get_region(),
			'repository'        => $repository_name,
			'access_key_id'     => $this->get_aws_key(),
			'secret_access_key' => $this->get_aws_secret(),
		];

		return $repositories;
	}

	/**
	 * Gets the aws_key.
	 *
	 * @return string
	 */
	private function get_aws_key() : string {
		return $this->get_assoc_arg( 'aws_key', [ 'prompt' => 'AWS key' ] );
	}

	/**
	 * Gets the aws_secret.
	 *
	 * @return string
	 */
	private function get_aws_secret() : string {
		return $this->get_assoc_arg( 'aws_secret', [ 'prompt' => 'AWS secret' ] );
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

	/**
	 * Returns whether credentials should be tested.
	 *
	 * @return bool
	 */
	private function should_test_credentials() : bool {
		return ! wp_cli()::get_flag_value( $this->get_assoc_args(), 'skip_test' );
	}

	/**
	 * Maybe test AWS credentials.
	 */
	private function maybe_test_credentials() {
		if ( ! $this->should_test_credentials() ) {
			return;
		}

		$this->storage->set_configuration(
			$this->aws_authentication_factory->get(
				[
					'region'     => $this->get_region(),
					'key'        => $this->get_aws_key(),
					'secret'     => $this->get_aws_secret(),
					'repository' => $this->get_repository_name(),
				]
			)
		);
		$this->storage->test_connection();
	}
}
