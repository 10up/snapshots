<?php
/**
 * Interface for Storage Connectors.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

/**
 * Interface StorageConnectorInterface
 *
 * @package TenUp\WPSnapshots
 */
interface ConnectorInterface {

	/**
	 * Sets the configuration.
	 *
	 * @param object $configuration Configuration.
	 */
	public function set_configuration( object $configuration );

	/**
	 * Gets the configuration.
	 *
	 * @return object
	 */
	public function get_configuration() : object;
}
