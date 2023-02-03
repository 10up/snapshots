<?php
/**
 * Plugin container.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Infrastructure\Container;
use TenUp\WPSnapshots\Log\WPCLILogger;
use TenUp\WPSnapshots\Snapshots\{DynamoDBConnector, FileZipper, S3StorageConnector, SnapshotMetaFromFileSystem};
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\{Configure, Create, CreateRepository, Delete, Download, Pull, Push, Search};
use TenUp\WPSnapshots\WPCLICommands\Create\{Scrubber, WPCLIDBExport, Trimmer};
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacerFactory;
use TenUp\WPSnapshots\WPSnapshotsConfig\WPSnapshotsConfigFromFileSystem;

/**
 * Plugin container.
 *
 * @package TenUp\WPSnapshots
 */
final class Plugin extends Container {

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
			'wpcli_commands/create_repository' => CreateRepository::class,
			'wpcli_commands/create'            => Create::class,
			'wpcli_commands/delete'            => Delete::class,
			'wpcli_commands/download'          => Download::class,
			'wpcli_commands/pull'              => Pull::class,
			'wpcli_commands/push'              => Push::class,
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
			'file_system'                             => FileSystem::class,
			'log/wpcli_logger'                        => WPCLILogger::class,
			'snapshots_filesystem'                    => WPSnapshotsDirectory::class,
			'snapshots/db_connector'                  => DynamoDBConnector::class,
			'snapshots/db_dumper'                     => WPCLIDBExport::class,
			'snapshots/file_zipper'                   => FileZipper::class,
			'snapshots/scrubber'                      => Scrubber::class,
			'snapshots/snapshot_meta'                 => SnapshotMetaFromFileSystem::class,
			'snapshots/storage_connector'             => S3StorageConnector::class,
			'snapshots/trimmer'                       => Trimmer::class,
			'wordpress/database'                      => Database::class,
			'wp_snapshots_config/wp_snapshots_config' => WPSnapshotsConfigFromFileSystem::class,
			'wpcli/prompt'                            => Prompt::class,
			'wpcli/url_replacer_factory'              => URLReplacerFactory::class,
		];

		/**
		 * Filters the services for the plugin.
		 *
		 * @param array $services Service modules.
		 */
		return (array) apply_filters( 'wpsnapshots_services', $services );
	}
}
