<?php
/**
 * Abstract WPCLICommand class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLI;

use TenUp\WPSnapshots\Infrastructure\{Module, Conditional, Registerable};
use TenUp\WPSnapshots\Log\{Logging, WPCLILogger};
use TenUp\WPSnapshots\Snapshots\{AWSAuthenticationFactory, DBConnectorInterface, StorageConnectorInterface};
use TenUp\WPSnapshots\WPSnapshotsConfig\WPSnapshotsConfigInterface;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Abstract class WPCLICommand
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
abstract class WPCLICommand implements Conditional, Registerable, Module {

	use Logging;

	/**
	 * Prompt instance.
	 *
	 * @var Prompt
	 */
	protected $prompt;

	/**
	 * ConfigConnectorInterface instance.
	 *
	 * @var WPSnapshotsConfigInterface
	 */
	protected $config;

	/**
	 * AWSAuthenticationFactory instance.
	 *
	 * @var AWSAuthenticationFactory
	 */
	protected $aws_authentication_factory;

	/**
	 * StorageConnectorInterface instance.
	 *
	 * @var StorageConnectorInterface
	 */
	protected $storage_connector;

	/**
	 * DBConnectorInterface instance.
	 *
	 * @var DBConnectorInterface
	 */
	protected $db_connector;

	/**
	 * Args passed to the command.
	 *
	 * @var array
	 */
	private $args = [];

	/**
	 * Associative args passed to the command.
	 *
	 * @var array
	 */
	private $assoc_args = [];

	/**
	 * Returns whether the module is needed.
	 *
	 * @return bool
	 */
	public static function is_needed() : bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * WPCLICommand constructor.
	 *
	 * @param WPCLILogger                $logger WPCLILogger instance.
	 * @param Prompt                     $prompt Prompt instance.
	 * @param WPSnapshotsConfigInterface $config ConfigConnectorInterface instance.
	 * @param AWSAuthenticationFactory   $aws_authentication_factory AWSAuthenticationFactory instance.
	 * @param StorageConnectorInterface  $storage_connector StorageConnectorInterface instance.
	 * @param DBConnectorInterface       $db_connector DBConnectorInterface instance.
	 */
	public function __construct(
		WPCLILogger $logger,
		Prompt $prompt,
		WPSnapshotsConfigInterface $config,
		AWSAuthenticationFactory $aws_authentication_factory,
		StorageConnectorInterface $storage_connector,
		DBConnectorInterface $db_connector,
	) {
		$this->prompt                     = $prompt;
		$this->config                     = $config;
		$this->aws_authentication_factory = $aws_authentication_factory;
		$this->storage_connector          = $storage_connector;
		$this->db_connector               = $db_connector;
		$this->set_logger( $logger );
	}

	/**
	 * Registers the module.
	 */
	public function register() {
		wp_cli()::add_command( 'wpsnapshots ' . $this->get_command(), [ $this, 'execute' ], $this->get_command_parameters() );
	}

	/**
	 * Get args.
	 *
	 * @return array
	 */
	public function get_args() : array {
		return $this->args;
	}

	/**
	 * Get assoc_args.
	 *
	 * @return array
	 */
	public function get_assoc_args() : array {
		return $this->assoc_args;
	}

	/**
	 * Get an associative arg.
	 *
	 * @param string $key Key.
	 * @param ?array $prompt_config Configuration for prompting.
	 * @return mixed
	 */
	public function get_assoc_arg( string $key, ?array $prompt_config = null ) {
		if ( $prompt_config ) {
			$this->assoc_args = $this->prompt->get_arg_or_prompt( $this->assoc_args, array_merge( $prompt_config, compact( 'key' ) ) );
		}

		return $this->assoc_args[ $key ] ?? null;
	}

	/**
	 * Set args.
	 *
	 * @param array $args Arguments passed to the command.
	 */
	public function set_args( array $args ) {
		$this->args = $args;
	}

	/**
	 * Set assoc_args.
	 *
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function set_assoc_args( array $assoc_args ) {
		$this->assoc_args = $assoc_args;
	}

	/**
	 * Set a single assoc_arg.
	 *
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 */
	public function set_assoc_arg( string $key, $value ) {
		$this->assoc_args[ $key ] = $value;
	}

	/**
	 * Callback for the command.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	abstract protected function execute( array $args, array $assoc_args );

	/**
	 * Gets the command.
	 *
	 * @return string
	 */
	abstract protected function get_command() : string;

	/**
	 * Gets the parameters.
	 *
	 * @return array
	 */
	abstract protected function get_command_parameters() : array;
}
