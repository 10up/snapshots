<?php
/**
 * Configure command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Commands;

use WP_CLI;
use TenUp\WPSnapshots\Config;
use TenUp\WPSnapshots\S3;

/**
 * Configure command
 *
 * @package TenUp\WPSnapshots\Commands
 */
final class Configure extends WPSnapshotsCommand {
	/**
	 * Config instance.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Configure constructor.
	 *
	 * @param Config $config Config instance.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Configures WP Snapshots for your environment.
	 *
	 * ## ARGUMENTS
	 *
	 * <repository>
	 * : The name of the repository to configure.
	 *
	 * ## OPTIONS
	 *
	 * [--region]
	 * : The AWS region to use. Defaults to us-west-1.
	 *
	 * [--aws_key]
	 * : The AWS key to use. If it's not provided, user will be prompted for it.
	 *
	 * [--aws_secret]
	 * : The AWS secret to use. If it's not provided, user will be prompted for it.
	 *
	 * [--user_name]
	 * : The username to use. If it's not provided, user will be prompted for it.
	 *
	 * [--user_email]
	 * : The user email to use. If it's not provided, user will be prompted for it.
	 *
	 * ## EXAMPLES
	 *
	 * wp snapshots configure 10up
	 * wp snapshots configure 10up --region=us-west-1 --aws_key=123 --aws_secret=456 --user_name=John --user_email=john.doe@example.com
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		$this->args       = $args;
		$this->assoc_args = $assoc_args;

		if ( ! is_array( $this->args ) || empty( $this->args ) ) {
			WP_CLI::error( 'Please provide a repository name.' );
		}

		$repository = reset( $this->args );

		$config = $this->config->get_config();

		if ( ! empty( $config['repositories'][ $repository ] ) ) {
			WP_CLI::confirm( 'This repository is already configured. Do you want to overwrite the existing configuration?' );
		}

		$user_name  = $this->get_arg_or_prompt( 'user_name', 'Your name' );
		$user_email = $this->get_arg_or_prompt( 'user_email', 'Your email' );

		$current_respositories = $this->config->get( 'repositories', [] );
		$region                = $this->get_arg_or_prompt( 'region', 'AWS region', 'us-west-1' );
		$access_key_id         = $this->get_arg_or_prompt( 'aws_key', 'AWS key' );
		$secret_access_key     = $this->get_arg_or_prompt( 'aws_secret', 'AWS secret' );

		$current_respositories[ $repository ] = compact( 'repository', 'access_key_id', 'secret_access_key', 'region' );

		$this->test_credentials( $repository, $access_key_id, $secret_access_key, $region );

		$this->config->set( 'user_name', $user_name, false );
		$this->config->set( 'user_email', $user_email, false );
		$this->config->set( 'repositories', $current_respositories );

		WP_CLI::success( 'WP Snapshots configuration verified and saved.' );
	}

	/**
	 * Test AWS credentials.
	 *
	 * @param string $repository Repository name.
	 * @param string $access_key_id AWS key.
	 * @param string $secret_access_key AWS secret.
	 * @param string $region AWS region.
	 */
	private function test_credentials( $repository, $access_key_id, $secret_access_key, $region ) {
		$s3 = new S3( $repository, $access_key_id, $secret_access_key, $region );

		if ( ! $s3->test_connection() ) {
			WP_CLI::error( 'Could not connect to S3. Please check your credentials.' );
		}
	}
}
