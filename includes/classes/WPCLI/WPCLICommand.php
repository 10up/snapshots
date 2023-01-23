<?php
/**
 * Abstract WPCLICommand class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLI;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\FileSystem;
use TenUp\WPSnapshots\Infrastructure\{Module, Conditional};
use TenUp\WPSnapshots\Log\{Logging, WPCLILogger};
use TenUp\WPSnapshots\Snapshots\{DBConnectorInterface, SnapshotMetaInterface, StorageConnectorInterface};
use TenUp\WPSnapshots\WPSnapshotsConfig\WPSnapshotsConfigInterface;
use TenUp\WPSnapshots\SnapshotsFiles;
use TenUp\WPSnapshots\WordPress\Database;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Abstract class WPCLICommand
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
abstract class WPCLICommand implements Conditional, Module {

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
	 * SnapshotMetaInteface instance.
	 *
	 * @var SnapshotMetaInterface
	 */
	protected $snapshot_meta;

	/**
	 * SnapshotsFiles instance.
	 *
	 * @var SnapshotsFiles
	 */
	protected $snapshots_filesystem;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	protected $wordpress_database;

	/**
	 * FileSystem instance.
	 *
	 * @var FileSystem
	 */
	protected $filesystem;

	/**
	 * Args passed to the command.
	 *
	 * @var array
	 */
	protected $args = [];

	/**
	 * Associative args passed to the command.
	 *
	 * @var array
	 */
	protected $assoc_args = [];

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
	 * @param StorageConnectorInterface  $storage_connector StorageConnectorInterface instance.
	 * @param DBConnectorInterface       $db_connector DBConnectorInterface instance.
	 * @param SnapshotMetaInterface      $snapshot_meta SnapshotMetaInterface instance.
	 * @param SnapshotsFiles             $snapshots_filesystem SnapshotsFiles instance.
	 * @param Database                   $wordpress_database Database instance.
	 * @param FileSystem                 $filesystem FileSystem instance.
	 */
	public function __construct(
		WPCLILogger $logger,
		Prompt $prompt,
		WPSnapshotsConfigInterface $config,
		StorageConnectorInterface $storage_connector,
		DBConnectorInterface $db_connector,
		SnapshotMetaInterface $snapshot_meta,
		SnapshotsFiles $snapshots_filesystem,
		Database $wordpress_database,
		FileSystem $filesystem
	) {
		$this->prompt               = $prompt;
		$this->config               = $config;
		$this->storage_connector    = $storage_connector;
		$this->db_connector         = $db_connector;
		$this->snapshot_meta        = $snapshot_meta;
		$this->snapshots_filesystem = $snapshots_filesystem;
		$this->wordpress_database   = $wordpress_database;
		$this->filesystem           = $filesystem;
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

		if ( ! isset( $this->assoc_args[ $key ] ) ) {
			$value = $this->get_default_arg_value( $key );

			if ( $value ) {
				$this->assoc_args[ $key ] = $value;
			}

			return $value;
		}

		return $this->assoc_args[ $key ];
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
	 * Gets the repository name.
	 *
	 * @param bool $required Whether the arg is required.
	 * @param ?int $positional_arg_index Positional arg index. If null, the repository will be retrieved form assoc args.
	 * @return string
	 *
	 * @throws WPSnapshotsException If no repository name is provided.
	 */
	protected function get_repository_name( bool $required = false, ?int $positional_arg_index = null ) : string {
		if ( is_int( $positional_arg_index ) ) {
			$args = $this->get_args();

			$repository_name = $args[ $positional_arg_index ] ?? null;
		} else {
			$repository_name = $this->get_assoc_arg( 'repository' ) ?? null;
		}

		if ( $required && ! $repository_name ) {
			throw new WPSnapshotsException( 'Please provide a repository name.' );
		}

		return $repository_name ?? '10up';
	}

	/**
	 * Gets the default value for a given arg.
	 *
	 * @param string $arg Arg.
	 * @return mixed
	 */
	protected function get_default_arg_value( string $arg ) {
		$synopsis = $this->get_command_parameters()['synopsis'] ?? [];

		foreach ( $synopsis as $synopsis_arg ) {
			if ( $arg === $synopsis_arg['name'] ) {
				return $synopsis_arg['default'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Format bytes to pretty file size
	 *
	 * @param  int $size     Number of bytes
	 * @param  int $precision Decimal precision
	 * @return string
	 */
	protected function format_bytes( $size, $precision = 2 ) {
		$base     = log( $size, 1024 );
		$suffixes = [ '', 'KB', 'MB', 'GB', 'TB' ];

		return round( pow( 1024, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[ floor( $base ) ];
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
