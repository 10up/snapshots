<?php
/**
 * Abstract WPCLICommand class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLI;

use TenUp\WPSnapshots\Infrastructure\{Module, Conditional, Registerable};

/**
 * Abstract class WPCLICommand
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
abstract class WPCLICommand implements Conditional, Registerable, Module {

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
	 * Prompt instance.
	 *
	 * @var Prompt
	 */
	protected $prompt;

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
	 * @param Prompt $prompt Prompt instance.
	 */
	public function __construct( Prompt $prompt ) {
		$this->prompt = $prompt;
	}

	/**
	 * Registers the module.
	 */
	public function register() {
		wp_cli()::add_command( 'wpsnapshots ' . $this->get_command(), [ $this, 'execute' ], $this->get_command_parameters() );
	}

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

	/**
	 * Callback for the command.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	abstract protected function execute( array $args, array $assoc_args );

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
}
