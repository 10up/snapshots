<?php
/**
 * Plugin container.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Infrastructure\Container;
use TenUp\WPSnapshots\Snapshots\AWSAuthenticationFactory;
use TenUp\WPSnapshots\Snapshots\S3StorageConnector;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\Configure;
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
	 * @return array
	 */
	protected function get_modules(): array {
		$components = [
			Configure::class,
		];

		/**
		 * Filters the components for the plugin.
		 *
		 * @param array $components Client components.
		 */
		return apply_filters( 'wpsnapshots_components', $components );
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
			AWSAuthenticationFactory::class,
			Prompt::class,
			S3StorageConnector::class,
			SnapshotsFileSystem::class,
			WPSnapshotsConfigFromFileSystem::class,
		];

		/**
		 * Filters the services for the plugin.
		 *
		 * @param array $services Service modules.
		 */
		return apply_filters( 'wpsnapshots_services', $services );
	}
}
