<?php
/**
 * Plugin container.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Infrastructure\Container;
use TenUp\WPSnapshots\Log\WPCLILogger;
use TenUp\WPSnapshots\Snapshots\{AWSAuthenticationFactory, DynamoDBConnector, S3StorageConnector, SnapshotMetaFromFileSystem};
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\{Configure, Download, Search};
use TenUp\WPSnapshots\WPSnapshotsConfig\WPSnapshotsConfigFromFileSystem;

/**
 * Plugin container.
 *
 * @package TenUp\WPSnapshots
 */
final class Plugin extends Container {

	/**
	 * Registers the plugin.
	 */
	public function register() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_filter( 'wpsnapshots_services', [ $this, 'add_wp_cli_services' ], -99 );
		}

		if ( defined( 'WPSNAPSHOTS_USE_FILE_SYSTEM' ) && true === WPSNAPSHOTS_USE_FILE_SYSTEM ) {
			add_filter( 'wpsnapshots_services', [ $this, 'add_file_system_services' ], -99 );
		}

		parent::register();
	}

	/**
	 * Provides components for the plugin.
	 *
	 * Modules are classes that are instantiated and registered with the container.
	 *
	 * @return array
	 */
	protected function get_modules(): array {
		$components = [
			'wpcli_commands/configure' => Configure::class,
			'wpcli_commands/download'  => Download::class,
			'wpcli_commands/search'    => Search::class,
		];

		/**
		 * Filters the components for the plugin.
		 *
		 * @param array $components Client components.
		 */
		return (array) apply_filters( 'wpsnapshots_components', $components );
	}

	/**
	 * Provides the services for the plugin.
	 *
	 * Services are classes that are instantiated on demand when components are instantiated.
	 *
	 * @return array
	 */
	protected function get_services(): array {
		$services = [
			'snapshots_filesystem'                    => null,
			'snapshots/aws_authentication_factory'    => AWSAuthenticationFactory::class,
			'snapshots/db_connector'                  => DynamoDBConnector::class,
			'snapshots/snapshot_meta'                 => null,
			'snapshots/storage_connector'             => S3StorageConnector::class,
			'wp_snapshots_config/wp_snapshots_config' => null,
		];

		/**
		 * Filters the services for the plugin.
		 *
		 * @param array $services Service modules.
		 */
		return (array) apply_filters( 'wpsnapshots_services', $services );
	}

	/**
	 * Adds WP-CLI services.
	 *
	 * @param array $services Services.
	 * @return array
	 */
	public function add_wp_cli_services( array $services ): array {
		$wp_cli_services = [
			'log/wpcli_logger' => WPCLILogger::class,
			'wpcli/prompt'     => Prompt::class,
		];

		return array_merge(
			$services,
			$wp_cli_services
		);
	}

	/**
	 * Adds file system services.
	 *
	 * @param array $services Services.
	 * @return array
	 */
	public function add_file_system_services( array $services ): array {
		$file_system_services = [
			'snapshots_filesystem'                    => SnapshotsFileSystem::class,
			'snapshots/snapshot_meta'                 => SnapshotMetaFromFileSystem::class,
			'wp_snapshots_config/wp_snapshots_config' => WPSnapshotsConfigFromFileSystem::class,
		];

		return array_merge(
			$services,
			$file_system_services
		);
	}
}
