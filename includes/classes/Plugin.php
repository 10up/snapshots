<?php
/**
 * Plugin container.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Infrastructure\Container;
use TenUp\WPSnapshots\Log\WPCLILogger;
use TenUp\WPSnapshots\Snapshots\{DynamoDBConnector, FileZipper, S3StorageConnector, SnapshotCreator, SnapshotMetaFromFileSystem, Trimmer};
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\{Configure, Create, CreateRepository, Download, Pull, Search};
use TenUp\WPSnapshots\WPCLICommands\Create\{Scrubber, WPCLIDumper};
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacerFactory;
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
	public function register() : void {
		if ( defined( 'WP_CLI' ) ) {
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
	 * @return string[]
	 */
	protected function get_modules(): array {
		$components = [
			'wpcli_commands/configure'         => Configure::class,
			'wpcli_commands/create'            => Create::class,
			'wpcli_commands/create_repository' => CreateRepository::class,
			'wpcli_commands/download'          => Download::class,
			'wpcli_commands/pull'              => Pull::class,
			'wpcli_commands/search'            => Search::class,
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
	 * @return string[]
	 */
	protected function get_services(): array {
		$services = [
			'file_system'                             => null,
			'snapshots/db_dumper'                     => null,
			'snapshots/file_zipper'                   => FileZipper::class,
			'snapshots/snapshot_creator'              => SnapshotCreator::class,
			'snapshots_filesystem'                    => null,
			'snapshots/db_connector'                  => DynamoDBConnector::class,
			'snapshots/scrubber'                      => Scrubber::class,
			'snapshots/snapshot_meta'                 => null,
			'snapshots/storage_connector'             => S3StorageConnector::class,
			'snapshots/trimmer'                       => Trimmer::class,
			'wp_snapshots_config/wp_snapshots_config' => null,
			'wordpress/database'                      => Database::class,
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
	 * @param string[] $services Services.
	 * @return string[]
	 */
	public function add_wp_cli_services( array $services ): array {
		$wp_cli_services = [
			'log/wpcli_logger'           => WPCLILogger::class,
			'snapshots/db_dumper'        => WPCLIDumper::class,
			'wpcli/prompt'               => Prompt::class,
			'wpcli/url_replacer_factory' => URLReplacerFactory::class,
		];

		return array_merge(
			$services,
			$wp_cli_services
		);
	}

	/**
	 * Adds file system services.
	 *
	 * @param string[] $services Services.
	 * @return string[]
	 */
	public function add_file_system_services( array $services ): array {
		$file_system_services = [
			'file_system'                             => FileSystem::class,
			'snapshots_filesystem'                    => SnapshotFiles::class,
			'snapshots/snapshot_meta'                 => SnapshotMetaFromFileSystem::class,
			'wp_snapshots_config/wp_snapshots_config' => WPSnapshotsConfigFromFileSystem::class,
		];

		return array_merge(
			$services,
			$file_system_services
		);
	}
}
