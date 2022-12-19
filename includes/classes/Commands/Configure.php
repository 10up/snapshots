<?php
/**
 * Configure command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Commands;

use TenUp\WPSnapshots\Config;

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
	 * wp snapshots configure
	 * wp snapshots configure --region=us-west-1 --aws_key=123 --aws_secret=456 --user_name=John --user_email=john.doe@example.com
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		$this->args       = $args;
		$this->assoc_args = $assoc_args;

		$this->config->set( 'region', $this->get_arg_or_prompt( 'region', 'AWS region', $this->config->get_default( 'region' ) ) );
		$this->config->set( 'aws_key', $this->get_arg_or_prompt( 'aws_key', 'AWS key' ) );
		$this->config->set( 'aws_secret', $this->get_arg_or_prompt( 'aws_secret', 'AWS secret' ) );
		$this->config->set( 'user_name', $this->get_arg_or_prompt( 'user_name', 'User name' ) );
		$this->config->set( 'user_email', $this->get_arg_or_prompt( 'user_email', 'User email' ) );

		$this->config->save();
	}

}
