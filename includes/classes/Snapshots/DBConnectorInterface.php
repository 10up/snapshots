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
interface DBConnectorInterface extends ConnectorInterface {

	/**
	 * Searches the database.
	 *
	 * @param  string $query Search query string
	 * @return array
	 */
	public function search( string $query ) : array;
}
